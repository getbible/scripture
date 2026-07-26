<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Integration\Joomla;

use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;

/**
 * Callable bridge for Joomla CMS Scheduled Tasks plugins.
 *
 * @since 0.3.0
 */
final class ScheduledRefreshHandler
{
    /**
     * Creates the scheduler bridge.
     *
     * @param MaintenanceServiceInterface $maintenance Maintenance lifecycle.
     *
     * @since 0.3.0
     */
    public function __construct(private MaintenanceServiceInterface $maintenance)
    {
    }

    /**
     * Runs interval-gated refresh for configured translations.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function run(): MaintenanceResult
    {
        return $this->maintenance->refreshIfDue();
    }

    /**
     * Invokes the scheduler bridge as a callable service.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function __invoke(): MaintenanceResult
    {
        return $this->run();
    }
}
