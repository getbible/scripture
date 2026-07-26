<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Console\ConsoleApplicationFactory;
use GetBible\Scripture\DependencyInjection\ContainerFactory;
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
}
