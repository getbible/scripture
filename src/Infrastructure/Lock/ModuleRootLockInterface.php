<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

/**
 * Coordinates readers and writers of the configured SWORD module root.
 *
 * @since 0.2.0
 */
interface ModuleRootLockInterface
{
    /**
     * Executes an operation while module replacement is excluded.
     *
     * @template T
     *
     * @param callable(): T $operation Read operation.
     *
     * @return T
     * @since 0.2.0
     */
    public function read(callable $operation): mixed;

    /**
     * Executes an operation while all module readers and writers are excluded.
     *
     * @template T
     *
     * @param callable(): T $operation Write operation.
     *
     * @return T
     * @since 0.2.0
     */
    public function write(callable $operation): mixed;
}
