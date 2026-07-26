<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Maintenance;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\JsonMaintenanceStateStore;
use GetBible\Scripture\Maintenance\MaintenanceState;
use PHPUnit\Framework\TestCase;

/**
 * Verifies durable maintenance state persistence and validation.
 *
 * @since 0.3.0
 */
final class JsonMaintenanceStateStoreTest extends TestCase
{
    /**
     * Per-test cache root.
     *
     * @var string
     * @since 0.3.0
     */
    private string $cachePath;

    /**
     * Creates an isolated cache path.
     *
     * @return void
     * @since 0.3.0
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir()
            . '/getbible-scripture-state-test-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes the isolated persisted state.
     *
     * @return void
     * @since 0.3.0
     */
    protected function tearDown(): void
    {
        $statePath = $this->cachePath . '/maintenance/state.json';

        if (is_file($statePath)) {
            unlink($statePath);
        }

        if (is_dir(dirname($statePath))) {
            rmdir(dirname($statePath));
        }

        if (is_dir($this->cachePath)) {
            rmdir($this->cachePath);
        }
    }

    /**
     * Verifies a successful state survives an atomic round trip.
     *
     * @return void
     * @since 0.3.0
     */
    public function testRoundTripsState(): void
    {
        $store = $this->store();
        $completedAt = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        self::assertNull($store->load()->lastAttemptAt());

        $store->save(MaintenanceState::empty()->succeededAt($completedAt));
        $restored = $store->load();

        self::assertSame(
            $completedAt->format(DATE_ATOM),
            $restored->lastSuccessAt()?->format(DATE_ATOM),
        );
        self::assertSame(0, $restored->consecutiveFailures());
    }

    /**
     * Verifies corrupt durable state is rejected instead of silently reset.
     *
     * @return void
     * @since 0.3.0
     */
    public function testRejectsCorruptState(): void
    {
        $store = $this->store();
        $store->save(MaintenanceState::empty());
        $statePath = $this->cachePath . '/maintenance/state.json';
        self::assertNotFalse(file_put_contents($statePath, '{"format":"invalid"}'));

        $this->expectException(\UnexpectedValueException::class);
        $store->load();
    }

    /**
     * Creates the state store for the isolated path.
     *
     * @return JsonMaintenanceStateStore
     * @since 0.3.0
     */
    private function store(): JsonMaintenanceStateStore
    {
        return new JsonMaintenanceStateStore(Configuration::fromEnvironment([
            'cache_path' => $this->cachePath,
        ]));
    }
}
