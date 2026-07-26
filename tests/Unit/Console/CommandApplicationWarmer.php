<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Setup\ApplicationWarmerInterface;

/**
 * Fails if a command unexpectedly requests warming.
 *
 * @since 1.0.0
 */
final class CommandApplicationWarmer implements ApplicationWarmerInterface
{
    /**
     * Rejects unexpected warming in command tests.
     *
     * @param Configuration $configuration Candidate configuration.
     * @param list<string> $modules Module targets.
     *
     * @return MaintenanceResult
     * @since 1.0.0
     */
    public function warm(Configuration $configuration, array $modules = []): MaintenanceResult
    {
        throw new \LogicException('Command test unexpectedly requested warming.');
    }
}
