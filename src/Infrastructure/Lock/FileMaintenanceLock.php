<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

use GetBible\Scripture\Configuration\Configuration;

/**
 * Serializes complete maintenance runs with a bounded file lock.
 *
 * @since 0.3.0
 */
final class FileMaintenanceLock implements MaintenanceLockInterface
{
    /**
     * Underlying bounded file lock.
     *
     * @var BoundedFileLock
     * @since 0.3.0
     */
    private BoundedFileLock $lock;

    /**
     * Creates the process-shared maintenance lock.
     *
     * @param Configuration $configuration Runtime configuration.
     *
     * @since 0.3.0
     */
    public function __construct(Configuration $configuration)
    {
        $this->lock = new BoundedFileLock(
            $configuration->cachePath() . '/locks/maintenance.lock',
            $configuration->lockTimeout(),
        );
    }

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
    public function run(callable $operation): mixed
    {
        return $this->lock->synchronized(LOCK_EX, $operation);
    }
}
