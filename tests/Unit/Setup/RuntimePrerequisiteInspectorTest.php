<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Setup\RuntimePrerequisiteInspector;
use PHPUnit\Framework\TestCase;

/**
 * Verifies inspection remains safe when the optional native runtime is absent.
 *
 * @since 1.0.0
 */
final class RuntimePrerequisiteInspectorTest extends TestCase
{
    /**
     * Restores the missing-extension baseline.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        NativeRuntimeState::reset();
    }

    /**
     * Restores fake Engine behavior after each test.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        NativeRuntimeState::reset();

        if (\class_exists(\GetBible\Sword\Engine::class, false) && !\extension_loaded('getbiblesword')) {
            \GetBible\Sword\Engine::$throwMetadata = false;
        }
    }

    /**
     * Verifies absent native extension metadata is reported without throwing.
     *
     * @return void
     * @since 1.0.0
     */
    public function testReportsMissingExtensionWithoutSideEffects(): void
    {
        if (\extension_loaded('getbiblesword')) {
            self::markTestSkipped('This test covers the runtime branch where getbiblesword is absent.');
        }

        $report = (new RuntimePrerequisiteInspector())->inspect();

        self::assertFalse($report->ready());
        self::assertFalse($report->extensionLoaded());
        self::assertNull($report->extensionVersion());
        self::assertNull($report->abiVersion());
        self::assertNull($report->contractIdentifier());
        self::assertNull($report->productVersion());
        self::assertNull($report->error());
    }

    /**
     * Verifies compatible native metadata produces a ready report.
     *
     * @return void
     * @since 1.0.0
     */
    public function testReportsCompatibleNativeMetadata(): void
    {
        $this->loadFakeEngine();
        NativeRuntimeState::$extensionLoaded = true;
        NativeRuntimeState::$engineClassAvailable = true;
        NativeRuntimeState::$extensionVersion = '0.1.0';

        $report = (new RuntimePrerequisiteInspector())->inspect();

        self::assertTrue($report->ready());
        self::assertSame('0.1.0', $report->extensionVersion());
        self::assertSame(1, $report->abiVersion());
        self::assertSame('getbiblesword.ndjson/v1', $report->contractIdentifier());
        self::assertSame('0.3.0', $report->productVersion());
        self::assertNull($report->error());
    }

    /**
     * Verifies native metadata exceptions become safe diagnostic reports.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCapturesNativeMetadataFailure(): void
    {
        $this->loadFakeEngine();
        NativeRuntimeState::$extensionLoaded = true;
        NativeRuntimeState::$engineClassAvailable = true;
        NativeRuntimeState::$extensionVersion = '0.1.0';
        \GetBible\Sword\Engine::$throwMetadata = true;

        $report = (new RuntimePrerequisiteInspector())->inspect();

        self::assertFalse($report->ready());
        self::assertTrue($report->extensionLoaded());
        self::assertTrue($report->engineClassAvailable());
        self::assertSame('0.1.0', $report->extensionVersion());
        self::assertNull($report->abiVersion());
        self::assertNull($report->contractIdentifier());
        self::assertNull($report->productVersion());
        self::assertSame('native metadata failed', $report->error());
    }

    /**
     * Loads the deterministic Engine substitute when the real extension is absent.
     *
     * @return void
     * @since 1.0.0
     */
    private function loadFakeEngine(): void
    {
        if (\extension_loaded('getbiblesword')) {
            self::markTestSkipped('Native metadata substitution requires the extension to be absent.');
        }

        if (!\class_exists(\GetBible\Sword\Engine::class, false)) {
            require_once __DIR__ . '/../../Fixtures/Native/GetBible/Sword/Engine.php';
        }

        \GetBible\Sword\Engine::$throwMetadata = false;
    }
}

/**
 * Overrides the extension probe only inside the Setup namespace.
 *
 * @param string $extension Extension name.
 *
 * @return bool
 * @since 1.0.0
 */
function extension_loaded(string $extension): bool
{
    return $extension === 'getbiblesword'
        ? NativeRuntimeState::$extensionLoaded
        : \extension_loaded($extension);
}

/**
 * Overrides the Engine-class probe only inside the Setup namespace.
 *
 * @param class-string|string $class Class name.
 * @param bool $autoload Whether autoloading is allowed.
 *
 * @return bool
 * @since 1.0.0
 */
function class_exists(string $class, bool $autoload = true): bool
{
    return $class === \GetBible\Sword\Engine::class
        ? NativeRuntimeState::$engineClassAvailable
        : \class_exists($class, $autoload);
}

/**
 * Overrides the extension version only inside the Setup namespace.
 *
 * @param string|null $extension Extension name.
 *
 * @return string|false
 * @since 1.0.0
 */
function phpversion(?string $extension = null): string|false
{
    return $extension === 'getbiblesword'
        ? NativeRuntimeState::$extensionVersion
        : \phpversion($extension);
}
