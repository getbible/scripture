<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

use GetBible\Scripture\Exception\ProvisioningUnavailableException;

/**
 * Explicitly reports that getBibleSword ABI v1 has no provisioning capability.
 *
 * @since 0.1.0
 */
final class AbiV1ModuleProvisioner implements ModuleProvisionerInterface
{
    /**
     * Reports that ABI v1 cannot install or update modules.
     *
     * @return ProvisioningCapabilities
     * @since 0.1.0
     */
    public function capabilities(): ProvisioningCapabilities
    {
        return ProvisioningCapabilities::abiV1();
    }

    /**
     * Rejects selected-module installation until a safe native API exists.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function installTranslations(array $modules): ProvisioningResult
    {
        throw $this->unavailable();
    }

    /**
     * Rejects all-module installation until a safe native API exists.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function installAllTranslations(): ProvisioningResult
    {
        throw $this->unavailable();
    }

    /**
     * Rejects remote refresh until a safe native API exists.
     *
     * @param list<string> $modules Exact module identifiers or an empty list.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function refreshTranslations(array $modules = []): ProvisioningResult
    {
        throw $this->unavailable();
    }

    /**
     * Rejects removal until a safe native API exists.
     *
     * @param string $module Exact module identifier.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function removeTranslation(string $module): ProvisioningResult
    {
        throw $this->unavailable();
    }

    /**
     * Creates the stable capability error.
     *
     * @return ProvisioningUnavailableException
     * @since 0.1.0
     */
    private function unavailable(): ProvisioningUnavailableException
    {
        return new ProvisioningUnavailableException(
            'getBibleSword ABI v1 cannot install or refresh modules safely. '
            . 'Use preinstalled SWORD modules until the native provisioning API is released.',
        );
    }
}
