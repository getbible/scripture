<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

/**
 * Persists interval and failure state for scheduled maintenance.
 *
 * @since 0.3.0
 */
interface MaintenanceStateStoreInterface
{
    /**
     * Loads state or returns empty state when no run has completed.
     *
     * @return MaintenanceState
     * @since 0.3.0
     */
    public function load(): MaintenanceState;

    /**
     * Atomically replaces the durable maintenance state.
     *
     * @param MaintenanceState $state New state.
     *
     * @return void
     * @since 0.3.0
     */
    public function save(MaintenanceState $state): void;
}
