<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Infrastructure;

use GetBible\Scripture\Infrastructure\Lock\GenerationLease;
use PHPUnit\Framework\TestCase;

/**
 * Verifies old generations cannot be cleaned while a reader leases them.
 *
 * @since 1.0.0
 */
final class GenerationLeaseTest extends TestCase
{
    /**
     * Controlled test cache root.
     *
     * @var string
     * @since 1.0.0
     */
    private string $moduleRoot;

    /**
     * Creates an isolated module cache root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->moduleRoot = sys_get_temp_dir() . '/getbible-generation-lease-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->moduleRoot, 0700, true));
    }

    /**
     * Removes the controlled test cache root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->moduleRoot, \FilesystemIterator::SKIP_DOTS),
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

        rmdir($this->moduleRoot);
    }

    /**
     * Verifies cleanup is nonblocking and succeeds after release.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCleanupWaitsForReaderRelease(): void
    {
        $generation = str_repeat('a', 64);
        $lease = new GenerationLease($this->moduleRoot, $generation);
        $cleaned = false;

        self::assertFalse(GenerationLease::cleanup(
            $this->moduleRoot,
            $generation,
            static function () use (&$cleaned): void {
                $cleaned = true;
            },
        ));
        self::assertFalse($cleaned);

        unset($lease);

        self::assertTrue(GenerationLease::cleanup(
            $this->moduleRoot,
            $generation,
            static function () use (&$cleaned): void {
                $cleaned = true;
            },
        ));
        self::assertTrue($cleaned);
    }

    /**
     * Verifies explicit release is idempotent and permits cleanup.
     *
     * @return void
     * @since 1.0.0
     */
    public function testExplicitReleaseIsIdempotent(): void
    {
        $generation = str_repeat('b', 64);
        $lease = new GenerationLease($this->moduleRoot, $generation);
        $lease->release();
        $lease->release();

        self::assertTrue(GenerationLease::cleanup(
            $this->moduleRoot,
            $generation,
            static function (): void {
            },
        ));
    }

    /**
     * Verifies lease paths accept only content-addressed identifiers.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsInvalidGenerationIdentifier(): void
    {
        try {
            new GenerationLease($this->moduleRoot, 'not-a-generation');
            self::fail('An invalid generation lease identifier was accepted.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(\InvalidArgumentException::class);
        GenerationLease::cleanup(
            $this->moduleRoot,
            str_repeat('g', 64),
            static function (): void {
            },
        );
    }
}
