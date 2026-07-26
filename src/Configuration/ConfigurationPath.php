<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Configuration;

/**
 * Resolves an explicit application configuration path without inventing one.
 *
 * @since 1.0.0
 */
final class ConfigurationPath
{
    /**
     * Environment variable used when the caller does not supply a path.
     *
     * @since 1.0.0
     */
    public const ENVIRONMENT_VARIABLE = 'GETBIBLE_SCRIPTURE_CONFIG_PATH';

    /**
     * Resolves and validates the caller or environment path.
     *
     * @param string|null $path Caller-supplied path.
     *
     * @return string|null
     * @since 1.0.0
     */
    public static function resolve(?string $path = null): ?string
    {
        $candidate = $path;

        if ($candidate === null || trim($candidate) === '') {
            $environment = getenv(self::ENVIRONMENT_VARIABLE);
            $candidate = is_string($environment) ? $environment : null;
        }

        if ($candidate === null || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);

        if (str_contains($candidate, "\0")) {
            throw new \InvalidArgumentException('The Scripture configuration path cannot contain NUL bytes.');
        }

        if (!str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('The Scripture configuration path must be absolute.');
        }

        $normalized = rtrim($candidate, '/\\');

        if ($normalized === '') {
            throw new \InvalidArgumentException(
                'The Scripture configuration path must identify a file, not the filesystem root.',
            );
        }

        return $normalized;
    }

    /**
     * Prevents instantiation of this resolver.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }
}
