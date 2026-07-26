<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Infrastructure;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Infrastructure\Lock\BoundedFileLock;
use GetBible\Scripture\Infrastructure\Lock\FileMaintenanceLock;
use GetBible\Scripture\Infrastructure\Lock\FileModuleRootLock;
use PHPUnit\Framework\TestCase;

/**
 * Verifies bounded lifecycle lock construction and callback execution.
 *
 * @since 1.0.0
 */
final class LockClassesTest extends TestCase
{
    /**
     * Isolated lock root.
     *
     * @var string
     * @since 1.0.0
     */
    private string $directory;

    /**
     * Creates an isolated lock directory.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . '/getbible-scripture-locks-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes lock files created during the test.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->directory,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->directory);
    }

    /**
     * Verifies shared and exclusive callbacks return their exact values.
     *
     * @return void
     * @since 1.0.0
     */
    public function testBoundedLockExecutesAndReleasesCallbacks(): void
    {
        $lock = new BoundedFileLock($this->directory . '/direct/lifecycle.lock', 1);

        self::assertSame(
            'shared',
            $lock->synchronized(LOCK_SH, static fn (): string => 'shared'),
        );
        self::assertSame(
            ['exclusive'],
            $lock->synchronized(LOCK_EX, static fn (): array => ['exclusive']),
        );
        self::assertFileExists($this->directory . '/direct/lifecycle.lock');
    }

    /**
     * Verifies configuration-backed lifecycle locks use their correct modes.
     *
     * @return void
     * @since 1.0.0
     */
    public function testConfigurationBackedLocksExecuteCallbacks(): void
    {
        $configuration = Configuration::fromEnvironment([
            'cache_path' => $this->directory,
            'lock_timeout' => 1,
        ]);
        $maintenance = new FileMaintenanceLock($configuration);
        $moduleRoot = new FileModuleRootLock($configuration);

        self::assertSame(11, $maintenance->run(static fn (): int => 11));
        self::assertSame(12, $moduleRoot->read(static fn (): int => 12));
        self::assertSame(13, $moduleRoot->write(static fn (): int => 13));
        self::assertFileExists($this->directory . '/locks/maintenance.lock');
        self::assertFileExists($this->directory . '/locks/module-root.lock');
    }

    /**
     * Verifies invalid lock construction and modes are rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInvalidLockArgumentsAreRejected(): void
    {
        try {
            new BoundedFileLock('', 1);
            self::fail('An empty lock path was accepted.');
        } catch (\InvalidArgumentException) {
        }

        try {
            new BoundedFileLock($this->directory . '/invalid.lock', 0);
            self::fail('A zero lock timeout was accepted.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        (new BoundedFileLock($this->directory . '/invalid-mode.lock', 1))
            ->synchronized(0, static fn (): null => null);
    }

    /**
     * Verifies callback failures still release the advisory lock.
     *
     * @return void
     * @since 1.0.0
     */
    public function testBoundedLockReleasesAfterCallbackFailure(): void
    {
        $lock = new BoundedFileLock($this->directory . '/failure/lifecycle.lock', 1);

        try {
            $lock->synchronized(
                LOCK_EX,
                self::throwExpectedCallbackFailure(...),
            );
            self::fail('The callback failure was not propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Expected callback failure.', $exception->getMessage());
        }

        self::assertSame(
            'reacquired',
            $lock->synchronized(LOCK_EX, static fn (): string => 'reacquired'),
        );
    }

    /**
     * Verifies a contended advisory lock fails within its configured bound.
     *
     * @return void
     * @since 1.0.0
     */
    public function testBoundedLockTimesOutWhenContended(): void
    {
        self::assertTrue(mkdir($this->directory, 0700, true));
        $path = $this->directory . '/contended.lock';
        $holder = fopen($path, 'c+b');
        self::assertIsResource($holder);
        self::assertTrue(flock($holder, LOCK_EX | LOCK_NB));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Timed out after 1 seconds');
            (new BoundedFileLock($path, 1))
                ->synchronized(LOCK_EX, static fn (): null => null);
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }
    }

    /**
     * Throws the deterministic callback failure used by the lock-release test.
     *
     * @return void
     * @since 1.0.0
     */
    private static function throwExpectedCallbackFailure(): void
    {
        throw new \RuntimeException('Expected callback failure.');
    }
}
