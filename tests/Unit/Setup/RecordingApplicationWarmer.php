<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\MaintenanceModuleResult;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Setup\ApplicationWarmerInterface;

/**
 * Records candidate configuration passed to the warming seam.
 *
 * @since 1.0.0
 */
final class RecordingApplicationWarmer implements ApplicationWarmerInterface
{
    /**
     * Last warmed configuration.
     *
     * @var Configuration|null
     * @since 1.0.0
     */
    public ?Configuration $configuration = null;

    /**
     * Last warmed module targets.
     *
     * @var list<string>
     * @since 1.0.0
     */
    public array $modules = [];

    /**
     * Records and successfully warms the candidate.
     *
     * @param Configuration $configuration Candidate configuration.
     * @param list<string> $modules Explicit targets.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    public function warm(Configuration $configuration, array $modules = []): MaintenanceResult
    {
        $this->configuration = $configuration;
        $this->modules = $modules;
        $now = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');

        return new MaintenanceResult(
            'initialize',
            $now,
            $now,
            true,
            array_map(
                static fn (string $module): MaintenanceModuleResult => new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_READY,
                ),
                $modules,
            ),
            null,
            [],
        );
    }
}
