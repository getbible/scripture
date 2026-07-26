<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Service;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use GetBible\Scripture\Maintenance\MaintenanceState;
use GetBible\Scripture\Maintenance\MaintenanceStatus;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningCoordinatorInterface;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Service\Scripture;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use GetBible\Scripture\Tests\Support\SnapshotFixtureFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies delegation, convenience queries, and process-local cache invalidation.
 *
 * @since 1.0.0
 */
final class ScriptureBehaviorTest extends TestCase
{
    /**
     * Isolated cache root.
     *
     * @var string
     * @since 1.0.0
     */
    private string $cachePath;

    /**
     * Creates an isolated cache root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/getbible-scripture-facade-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->cachePath, 0700, true));
    }

    /**
     * Removes the isolated cache root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        $this->deleteDirectory($this->cachePath);
    }

    /**
     * Verifies every application-facing facade operation delegates and invalidates safely.
     *
     * @return void
     * @since 1.0.0
     */
    public function testDelegatesQueriesMaintenanceAndProvisioning(): void
    {
        $firstSnapshot = SnapshotFixtureFactory::create($this->cachePath);
        $secondSnapshot = SnapshotFixtureFactory::create(
            $this->cachePath,
            new \DateTimeImmutable('2026-07-27T12:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        );
        $activeSnapshot = $firstSnapshot;
        $catalog = $this->createStub(ModuleCatalogInterface::class);
        $catalog->method('translations')->willReturn([$firstSnapshot->metadata()]);
        $snapshots = $this->createStub(SnapshotManagerInterface::class);
        $snapshots->method('get')->willReturnCallback(
            static function (string $module) use (&$activeSnapshot): SnapshotIndex {
                self::assertSame('TestBible', $module);

                return $activeSnapshot;
            },
        );
        $snapshots->method('refresh')->willReturn($secondSnapshot);
        $capabilities = new ProvisioningCapabilities('test', 'test/v1', true, true, true, true);
        $provisioningResult = new ProvisioningResult('test', []);
        $provisioning = $this->provisioningMock($capabilities, $provisioningResult);
        [$maintenance, $initialize, $refresh, $skipped, $due, $status] = $this->maintenanceMock(
            $capabilities,
        );
        $scripture = new Scripture($catalog, $snapshots, $provisioning, $maintenance);

        $translations = $scripture->translations();
        self::assertCount(1, $translations);
        self::assertSame('TestBible', $translations[0]->name()->bytes());
        $translation = $scripture->translation(' TestBible ');
        self::assertSame($translation, $scripture->translation('TestBible'));
        self::assertSame('Word', $scripture->verse('TestBible', 'John', 1, 1)->stripped()?->requireUtf8());
        self::assertCount(1, $scripture->verses('TestBible', 'John', 1, 1, 1));
        self::assertSame($status, $scripture->maintenanceStatus());
        self::assertTrue($scripture->canProvisionModules());
        self::assertSame($capabilities, $scripture->provisioningCapabilities());

        self::assertSame($initialize, $scripture->initialize(['TestBible'], true));
        $afterInitialize = $scripture->translation('TestBible');
        self::assertNotSame($translation, $afterInitialize);

        self::assertSame($refresh, $scripture->refresh(['TestBible']));
        $afterRefresh = $scripture->translation('TestBible');
        self::assertNotSame($afterInitialize, $afterRefresh);

        self::assertSame($skipped, $scripture->refreshIfDue(['TestBible']));
        self::assertSame($afterRefresh, $scripture->translation('TestBible'));
        self::assertSame($due, $scripture->refreshIfDue(['TestBible']));
        self::assertNotSame($afterRefresh, $scripture->translation('TestBible'));

        $refreshed = $scripture->refreshTranslation('TestBible');
        self::assertSame('2026-07-27T12:00:00+00:00', $refreshed->activatedAt()->format(DATE_ATOM));
        self::assertSame($provisioningResult, $scripture->installTranslations(['TestBible']));
        self::assertSame($provisioningResult, $scripture->installAllTranslations());
        self::assertSame($provisioningResult, $scripture->refreshModules());
        self::assertSame($provisioningResult, $scripture->refreshSelectedModules(['TestBible']));
        self::assertSame($provisioningResult, $scripture->removeTranslation('TestBible'));
    }

    /**
     * Creates a provisioning mock covering every mutation operation.
     *
     * @param ProvisioningCapabilities $capabilities Fixed capability set.
     * @param ProvisioningResult $result Fixed operation result.
     *
     * @return ProvisioningCoordinatorInterface&MockObject
     * @since 1.0.0
     */
    private function provisioningMock(
        ProvisioningCapabilities $capabilities,
        ProvisioningResult $result,
    ): ProvisioningCoordinatorInterface&MockObject {
        $provisioning = $this->createMock(ProvisioningCoordinatorInterface::class);
        $provisioning->method('capabilities')->willReturn($capabilities);
        $provisioning->expects(self::once())->method('install')->with(['TestBible'])->willReturn($result);
        $provisioning->expects(self::once())->method('installAll')->willReturn($result);
        $provisioning->expects(self::exactly(2))
            ->method('refresh')
            ->willReturnCallback(
                static function (array $modules = []) use ($result): ProvisioningResult {
                    self::assertTrue($modules === [] || $modules === ['TestBible']);

                    return $result;
                },
            );
        $provisioning->expects(self::once())->method('remove')->with('TestBible')->willReturn($result);

        return $provisioning;
    }

    /**
     * Creates maintenance results and a mock covering each facade operation.
     *
     * @param ProvisioningCapabilities $capabilities Fixed capability set.
     *
     * @return array{
     *     MaintenanceServiceInterface&MockObject,
     *     MaintenanceResult,
     *     MaintenanceResult,
     *     MaintenanceResult,
     *     MaintenanceResult,
     *     MaintenanceStatus
     * }
     * @since 1.0.0
     */
    private function maintenanceMock(ProvisioningCapabilities $capabilities): array
    {
        $now = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $initialize = new MaintenanceResult('initialize', $now, $now, true, [], null, []);
        $refresh = new MaintenanceResult('refresh', $now, $now, true, [], null, []);
        $skipped = new MaintenanceResult(
            'refresh-if-due',
            $now,
            $now,
            false,
            [],
            null,
            [],
            'Not due.',
        );
        $due = new MaintenanceResult('refresh-if-due', $now, $now, true, [], null, []);
        $status = new MaintenanceStatus(
            $now,
            true,
            null,
            MaintenanceState::empty(),
            $capabilities,
            true,
            ['TestBible'],
            ['TestBible'],
        );
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->expects(self::once())
            ->method('initialize')
            ->with(['TestBible'], true)
            ->willReturn($initialize);
        $maintenance->expects(self::once())->method('refresh')->with(['TestBible'])->willReturn($refresh);
        $maintenance->expects(self::exactly(2))
            ->method('refreshIfDue')
            ->with(['TestBible'])
            ->willReturnOnConsecutiveCalls($skipped, $due);
        $maintenance->expects(self::once())->method('status')->willReturn($status);

        return [$maintenance, $initialize, $refresh, $skipped, $due, $status];
    }

    /**
     * Recursively removes a controlled test directory.
     *
     * @param string $path Controlled temporary path.
     *
     * @return void
     * @since 1.0.0
     */
    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
