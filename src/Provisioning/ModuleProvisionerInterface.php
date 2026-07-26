<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

/**
 * Defines explicit installation and remote refresh operations for Bible modules.
 *
 * @since 0.1.0
 */
interface ModuleProvisionerInterface
{
    /**
     * Returns exact backend capabilities.
     *
     * @return ProvisioningCapabilities
     * @since 0.2.0
     */
    public function capabilities(): ProvisioningCapabilities;

    /**
     * Installs selected policy-approved Bible translations.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function installTranslations(array $modules): ProvisioningResult;

    /**
     * Installs every policy-approved Bible translation into a staged root.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function installAllTranslations(): ProvisioningResult;

    /**
     * Refreshes every installed Bible translation through the remote repository.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function refreshTranslations(array $modules = []): ProvisioningResult;

    /**
     * Removes one installed Bible translation.
     *
     * @param string $module Exact module identifier.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function removeTranslation(string $module): ProvisioningResult;
}
