<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\DependencyInjection;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use Joomla\DI\Container;

/**
 * Creates a standalone Joomla DI container for Scripture applications.
 *
 * @since 0.1.0
 */
final class ContainerFactory
{
    /**
     * Creates and configures a new Joomla container.
     *
     * @param Configuration|null $configuration Optional explicit configuration.
     * @param string|null $configurationPath Optional durable JSON configuration path.
     *
     * @return Container
     * @since 0.1.0
     */
    public static function create(
        ?Configuration $configuration = null,
        ?string $configurationPath = null,
    ): Container {
        if ($configuration === null) {
            $repository = (new JsonConfigurationRepositoryFactory())->create($configurationPath);
            $configuration = Configuration::fromPersisted($repository->load());
        }

        $container = new Container();
        $container->share(
            Configuration::class,
            $configuration,
            true,
        );
        $container->registerServiceProvider(new ScriptureServiceProvider());

        return $container;
    }

    /**
     * Prevents instantiation of this factory.
     *
     * @since 0.1.0
     */
    private function __construct()
    {
    }
}
