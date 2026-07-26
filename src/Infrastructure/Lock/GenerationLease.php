<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Lock;

use Joomla\Filesystem\Folder;

/**
 * Retains a shared lease while an immutable snapshot generation is readable.
 *
 * Lease files live outside generation directories. A cleaner can therefore
 * acquire an exclusive non-blocking lease before deleting an old generation
 * without unlinking the synchronization inode itself.
 *
 * @since 1.0.0
 */
final class GenerationLease
{
    /**
     * Open lease resource.
     *
     * @var resource|null
     * @since 1.0.0
     */
    private mixed $handle = null;

    /**
     * Acquires a shared reader lease.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     * @param string $generation Validated generation identifier.
     *
     * @since 1.0.0
     */
    public function __construct(string $moduleRoot, string $generation)
    {
        $path = self::path($moduleRoot, $generation);
        $handle = fopen($path, 'c+b');

        if (!is_resource($handle)) {
            throw new \RuntimeException(sprintf('Unable to open generation lease "%s".', $path));
        }

        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            throw new \RuntimeException(sprintf('Unable to acquire generation lease "%s".', $path));
        }

        $this->handle = $handle;
    }

    /**
     * Releases the shared reader lease.
     *
     * @since 1.0.0
     */
    public function __destruct()
    {
        $this->release();
    }

    /**
     * Releases the shared reader lease idempotently.
     *
     * @return void
     * @since 1.0.0
     */
    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    /**
     * Runs cleanup only when no reader currently leases the generation.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     * @param string $generation Validated generation identifier.
     * @param callable(): void $cleanup Generation cleanup callback.
     *
     * @return bool Whether the exclusive lease was acquired.
     * @since 1.0.0
     */
    public static function cleanup(
        string $moduleRoot,
        string $generation,
        callable $cleanup,
    ): bool {
        $path = self::path($moduleRoot, $generation);
        $handle = fopen($path, 'c+b');

        if (!is_resource($handle)) {
            throw new \RuntimeException(sprintf('Unable to open generation cleanup lease "%s".', $path));
        }

        $acquired = false;

        try {
            $acquired = flock($handle, LOCK_EX | LOCK_NB);

            if (!$acquired) {
                return false;
            }

            $cleanup();

            return true;
        } finally {
            if ($acquired) {
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }

    /**
     * Returns a controlled lease path and creates its directory.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     * @param string $generation Validated generation identifier.
     *
     * @return string
     * @since 1.0.0
     */
    private static function path(string $moduleRoot, string $generation): string
    {
        if (preg_match('/^[0-9a-f]{64}$/D', $generation) !== 1) {
            throw new \InvalidArgumentException('A generation lease requires a SHA-256 identifier.');
        }

        $directory = $moduleRoot . '/leases';

        if (!Folder::create($directory, 0750)) {
            throw new \RuntimeException(sprintf('Unable to create generation lease directory "%s".', $directory));
        }

        return $directory . '/' . $generation . '.lock';
    }
}
