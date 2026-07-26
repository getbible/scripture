<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Snapshot;

/**
 * Opens, warms, and atomically refreshes immutable translation snapshots.
 *
 * @since 0.1.0
 */
interface SnapshotManagerInterface
{
    /**
     * Opens a fresh snapshot, warming it when necessary.
     *
     * @param string $module Exact installed Bible module identifier.
     *
     * @return SnapshotIndex
     * @since 0.1.0
     */
    public function get(string $module): SnapshotIndex;

    /**
     * Forces a new validated native export and snapshot activation.
     *
     * @param string $module Exact installed Bible module identifier.
     *
     * @return SnapshotIndex
     * @since 0.1.0
     */
    public function refresh(string $module): SnapshotIndex;

    /**
     * Clears process-local open snapshot objects.
     *
     * @return void
     * @since 0.1.0
     */
    public function clear(): void;
}
