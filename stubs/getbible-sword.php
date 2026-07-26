<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Sword;

/**
 * Static-analysis declaration of the native extension exception.
 *
 * @since 0.1.0
 */
class Exception extends \RuntimeException
{
}

/**
 * Static-analysis declaration of the native extension engine.
 *
 * The extension supplies this class at runtime. This declaration is loaded only
 * by PHPStan and is deliberately absent from Composer autoloading.
 *
 * @since 0.1.0
 */
final class Engine
{
    /**
     * Creates an engine for an optional explicit module root.
     *
     * @param string|null $modulePath Explicit SWORD module root.
     *
     * @since 0.1.0
     */
    public function __construct(?string $modulePath = null)
    {
    }

    /**
     * Returns the embedded getBibleSword ABI version.
     *
     * @return int
     * @since 0.1.0
     */
    public static function abiVersion(): int
    {
    }

    /**
     * Returns the embedded getBibleSword product version.
     *
     * @return string
     * @since 0.1.0
     */
    public static function productVersion(): string
    {
    }

    /**
     * Returns the emitted stream contract identifier.
     *
     * @return string
     * @since 0.1.0
     */
    public static function contractIdentifier(): string
    {
    }

    /**
     * Returns the resolved SWORD module root.
     *
     * @return string
     * @since 0.1.0
     */
    public function modulePath(): string
    {
    }

    /**
     * Streams installed module metadata.
     *
     * @param resource $destination Writable PHP stream.
     *
     * @return int Number of serialized bytes.
     * @since 0.1.0
     */
    public function streamModules(mixed $destination): int
    {
    }

    /**
     * Streams one installed module.
     *
     * @param string $module Exact module identifier.
     * @param resource $destination Writable PHP stream.
     * @param int $artifactChunkSize Artifact chunk size.
     *
     * @return int Number of serialized bytes.
     * @since 0.1.0
     */
    public function streamModule(
        string $module,
        mixed $destination,
        int $artifactChunkSize = 1048576,
    ): int {
    }
}
