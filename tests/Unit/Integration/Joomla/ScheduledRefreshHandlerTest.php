<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Integration\Joomla;

use GetBible\Scripture\Integration\Joomla\ScheduledRefreshHandler;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the Joomla Scheduled Tasks bridge.
 *
 * @since 1.0.0
 */
final class ScheduledRefreshHandlerTest extends TestCase
{
    /**
     * Verifies direct and callable execution delegate to interval-gated refresh.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRunAndInvokeDelegateToRefreshIfDue(): void
    {
        $result = $this->maintenanceResult();
        $maintenance = $this->createMock(MaintenanceServiceInterface::class);
        $maintenance->expects(self::exactly(2))
            ->method('refreshIfDue')
            ->willReturn($result);
        $handler = new ScheduledRefreshHandler($maintenance);

        self::assertSame($result, $handler->run());
        self::assertSame($result, $handler());
    }

    /**
     * Creates a deterministic skipped maintenance result.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    private function maintenanceResult(): MaintenanceResult
    {
        $time = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        return new MaintenanceResult(
            'refresh-if-due',
            $time,
            $time,
            false,
            [],
            null,
            [],
            'The configured refresh interval has not elapsed.',
        );
    }
}
