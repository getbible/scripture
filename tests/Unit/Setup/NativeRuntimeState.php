<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

/**
 * Mutable process-local native probe used only by isolated runtime tests.
 *
 * @since 1.0.0
 */
final class NativeRuntimeState
{
    /**
     * Whether the test extension probe reports loaded.
     *
     * @var bool
     * @since 1.0.0
     */
    public static bool $extensionLoaded = false;

    /**
     * Whether the test class probe reports the Engine class.
     *
     * @var bool
     * @since 1.0.0
     */
    public static bool $engineClassAvailable = false;

    /**
     * Test extension version.
     *
     * @var string|false
     * @since 1.0.0
     */
    public static string|false $extensionVersion = false;

    /**
     * Restores the absent-extension baseline.
     *
     * @return void
     * @since 1.0.0
     */
    public static function reset(): void
    {
        self::$extensionLoaded = false;
        self::$engineClassAvailable = false;
        self::$extensionVersion = false;
    }

    /**
     * Prevents instantiation of test probe state.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }
}
