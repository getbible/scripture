<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Console\InitializeCommand;
use GetBible\Scripture\Console\RefreshCommand;
use GetBible\Scripture\Console\StatusCommand;
use GetBible\Scripture\Maintenance\MaintenanceModuleResult;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use GetBible\Scripture\Maintenance\MaintenanceState;
use GetBible\Scripture\Maintenance\MaintenanceStatus;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use Joomla\Console\Command\AbstractCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Verifies maintenance command dispatch, exit codes, and JSON output.
 *
 * @since 1.0.0
 */
final class MaintenanceCommandsTest extends TestCase
{
    /**
     * Verifies initialize forwards modules and the explicit all flag.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInitializeDispatchesSelectedModules(): void
    {
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->expects(self::once())
            ->method('initialize')
            ->with(['KJV', 'WEB'], true)
            ->willReturn($this->result('initialize', MaintenanceModuleResult::STATUS_READY));

        [$exitCode, $payload] = $this->execute(new InitializeCommand($maintenance), [
            '--module' => ['KJV', 'WEB'],
            '--all' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('initialize', $payload['operation'] ?? null);
        self::assertTrue($payload['succeeded'] ?? false);
    }

    /**
     * Verifies initialize returns a failing process status for module failure.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInitializeReturnsFailureExitCode(): void
    {
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->method('initialize')->willReturn(
            $this->result('initialize', MaintenanceModuleResult::STATUS_FAILED),
        );

        [$exitCode, $payload] = $this->execute(new InitializeCommand($maintenance), []);

        self::assertSame(1, $exitCode);
        self::assertFalse($payload['succeeded'] ?? true);
    }

    /**
     * Verifies a forced refresh uses the unconditional service operation.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRefreshDispatchesForcedOperation(): void
    {
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->expects(self::once())
            ->method('refresh')
            ->with(['KJV'])
            ->willReturn($this->result('refresh', MaintenanceModuleResult::STATUS_REFRESHED));
        $maintenance->expects(self::never())->method('refreshIfDue');

        [$exitCode, $payload] = $this->execute(new RefreshCommand($maintenance), [
            '--module' => ['KJV'],
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('refresh', $payload['operation'] ?? null);
    }

    /**
     * Verifies interval-gated refresh uses the due-aware service operation.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRefreshDispatchesIfDueOperation(): void
    {
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->expects(self::never())->method('refresh');
        $maintenance->expects(self::once())
            ->method('refreshIfDue')
            ->with(['WEB'])
            ->willReturn($this->result('refresh-if-due', MaintenanceModuleResult::STATUS_SKIPPED));

        [$exitCode, $payload] = $this->execute(new RefreshCommand($maintenance), [
            '--module' => ['WEB'],
            '--if-due' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('refresh-if-due', $payload['operation'] ?? null);
    }

    /**
     * Verifies status emits the service's complete health report.
     *
     * @return void
     * @since 1.0.0
     */
    public function testStatusEmitsOperationalState(): void
    {
        $status = new MaintenanceStatus(
            new \DateTimeImmutable('2026-07-26T12:00:00+00:00'),
            false,
            new \DateTimeImmutable('2026-08-26T12:00:00+00:00'),
            MaintenanceState::empty(),
            ProvisioningCapabilities::abiV1(),
            false,
            ['KJV'],
            ['KJV', 'WEB'],
        );
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->expects(self::once())->method('status')->willReturn($status);

        [$exitCode, $payload] = $this->execute(new StatusCommand($maintenance), []);

        self::assertSame(0, $exitCode);
        self::assertFalse($payload['due'] ?? true);
        self::assertSame(['KJV'], $payload['configured_modules'] ?? null);
        self::assertSame(['KJV', 'WEB'], $payload['installed_modules'] ?? null);
    }

    /**
     * Executes one Joomla command and decodes its JSON output.
     *
     * @param AbstractCommand $command Command under test.
     * @param array<string, bool|list<string>> $parameters Input parameters.
     *
     * @return array{int, array<string, mixed>}
     * @since 1.0.0
     */
    private function execute(AbstractCommand $command, array $parameters): array
    {
        $input = new ArrayInput($parameters);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $exitCode = $command->execute($input, $output);
        $payload = json_decode($output->fetch(), true, 32, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return [$exitCode, $payload];
    }

    /**
     * Creates a deterministic maintenance outcome.
     *
     * @param string $operation Operation name.
     * @param string $status Module status.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    private function result(string $operation, string $status): MaintenanceResult
    {
        $time = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        return new MaintenanceResult(
            $operation,
            $time,
            $time,
            true,
            [new MaintenanceModuleResult('KJV', $status)],
            null,
            [],
        );
    }
}
