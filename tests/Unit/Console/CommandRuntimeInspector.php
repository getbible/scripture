<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Setup\RuntimePrerequisiteInspectorInterface;
use GetBible\Scripture\Setup\RuntimePrerequisiteReport;

/**
 * Supplies a compatible runtime to command tests.
 *
 * @since 1.0.0
 */
final class CommandRuntimeInspector implements RuntimePrerequisiteInspectorInterface
{
    /**
     * Returns a compatible native report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport
    {
        return new RuntimePrerequisiteReport(
            true,
            true,
            '0.1.0',
            RuntimePrerequisiteReport::EXPECTED_ABI,
            RuntimePrerequisiteReport::EXPECTED_CONTRACT,
            '0.3.0',
        );
    }
}
