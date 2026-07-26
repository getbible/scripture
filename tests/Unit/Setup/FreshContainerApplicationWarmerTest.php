<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\MaintenanceModuleResult;
use GetBible\Scripture\Setup\FreshContainerApplicationWarmer;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the production warmer composes a fresh Joomla container.
 *
 * @since 1.0.0
 */
final class FreshContainerApplicationWarmerTest extends TestCase
{
    /**
     * Isolated application root.
     *
     * @var string
     * @since 1.0.0
     */
    private string $directory = '';

    /**
     * Creates an isolated cache and fake native runtime.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        if (\extension_loaded('getbiblesword')) {
            self::markTestSkipped('The deterministic warmer test requires the extension to be absent.');
        }

        $this->directory = sys_get_temp_dir()
            . '/getbible-scripture-fresh-warmer-'
            . bin2hex(random_bytes(8));
        require_once __DIR__ . '/NativeSwordFunctionOverrides.php';

        if (!\class_exists(\GetBible\Sword\Engine::class, false)) {
            require_once __DIR__ . '/../../Fixtures/Native/GetBible/Sword/Engine.php';
        }

        NativeRuntimeState::$extensionLoaded = true;
        NativeRuntimeState::$engineClassAvailable = true;
        NativeRuntimeState::$extensionVersion = '0.1.0';
        \GetBible\Sword\Engine::$throwMetadata = false;
        \GetBible\Sword\Engine::$fixturePath = __DIR__ . '/../../Fixtures/test-bible.ndjson';
    }

    /**
     * Removes the generated cache and restores the native probe.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        NativeRuntimeState::reset();
        $this->removeDirectory($this->directory);
    }

    /**
     * Verifies selected installed modules are warmed through the fresh container.
     *
     * @return void
     * @since 1.0.0
     */
    public function testWarmsSelectedModuleThroughFreshContainer(): void
    {
        $configuration = Configuration::fromEnvironment([
            'module_path' => $this->directory . '/sword',
            'cache_path' => $this->directory . '/cache',
            'modules' => ['TestBible'],
            'auto_refresh' => false,
            'lock_timeout' => 2,
        ]);

        $result = (new FreshContainerApplicationWarmer())->warm(
            $configuration,
            ['TestBible'],
        );

        self::assertTrue($result->succeeded());
        self::assertCount(1, $result->modules());
        self::assertSame('TestBible', $result->modules()[0]->module());
        self::assertSame(MaintenanceModuleResult::STATUS_READY, $result->modules()[0]->status());
        self::assertFileExists($configuration->maintenanceStatePath());
    }

    /**
     * Recursively removes one isolated test directory.
     *
     * @param string $directory Directory path.
     *
     * @return void
     * @since 1.0.0
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
