<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Setup\RuntimePrerequisiteReport;
use PHPUnit\Framework\TestCase;

/**
 * Verifies strict native-runtime compatibility reporting.
 *
 * @since 1.0.0
 */
final class RuntimePrerequisiteReportTest extends TestCase
{
    /**
     * Verifies exact ABI and contract compatibility is ready.
     *
     * @return void
     * @since 1.0.0
     */
    public function testReportsCompatibleRuntimeAsReady(): void
    {
        $report = new RuntimePrerequisiteReport(
            true,
            true,
            '0.1.0',
            RuntimePrerequisiteReport::EXPECTED_ABI,
            RuntimePrerequisiteReport::EXPECTED_CONTRACT,
            '0.3.0',
        );

        self::assertTrue($report->ready());
        self::assertTrue($report->toArray()['abi']['compatible']);
        self::assertTrue($report->toArray()['contract']['compatible']);
    }

    /**
     * Verifies an ABI mismatch is reported without being accepted.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsAbiMismatch(): void
    {
        $report = new RuntimePrerequisiteReport(
            true,
            true,
            '0.1.0',
            2,
            RuntimePrerequisiteReport::EXPECTED_CONTRACT,
            '0.3.0',
        );

        self::assertFalse($report->ready());
        self::assertFalse($report->toArray()['abi']['compatible']);
    }
}
