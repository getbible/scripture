<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Provisioning;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Event\EventName;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Provisioning\ModuleProvisionerInterface;
use GetBible\Scripture\Provisioning\ModuleProvisioningResult;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningCoordinator;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Verifies every coordinator operation and its failure boundary.
 *
 * @since 1.0.0
 */
final class ProvisioningCoordinatorOperationsTest extends TestCase
{
    /**
     * Verifies install, install-all, and removal are locked and invalidated.
     *
     * @return void
     * @since 1.0.0
     */
    public function testMutationOperationsDelegateUnderLock(): void
    {
        $capabilities = new ProvisioningCapabilities('fixture', 'fixture/v1', true, true, true, true);
        $provisioner = $this->createMock(ModuleProvisionerInterface::class);
        $provisioner->method('capabilities')->willReturn($capabilities);
        $provisioner->expects(self::once())
            ->method('installTranslations')
            ->with(['KJV'])
            ->willReturn($this->result('install-selected', 'KJV', ModuleProvisioningResult::ACTION_INSTALL));
        $provisioner->expects(self::once())
            ->method('installAllTranslations')
            ->willReturn(new ProvisioningResult('install-all', []));
        $provisioner->expects(self::once())
            ->method('removeTranslation')
            ->with('WEB')
            ->willReturn($this->result('remove', 'WEB', ModuleProvisioningResult::ACTION_REMOVE));

        $lock = $this->createMock(ModuleRootLockInterface::class);
        $lock->expects(self::exactly(3))
            ->method('write')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $catalog = $this->createMock(ModuleCatalogInterface::class);
        $catalog->expects(self::exactly(3))->method('clear');
        $snapshots = $this->createMock(SnapshotManagerInterface::class);
        $snapshots->expects(self::exactly(3))->method('clear');
        $coordinator = new ProvisioningCoordinator(
            $provisioner,
            $lock,
            $catalog,
            $snapshots,
            new Dispatcher(),
        );

        self::assertSame($capabilities, $coordinator->capabilities());
        self::assertSame(['KJV'], $coordinator->install([' KJV ', 'KJV'])->installed());
        self::assertSame([], $coordinator->installAll()->modules());
        self::assertSame(['WEB'], $coordinator->remove(' WEB ')->removed());
    }

    /**
     * Verifies invalid requested identifiers fail before locking.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInvalidInstallRequestsFailBeforeLocking(): void
    {
        $provisioner = $this->createStub(ModuleProvisionerInterface::class);
        $lock = $this->createMock(ModuleRootLockInterface::class);
        $lock->expects(self::never())->method('write');
        $coordinator = new ProvisioningCoordinator(
            $provisioner,
            $lock,
            $this->createStub(ModuleCatalogInterface::class),
            $this->createStub(SnapshotManagerInterface::class),
            new Dispatcher(),
        );

        foreach ([[], ['../KJV']] as $modules) {
            try {
                $coordinator->install($modules);
                self::fail('The invalid install request was accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        try {
            (new \ReflectionMethod($coordinator, 'install'))->invoke($coordinator, [123]);
            self::fail('A non-string module identifier was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('strings', $exception->getMessage());
        }
    }

    /**
     * Verifies backend failures emit failure state and retain caches.
     *
     * @return void
     * @since 1.0.0
     */
    public function testBackendFailureDoesNotInvalidateCaches(): void
    {
        $provisioner = $this->createStub(ModuleProvisionerInterface::class);
        $provisioner->method('capabilities')->willReturn(ProvisioningCapabilities::abiV1());
        $provisioner->method('refreshTranslations')->willThrowException(new \RuntimeException('backend failed'));
        $lock = $this->createStub(ModuleRootLockInterface::class);
        $lock->method('write')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $catalog = $this->createMock(ModuleCatalogInterface::class);
        $catalog->expects(self::never())->method('clear');
        $snapshots = $this->createMock(SnapshotManagerInterface::class);
        $snapshots->expects(self::never())->method('clear');
        $dispatcher = new Dispatcher();
        $failedEvents = 0;
        $dispatcher->addListener(EventName::PROVISIONING_FAILED, static function () use (&$failedEvents): void {
            ++$failedEvents;
        });
        $coordinator = new ProvisioningCoordinator($provisioner, $lock, $catalog, $snapshots, $dispatcher);

        try {
            $coordinator->refresh();
            self::fail('The backend failure was swallowed.');
        } catch (\RuntimeException $exception) {
            self::assertSame('backend failed', $exception->getMessage());
        }

        self::assertSame(1, $failedEvents);
    }

    /**
     * Creates one changed provisioning result.
     *
     * @param string $operation Operation name.
     * @param string $module Module identifier.
     * @param string $action Module action.
     *
     * @return ProvisioningResult
     * @since 1.0.0
     */
    private function result(string $operation, string $module, string $action): ProvisioningResult
    {
        return new ProvisioningResult($operation, [
            new ModuleProvisioningResult(
                $module,
                $action,
                ModuleProvisioningResult::STATUS_CHANGED,
            ),
        ]);
    }
}
