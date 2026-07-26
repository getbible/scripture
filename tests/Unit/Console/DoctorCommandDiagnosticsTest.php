<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Console\DoctorCommand;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use GetBible\Scripture\Setup\SetupServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Verifies human-readable and error diagnostic branches of doctor.
 *
 * @since 1.0.0
 */
final class DoctorCommandDiagnosticsTest extends TestCase
{
    /**
     * Verifies interactive doctor output leads with a concise success message.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInteractiveDoctorReportsSuccessAndJson(): void
    {
        $service = $this->createMock(SetupServiceInterface::class);
        $service->method('inspect')->willReturn($this->readyRuntime());
        $service->expects(self::once())
            ->method('configuration')
            ->with(null)
            ->willReturn(Configuration::fromEnvironment(['cache_path' => '/srv/cache']));
        $input = new ArrayInput([]);
        $input->setInteractive(true);
        $output = new BufferedOutput();

        $exitCode = (new DoctorCommand($service))->execute($input, $output);
        $display = $output->fetch();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Scripture is ready.', $display);
        self::assertStringContainsString('"operation": "doctor"', $display);
    }

    /**
     * Verifies invalid configuration paths become deterministic diagnostics.
     *
     * @return void
     * @since 1.0.0
     */
    public function testDoctorReportsConfigurationPathFailure(): void
    {
        $service = $this->createMock(SetupServiceInterface::class);
        $service->method('inspect')->willReturn($this->readyRuntime());
        $service->expects(self::never())->method('configuration');
        $input = new ArrayInput([
            '--config' => 'relative.json',
            '--json' => true,
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        $exitCode = (new DoctorCommand($service))->execute($input, $output);
        $payload = json_decode($output->fetch(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertIsArray($payload);
        self::assertFalse($payload['succeeded'] ?? true);
        self::assertSame('relative.json', $payload['configuration_path'] ?? null);
        self::assertNull($payload['configuration'] ?? null);
        self::assertStringContainsString(
            'must be absolute',
            (string) ($payload['errors'][0] ?? ''),
        );
    }

    /**
     * Verifies incompatible runtime produces a human-readable failure heading.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInteractiveDoctorReportsRuntimeFailure(): void
    {
        $service = $this->createStub(SetupServiceInterface::class);
        $service->method('inspect')->willReturn(new RuntimePrerequisiteReport(
            false,
            false,
            null,
            null,
            null,
            null,
        ));
        $service->method('configuration')->willReturn(
            Configuration::fromEnvironment(['cache_path' => '/srv/cache']),
        );
        $input = new ArrayInput([]);
        $input->setInteractive(true);
        $output = new BufferedOutput();

        $exitCode = (new DoctorCommand($service))->execute($input, $output);
        $display = $output->fetch();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Scripture is not ready.', $display);
        self::assertStringContainsString('"ready": false', $display);
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
}
