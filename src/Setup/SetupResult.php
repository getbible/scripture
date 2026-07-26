<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Maintenance\MaintenanceResult;

/**
 * Immutable aggregate describing configuration and warming outcomes.
 *
 * @since 1.0.0
 */
final class SetupResult
{
    /**
     * Validated ordered setup errors.
     *
     * @var list<string>
     * @since 1.0.0
     */
    private array $errors;

    /**
     * Creates a complete setup result.
     *
     * @param string $configurationPath Durable configuration path.
     * @param Configuration $configuration Persisted immutable configuration.
     * @param RuntimePrerequisiteReport $runtime Native runtime report.
     * @param bool $warmRequested Whether warming was requested.
     * @param MaintenanceResult|null $maintenance Optional warming result.
     * @param list<string> $errors Ordered errors.
     *
     * @since 1.0.0
     */
    public function __construct(
        private string $configurationPath,
        private Configuration $configuration,
        private RuntimePrerequisiteReport $runtime,
        private bool $warmRequested,
        private ?MaintenanceResult $maintenance,
        array $errors = [],
    ) {
        $validated = [];

        foreach ($errors as $error) {
            if (trim($error) === '') {
                throw new \InvalidArgumentException('Setup errors must be non-empty strings.');
            }

            $validated[] = $error;
        }

        $this->errors = $validated;
    }

    /**
     * Reports whether configuration and requested warming succeeded.
     *
     * @return bool
     * @since 1.0.0
     */
    public function succeeded(): bool
    {
        return $this->errors === []
            && (!$this->warmRequested || ($this->maintenance !== null && $this->maintenance->succeeded()));
    }

    /**
     * Returns the durable configuration path.
     *
     * @return string
     * @since 1.0.0
     */
    public function configurationPath(): string
    {
        return $this->configurationPath;
    }

    /**
     * Returns the persisted immutable configuration.
     *
     * @return Configuration
     * @since 1.0.0
     */
    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Returns the native runtime report captured during setup.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function runtime(): RuntimePrerequisiteReport
    {
        return $this->runtime;
    }

    /**
     * Reports whether warming was requested.
     *
     * @return bool
     * @since 1.0.0
     */
    public function warmRequested(): bool
    {
        return $this->warmRequested;
    }

    /**
     * Returns the optional warming outcome.
     *
     * @return MaintenanceResult|null
     * @since 1.0.0
     */
    public function maintenance(): ?MaintenanceResult
    {
        return $this->maintenance;
    }

    /**
     * Returns ordered errors.
     *
     * @return list<string>
     * @since 1.0.0
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Returns a deterministic serialization-safe setup result.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'operation' => 'setup',
            'succeeded' => $this->succeeded(),
            'configuration_path' => $this->configurationPath,
            'configuration' => [
                'module_path' => $this->configuration->modulePath(),
                'cache_path' => $this->configuration->cachePath(),
                'refresh_interval' => $this->configuration->refreshIntervalSpec(),
                'auto_refresh' => $this->configuration->autoRefresh(),
                'lock_timeout' => $this->configuration->lockTimeout(),
                'modules' => $this->configuration->modules(),
                'provisioning_enabled' => $this->configuration->provisioningEnabled(),
                'install_all' => $this->configuration->installAll(),
            ],
            'runtime' => $this->runtime->toArray(),
            'warm_requested' => $this->warmRequested,
            'maintenance' => $this->maintenance?->toArray(),
            'errors' => $this->errors,
        ];
    }
}
