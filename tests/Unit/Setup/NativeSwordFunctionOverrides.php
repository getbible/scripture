<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Sword;

use GetBible\Scripture\Tests\Unit\Setup\NativeRuntimeState;

/**
 * Provides a deterministic extension probe to the native adapter in unit tests.
 *
 * @param string $extension Extension name.
 *
 * @return bool
 * @since 1.0.0
 */
function extension_loaded(string $extension): bool
{
    return $extension === 'getbiblesword'
        ? NativeRuntimeState::$extensionLoaded
        : \extension_loaded($extension);
}

/**
 * Provides a deterministic Engine-class probe to the native adapter.
 *
 * @param class-string|string $class Class name.
 * @param bool $autoload Whether autoloading is allowed.
 *
 * @return bool
 * @since 1.0.0
 */
function class_exists(string $class, bool $autoload = true): bool
{
    return $class === \GetBible\Sword\Engine::class
        ? NativeRuntimeState::$engineClassAvailable
        : \class_exists($class, $autoload);
}
