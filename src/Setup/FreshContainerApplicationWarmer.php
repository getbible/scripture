<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\DependencyInjection\ContainerFactory;
use GetBible\Scripture\DependencyInjection\ContainerService;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;

/**
 * Warms with a fresh container so immutable runtime settings are never mutated.
 *
 * @since 1.0.0
 */
final class FreshContainerApplicationWarmer implements ApplicationWarmerInterface
{
    /**
     * Creates or opens validated snapshots for selected installed modules.
     *
     * @param Configuration $configuration Candidate configuration.
     * @param list<string> $modules Explicit targets, or an empty list for configured targets.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    public function warm(Configuration $configuration, array $modules = []): MaintenanceResult
    {
        $container = ContainerFactory::create($configuration);
        $maintenance = ContainerService::get($container, MaintenanceServiceInterface::class);

        return $maintenance->initialize($modules, false);
    }
}
