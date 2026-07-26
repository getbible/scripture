<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

use GetBible\Scripture\Configuration\Configuration;

/**
 * Validates setup, persists application settings, and warms installed modules.
 *
 * @since 1.0.0
 */
interface SetupServiceInterface
{
    /**
     * Inspects the already-loaded native runtime without changing it.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport;

    /**
     * Resolves current persisted and environment-backed configuration.
     *
     * @param string|null $configurationPath Explicit durable path.
     *
     * @return Configuration
     * @since 1.0.0
     */
    public function configuration(?string $configurationPath = null): Configuration;

    /**
     * Validates, atomically persists, and optionally warms configuration.
     *
     * @param SetupRequest $request Setup request.
     *
     * @return SetupResult
     * @since 1.0.0
     */
    public function apply(SetupRequest $request): SetupResult;
}
