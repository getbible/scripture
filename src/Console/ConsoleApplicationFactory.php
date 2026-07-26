<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Console;

use GetBible\Scripture\DependencyInjection\ContainerService;
use GetBible\Scripture\Exception\NativeEngineException;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use GetBible\Scripture\Setup\SetupServiceInterface;
use Joomla\Console\Application;
use Joomla\DI\Container;
use Joomla\Event\DispatcherInterface;

/**
 * Creates the standalone Joomla Console maintenance application.
 *
 * @since 0.3.0
 */
final class ConsoleApplicationFactory
{
    /**
     * Creates a configured console application from the Joomla DI container.
     *
     * @param Container $container Scripture composition root.
     *
     * @return Application
     * @since 0.3.0
     */
    public static function create(Container $container): Application
    {
        $application = new Application();
        $application->setName('GetBible Scripture');
        $application->setVersion(self::version());
        $application->setDispatcher(ContainerService::get($container, DispatcherInterface::class));
        $setup = ContainerService::get($container, SetupServiceInterface::class);

        $application->addCommand(new DoctorCommand($setup));
        $application->addCommand(new SetupCommand($setup));

        try {
            $maintenance = ContainerService::get($container, MaintenanceServiceInterface::class);
            $application->addCommand(new InitializeCommand($maintenance));
            $application->addCommand(new RefreshCommand($maintenance));
            $application->addCommand(new StatusCommand($maintenance));
        } catch (NativeEngineException) {
            // Doctor and setup remain available to diagnose a missing native runtime.
        }

        return $application;
    }

    /**
     * Reads the package version without introducing generated constants.
     *
     * @return string
     * @since 0.3.0
     */
    private static function version(): string
    {
        $version = @file_get_contents(dirname(__DIR__, 2) . '/VERSION');

        return is_string($version) && trim($version) !== '' ? trim($version) : 'unknown';
    }

    /**
     * Prevents instantiation of this factory.
     *
     * @since 0.3.0
     */
    private function __construct()
    {
    }
}
