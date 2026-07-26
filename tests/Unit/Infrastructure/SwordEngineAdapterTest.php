<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Infrastructure;

use GetBible\Scripture\Infrastructure\Sword\SwordEngineAdapter;
use GetBible\Scripture\Tests\Unit\Setup\NativeRuntimeState;
use PHPUnit\Framework\TestCase;

/**
 * Verifies delegation through the native SWORD engine adapter.
 *
 * @since 1.0.0
 */
final class SwordEngineAdapterTest extends TestCase
{
    /**
     * Creates a deterministic native engine substitute.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        if (\extension_loaded('getbiblesword')) {
            self::markTestSkipped('The deterministic adapter test requires the extension to be absent.');
        }

        if (!\class_exists(\GetBible\Sword\Engine::class, false)) {
            require_once __DIR__ . '/../../Fixtures/Native/GetBible/Sword/Engine.php';
        }

        NativeRuntimeState::$extensionLoaded = true;
        NativeRuntimeState::$engineClassAvailable = true;
        NativeRuntimeState::$extensionVersion = '0.1.0';
        NativeRuntimeState::$fixturePath = __DIR__ . '/../../Fixtures/test-bible.ndjson';
        NativeRuntimeState::$throwMetadata = false;
    }

    /**
     * Restores the missing-extension baseline.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        NativeRuntimeState::reset();
    }

    /**
     * Verifies resolved paths and both stream operations delegate losslessly.
     *
     * @return void
     * @since 1.0.0
     */
    public function testDelegatesResolvedPathAndStreamOperations(): void
    {
        $adapter = new SwordEngineAdapter('/test/explicit-sword');
        self::assertSame('/test/explicit-sword', $adapter->modulePath());

        $modules = $this->temporaryStream();
        $module = $this->temporaryStream();

        try {
            $moduleBytes = $adapter->streamModules($modules);
            rewind($modules);
            $moduleList = stream_get_contents($modules);
            self::assertIsString($moduleList);
            self::assertSame($moduleBytes, strlen($moduleList));
            self::assertStringContainsString('"command":"list"', $moduleList);

            $extractBytes = $adapter->streamModule('TestBible', $module, 4096);
            rewind($module);
            $extract = stream_get_contents($module);
            $fixture = file_get_contents(NativeRuntimeState::$fixturePath);
            self::assertIsString($extract);
            self::assertIsString($fixture);
            self::assertSame($extractBytes, strlen($extract));
            self::assertSame($fixture, $extract);
        } finally {
            fclose($modules);
            fclose($module);
        }
    }

    /**
     * Creates a writable in-memory stream.
     *
     * @return resource
     * @since 1.0.0
     */
    private function temporaryStream(): mixed
    {
        $stream = fopen('php://temp', 'w+b');

        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create an adapter test stream.');
        }

        return $stream;
    }
}
