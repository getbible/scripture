<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\DependencyInjection;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\JsonConfigurationRepository;
use GetBible\Scripture\DependencyInjection\ContainerFactory;
use GetBible\Scripture\DependencyInjection\ContainerService;
use GetBible\Scripture\DependencyInjection\ScriptureServiceProvider;
use Joomla\DI\Container;
use PHPUnit\Framework\TestCase;

/**
 * Verifies both explicit and persisted Joomla composition roots.
 *
 * @since 1.0.0
 */
final class ContainerFactoryTest extends TestCase
{
    /**
     * Temporary application root.
     *
     * @var string
     * @since 1.0.0
     */
    private string $directory;

    /**
     * Creates an isolated configuration root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . '/getbible-scripture-container-factory-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes persisted configuration.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        $path = $this->directory . '/configuration.json';

        if (is_file($path)) {
            unlink($path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * Verifies the factory loads durable configuration when none is injected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCreatesContainerFromPersistedConfiguration(): void
    {
        $path = $this->directory . '/configuration.json';
        (new JsonConfigurationRepository($path))->save(Configuration::fromEnvironment([
            'cache_path' => '/persisted/cache',
            'modules' => ['KJV'],
        ]));

        $container = ContainerFactory::create(null, $path);
        $configuration = ContainerService::get($container, Configuration::class);

        self::assertSame('/persisted/cache', $configuration->cachePath());
        self::assertSame(['KJV'], $configuration->modules());
    }

    /**
     * Verifies the service provider supplies configuration to a bare container.
     *
     * @return void
     * @since 1.0.0
     */
    public function testProviderSuppliesMissingConfiguration(): void
    {
        $container = new Container();
        $container->registerServiceProvider(new ScriptureServiceProvider());

        self::assertInstanceOf(
            Configuration::class,
            ContainerService::get($container, Configuration::class),
        );
    }
}
