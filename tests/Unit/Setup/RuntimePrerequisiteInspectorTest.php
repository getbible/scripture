<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Setup\RuntimePrerequisiteInspector;
use PHPUnit\Framework\TestCase;

/**
 * Verifies inspection remains safe when the optional native runtime is absent.
 *
 * @since 1.0.0
 */
final class RuntimePrerequisiteInspectorTest extends TestCase
{
    /**
     * Verifies absent native extension metadata is reported without throwing.
     *
     * @return void
     * @since 1.0.0
     */
    public function testReportsMissingExtensionWithoutSideEffects(): void
    {
        if (extension_loaded('getbiblesword')) {
            self::markTestSkipped('This test covers the runtime branch where getbiblesword is absent.');
        }

        $report = (new RuntimePrerequisiteInspector())->inspect();

        self::assertFalse($report->ready());
        self::assertFalse($report->extensionLoaded());
        self::assertNull($report->extensionVersion());
        self::assertNull($report->abiVersion());
        self::assertNull($report->contractIdentifier());
        self::assertNull($report->productVersion());
        self::assertNull($report->error());
    }
}
