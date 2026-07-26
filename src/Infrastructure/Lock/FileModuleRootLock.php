<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

use GetBible\Scripture\Configuration\Configuration;
use Joomla\Filesystem\Folder;

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
     * @var string
     * @since 0.2.0
     */
    private string $path;

    /**
     * Creates the shared application lifecycle lock.
     *
     * @param Configuration $configuration Runtime configuration.
     *
     * @since 0.2.0
     */
    public function __construct(private Configuration $configuration)
    {
        $this->path = $configuration->cachePath() . '/locks/module-root.lock';
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
        return $this->synchronized(LOCK_SH, $operation);
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
        return $this->synchronized(LOCK_EX, $operation);
    }

    /**
     * Acquires a bounded lock, invokes an operation, and always releases it.
     *
     * @template T
     *
     * @param int $mode LOCK_SH or LOCK_EX.
     * @param callable(): T $operation Protected operation.
     *
     * @return T
     * @since 0.2.0
     */
    private function synchronized(int $mode, callable $operation): mixed
    {
        $directory = dirname($this->path);

        if (!Folder::create($directory, 0750)) {
            throw new \RuntimeException(sprintf('Unable to create lifecycle lock directory "%s".', $directory));
        }

        $handle = fopen($this->path, 'c+b');

        if (!is_resource($handle)) {
            throw new \RuntimeException(sprintf('Unable to open lifecycle lock "%s".', $this->path));
        }

        $deadline = hrtime(true) + ($this->configuration->lockTimeout() * 1_000_000_000);
        $acquired = false;

        try {
            do {
                $acquired = flock($handle, $mode | LOCK_NB);

                if ($acquired) {
                    break;
                }

                usleep(50_000);
            } while (hrtime(true) < $deadline);

            if (!$acquired) {
                throw new \RuntimeException(sprintf(
                    'Timed out after %d seconds waiting for lifecycle lock "%s".',
                    $this->configuration->lockTimeout(),
                    $this->path,
                ));
            }

            return $operation();
        } finally {
            if ($acquired) {
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }
}
