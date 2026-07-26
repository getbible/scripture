<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Setup\RuntimePrerequisiteInspectorInterface;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;

/**
 * Deterministic runtime inspector test double.
 *
 * @since 1.0.0
 */
final class FixedRuntimeInspector implements RuntimePrerequisiteInspectorInterface
{
    /**
     * Creates the inspector.
     *
     * @param RuntimePrerequisiteReport $report Fixed report.
     *
     * @since 1.0.0
     */
    public function __construct(private RuntimePrerequisiteReport $report)
    {
    }

    /**
     * Returns the fixed report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport
    {
        return $this->report;
    }
}
