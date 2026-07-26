<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

use GetBible\Scripture\Tests\Unit\Setup\NativeRuntimeState;

/**
 * Overrides the extension probe only inside the Setup namespace.
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
 * Overrides the Engine-class probe only inside the Setup namespace.
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

/**
 * Overrides the extension version only inside the Setup namespace.
 *
 * @param string|null $extension Extension name.
 *
 * @return string|false
 * @since 1.0.0
 */
function phpversion(?string $extension = null): string|false
{
    return $extension === 'getbiblesword'
        ? NativeRuntimeState::$extensionVersion
        : \phpversion($extension);
}
