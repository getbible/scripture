<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\DependencyInjection;

use GetBible\Scripture\Catalog\ModuleCatalog;
use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Clock\SystemClock;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Contract\ContractV1ValidatorInterface;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Infrastructure\Sword\SwordEngineAdapter;
use GetBible\Scripture\Provisioning\AbiV1ModuleProvisioner;
use GetBible\Scripture\Provisioning\ModuleProvisionerInterface;
use GetBible\Scripture\Service\Scripture;
use GetBible\Scripture\Service\ScriptureInterface;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use GetBible\Scripture\Snapshot\TranslationSnapshotManager;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherInterface;

/**
 * Registers Scripture contracts and shared implementations with Joomla DI.
 *
 * @since 0.1.0
 */
final class ScriptureServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers lazy shared services and public aliases.
     *
     * @param Container $container Joomla dependency injection container.
     *
     * @return void
     * @since 0.1.0
     */
    public function register(Container $container)
    {
        if (!$container->has(Configuration::class)) {
            $container->share(
                Configuration::class,
                static fn (): Configuration => Configuration::fromEnvironment(),
                true,
            );
        }

        if (!$container->has(ClockInterface::class)) {
            $container->share(ClockInterface::class, static fn (): ClockInterface => new SystemClock(), true);
        }

        if (!$container->has(DispatcherInterface::class)) {
            $container->share(
                DispatcherInterface::class,
                static fn (): DispatcherInterface => new Dispatcher(),
                true,
            );
        }

        $container->share(
            ModuleExtractorInterface::class,
            static fn (Container $container): ModuleExtractorInterface => new SwordEngineAdapter(
                $container->get(Configuration::class)->modulePath(),
            ),
            true,
        );
        $container->share(
            ContractV1ValidatorInterface::class,
            static fn (): ContractV1ValidatorInterface => new ContractV1Validator(),
            true,
        );
        $container->share(
            ModuleCatalogInterface::class,
            static fn (Container $container): ModuleCatalogInterface => new ModuleCatalog(
                $container->get(ModuleExtractorInterface::class),
                $container->get(ContractV1ValidatorInterface::class),
            ),
            true,
        );
        $container->share(
            SnapshotManagerInterface::class,
            static fn (Container $container): SnapshotManagerInterface => new TranslationSnapshotManager(
                $container->get(Configuration::class),
                $container->get(ClockInterface::class),
                $container->get(ModuleCatalogInterface::class),
                $container->get(ModuleExtractorInterface::class),
                $container->get(ContractV1ValidatorInterface::class),
                $container->get(DispatcherInterface::class),
            ),
            true,
        );
        $container->share(
            ModuleProvisionerInterface::class,
            static fn (): ModuleProvisionerInterface => new AbiV1ModuleProvisioner(),
            true,
        );
        $container->share(
            ScriptureInterface::class,
            static fn (Container $container): ScriptureInterface => new Scripture(
                $container->get(ModuleCatalogInterface::class),
                $container->get(SnapshotManagerInterface::class),
                $container->get(ModuleProvisionerInterface::class),
            ),
            true,
        );
        $container->alias(Scripture::class, ScriptureInterface::class);
    }
}
