<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Maintenance;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Infrastructure\Lock\MaintenanceLockInterface;
use GetBible\Scripture\Maintenance\MaintenanceService;
use GetBible\Scripture\Maintenance\MaintenanceState;
use GetBible\Scripture\Maintenance\MaintenanceStateStoreInterface;
use GetBible\Scripture\Provisioning\ModuleProvisioningResult;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningCoordinatorInterface;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Verifies maintenance success, failure isolation, provisioning, and status policy.
 *
 * @since 1.0.0
 */
final class MaintenanceServiceBehaviorTest extends TestCase
{
    /**
     * Verifies forced refresh rebuilds an installed snapshot and updates health status.
     *
     * @return void
     * @since 1.0.0
     */
    public function testDueRefreshRebuildsInstalledModuleAndReportsStatus(): void
    {
        $metadata = $this->metadata();
        $catalog = $this->createStub(ModuleCatalogInterface::class);
        $catalog->method('translations')->willReturn([$metadata]);
        $snapshots = $this->createMock(SnapshotManagerInterface::class);
        $snapshots->expects(self::once())
            ->method('refresh')
            ->with('TestBible')
            ->willReturn($this->snapshot());
        $provisioning = $this->createStub(ProvisioningCoordinatorInterface::class);
        $provisioning->method('capabilities')->willReturn(ProvisioningCapabilities::abiV1());
        $store = $this->stateStore();
        $service = $this->service(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/scripture-maintenance-refresh',
                'modules' => ['TestBible'],
                'provisioning_enabled' => false,
            ]),
            $catalog,
            $snapshots,
            $provisioning,
            $store,
        );

        $result = $service->refreshIfDue([' TestBible ', 'TestBible']);

        self::assertTrue($result->succeeded());
        self::assertSame('refresh-if-due', $result->operation());
        self::assertSame('refreshed', $result->modules()[0]->status());
        self::assertNotNull($store->load()->lastSuccessAt());

        $status = $service->status()->toArray();
        self::assertFalse($status['due']);
        self::assertSame(['TestBible'], $status['configured_modules']);
        self::assertSame(['TestBible'], $status['installed_modules']);
        self::assertFalse($status['provisioning_enabled']);
    }

    /**
     * Verifies failed native provisioning and snapshot rebuilds are both isolated and persisted.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRefreshCapturesProvisioningAndSnapshotFailures(): void
    {
        $catalog = $this->createStub(ModuleCatalogInterface::class);
        $catalog->method('translations')->willReturn([$this->metadata()]);
        $snapshots = $this->createMock(SnapshotManagerInterface::class);
        $snapshots->expects(self::once())
            ->method('refresh')
            ->with('TestBible')
            ->willThrowException(new \RuntimeException('Snapshot rebuild failed.'));
        $provisioning = $this->createMock(ProvisioningCoordinatorInterface::class);
        $provisioning->method('capabilities')->willReturn(
            new ProvisioningCapabilities('test', 'test/v1', true, true, true, true),
        );
        $provisioning->expects(self::once())
            ->method('refresh')
            ->with(['TestBible'])
            ->willReturn(new ProvisioningResult('refresh', [
                new ModuleProvisioningResult(
                    'TestBible',
                    ModuleProvisioningResult::ACTION_REFRESH,
                    ModuleProvisioningResult::STATUS_FAILED,
                    'Remote mirror unavailable.',
                ),
            ]));
        $store = $this->stateStore();
        $service = $this->service(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/scripture-maintenance-failure',
                'modules' => ['TestBible'],
                'provisioning_enabled' => true,
            ]),
            $catalog,
            $snapshots,
            $provisioning,
            $store,
        );

        $result = $service->refresh();

        self::assertFalse($result->succeeded());
        self::assertSame(['Native provisioning failed for: TestBible.'], $result->errors());
        self::assertSame('failed', $result->modules()[0]->status());
        self::assertSame('Snapshot rebuild failed.', $result->modules()[0]->message());
        self::assertSame(1, $store->load()->consecutiveFailures());
        self::assertStringContainsString(
            'Snapshot rebuild failed.',
            (string) $store->load()->lastError(),
        );
    }

    /**
     * Verifies selected initialization installs a missing module before warming it.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInitializeInstallsMissingSelectedModule(): void
    {
        $metadata = $this->metadata();
        $catalog = $this->createStub(ModuleCatalogInterface::class);
        $catalog->method('translations')->willReturnOnConsecutiveCalls([], [$metadata]);
        $snapshots = $this->createMock(SnapshotManagerInterface::class);
        $snapshots->expects(self::once())
            ->method('get')
            ->with('TestBible')
            ->willReturn($this->snapshot());
        $provisioning = $this->createMock(ProvisioningCoordinatorInterface::class);
        $provisioning->method('capabilities')->willReturn(
            new ProvisioningCapabilities('test', 'test/v1', true, false, false, false),
        );
        $provisioning->expects(self::once())
            ->method('install')
            ->with(['TestBible'])
            ->willReturnCallback(
                static function (): ProvisioningResult {
                    return new ProvisioningResult('install', [
                        new ModuleProvisioningResult(
                            'TestBible',
                            ModuleProvisioningResult::ACTION_INSTALL,
                            ModuleProvisioningResult::STATUS_CHANGED,
                        ),
                    ]);
                },
            );
        $store = $this->stateStore();
        $service = $this->service(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/scripture-maintenance-install',
                'modules' => ['TestBible'],
                'provisioning_enabled' => true,
            ]),
            $catalog,
            $snapshots,
            $provisioning,
            $store,
        );

        $result = $service->initialize();

        self::assertTrue($result->succeeded());
        self::assertSame(['TestBible'], $result->provisioning()?->installed());
        self::assertSame('ready', $result->modules()[0]->status());
    }

    /**
     * Verifies unavailable policy paths and orchestration failures remain explicit.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsInvalidTargetsAndPropagatesCatalogFailure(): void
    {
        $emptyCatalog = $this->createStub(ModuleCatalogInterface::class);
        $emptyCatalog->method('translations')->willReturn([]);
        $snapshots = $this->createStub(SnapshotManagerInterface::class);
        $provisioning = $this->createStub(ProvisioningCoordinatorInterface::class);
        $provisioning->method('capabilities')->willReturn(ProvisioningCapabilities::abiV1());
        $service = $this->service(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/scripture-maintenance-policy',
                'modules' => ['Missing'],
                'provisioning_enabled' => false,
            ]),
            $emptyCatalog,
            $snapshots,
            $provisioning,
            $this->stateStore(),
        );
        $result = $service->initialize();

        self::assertFalse($result->succeeded());
        self::assertStringContainsString('provisioning is disabled', $result->errors()[0]);
        self::assertSame('The translation is not installed.', $result->modules()[0]->message());

        try {
            (new \ReflectionMethod($service, 'refresh'))->invoke($service, [123]);
            self::fail('A non-string maintenance target was accepted.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $failingCatalog = $this->createStub(ModuleCatalogInterface::class);
        $failingCatalog->method('translations')
            ->willThrowException(new \RuntimeException('Catalog unavailable.'));
        $failingService = $this->service(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/scripture-maintenance-catalog-failure',
            ]),
            $failingCatalog,
            $snapshots,
            $provisioning,
            $this->stateStore(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Catalog unavailable.');
        $failingService->refresh();
    }

    /**
     * Verifies thrown backend failures become durable run errors without blocking local rebuilding.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRefreshCapturesThrownProvisioningFailure(): void
    {
        $catalog = $this->createStub(ModuleCatalogInterface::class);
        $catalog->method('translations')->willReturn([$this->metadata()]);
        $snapshots = $this->createMock(SnapshotManagerInterface::class);
        $snapshots->expects(self::once())
            ->method('refresh')
            ->with('TestBible')
            ->willReturn($this->snapshot());
        $provisioning = $this->createMock(ProvisioningCoordinatorInterface::class);
        $provisioning->method('capabilities')->willReturn(
            new ProvisioningCapabilities('test', 'test/v1', false, false, true, false),
        );
        $provisioning->expects(self::once())
            ->method('refresh')
            ->with(['TestBible'])
            ->willThrowException(new \RuntimeException('Native backend crashed.'));
        $store = $this->stateStore();
        $service = $this->service(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/scripture-maintenance-native-failure',
                'modules' => ['TestBible'],
                'provisioning_enabled' => true,
            ]),
            $catalog,
            $snapshots,
            $provisioning,
            $store,
        );

        $result = $service->refresh();

        self::assertFalse($result->succeeded());
        self::assertSame(
            ['Native provisioning failed: Native backend crashed.'],
            $result->errors(),
        );
        self::assertSame('refreshed', $result->modules()[0]->status());
        self::assertSame(1, $store->load()->consecutiveFailures());
    }

    /**
     * Creates deterministic production orchestration around supplied test seams.
     *
     * @param Configuration $configuration Fixed policy.
     * @param ModuleCatalogInterface $catalog Catalog seam.
     * @param SnapshotManagerInterface $snapshots Snapshot seam.
     * @param ProvisioningCoordinatorInterface $provisioning Provisioning seam.
     * @param MaintenanceStateStoreInterface $store State seam.
     *
     * @return MaintenanceService
     * @since 1.0.0
     */
    private function service(
        Configuration $configuration,
        ModuleCatalogInterface $catalog,
        SnapshotManagerInterface $snapshots,
        ProvisioningCoordinatorInterface $provisioning,
        MaintenanceStateStoreInterface $store,
    ): MaintenanceService {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-07-26T12:01:00+00:00'));

        return new MaintenanceService(
            $configuration,
            $clock,
            $catalog,
            $snapshots,
            $provisioning,
            $store,
            $this->directLock(),
            new Dispatcher(),
        );
    }

    /**
     * Returns fixture translation metadata.
     *
     * @return TranslationMetadata
     * @since 1.0.0
     */
    private function metadata(): TranslationMetadata
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);
        $record = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($record);

        return TranslationMetadata::fromRecord($record);
    }

    /**
     * Returns an uninitialized value used only as a type-safe manager result.
     *
     * @return SnapshotIndex
     * @since 1.0.0
     */
    private function snapshot(): SnapshotIndex
    {
        return (new \ReflectionClass(SnapshotIndex::class))->newInstanceWithoutConstructor();
    }

    /**
     * Creates an in-memory durable state seam.
     *
     * @return MaintenanceStateStoreInterface
     * @since 1.0.0
     */
    private function stateStore(): MaintenanceStateStoreInterface
    {
        return new class () implements MaintenanceStateStoreInterface {
            /**
             * Current state.
             *
             * @var MaintenanceState
             */
            private MaintenanceState $state;

            /**
             * Creates empty state.
             */
            public function __construct()
            {
                $this->state = MaintenanceState::empty();
            }

            /**
             * Returns current state.
             *
             * @return MaintenanceState
             */
            public function load(): MaintenanceState
            {
                return $this->state;
            }

            /**
             * Persists current state.
             *
             * @param MaintenanceState $state Replacement state.
             *
             * @return void
             */
            public function save(MaintenanceState $state): void
            {
                $this->state = $state;
            }
        };
    }

    /**
     * Creates a direct whole-run lock seam.
     *
     * @return MaintenanceLockInterface
     * @since 1.0.0
     */
    private function directLock(): MaintenanceLockInterface
    {
        return new class () implements MaintenanceLockInterface {
            /**
             * Executes the operation directly.
             *
             * @template T
             *
             * @param callable(): T $operation Protected operation.
             *
             * @return T
             */
            public function run(callable $operation): mixed
            {
                return $operation();
            }
        };
    }
}
