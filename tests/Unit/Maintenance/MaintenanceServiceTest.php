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
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningCoordinatorInterface;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Verifies initialization and interval-gated maintenance orchestration.
 *
 * @since 0.3.0
 */
final class MaintenanceServiceTest extends TestCase
{
    /**
     * Verifies an installed configured translation is warmed and records success.
     *
     * @return void
     * @since 0.3.0
     */
    public function testInitializeWarmsInstalledTranslationAndRecordsSuccess(): void
    {
        $fixture = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($fixture);
        $record = json_decode($fixture[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $metadata = TranslationMetadata::fromRecord($record);
        $catalog = $this->catalog($metadata);
        $snapshots = $this->snapshots();
        $store = $this->stateStore();
        $service = new MaintenanceService(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/getbible-scripture-maintenance-test',
                'modules' => ['TestBible'],
                'provisioning_enabled' => false,
            ]),
            $this->clock(),
            $catalog,
            $snapshots,
            $this->provisioning(),
            $store,
            $this->lock(),
            new Dispatcher(),
        );

        $result = $service->initialize();

        self::assertTrue($result->succeeded());
        self::assertSame('ready', $result->modules()[0]->status());
        self::assertNotNull($store->load()->lastSuccessAt());
    }

    /**
     * Verifies interval-gated refresh skips without touching native services.
     *
     * @return void
     * @since 0.3.0
     */
    public function testRefreshIfDueSkipsAfterRecentSuccess(): void
    {
        $store = $this->stateStore();
        $store->save(
            $store->load()->succeededAt(new \DateTimeImmutable('2026-07-26T12:00:00+00:00')),
        );
        $service = new MaintenanceService(
            Configuration::fromEnvironment([
                'cache_path' => sys_get_temp_dir() . '/getbible-scripture-maintenance-test',
                'refresh_interval' => 'P1M',
            ]),
            $this->clock(),
            $this->catalog(),
            $this->snapshots(),
            $this->provisioning(),
            $store,
            $this->lock(),
            new Dispatcher(),
        );

        $result = $service->refreshIfDue();

        self::assertTrue($result->succeeded());
        self::assertFalse($result->due());
        self::assertSame('The configured refresh interval has not elapsed.', $result->skipReason());
    }

    /**
     * Creates a fixed clock.
     *
     * @return ClockInterface
     * @since 0.3.0
     */
    private function clock(): ClockInterface
    {
        return new class () implements ClockInterface {
            /**
             * Returns deterministic UTC time.
             *
             * @return \DateTimeImmutable
             */
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-07-26T12:01:00+00:00');
            }
        };
    }

    /**
     * Creates an in-memory state store.
     *
     * @return MaintenanceStateStoreInterface
     * @since 0.3.0
     */
    private function stateStore(): MaintenanceStateStoreInterface
    {
        return new class () implements MaintenanceStateStoreInterface {
            /**
             * Current in-memory state.
             *
             * @var MaintenanceState
             */
            public MaintenanceState $state;

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
             * Replaces current state.
             *
             * @param MaintenanceState $state New state.
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
     * Creates a direct test maintenance lock.
     *
     * @return MaintenanceLockInterface
     * @since 0.3.0
     */
    private function lock(): MaintenanceLockInterface
    {
        return new class () implements MaintenanceLockInterface {
            /**
             * Executes the callback directly.
             *
             * @template T
             *
             * @param callable(): T $operation Callback.
             *
             * @return T
             */
            public function run(callable $operation): mixed
            {
                return $operation();
            }
        };
    }

    /**
     * Creates a fixed installed-module catalog.
     *
     * @param TranslationMetadata|null $metadata Optional installed translation.
     *
     * @return ModuleCatalogInterface
     * @since 0.3.0
     */
    private function catalog(?TranslationMetadata $metadata = null): ModuleCatalogInterface
    {
        return new class ($metadata) implements ModuleCatalogInterface {
            /**
             * Creates a fixed catalog.
             *
             * @param TranslationMetadata|null $metadata Optional translation.
             */
            public function __construct(private ?TranslationMetadata $metadata)
            {
            }

            /**
             * Returns the optional translation.
             *
             * @return list<TranslationMetadata>
             */
            public function translations(): array
            {
                return $this->metadata === null ? [] : [$this->metadata];
            }

            /**
             * Returns the fixed translation.
             *
             * @param string $module Module identifier.
             *
             * @return TranslationMetadata
             */
            public function translation(string $module): TranslationMetadata
            {
                if ($this->metadata === null || $this->metadata->name()->bytes() !== $module) {
                    throw new \RuntimeException('Translation is not installed.');
                }

                return $this->metadata;
            }

            /**
             * Clears no in-memory state.
             *
             * @return void
             */
            public function clear(): void
            {
            }
        };
    }

    /**
     * Creates a counting snapshot manager.
     *
     * @return SnapshotManagerInterface
     * @since 0.3.0
     */
    private function snapshots(): SnapshotManagerInterface
    {
        $reflection = new \ReflectionClass(SnapshotIndex::class);
        $snapshot = $reflection->newInstanceWithoutConstructor();

        if (!$snapshot instanceof SnapshotIndex) {
            throw new \RuntimeException('Unable to create a type-safe SnapshotIndex test object.');
        }

        return new class ($snapshot) implements SnapshotManagerInterface {
            /**
             * Number of get calls.
             *
             * @var int
             */
            public int $gets = 0;

            /**
             * Number of refresh calls.
             *
             * @var int
             */
            public int $refreshes = 0;

            /**
             * Creates the snapshot manager.
             *
             * @param SnapshotIndex $snapshot Uninitialized type-safe test object.
             */
            public function __construct(private SnapshotIndex $snapshot)
            {
            }

            /**
             * Counts and returns a snapshot.
             *
             * @param string $module Module identifier.
             *
             * @return SnapshotIndex
             */
            public function get(string $module): SnapshotIndex
            {
                ++$this->gets;

                return $this->snapshot;
            }

            /**
             * Counts and returns a refreshed snapshot.
             *
             * @param string $module Module identifier.
             *
             * @return SnapshotIndex
             */
            public function refresh(string $module): SnapshotIndex
            {
                ++$this->refreshes;

                return $this->snapshot;
            }

            /**
             * Clears no process state.
             *
             * @return void
             */
            public function clear(): void
            {
            }
        };
    }

    /**
     * Creates an unavailable provisioning coordinator.
     *
     * @return ProvisioningCoordinatorInterface
     * @since 0.3.0
     */
    private function provisioning(): ProvisioningCoordinatorInterface
    {
        return new class () implements ProvisioningCoordinatorInterface {
            /**
             * Returns ABI v1 capabilities.
             *
             * @return ProvisioningCapabilities
             */
            public function capabilities(): ProvisioningCapabilities
            {
                return ProvisioningCapabilities::abiV1();
            }

            /**
             * Rejects selected installation.
             *
             * @param list<string> $modules Module identifiers.
             *
             * @return ProvisioningResult
             */
            public function install(array $modules): ProvisioningResult
            {
                throw new \LogicException('Provisioning is unavailable.');
            }

            /**
             * Rejects all-module installation.
             *
             * @return ProvisioningResult
             */
            public function installAll(): ProvisioningResult
            {
                throw new \LogicException('Provisioning is unavailable.');
            }

            /**
             * Rejects remote refresh.
             *
             * @param list<string> $modules Module identifiers.
             *
             * @return ProvisioningResult
             */
            public function refresh(array $modules = []): ProvisioningResult
            {
                throw new \LogicException('Provisioning is unavailable.');
            }

            /**
             * Rejects removal.
             *
             * @param string $module Module identifier.
             *
             * @return ProvisioningResult
             */
            public function remove(string $module): ProvisioningResult
            {
                throw new \LogicException('Provisioning is unavailable.');
            }
        };
    }
}
