<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\DependencyInjection;

use GetBible\Scripture\Catalog\ModuleCatalog;
use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Clock\SystemClock;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\ConfigurationPath;
use GetBible\Scripture\Configuration\ConfigurationRepositoryFactoryInterface;
use GetBible\Scripture\Configuration\ConfigurationRepositoryInterface;
use GetBible\Scripture\Configuration\JsonConfigurationRepositoryFactory;
use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Contract\ContractV1ValidatorInterface;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Infrastructure\Sword\SwordEngineAdapter;
use GetBible\Scripture\Infrastructure\Lock\FileModuleRootLock;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Infrastructure\Lock\FileMaintenanceLock;
use GetBible\Scripture\Infrastructure\Lock\MaintenanceLockInterface;
use GetBible\Scripture\Maintenance\JsonMaintenanceStateStore;
use GetBible\Scripture\Maintenance\MaintenanceService;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use GetBible\Scripture\Maintenance\MaintenanceStateStoreInterface;
use GetBible\Scripture\Integration\Joomla\ScheduledRefreshHandler;
use GetBible\Scripture\Provisioning\AbiV1ModuleProvisioner;
use GetBible\Scripture\Provisioning\ModuleProvisionerInterface;
use GetBible\Scripture\Provisioning\ProvisioningCoordinator;
use GetBible\Scripture\Provisioning\ProvisioningCoordinatorInterface;
use GetBible\Scripture\Service\Scripture;
use GetBible\Scripture\Service\ScriptureInterface;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use GetBible\Scripture\Snapshot\TranslationSnapshotManager;
use GetBible\Scripture\Setup\ApplicationWarmerInterface;
use GetBible\Scripture\Setup\FreshContainerApplicationWarmer;
use GetBible\Scripture\Setup\RuntimePrerequisiteInspector;
use GetBible\Scripture\Setup\RuntimePrerequisiteInspectorInterface;
use GetBible\Scripture\Setup\SetupService;
use GetBible\Scripture\Setup\SetupServiceInterface;
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
            ConfigurationRepositoryFactoryInterface::class,
            static fn (): ConfigurationRepositoryFactoryInterface => new JsonConfigurationRepositoryFactory(),
            true,
        );
        $container->share(
            ConfigurationRepositoryInterface::class,
            static fn (Container $container): ConfigurationRepositoryInterface => ContainerService::get(
                $container,
                ConfigurationRepositoryFactoryInterface::class,
            )->create(ConfigurationPath::resolve()),
            true,
        );
        $container->share(
            RuntimePrerequisiteInspectorInterface::class,
            static fn (): RuntimePrerequisiteInspectorInterface => new RuntimePrerequisiteInspector(),
            true,
        );
        $container->share(
            ApplicationWarmerInterface::class,
            static fn (): ApplicationWarmerInterface => new FreshContainerApplicationWarmer(),
            true,
        );
        $container->share(
            SetupServiceInterface::class,
            static fn (Container $container): SetupServiceInterface => new SetupService(
                ContainerService::get($container, ConfigurationRepositoryFactoryInterface::class),
                ContainerService::get($container, RuntimePrerequisiteInspectorInterface::class),
                ContainerService::get($container, ApplicationWarmerInterface::class),
            ),
            true,
        );
        $container->share(
            ModuleExtractorInterface::class,
            static fn (Container $container): ModuleExtractorInterface => new SwordEngineAdapter(
                ContainerService::get($container, Configuration::class)->modulePath(),
            ),
            true,
        );
        $container->share(
            ContractV1ValidatorInterface::class,
            static fn (): ContractV1ValidatorInterface => new ContractV1Validator(),
            true,
        );
        $container->share(
            ModuleRootLockInterface::class,
            static fn (Container $container): ModuleRootLockInterface => new FileModuleRootLock(
                ContainerService::get($container, Configuration::class),
            ),
            true,
        );
        $container->share(
            MaintenanceLockInterface::class,
            static fn (Container $container): MaintenanceLockInterface => new FileMaintenanceLock(
                ContainerService::get($container, Configuration::class),
            ),
            true,
        );
        $container->share(
            MaintenanceStateStoreInterface::class,
            static fn (Container $container): MaintenanceStateStoreInterface => new JsonMaintenanceStateStore(
                ContainerService::get($container, Configuration::class),
            ),
            true,
        );
        $container->share(
            ModuleCatalogInterface::class,
            static fn (Container $container): ModuleCatalogInterface => new ModuleCatalog(
                ContainerService::get($container, ModuleExtractorInterface::class),
                ContainerService::get($container, ContractV1ValidatorInterface::class),
                ContainerService::get($container, ModuleRootLockInterface::class),
            ),
            true,
        );
        $container->share(
            SnapshotManagerInterface::class,
            static fn (Container $container): SnapshotManagerInterface => new TranslationSnapshotManager(
                ContainerService::get($container, Configuration::class),
                ContainerService::get($container, ClockInterface::class),
                ContainerService::get($container, ModuleCatalogInterface::class),
                ContainerService::get($container, ModuleExtractorInterface::class),
                ContainerService::get($container, ContractV1ValidatorInterface::class),
                ContainerService::get($container, DispatcherInterface::class),
                ContainerService::get($container, ModuleRootLockInterface::class),
            ),
            true,
        );
        $container->share(
            ModuleProvisionerInterface::class,
            static fn (): ModuleProvisionerInterface => new AbiV1ModuleProvisioner(),
            true,
        );
        $container->share(
            ProvisioningCoordinatorInterface::class,
            static fn (Container $container): ProvisioningCoordinatorInterface => new ProvisioningCoordinator(
                ContainerService::get($container, ModuleProvisionerInterface::class),
                ContainerService::get($container, ModuleRootLockInterface::class),
                ContainerService::get($container, ModuleCatalogInterface::class),
                ContainerService::get($container, SnapshotManagerInterface::class),
                ContainerService::get($container, DispatcherInterface::class),
            ),
            true,
        );
        $container->share(
            MaintenanceServiceInterface::class,
            static fn (Container $container): MaintenanceServiceInterface => new MaintenanceService(
                ContainerService::get($container, Configuration::class),
                ContainerService::get($container, ClockInterface::class),
                ContainerService::get($container, ModuleCatalogInterface::class),
                ContainerService::get($container, SnapshotManagerInterface::class),
                ContainerService::get($container, ProvisioningCoordinatorInterface::class),
                ContainerService::get($container, MaintenanceStateStoreInterface::class),
                ContainerService::get($container, MaintenanceLockInterface::class),
                ContainerService::get($container, DispatcherInterface::class),
            ),
            true,
        );
        $container->share(
            ScheduledRefreshHandler::class,
            static fn (Container $container): ScheduledRefreshHandler => new ScheduledRefreshHandler(
                ContainerService::get($container, MaintenanceServiceInterface::class),
            ),
            true,
        );
        $container->share(
            ScriptureInterface::class,
            static fn (Container $container): ScriptureInterface => new Scripture(
                ContainerService::get($container, ModuleCatalogInterface::class),
                ContainerService::get($container, SnapshotManagerInterface::class),
                ContainerService::get($container, ProvisioningCoordinatorInterface::class),
                ContainerService::get($container, MaintenanceServiceInterface::class),
            ),
            true,
        );
        $container->alias(Scripture::class, ScriptureInterface::class);
        $container->alias(SetupService::class, SetupServiceInterface::class);
    }
}
