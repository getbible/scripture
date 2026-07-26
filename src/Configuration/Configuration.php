<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Configuration;

use Joomla\Registry\Registry;

/**
 * Immutable, validated runtime configuration for the Scripture services.
 *
 * @since 0.1.0
 */
final class Configuration
{
    /**
     * Default ISO-8601 interval between warmed snapshot generations.
     *
     * @since 0.1.0
     */
    public const DEFAULT_REFRESH_INTERVAL = 'P1M';

    /**
     * Default maximum time spent waiting for an application lifecycle lock.
     *
     * @since 0.2.0
     */
    public const DEFAULT_LOCK_TIMEOUT = 30;

    /**
     * Joomla registry containing normalized configuration values.
     *
     * @var Registry
     * @since 0.1.0
     */
    private Registry $registry;

    /**
     * Creates validated configuration from normalized values.
     *
     * @param Registry $registry Configuration registry.
     *
     * @since 0.1.0
     */
    private function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Creates configuration from explicit values, environment, and defaults.
     *
     * @param array<string, bool|int|string|list<string>|null> $values Explicit configuration.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromEnvironment(array $values = []): self
    {
        $modulePath = self::firstString(
            $values['module_path'] ?? null,
            self::environment('GETBIBLE_SCRIPTURE_MODULE_PATH'),
        );
        $cachePath = self::firstString(
            $values['cache_path'] ?? null,
            self::environment('GETBIBLE_SCRIPTURE_CACHE_PATH'),
            self::defaultCachePath(),
        );
        $refreshInterval = self::firstString(
            $values['refresh_interval'] ?? null,
            self::environment('GETBIBLE_SCRIPTURE_REFRESH_INTERVAL'),
            self::DEFAULT_REFRESH_INTERVAL,
        );
        $autoRefresh = self::booleanValue(
            $values['auto_refresh'] ?? self::environment('GETBIBLE_SCRIPTURE_AUTO_REFRESH'),
            true,
        );
        $lockTimeout = self::positiveInteger(
            $values['lock_timeout'] ?? self::environment('GETBIBLE_SCRIPTURE_LOCK_TIMEOUT'),
            self::DEFAULT_LOCK_TIMEOUT,
            'lock timeout',
        );
        $modules = self::moduleList(
            $values['modules'] ?? self::environment('GETBIBLE_SCRIPTURE_MODULES'),
        );
        $provisioningEnabled = self::booleanValue(
            $values['provisioning_enabled'] ?? self::environment('GETBIBLE_SCRIPTURE_PROVISIONING_ENABLED'),
            false,
        );
        $installAll = self::booleanValue(
            $values['install_all'] ?? self::environment('GETBIBLE_SCRIPTURE_INSTALL_ALL'),
            false,
        );

        if ($cachePath === null) {
            throw new \InvalidArgumentException('A non-empty Scripture cache path is required.');
        }

        if ($refreshInterval === null) {
            throw new \InvalidArgumentException('A non-empty refresh interval is required.');
        }

        try {
            $interval = new \DateInterval($refreshInterval);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException(
                sprintf('Invalid ISO-8601 refresh interval "%s".', $refreshInterval),
                0,
                $exception,
            );
        }

        $epoch = new \DateTimeImmutable('@0');

        if ($interval->invert === 1 || $epoch->add($interval) <= $epoch) {
            throw new \InvalidArgumentException('The refresh interval must advance time.');
        }

        if ($installAll && !$provisioningEnabled) {
            throw new \InvalidArgumentException(
                'All-module installation requires explicit provisioning_enabled configuration.',
            );
        }

        return new self(new Registry([
            'module_path' => $modulePath,
            'cache_path' => self::normalizePath($cachePath),
            'refresh_interval' => $refreshInterval,
            'auto_refresh' => $autoRefresh,
            'lock_timeout' => $lockTimeout,
            'modules' => $modules,
            'provisioning_enabled' => $provisioningEnabled,
            'install_all' => $installAll,
        ]));
    }

    /**
     * Returns the explicit SWORD root or null for native resolution.
     *
     * @return string|null
     * @since 0.1.0
     */
    public function modulePath(): ?string
    {
        $value = $this->registry->get('module_path');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Returns the durable snapshot cache root.
     *
     * @return string
     * @since 0.1.0
     */
    public function cachePath(): string
    {
        return (string) $this->registry->get('cache_path');
    }

    /**
     * Returns the configured ISO-8601 refresh interval.
     *
     * @return string
     * @since 0.1.0
     */
    public function refreshIntervalSpec(): string
    {
        return (string) $this->registry->get('refresh_interval');
    }

    /**
     * Returns a new interval instance for freshness calculations.
     *
     * @return \DateInterval
     * @since 0.1.0
     */
    public function refreshInterval(): \DateInterval
    {
        return new \DateInterval($this->refreshIntervalSpec());
    }

    /**
     * Indicates whether stale snapshots warm on their next query.
     *
     * @return bool
     * @since 0.1.0
     */
    public function autoRefresh(): bool
    {
        return (bool) $this->registry->get('auto_refresh');
    }

    /**
     * Returns the maximum number of seconds spent waiting for a lifecycle lock.
     *
     * @return int
     * @since 0.2.0
     */
    public function lockTimeout(): int
    {
        return (int) $this->registry->get('lock_timeout');
    }

    /**
     * Returns explicitly configured translation module identifiers.
     *
     * @return list<string>
     * @since 0.3.0
     */
    public function modules(): array
    {
        $modules = $this->registry->get('modules', []);

        if (!is_array($modules)) {
            throw new \LogicException('Normalized module configuration is not an array.');
        }

        $validated = [];

        foreach ($modules as $module) {
            if (!is_string($module)) {
                throw new \LogicException('Normalized module configuration contains a non-string value.');
            }

            $validated[] = $module;
        }

        return $validated;
    }

    /**
     * Reports whether maintenance may invoke mutating native provisioning.
     *
     * @return bool
     * @since 0.3.0
     */
    public function provisioningEnabled(): bool
    {
        return (bool) $this->registry->get('provisioning_enabled');
    }

    /**
     * Reports whether initialization should install every approved translation.
     *
     * @return bool
     * @since 0.3.0
     */
    public function installAll(): bool
    {
        return (bool) $this->registry->get('install_all');
    }

    /**
     * Returns the durable maintenance state path.
     *
     * @return string
     * @since 0.3.0
     */
    public function maintenanceStatePath(): string
    {
        return $this->cachePath() . '/maintenance/state.json';
    }

    /**
     * Returns a defensive copy of the underlying Joomla registry.
     *
     * @return Registry
     * @since 0.1.0
     */
    public function registry(): Registry
    {
        return clone $this->registry;
    }

    /**
     * Returns the first non-empty scalar string.
     *
     * @param bool|int|string|array<array-key, mixed>|null ...$values Candidate values.
     *
     * @return string|null
     * @since 0.1.0
     */
    private static function firstString(bool|int|string|array|null ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Reads a non-empty environment variable.
     *
     * @param string $name Environment variable name.
     *
     * @return string|null
     * @since 0.1.0
     */
    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Parses a strict boolean configuration value.
     *
     * @param bool|int|string|array<array-key, mixed>|null $value Candidate value.
     * @param bool $default Default when no value is supplied.
     *
     * @return bool
     * @since 0.1.0
     */
    private static function booleanValue(bool|int|string|array|null $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            throw new \InvalidArgumentException('A boolean configuration value cannot be an array.');
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => throw new \InvalidArgumentException(
                    sprintf('Invalid boolean configuration value "%d".', $value),
                ),
            };
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new \InvalidArgumentException(
                sprintf('Invalid boolean configuration value "%s".', $value),
            ),
        };
    }

    /**
     * Parses a strictly positive integer configuration value.
     *
     * @param bool|int|string|array<array-key, mixed>|null $value Candidate value.
     * @param int $default Default when no value is supplied.
     * @param string $label Human-readable setting label.
     *
     * @return int
     * @since 0.2.0
     */
    private static function positiveInteger(
        bool|int|string|array|null $value,
        int $default,
        string $label,
    ): int {
        if ($value === null) {
            return $default;
        }

        if (
            is_array($value)
            || is_bool($value)
            || (is_string($value) && preg_match('/^[1-9][0-9]*$/D', trim($value)) !== 1)
        ) {
            throw new \InvalidArgumentException(sprintf('Invalid %s value.', $label));
        }

        $integer = (int) $value;

        if ($integer < 1) {
            throw new \InvalidArgumentException(sprintf('The %s must be greater than zero.', $label));
        }

        return $integer;
    }

    /**
     * Parses, validates, and de-duplicates configured module identifiers.
     *
     * @param bool|int|string|array<array-key, mixed>|null $value Candidate module configuration.
     *
     * @return list<string>
     * @since 0.3.0
     */
    private static function moduleList(bool|int|string|array|null $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_bool($value) || is_int($value)) {
            throw new \InvalidArgumentException('Configured modules must be an array or comma-separated string.');
        }

        $modules = is_string($value) ? explode(',', $value) : $value;
        $normalized = [];

        foreach ($modules as $module) {
            if (!is_string($module)) {
                throw new \InvalidArgumentException('Every configured module identifier must be a string.');
            }

            $module = trim($module);

            if ($module === '' || str_contains($module, "\0")) {
                throw new \InvalidArgumentException(
                    'Configured module identifiers must be non-empty and contain no NUL bytes.',
                );
            }

            $normalized[$module] = $module;
        }

        return array_values($normalized);
    }

    /**
     * Resolves a safe per-user default cache path.
     *
     * @return string
     * @since 0.1.0
     */
    private static function defaultCachePath(): string
    {
        $xdg = self::environment('XDG_CACHE_HOME');

        if ($xdg !== null) {
            return $xdg . '/getbible/scripture';
        }

        $home = self::environment('HOME');

        if ($home !== null) {
            return $home . '/.cache/getbible/scripture';
        }

        $userId = function_exists('posix_geteuid') ? (string) posix_geteuid() : 'unknown';

        return sys_get_temp_dir() . '/getbible-scripture-' . $userId;
    }

    /**
     * Removes trailing separators without changing a filesystem root.
     *
     * @param string $path Filesystem path.
     *
     * @return string
     * @since 0.1.0
     */
    private static function normalizePath(string $path): string
    {
        $normalized = rtrim($path, '/\\');

        return $normalized === '' ? DIRECTORY_SEPARATOR : $normalized;
    }
}
