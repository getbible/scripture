<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

/**
 * Inspects the active PHP runtime without modifying host state.
 *
 * @since 1.0.0
 */
interface RuntimePrerequisiteInspectorInterface
{
    /**
     * Returns the exact native extension compatibility report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport;
}
