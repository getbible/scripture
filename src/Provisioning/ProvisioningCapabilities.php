<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

/**
 * Immutable feature description for an injected module provisioning backend.
 *
 * @since 0.2.0
 */
final class ProvisioningCapabilities
{
    /**
     * Creates an explicit capability set.
     *
     * @param string $backend Stable backend identifier.
     * @param string $contract Stable backend contract identifier.
     * @param bool $installSelected Whether selected modules can be installed.
     * @param bool $installAll Whether all policy-approved modules can be installed.
     * @param bool $refresh Whether installed modules can be refreshed remotely.
     * @param bool $remove Whether installed modules can be removed.
     *
     * @since 0.2.0
     */
    public function __construct(
        private string $backend,
        private string $contract,
        private bool $installSelected,
        private bool $installAll,
        private bool $refresh,
        private bool $remove,
    ) {
        if (trim($backend) === '' || trim($contract) === '') {
            throw new \InvalidArgumentException('Provisioning backend and contract identifiers are required.');
        }
    }

    /**
     * Creates the exact getBibleSword ABI v1 capability set.
     *
     * @return self
     * @since 0.2.0
     */
    public static function abiV1(): self
    {
        return new self(
            'getbible/sword',
            'getbiblesword.ndjson/v1',
            false,
            false,
            false,
            false,
        );
    }

    /**
     * Returns the stable backend identifier.
     *
     * @return string
     * @since 0.2.0
     */
    public function backend(): string
    {
        return $this->backend;
    }

    /**
     * Returns the stable backend contract identifier.
     *
     * @return string
     * @since 0.2.0
     */
    public function contract(): string
    {
        return $this->contract;
    }

    /**
     * Reports selected-module installation support.
     *
     * @return bool
     * @since 0.2.0
     */
    public function canInstallSelected(): bool
    {
        return $this->installSelected;
    }

    /**
     * Reports all-module installation support.
     *
     * @return bool
     * @since 0.2.0
     */
    public function canInstallAll(): bool
    {
        return $this->installAll;
    }

    /**
     * Reports remote refresh support.
     *
     * @return bool
     * @since 0.2.0
     */
    public function canRefresh(): bool
    {
        return $this->refresh;
    }

    /**
     * Reports module removal support.
     *
     * @return bool
     * @since 0.2.0
     */
    public function canRemove(): bool
    {
        return $this->remove;
    }

    /**
     * Reports whether any mutating provisioning operation is available.
     *
     * @return bool
     * @since 0.2.0
     */
    public function isAvailable(): bool
    {
        return $this->installSelected || $this->installAll || $this->refresh || $this->remove;
    }

    /**
     * Returns a serialization-safe capability map.
     *
     * @return array{
     *     backend: string,
     *     contract: string,
     *     install_selected: bool,
     *     install_all: bool,
     *     refresh: bool,
     *     remove: bool
     * }
     * @since 0.2.0
     */
    public function toArray(): array
    {
        return [
            'backend' => $this->backend,
            'contract' => $this->contract,
            'install_selected' => $this->installSelected,
            'install_all' => $this->installAll,
            'refresh' => $this->refresh,
            'remove' => $this->remove,
        ];
    }
}
