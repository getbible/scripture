<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

use GetBible\Scripture\Configuration\Configuration;

/**
 * Implements bounded interprocess SWORD root locking with flock.
 *
 * @since 0.2.0
 */
final class FileModuleRootLock implements ModuleRootLockInterface
{
    /**
     * Absolute lock-file path.
     *
     * @var BoundedFileLock
     * @since 0.2.0
     */
    private BoundedFileLock $lock;

    /**
     * Creates the shared application lifecycle lock.
     *
     * @param Configuration $configuration Runtime configuration.
     *
     * @since 0.2.0
     */
    public function __construct(Configuration $configuration)
    {
        $this->lock = new BoundedFileLock(
            $configuration->cachePath() . '/locks/module-root.lock',
            $configuration->lockTimeout(),
        );
    }

    /**
     * Executes an operation under a shared lock.
     *
     * @template T
     *
     * @param callable(): T $operation Read operation.
     *
     * @return T
     * @since 0.2.0
     */
    public function read(callable $operation): mixed
    {
        return $this->lock->synchronized(LOCK_SH, $operation);
    }

    /**
     * Executes an operation under an exclusive lock.
     *
     * @template T
     *
     * @param callable(): T $operation Write operation.
     *
     * @return T
     * @since 0.2.0
     */
    public function write(callable $operation): mixed
    {
        return $this->lock->synchronized(LOCK_EX, $operation);
    }
}
