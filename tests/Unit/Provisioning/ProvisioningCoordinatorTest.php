<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Provisioning;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Provisioning\ModuleProvisionerInterface;
use GetBible\Scripture\Provisioning\ModuleProvisioningResult;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningCoordinator;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Verifies module mutation locking, normalization, and cache invalidation.
 *
 * @since 0.2.0
 */
final class ProvisioningCoordinatorTest extends TestCase
{
    /**
     * Verifies one remote refresh is serialized and clears process caches.
     *
     * @return void
     * @since 0.2.0
     */
    public function testRefreshIsLockedAndInvalidatesCaches(): void
    {
        $provisioner = new class () implements ModuleProvisionerInterface {
            /**
             * Last requested module identifiers.
             *
             * @var list<string>
             */
            public array $refreshed = [];

            /**
             * Returns a complete test capability set.
             *
             * @return ProvisioningCapabilities
             */
            public function capabilities(): ProvisioningCapabilities
            {
                return new ProvisioningCapabilities('test', 'test/v1', true, true, true, true);
            }

            /**
             * Rejects an unused selected installation.
             *
             * @param list<string> $modules Module identifiers.
             *
             * @return ProvisioningResult
             */
            public function installTranslations(array $modules): ProvisioningResult
            {
                throw new \LogicException('Not used in this test.');
            }

            /**
             * Rejects an unused all-module installation.
             *
             * @return ProvisioningResult
             */
            public function installAllTranslations(): ProvisioningResult
            {
                throw new \LogicException('Not used in this test.');
            }

            /**
             * Returns changed outcomes for every requested module.
             *
             * @param list<string> $modules Module identifiers.
             *
             * @return ProvisioningResult
             */
            public function refreshTranslations(array $modules = []): ProvisioningResult
            {
                $this->refreshed = $modules;

                return new ProvisioningResult(
                    'refresh',
                    array_map(
                        static fn (string $module): ModuleProvisioningResult => new ModuleProvisioningResult(
                            $module,
                            ModuleProvisioningResult::ACTION_REFRESH,
                            ModuleProvisioningResult::STATUS_CHANGED,
                        ),
                        $modules,
                    ),
                );
            }

            /**
             * Rejects an unused module removal.
             *
             * @param string $module Module identifier.
             *
             * @return ProvisioningResult
             */
            public function removeTranslation(string $module): ProvisioningResult
            {
                throw new \LogicException('Not used in this test.');
            }
        };

        $lock = new class () implements ModuleRootLockInterface {
            /**
             * Number of acquired write locks.
             *
             * @var int
             */
            public int $writes = 0;

            /**
             * Executes a read callback.
             *
             * @template T
             *
             * @param callable(): T $operation Callback.
             *
             * @return T
             */
            public function read(callable $operation): mixed
            {
                return $operation();
            }

            /**
             * Executes a write callback.
             *
             * @template T
             *
             * @param callable(): T $operation Callback.
             *
             * @return T
             */
            public function write(callable $operation): mixed
            {
                ++$this->writes;

                return $operation();
            }
        };

        $catalog = new class () implements ModuleCatalogInterface {
            /**
             * Number of process-cache invalidations.
             *
             * @var int
             */
            public int $clears = 0;

            /**
             * Returns no translations.
             *
             * @return list<TranslationMetadata>
             */
            public function translations(): array
            {
                return [];
            }

            /**
             * Rejects an unused lookup.
             *
             * @param string $module Module identifier.
             *
             * @return TranslationMetadata
             */
            public function translation(string $module): TranslationMetadata
            {
                throw new \LogicException('Not used in this test.');
            }

            /**
             * Counts process-cache invalidations.
             *
             * @return void
             */
            public function clear(): void
            {
                ++$this->clears;
            }
        };

        $snapshots = new class () implements SnapshotManagerInterface {
            /**
             * Number of process-cache invalidations.
             *
             * @var int
             */
            public int $clears = 0;

            /**
             * Rejects an unused snapshot lookup.
             *
             * @param string $module Module identifier.
             *
             * @return SnapshotIndex
             */
            public function get(string $module): SnapshotIndex
            {
                throw new \LogicException('Not used in this test.');
            }

            /**
             * Rejects an unused snapshot refresh.
             *
             * @param string $module Module identifier.
             *
             * @return SnapshotIndex
             */
            public function refresh(string $module): SnapshotIndex
            {
                throw new \LogicException('Not used in this test.');
            }

            /**
             * Counts process-cache invalidations.
             *
             * @return void
             */
            public function clear(): void
            {
                ++$this->clears;
            }
        };

        $coordinator = new ProvisioningCoordinator(
            $provisioner,
            $lock,
            $catalog,
            $snapshots,
            new Dispatcher(),
        );
        $result = $coordinator->refresh([' KJV ', 'KJV', 'WEB']);

        self::assertSame(['KJV', 'WEB'], $provisioner->refreshed);
        self::assertSame(['KJV', 'WEB'], $result->updated());
        self::assertSame(1, $lock->writes);
        self::assertSame(1, $catalog->clears);
        self::assertSame(1, $snapshots->clears);
    }
}
