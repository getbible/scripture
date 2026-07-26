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
     * Reports whether the active native boundary supports provisioning.
     *
     * @return bool
     * @since 0.1.0
     */
    public function isAvailable(): bool;

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
    public function refreshTranslations(): ProvisioningResult;
}
