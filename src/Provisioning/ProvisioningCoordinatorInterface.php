<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

/**
 * Coordinates policy-safe module mutation with readers, caches, and events.
 *
 * @since 0.2.0
 */
interface ProvisioningCoordinatorInterface
{
    /**
     * Returns exact injected backend capabilities.
     *
     * @return ProvisioningCapabilities
     * @since 0.2.0
     */
    public function capabilities(): ProvisioningCapabilities;

    /**
     * Installs selected Bible translations.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function install(array $modules): ProvisioningResult;

    /**
     * Installs all policy-approved Bible translations.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function installAll(): ProvisioningResult;

    /**
     * Refreshes selected or all installed Bible translations.
     *
     * @param list<string> $modules Exact identifiers, or an empty list for all installed translations.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function refresh(array $modules = []): ProvisioningResult;

    /**
     * Removes one installed Bible translation.
     *
     * @param string $module Exact module identifier.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function remove(string $module): ProvisioningResult;
}
