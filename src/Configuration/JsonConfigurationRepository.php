<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Configuration;

/**
 * Persists configuration as restrictive, atomic, schema-limited JSON.
 *
 * @since 1.0.0
 */
final class JsonConfigurationRepository implements ConfigurationRepositoryInterface
{
    /**
     * Persisted document identifier.
     *
     * @since 1.0.0
     */
    private const FORMAT = 'getbible.scripture.configuration/v1';

    /**
     * Allowed persisted configuration keys.
     *
     * @since 1.0.0
     */
    private const ALLOWED_KEYS = [
        'module_path',
        'cache_path',
        'refresh_interval',
        'auto_refresh',
        'lock_timeout',
        'modules',
        'provisioning_enabled',
        'install_all',
    ];

    /**
     * Creates a repository for an explicit path.
     *
     * @param string|null $path Absolute path, or null for non-persistent configuration.
     *
     * @since 1.0.0
     */
    public function __construct(private ?string $path)
    {
        if ($path !== null && ConfigurationPath::resolve($path) !== $path) {
            throw new \InvalidArgumentException('The Scripture configuration path is not normalized.');
        }
    }

    /**
     * Returns the explicit durable configuration path.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * Reports whether a durable configuration file currently exists.
     *
     * @return bool
     * @since 1.0.0
     */
    public function exists(): bool
    {
        return $this->path !== null && is_file($this->path) && !is_link($this->path);
    }

    /**
     * Loads and verifies the persisted setting map.
     *
     * @return array<string, bool|int|string|list<string>|null>
     * @since 1.0.0
     */
    public function load(): array
    {
        if ($this->path === null || !file_exists($this->path)) {
            return [];
        }

        if (is_link($this->path) || !is_file($this->path) || !is_readable($this->path)) {
            throw new \RuntimeException('The Scripture configuration path is not a readable regular file.');
        }

        $json = file_get_contents($this->path);

        if (!is_string($json)) {
            throw new \RuntimeException('The Scripture configuration file could not be read.');
        }

        try {
            $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException(
                'The Scripture configuration file is not valid JSON.',
                0,
                $exception,
            );
        }

        if (!is_array($document) || array_is_list($document) || ($document['format'] ?? null) !== self::FORMAT) {
            throw new \UnexpectedValueException('The Scripture configuration document format is invalid.');
        }

        $settings = $document['settings'] ?? null;

        if (!is_array($settings) || array_is_list($settings)) {
            throw new \UnexpectedValueException('The Scripture configuration settings map is invalid.');
        }

        return $this->validateSettings($settings);
    }

    /**
     * Atomically persists one immutable validated configuration.
     *
     * @param Configuration $configuration Validated configuration.
     *
     * @return void
     * @since 1.0.0
     */
    public function save(Configuration $configuration): void
    {
        if ($this->path === null) {
            throw new \LogicException(
                'A configuration path must be supplied explicitly or through '
                . ConfigurationPath::ENVIRONMENT_VARIABLE . '.',
            );
        }

        if (is_link($this->path)) {
            throw new \RuntimeException('Refusing to replace a symbolic-link configuration path.');
        }

        $directory = dirname($this->path);
        $this->ensureDirectory($directory);
        $settings = $this->configurationSettings($configuration);
        $payload = json_encode(
            [
                'format' => self::FORMAT,
                'settings' => $settings,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
        $temporary = tempnam($directory, '.scripture-config-');

        if (!is_string($temporary)) {
            throw new \RuntimeException('A temporary Scripture configuration file could not be created.');
        }

        try {
            if (!chmod($temporary, 0600)) {
                throw new \RuntimeException('Restrictive permissions could not be applied to staged configuration.');
            }

            $stream = fopen($temporary, 'wb');

            if ($stream === false) {
                throw new \RuntimeException('The staged Scripture configuration could not be opened.');
            }

            try {
                $written = fwrite($stream, $payload);

                if ($written !== strlen($payload) || !fflush($stream)) {
                    throw new \RuntimeException('The staged Scripture configuration could not be written completely.');
                }

                if (function_exists('fsync') && !fsync($stream)) {
                    throw new \RuntimeException('The staged Scripture configuration could not be synchronized.');
                }
            } finally {
                fclose($stream);
            }

            if (!rename($temporary, $this->path)) {
                throw new \RuntimeException('The Scripture configuration could not be activated atomically.');
            }

            if (!chmod($this->path, 0600)) {
                throw new \RuntimeException('Restrictive permissions could not be applied to configuration.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Creates the configuration parent with restrictive permissions.
     *
     * @param string $directory Parent directory.
     *
     * @return void
     * @since 1.0.0
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('The Scripture configuration directory could not be created.');
        }

        if (is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new \RuntimeException('The Scripture configuration directory is not a writable regular directory.');
        }
    }

    /**
     * Converts validated configuration into an allowlisted scalar map.
     *
     * @param Configuration $configuration Validated immutable configuration.
     *
     * @return array<string, bool|int|string|list<string>|null>
     * @since 1.0.0
     */
    private function configurationSettings(Configuration $configuration): array
    {
        return [
            'module_path' => $configuration->modulePath(),
            'cache_path' => $configuration->cachePath(),
            'refresh_interval' => $configuration->refreshIntervalSpec(),
            'auto_refresh' => $configuration->autoRefresh(),
            'lock_timeout' => $configuration->lockTimeout(),
            'modules' => $configuration->modules(),
            'provisioning_enabled' => $configuration->provisioningEnabled(),
            'install_all' => $configuration->installAll(),
        ];
    }

    /**
     * Runtime-verifies the persisted setting map and rejects unknown keys.
     *
     * @param array<array-key, mixed> $settings Untrusted decoded settings.
     *
     * @return array<string, bool|int|string|list<string>|null>
     * @since 1.0.0
     */
    private function validateSettings(array $settings): array
    {
        $validated = [];

        foreach ($settings as $key => $value) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_KEYS, true)) {
                throw new \UnexpectedValueException('The Scripture configuration contains an unknown setting.');
            }

            if ($key === 'modules') {
                if (!is_array($value) || !array_is_list($value)) {
                    throw new \UnexpectedValueException(
                        'The persisted Scripture setting "modules" must be a list of strings.',
                    );
                }

                $items = [];

                foreach ($value as $item) {
                    if (!is_string($item)) {
                        throw new \UnexpectedValueException(
                            'The persisted Scripture setting "modules" must contain only strings.',
                        );
                    }

                    $items[] = $item;
                }

                $validated[$key] = $items;
                continue;
            }

            $valid = match ($key) {
                'module_path' => $value === null || is_string($value),
                'cache_path', 'refresh_interval' => is_string($value),
                'auto_refresh', 'provisioning_enabled', 'install_all' => is_bool($value),
                'lock_timeout' => is_int($value),
                default => false,
            };

            if (!$valid) {
                $expected = match ($key) {
                    'module_path' => 'a string or null',
                    'cache_path', 'refresh_interval' => 'a string',
                    'auto_refresh', 'provisioning_enabled', 'install_all' => 'a boolean',
                    'lock_timeout' => 'an integer',
                    default => 'a supported value',
                };

                throw new \UnexpectedValueException(sprintf(
                    'The persisted Scripture setting "%s" must be %s.',
                    $key,
                    $expected,
                ));
            }

            $validated[$key] = $value;
        }

        try {
            Configuration::fromEnvironment($validated);
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException(
                'The persisted Scripture settings are invalid: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        return $validated;
    }
}
