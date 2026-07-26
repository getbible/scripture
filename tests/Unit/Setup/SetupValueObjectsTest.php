<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\MaintenanceModuleResult;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use GetBible\Scripture\Setup\SetupRequest;
use GetBible\Scripture\Setup\SetupResult;
use PHPUnit\Framework\TestCase;

/**
 * Verifies setup request, runtime report, and setup result value semantics.
 *
 * @since 1.0.0
 */
final class SetupValueObjectsTest extends TestCase
{
    /**
     * Verifies setup requests preserve all caller choices.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSetupRequestExposesCallerChoices(): void
    {
        $request = new SetupRequest(
            '/srv/application/scripture.json',
            ['cache_path' => '/srv/cache', 'modules' => ['KJV']],
            false,
        );

        self::assertSame('/srv/application/scripture.json', $request->configurationPath());
        self::assertSame(
            ['cache_path' => '/srv/cache', 'modules' => ['KJV']],
            $request->values(),
        );
        self::assertFalse($request->warm());
    }

    /**
     * Verifies a compatibility report exposes and serializes every diagnostic.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRuntimeReportExposesCompleteDiagnostics(): void
    {
        $report = new RuntimePrerequisiteReport(
            true,
            false,
            '0.1.0',
            2,
            'other-contract',
            '0.3.0',
            'Engine class unavailable.',
        );

        self::assertFalse($report->ready());
        self::assertTrue($report->extensionLoaded());
        self::assertFalse($report->engineClassAvailable());
        self::assertSame('0.1.0', $report->extensionVersion());
        self::assertSame(2, $report->abiVersion());
        self::assertSame('other-contract', $report->contractIdentifier());
        self::assertSame('0.3.0', $report->productVersion());
        self::assertSame('Engine class unavailable.', $report->error());
        self::assertSame([
            'ready' => false,
            'extension_loaded' => true,
            'engine_class_available' => false,
            'extension_version' => '0.1.0',
            'abi' => [
                'expected' => RuntimePrerequisiteReport::EXPECTED_ABI,
                'actual' => 2,
                'compatible' => false,
            ],
            'contract' => [
                'expected' => RuntimePrerequisiteReport::EXPECTED_CONTRACT,
                'actual' => 'other-contract',
                'compatible' => false,
            ],
            'product_version' => '0.3.0',
            'error' => 'Engine class unavailable.',
        ], $report->toArray());
    }

    /**
     * Verifies an empty runtime inspection error is rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRuntimeReportRejectsEmptyError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty');

        new RuntimePrerequisiteReport(false, false, null, null, null, null, ' ');
    }

    /**
     * Verifies a no-warm setup succeeds and serializes complete configuration.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSetupResultSerializesSuccessfulNoWarmOutcome(): void
    {
        $configuration = $this->configuration();
        $runtime = $this->readyRuntime();
        $result = new SetupResult(
            '/srv/application/scripture.json',
            $configuration,
            $runtime,
            false,
            null,
        );

        self::assertTrue($result->succeeded());
        self::assertSame('/srv/application/scripture.json', $result->configurationPath());
        self::assertSame($configuration, $result->configuration());
        self::assertSame($runtime, $result->runtime());
        self::assertFalse($result->warmRequested());
        self::assertNull($result->maintenance());
        self::assertSame([], $result->errors());
        self::assertSame([
            'module_path' => '/srv/sword',
            'cache_path' => '/srv/cache',
            'refresh_interval' => 'P7D',
            'auto_refresh' => false,
            'lock_timeout' => 45,
            'modules' => ['KJV'],
            'provisioning_enabled' => false,
            'install_all' => false,
        ], $result->toArray()['configuration']);
    }

    /**
     * Verifies requested warming succeeds only with a successful result.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSetupResultReflectsWarmingOutcome(): void
    {
        $successful = new SetupResult(
            '/srv/application/scripture.json',
            $this->configuration(),
            $this->readyRuntime(),
            true,
            $this->maintenance(MaintenanceModuleResult::STATUS_READY),
        );
        $failed = new SetupResult(
            '/srv/application/scripture.json',
            $this->configuration(),
            $this->readyRuntime(),
            true,
            $this->maintenance(MaintenanceModuleResult::STATUS_FAILED),
        );

        self::assertTrue($successful->succeeded());
        self::assertFalse($failed->succeeded());
        self::assertSame('initialize', $successful->maintenance()?->operation());
        self::assertFalse($failed->toArray()['succeeded']);
    }

    /**
     * Verifies explicit errors override otherwise successful outcomes.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSetupResultReportsExplicitErrors(): void
    {
        $result = new SetupResult(
            '/srv/application/scripture.json',
            $this->configuration(),
            $this->readyRuntime(),
            false,
            null,
            ['Persistence warning.'],
        );

        self::assertFalse($result->succeeded());
        self::assertSame(['Persistence warning.'], $result->errors());
        self::assertSame(['Persistence warning.'], $result->toArray()['errors']);
    }

    /**
     * Verifies empty setup errors are rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSetupResultRejectsEmptyError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be non-empty');

        new SetupResult(
            '/srv/application/scripture.json',
            $this->configuration(),
            $this->readyRuntime(),
            false,
            null,
            [' '],
        );
    }

    /**
     * Creates complete candidate configuration.
     *
     * @return Configuration
     * @since 1.0.0
     */
    private function configuration(): Configuration
    {
        return Configuration::fromEnvironment([
            'module_path' => '/srv/sword',
            'cache_path' => '/srv/cache',
            'refresh_interval' => 'P7D',
            'auto_refresh' => false,
            'lock_timeout' => 45,
            'modules' => ['KJV'],
        ]);
    }

    /**
     * Creates a compatible runtime report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    private function readyRuntime(): RuntimePrerequisiteReport
    {
        return new RuntimePrerequisiteReport(
            true,
            true,
            '0.1.0',
            RuntimePrerequisiteReport::EXPECTED_ABI,
            RuntimePrerequisiteReport::EXPECTED_CONTRACT,
            '0.3.0',
        );
    }

    /**
     * Creates one deterministic maintenance result.
     *
     * @param string $status Module status.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    private function maintenance(string $status): MaintenanceResult
    {
        $time = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        return new MaintenanceResult(
            'initialize',
            $time,
            $time,
            true,
            [new MaintenanceModuleResult('KJV', $status)],
            null,
            [],
        );
    }
}
