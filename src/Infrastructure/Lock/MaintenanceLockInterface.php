<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

/**
 * Prevents overlapping initialization and refresh maintenance runs.
 *
 * @since 0.3.0
 */
interface MaintenanceLockInterface
{
    /**
     * Executes one complete maintenance run under an exclusive lock.
     *
     * @template T
     *
     * @param callable(): T $operation Maintenance callback.
     *
     * @return T
     * @since 0.3.0
     */
    public function run(callable $operation): mixed;
}
