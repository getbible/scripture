<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Console\ConsoleApplicationFactory;
use GetBible\Scripture\DependencyInjection\ContainerFactory;
use GetBible\Scripture\Tests\Unit\Setup\NativeRuntimeState;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the standalone application remains useful without native services.
 *
 * @since 1.0.0
 */
final class ConsoleApplicationFactoryTest extends TestCase
{
    /**
     * Verifies doctor and setup remain registered when the extension is absent.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCreatesDiagnosticApplicationWithoutNativeRuntime(): void
    {
        if (extension_loaded('getbiblesword')) {
            self::markTestSkipped('This test covers a deployment before the extension is installed.');
        }

        $container = ContainerFactory::create(Configuration::fromEnvironment([
            'cache_path' => '/tmp/getbible-scripture-console-factory',
        ]));
        $application = ConsoleApplicationFactory::create($container);

        self::assertSame('GetBible Scripture', $application->getName());
        self::assertSame('1.0.0', $application->getVersion());
        self::assertTrue($application->hasCommand('scripture:doctor'));
        self::assertTrue($application->hasCommand('scripture:setup'));
        self::assertFalse($application->hasCommand('scripture:initialize'));
        self::assertFalse($application->hasCommand('scripture:refresh'));
        self::assertFalse($application->hasCommand('scripture:status'));
    }

    /**
     * Verifies maintenance commands register when native services can compose.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCreatesCompleteApplicationWithNativeRuntime(): void
    {
        if (\extension_loaded('getbiblesword')) {
            self::markTestSkipped('The deterministic application test requires the extension to be absent.');
        }

        require_once __DIR__ . '/../Setup/NativeSwordFunctionOverrides.php';

        if (!\class_exists(\GetBible\Sword\Engine::class, false)) {
            require_once __DIR__ . '/../../Fixtures/Native/GetBible/Sword/Engine.php';
        }

        NativeRuntimeState::$extensionLoaded = true;
        NativeRuntimeState::$engineClassAvailable = true;
        NativeRuntimeState::$extensionVersion = '0.1.0';
        NativeRuntimeState::$throwMetadata = false;

        try {
            $container = ContainerFactory::create(Configuration::fromEnvironment([
                'cache_path' => '/tmp/getbible-scripture-complete-console',
            ]));
            $application = ConsoleApplicationFactory::create($container);

            self::assertTrue($application->hasCommand('scripture:doctor'));
            self::assertTrue($application->hasCommand('scripture:setup'));
            self::assertTrue($application->hasCommand('scripture:initialize'));
            self::assertTrue($application->hasCommand('scripture:refresh'));
            self::assertTrue($application->hasCommand('scripture:status'));
        } finally {
            NativeRuntimeState::reset();
        }
    }
}
