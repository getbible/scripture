<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

use GetBible\Scripture\Provisioning\ProvisioningCapabilities;

/**
 * Immutable operational status for health checks and scheduler decisions.
 *
 * @since 0.3.0
 */
final class MaintenanceStatus
{
    /**
     * Creates complete operational status.
     *
     * @param \DateTimeImmutable $checkedAt Status time.
     * @param bool $due Whether refresh is currently due.
     * @param \DateTimeImmutable|null $nextDueAt Next scheduled due time.
     * @param MaintenanceState $state Durable maintenance state.
     * @param ProvisioningCapabilities $capabilities Native backend capabilities.
     * @param bool $provisioningEnabled Whether runtime policy permits mutation.
     * @param list<string> $configuredModules Configured module targets.
     * @param list<string> $installedModules Installed Bible module identifiers.
     *
     * @since 0.3.0
     */
    public function __construct(
        private \DateTimeImmutable $checkedAt,
        private bool $due,
        private ?\DateTimeImmutable $nextDueAt,
        private MaintenanceState $state,
        private ProvisioningCapabilities $capabilities,
        private bool $provisioningEnabled,
        private array $configuredModules,
        private array $installedModules,
    ) {
        $this->configuredModules = array_values($configuredModules);
        $this->installedModules = array_values($installedModules);
    }

    /**
     * Reports whether refresh is currently due.
     *
     * @return bool
     * @since 0.3.0
     */
    public function due(): bool
    {
        return $this->due;
    }

    /**
     * Returns durable maintenance state.
     *
     * @return MaintenanceState
     * @since 0.3.0
     */
    public function state(): MaintenanceState
    {
        return $this->state;
    }

    /**
     * Returns a serialization-safe operational status.
     *
     * @return array<string, mixed>
     * @since 0.3.0
     */
    public function toArray(): array
    {
        return [
            'checked_at' => $this->checkedAt->format(DATE_ATOM),
            'due' => $this->due,
            'next_due_at' => $this->nextDueAt?->format(DATE_ATOM),
            'provisioning_enabled' => $this->provisioningEnabled,
            'capabilities' => $this->capabilities->toArray(),
            'configured_modules' => $this->configuredModules,
            'installed_modules' => $this->installedModules,
            'state' => $this->state->toArray(),
        ];
    }
}
