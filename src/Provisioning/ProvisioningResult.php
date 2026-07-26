<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

/**
 * Immutable summary of a completed module provisioning operation.
 *
 * @since 0.1.0
 */
final class ProvisioningResult
{
    /**
     * Backend operation name.
     *
     * @var string
     * @since 0.2.0
     */
    private string $operation;

    /**
     * Per-module outcomes.
     *
     * @var list<ModuleProvisioningResult>
     * @since 0.2.0
     */
    private array $modules;

    /**
     * Creates a complete provisioning result.
     *
     * @param string $operation Backend operation name.
     * @param array<array-key, mixed> $modules Per-module outcomes.
     *
     * @since 0.2.0
     */
    public function __construct(string $operation, array $modules)
    {
        if (trim($operation) === '') {
            throw new \InvalidArgumentException('A provisioning operation name is required.');
        }

        $validated = [];

        foreach ($modules as $module) {
            if (!$module instanceof ModuleProvisioningResult) {
                throw new \InvalidArgumentException('Provisioning results must contain module outcomes.');
            }

            $validated[] = $module;
        }

        $this->operation = $operation;
        $this->modules = $validated;
    }

    /**
     * Returns the backend operation name.
     *
     * @return string
     * @since 0.2.0
     */
    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * Returns every per-module outcome in backend order.
     *
     * @return list<ModuleProvisioningResult>
     * @since 0.2.0
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * Returns installed modules.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function installed(): array
    {
        return $this->matching(ModuleProvisioningResult::ACTION_INSTALL, ModuleProvisioningResult::STATUS_CHANGED);
    }

    /**
     * Returns updated modules.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function updated(): array
    {
        return $this->matching(ModuleProvisioningResult::ACTION_REFRESH, ModuleProvisioningResult::STATUS_CHANGED);
    }

    /**
     * Returns skipped modules.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function skipped(): array
    {
        $modules = [];

        foreach ($this->modules as $module) {
            if ($module->status() === ModuleProvisioningResult::STATUS_SKIPPED) {
                $modules[] = $module->module();
            }
        }

        return $modules;
    }

    /**
     * Returns removed module identifiers.
     *
     * @return list<string>
     * @since 0.2.0
     */
    public function removed(): array
    {
        return $this->matching(ModuleProvisioningResult::ACTION_REMOVE, ModuleProvisioningResult::STATUS_CHANGED);
    }

    /**
     * Returns failed module identifiers.
     *
     * @return list<string>
     * @since 0.2.0
     */
    public function failed(): array
    {
        $modules = [];

        foreach ($this->modules as $module) {
            if ($module->failed()) {
                $modules[] = $module->module();
            }
        }

        return $modules;
    }

    /**
     * Reports whether every module operation completed without failure.
     *
     * @return bool
     * @since 0.2.0
     */
    public function succeeded(): bool
    {
        return $this->failed() === [];
    }

    /**
     * Reports whether at least one module changed.
     *
     * @return bool
     * @since 0.2.0
     */
    public function changed(): bool
    {
        foreach ($this->modules as $module) {
            if ($module->status() === ModuleProvisioningResult::STATUS_CHANGED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a serialization-safe result map.
     *
     * @return array{
     *     operation: string,
     *     succeeded: bool,
     *     changed: bool,
     *     modules: list<array<string, mixed>>
     * }
     * @since 0.2.0
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'succeeded' => $this->succeeded(),
            'changed' => $this->changed(),
            'modules' => array_map(
                static fn (ModuleProvisioningResult $module): array => $module->toArray(),
                $this->modules,
            ),
        ];
    }

    /**
     * Returns module identifiers matching an action and status.
     *
     * @param string $action Action filter.
     * @param string $status Status filter.
     *
     * @return list<string>
     * @since 0.2.0
     */
    private function matching(string $action, string $status): array
    {
        $modules = [];

        foreach ($this->modules as $module) {
            if ($module->action() === $action && $module->status() === $status) {
                $modules[] = $module->module();
            }
        }

        return $modules;
    }
}
