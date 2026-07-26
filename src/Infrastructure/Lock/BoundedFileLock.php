<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

use Joomla\Filesystem\Folder;

/**
 * Executes callbacks under a bounded advisory file lock.
 *
 * @since 0.3.0
 */
final class BoundedFileLock
{
    /**
     * Creates a lock at an explicit controlled path.
     *
     * @param string $path Absolute or application-controlled lock-file path.
     * @param int $timeout Maximum wait in seconds.
     *
     * @since 0.3.0
     */
    public function __construct(
        private string $path,
        private int $timeout,
    ) {
        if (trim($path) === '' || $timeout < 1) {
            throw new \InvalidArgumentException('A lock path and positive timeout are required.');
        }
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
     * @since 0.3.0
     */
    public function synchronized(int $mode, callable $operation): mixed
    {
        if ($mode !== LOCK_SH && $mode !== LOCK_EX) {
            throw new \InvalidArgumentException('A shared or exclusive flock mode is required.');
        }

        $directory = dirname($this->path);

        if (!Folder::create($directory, 0750)) {
            throw new \RuntimeException(sprintf('Unable to create lock directory "%s".', $directory));
        }

        $handle = fopen($this->path, 'c+b');

        if (!is_resource($handle)) {
            throw new \RuntimeException(sprintf('Unable to open lifecycle lock "%s".', $this->path));
        }

        $deadline = hrtime(true) + ($this->timeout * 1_000_000_000);
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
                    $this->timeout,
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
