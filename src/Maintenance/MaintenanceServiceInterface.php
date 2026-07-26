<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

/**
 * Runs explicit and interval-based Scripture initialization and refresh.
 *
 * @since 0.3.0
 */
interface MaintenanceServiceInterface
{
    /**
     * Installs missing configured modules when permitted and warms snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     * @param bool $all Whether every policy-approved translation should be installed.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function initialize(array $modules = [], bool $all = false): MaintenanceResult;

    /**
     * Refreshes remote modules when enabled and rebuilds their snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refresh(array $modules = []): MaintenanceResult;

    /**
     * Refreshes only when the durable interval policy is due.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refreshIfDue(array $modules = []): MaintenanceResult;

    /**
     * Returns current maintenance, capability, and installed-module status.
     *
     * @return MaintenanceStatus
     * @since 0.3.0
     */
    public function status(): MaintenanceStatus;
}
