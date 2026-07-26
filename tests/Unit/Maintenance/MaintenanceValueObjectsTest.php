<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Maintenance;

use GetBible\Scripture\Maintenance\MaintenanceModuleResult;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceState;
use GetBible\Scripture\Maintenance\MaintenanceStatus;
use GetBible\Scripture\Provisioning\ModuleProvisioningResult;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies maintenance result validation, accessors, and serialization.
 *
 * @since 1.0.0
 */
final class MaintenanceValueObjectsTest extends TestCase
{
    /**
     * Verifies all supported module statuses and their failure semantics.
     *
     * @param string $status Supported status.
     * @param bool $failed Expected failure flag.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('moduleStatuses')]
    public function testModuleResultExposesEverySupportedStatus(string $status, bool $failed): void
    {
        $result = new MaintenanceModuleResult('KJV', $status, 'diagnostic');

        self::assertSame('KJV', $result->module());
        self::assertSame($status, $result->status());
        self::assertSame('diagnostic', $result->message());
        self::assertSame($failed, $result->failed());
        self::assertSame(
            ['module' => 'KJV', 'status' => $status, 'message' => 'diagnostic'],
            $result->toArray(),
        );
    }

    /**
     * Supplies every supported module status.
     *
     * @return iterable<string, array{string, bool}>
     * @since 1.0.0
     */
    public static function moduleStatuses(): iterable
    {
        yield 'ready' => [MaintenanceModuleResult::STATUS_READY, false];
        yield 'refreshed' => [MaintenanceModuleResult::STATUS_REFRESHED, false];
        yield 'skipped' => [MaintenanceModuleResult::STATUS_SKIPPED, false];
        yield 'failed' => [MaintenanceModuleResult::STATUS_FAILED, true];
    }

    /**
     * Verifies aggregate accessors and nested serialization.
     *
     * @return void
     * @since 1.0.0
     */
    public function testMaintenanceResultExposesCompleteOutcome(): void
    {
        $started = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $completed = $started->modify('+2 seconds');
        $module = new MaintenanceModuleResult(
            'KJV',
            MaintenanceModuleResult::STATUS_READY,
        );
        $provisioning = new ProvisioningResult(
            'install',
            [
                new ModuleProvisioningResult(
                    'KJV',
                    ModuleProvisioningResult::ACTION_INSTALL,
                    ModuleProvisioningResult::STATUS_SKIPPED,
                    'Already installed.',
                ),
            ],
        );
        $result = new MaintenanceResult(
            'initialize',
            $started,
            $completed,
            false,
            [$module],
            $provisioning,
            [],
            'Already current.',
        );

        self::assertSame('initialize', $result->operation());
        self::assertSame($started, $result->startedAt());
        self::assertSame($completed, $result->completedAt());
        self::assertFalse($result->due());
        self::assertSame([$module], $result->modules());
        self::assertSame($provisioning, $result->provisioning());
        self::assertSame([], $result->errors());
        self::assertSame('Already current.', $result->skipReason());
        self::assertTrue($result->succeeded());
        self::assertSame('initialize', $result->toArray()['operation']);
        self::assertSame($provisioning->toArray(), $result->toArray()['provisioning']);
    }

    /**
     * Verifies every independent failure source fails the aggregate.
     *
     * @return void
     * @since 1.0.0
     */
    public function testMaintenanceResultDetectsModuleOperationAndProvisioningFailures(): void
    {
        $now = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $failedModule = new MaintenanceModuleResult(
            'KJV',
            MaintenanceModuleResult::STATUS_FAILED,
            'Snapshot failed.',
        );
        $failedProvisioning = new ProvisioningResult(
            'install',
            [
                new ModuleProvisioningResult(
                    'KJV',
                    ModuleProvisioningResult::ACTION_INSTALL,
                    ModuleProvisioningResult::STATUS_FAILED,
                    'Install failed.',
                ),
            ],
        );

        self::assertFalse(
            (new MaintenanceResult('refresh', $now, $now, true, [$failedModule], null, []))
                ->succeeded(),
        );
        self::assertFalse(
            (new MaintenanceResult('refresh', $now, $now, true, [], null, ['Run failed.']))
                ->succeeded(),
        );
        self::assertFalse(
            (new MaintenanceResult('refresh', $now, $now, true, [], $failedProvisioning, []))
                ->succeeded(),
        );
    }

    /**
     * Verifies operational status exposes state, policy, and capabilities.
     *
     * @return void
     * @since 1.0.0
     */
    public function testStatusExposesCompleteHealthSnapshot(): void
    {
        $checked = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $nextDue = $checked->modify('+1 month');
        $state = MaintenanceState::empty()->succeededAt($checked);
        $capabilities = ProvisioningCapabilities::abiV1();
        $status = new MaintenanceStatus(
            $checked,
            false,
            $nextDue,
            $state,
            $capabilities,
            false,
            ['KJV'],
            ['KJV', 'WEB'],
        );

        self::assertFalse($status->due());
        self::assertSame($state, $status->state());
        self::assertSame([
            'checked_at' => $checked->format(DATE_ATOM),
            'due' => false,
            'next_due_at' => $nextDue->format(DATE_ATOM),
            'provisioning_enabled' => false,
            'capabilities' => $capabilities->toArray(),
            'configured_modules' => ['KJV'],
            'installed_modules' => ['KJV', 'WEB'],
            'state' => $state->toArray(),
        ], $status->toArray());
    }

    /**
     * Verifies malformed maintenance values are rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInvalidMaintenanceValuesAreRejected(): void
    {
        try {
            new MaintenanceModuleResult('', MaintenanceModuleResult::STATUS_READY);
            self::fail('An empty module identifier was accepted.');
        } catch (\InvalidArgumentException) {
        }

        try {
            new MaintenanceModuleResult('KJV', 'unknown');
            self::fail('An unsupported module status was accepted.');
        } catch (\InvalidArgumentException) {
        }

        $now = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        try {
            new MaintenanceResult('', $now, $now, true, [], null, []);
            self::fail('An empty maintenance operation was accepted.');
        } catch (\InvalidArgumentException) {
        }

        try {
            new MaintenanceResult('refresh', $now, $now->modify('-1 second'), true, [], null, []);
            self::fail('Reverse timestamps were accepted.');
        } catch (\InvalidArgumentException) {
        }

        try {
            new MaintenanceResult('refresh', $now, $now, true, ['invalid'], null, []);
            self::fail('A non-module result was accepted.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        new MaintenanceResult('refresh', $now, $now, true, [], null, ['']);
    }
}
