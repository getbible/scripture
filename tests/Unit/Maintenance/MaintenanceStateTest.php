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
     * Verifies empty state accessors and immediate due semantics.
     *
     * @return void
     * @since 1.0.0
     */
    public function testEmptyStateIsDueAndHasNoHistory(): void
    {
        $state = MaintenanceState::empty();
        $now = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        self::assertTrue($state->isDue($now, new \DateInterval('P1M')));
        self::assertNull($state->nextDueAt(new \DateInterval('P1M')));
        self::assertNull($state->lastAttemptAt());
        self::assertNull($state->lastSuccessAt());
        self::assertNull($state->lastFailureAt());
        self::assertNull($state->lastError());
        self::assertSame(0, $state->consecutiveFailures());
    }

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

    /**
     * Verifies successful recovery clears the error and failure counter.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSuccessAfterFailureResetsFailureState(): void
    {
        $failure = new \DateTimeImmutable('2026-07-25T12:00:00+00:00');
        $success = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $state = MaintenanceState::empty()
            ->failedAt($failure, ' Temporary failure. ')
            ->succeededAt($success);

        self::assertSame($success, $state->lastAttemptAt());
        self::assertSame($success, $state->lastSuccessAt());
        self::assertSame($failure, $state->lastFailureAt());
        self::assertNull($state->lastError());
        self::assertSame(0, $state->consecutiveFailures());
    }

    /**
     * Verifies serialized state rejects invalid structures and timestamps.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInvalidSerializedStateIsRejected(): void
    {
        try {
            MaintenanceState::fromArray([]);
            self::fail('An unsupported state structure was accepted.');
        } catch (\UnexpectedValueException) {
        }

        try {
            MaintenanceState::fromArray([
                'format' => MaintenanceState::FORMAT,
                'consecutive_failures' => 0,
                'last_error' => [],
            ]);
            self::fail('A non-string last error was accepted.');
        } catch (\UnexpectedValueException) {
        }

        try {
            MaintenanceState::fromArray([
                'format' => MaintenanceState::FORMAT,
                'consecutive_failures' => 0,
                'last_attempt_at' => 123,
            ]);
            self::fail('A non-string timestamp was accepted.');
        } catch (\UnexpectedValueException) {
        }

        $this->expectException(\UnexpectedValueException::class);
        MaintenanceState::fromArray([
            'format' => MaintenanceState::FORMAT,
            'consecutive_failures' => 0,
            'last_attempt_at' => 'not a timestamp',
        ]);
    }

    /**
     * Verifies serialized state cannot contain a negative failure counter.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsNegativeFailureCounter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MaintenanceState::fromArray([
            'format' => MaintenanceState::FORMAT,
            'consecutive_failures' => -1,
        ]);
    }

    /**
     * Verifies failed transitions require a useful diagnostic.
     *
     * @return void
     * @since 1.0.0
     */
    public function testFailureRequiresDiagnostic(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MaintenanceState::empty()->failedAt(
            new \DateTimeImmutable('2026-07-26T12:00:00+00:00'),
            '  ',
        );
    }
}
