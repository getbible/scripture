<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Provisioning;

use GetBible\Scripture\Provisioning\ModuleProvisioningResult;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use PHPUnit\Framework\TestCase;

/**
 * Verifies provisioning capability and outcome value objects.
 *
 * @since 1.0.0
 */
final class ProvisioningValueObjectsTest extends TestCase
{
    /**
     * Verifies an explicit capability set exposes every operation.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCapabilitiesExposeEveryFlag(): void
    {
        $capabilities = new ProvisioningCapabilities('fixture', 'fixture/v1', true, false, true, false);

        self::assertSame('fixture', $capabilities->backend());
        self::assertSame('fixture/v1', $capabilities->contract());
        self::assertTrue($capabilities->canInstallSelected());
        self::assertFalse($capabilities->canInstallAll());
        self::assertTrue($capabilities->canRefresh());
        self::assertFalse($capabilities->canRemove());
        self::assertTrue($capabilities->isAvailable());
        self::assertSame([
            'backend' => 'fixture',
            'contract' => 'fixture/v1',
            'install_selected' => true,
            'install_all' => false,
            'refresh' => true,
            'remove' => false,
        ], $capabilities->toArray());
    }

    /**
     * Verifies ABI v1 accurately reports no mutating support.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAbiV1CapabilitiesAreUnavailable(): void
    {
        $capabilities = ProvisioningCapabilities::abiV1();

        self::assertSame('getbible/sword', $capabilities->backend());
        self::assertSame('getbiblesword.ndjson/v1', $capabilities->contract());
        self::assertFalse($capabilities->canInstallSelected());
        self::assertFalse($capabilities->canInstallAll());
        self::assertFalse($capabilities->canRefresh());
        self::assertFalse($capabilities->canRemove());
        self::assertFalse($capabilities->isAvailable());
    }

    /**
     * Verifies backend and contract identifiers are required.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCapabilitiesRejectBlankIdentifiers(): void
    {
        foreach ([['', 'v1'], ['backend', " \t"]] as [$backend, $contract]) {
            try {
                new ProvisioningCapabilities($backend, $contract, false, false, false, false);
                self::fail('A blank provisioning identifier was accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('identifiers', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies one module result exposes its complete serialized outcome.
     *
     * @return void
     * @since 1.0.0
     */
    public function testModuleResultExposesCompleteOutcome(): void
    {
        $result = new ModuleProvisioningResult(
            'KJV',
            ModuleProvisioningResult::ACTION_REFRESH,
            ModuleProvisioningResult::STATUS_FAILED,
            'Network unavailable.',
            ['attempt' => 2, 'retryable' => true],
        );

        self::assertSame('KJV', $result->module());
        self::assertSame(ModuleProvisioningResult::ACTION_REFRESH, $result->action());
        self::assertSame(ModuleProvisioningResult::STATUS_FAILED, $result->status());
        self::assertSame('Network unavailable.', $result->message());
        self::assertSame(['attempt' => 2, 'retryable' => true], $result->details());
        self::assertTrue($result->failed());
        self::assertSame([
            'module' => 'KJV',
            'action' => 'refresh',
            'status' => 'failed',
            'message' => 'Network unavailable.',
            'details' => ['attempt' => 2, 'retryable' => true],
        ], $result->toArray());
    }

    /**
     * Verifies module outcomes reject missing identifiers and unknown values.
     *
     * @return void
     * @since 1.0.0
     */
    public function testModuleResultRejectsInvalidMembers(): void
    {
        foreach (
            [
                ['', ModuleProvisioningResult::ACTION_INSTALL, ModuleProvisioningResult::STATUS_CHANGED],
                ['KJV', 'upgrade', ModuleProvisioningResult::STATUS_CHANGED],
                ['KJV', ModuleProvisioningResult::ACTION_INSTALL, 'unknown'],
            ] as [$module, $action, $status]
        ) {
            try {
                new ModuleProvisioningResult($module, $action, $status);
                self::fail('An invalid module provisioning result was accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies aggregate result filters preserve backend ordering.
     *
     * @return void
     * @since 1.0.0
     */
    public function testProvisioningResultExposesEveryOutcomeGroup(): void
    {
        $installed = new ModuleProvisioningResult(
            'KJV',
            ModuleProvisioningResult::ACTION_INSTALL,
            ModuleProvisioningResult::STATUS_CHANGED,
        );
        $updated = new ModuleProvisioningResult(
            'WEB',
            ModuleProvisioningResult::ACTION_REFRESH,
            ModuleProvisioningResult::STATUS_CHANGED,
        );
        $skipped = new ModuleProvisioningResult(
            'ASV',
            ModuleProvisioningResult::ACTION_REFRESH,
            ModuleProvisioningResult::STATUS_SKIPPED,
        );
        $removed = new ModuleProvisioningResult(
            'OLD',
            ModuleProvisioningResult::ACTION_REMOVE,
            ModuleProvisioningResult::STATUS_CHANGED,
        );
        $failed = new ModuleProvisioningResult(
            'BROKEN',
            ModuleProvisioningResult::ACTION_INSTALL,
            ModuleProvisioningResult::STATUS_FAILED,
        );
        $result = new ProvisioningResult('synchronize', [$installed, $updated, $skipped, $removed, $failed]);

        self::assertSame('synchronize', $result->operation());
        self::assertSame([$installed, $updated, $skipped, $removed, $failed], $result->modules());
        self::assertSame(['KJV'], $result->installed());
        self::assertSame(['WEB'], $result->updated());
        self::assertSame(['ASV'], $result->skipped());
        self::assertSame(['OLD'], $result->removed());
        self::assertSame(['BROKEN'], $result->failed());
        self::assertFalse($result->succeeded());
        self::assertTrue($result->changed());
        self::assertFalse($result->toArray()['succeeded']);
        self::assertCount(5, $result->toArray()['modules']);
    }

    /**
     * Verifies an empty aggregate is successful and unchanged.
     *
     * @return void
     * @since 1.0.0
     */
    public function testEmptyProvisioningResultIsSuccessfulAndUnchanged(): void
    {
        $result = new ProvisioningResult('noop', []);

        self::assertTrue($result->succeeded());
        self::assertFalse($result->changed());
        self::assertSame([], $result->failed());
    }

    /**
     * Verifies aggregate operation names and outcome objects are validated.
     *
     * @return void
     * @since 1.0.0
     */
    public function testProvisioningResultRejectsInvalidMembers(): void
    {
        foreach ([['', []], ['refresh', ['KJV']]] as [$operation, $modules]) {
            try {
                new ProvisioningResult($operation, $modules);
                self::fail('An invalid aggregate provisioning result was accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}
