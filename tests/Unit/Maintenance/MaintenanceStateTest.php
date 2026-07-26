<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Maintenance;

use GetBible\Scripture\Maintenance\MaintenanceState;
use PHPUnit\Framework\TestCase;

/**
 * Verifies durable interval and failure-state transitions.
 *
 * @since 0.3.0
 */
final class MaintenanceStateTest extends TestCase
{
    /**
     * Verifies monthly due calculations from the last complete success.
     *
     * @return void
     * @since 0.3.0
     */
    public function testSuccessfulStateUsesConfiguredInterval(): void
    {
        $success = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $state = MaintenanceState::empty()->succeededAt($success);
        $interval = new \DateInterval('P1M');

        self::assertFalse($state->isDue(
            new \DateTimeImmutable('2026-08-25T23:59:59+00:00'),
            $interval,
        ));
        self::assertTrue($state->isDue(
            new \DateTimeImmutable('2026-08-26T12:00:00+00:00'),
            $interval,
        ));
        self::assertSame(
            '2026-08-26T12:00:00+00:00',
            $state->nextDueAt($interval)?->format(DATE_ATOM),
        );
    }

    /**
     * Verifies failures preserve last success and survive serialization.
     *
     * @return void
     * @since 0.3.0
     */
    public function testFailureStateRoundTripsWithoutAdvancingSuccess(): void
    {
        $success = new \DateTimeImmutable('2026-07-01T00:00:00+00:00');
        $failure = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $state = MaintenanceState::empty()
            ->succeededAt($success)
            ->failedAt($failure, 'Network unavailable');
        $restored = MaintenanceState::fromArray($state->toArray());

        self::assertSame($success->format(DATE_ATOM), $restored->lastSuccessAt()?->format(DATE_ATOM));
        self::assertSame($failure->format(DATE_ATOM), $restored->lastFailureAt()?->format(DATE_ATOM));
        self::assertSame('Network unavailable', $restored->lastError());
        self::assertSame(1, $restored->consecutiveFailures());
    }
}
