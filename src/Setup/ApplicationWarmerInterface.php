<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\MaintenanceResult;

/**
 * Warms installed translations against a candidate immutable configuration.
 *
 * @since 1.0.0
 */
interface ApplicationWarmerInterface
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
    public function warm(Configuration $configuration, array $modules = []): MaintenanceResult;
}
