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
     * @param array<string, bool|string|null> $values Explicit configuration.
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

        if ($cachePath === null) {
            throw new \InvalidArgumentException('A non-empty Scripture cache path is required.');
        }

        if ($refreshInterval === null) {
            throw new \InvalidArgumentException('A non-empty refresh interval is required.');
        }

        try {
            new \DateInterval($refreshInterval);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException(
                sprintf('Invalid ISO-8601 refresh interval "%s".', $refreshInterval),
                0,
                $exception,
            );
        }

        return new self(new Registry([
            'module_path' => $modulePath,
            'cache_path' => self::normalizePath($cachePath),
            'refresh_interval' => $refreshInterval,
            'auto_refresh' => $autoRefresh,
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
     * @param bool|string|null ...$values Candidate values.
     *
     * @return string|null
     * @since 0.1.0
     */
    private static function firstString(bool|string|null ...$values): ?string
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
     * @param bool|string|null $value Candidate value.
     * @param bool             $default Default when no value is supplied.
     *
     * @return bool
     * @since 0.1.0
     */
    private static function booleanValue(bool|string|null $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
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
