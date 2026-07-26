<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

use GetBible\Scripture\Provisioning\ProvisioningResult;

/**
 * Immutable aggregate returned by initialization and refresh operations.
 *
 * @since 0.3.0
 */
final class MaintenanceResult
{
    /**
     * Creates a complete maintenance outcome.
     *
     * @param string $operation Stable operation name.
     * @param \DateTimeImmutable $startedAt Start time.
     * @param \DateTimeImmutable $completedAt Completion time.
     * @param bool $due Whether interval policy required work.
     * @param list<MaintenanceModuleResult> $modules Per-module outcomes.
     * @param ProvisioningResult|null $provisioning Optional remote provisioning outcome.
     * @param list<string> $errors Operation-level errors.
     * @param string|null $skipReason Reason interval work was skipped.
     *
     * @since 0.3.0
     */
    public function __construct(
        private string $operation,
        private \DateTimeImmutable $startedAt,
        private \DateTimeImmutable $completedAt,
        private bool $due,
        private array $modules,
        private ?ProvisioningResult $provisioning,
        private array $errors,
        private ?string $skipReason = null,
    ) {
        if (trim($operation) === '' || $completedAt < $startedAt) {
            throw new \InvalidArgumentException('Maintenance operation and chronological timestamps are required.');
        }

        foreach ($modules as $module) {
            if (!$module instanceof MaintenanceModuleResult) {
                throw new \InvalidArgumentException('Maintenance results must contain module outcomes.');
            }
        }

        foreach ($errors as $error) {
            if (!is_string($error) || trim($error) === '') {
                throw new \InvalidArgumentException('Maintenance errors must be non-empty strings.');
            }
        }

        $this->modules = array_values($modules);
        $this->errors = array_values($errors);
    }

    /**
     * Returns the stable operation name.
     *
     * @return string
     * @since 0.3.0
     */
    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * Returns the operation start time.
     *
     * @return \DateTimeImmutable
     * @since 0.3.0
     */
    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * Returns the operation completion time.
     *
     * @return \DateTimeImmutable
     * @since 0.3.0
     */
    public function completedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * Reports whether interval policy required work.
     *
     * @return bool
     * @since 0.3.0
     */
    public function due(): bool
    {
        return $this->due;
    }

    /**
     * Returns ordered per-module outcomes.
     *
     * @return list<MaintenanceModuleResult>
     * @since 0.3.0
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * Returns the optional remote provisioning outcome.
     *
     * @return ProvisioningResult|null
     * @since 0.3.0
     */
    public function provisioning(): ?ProvisioningResult
    {
        return $this->provisioning;
    }

    /**
     * Returns operation-level errors.
     *
     * @return list<string>
     * @since 0.3.0
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Returns the interval skip reason.
     *
     * @return string|null
     * @since 0.3.0
     */
    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    /**
     * Reports whether the complete operation succeeded.
     *
     * @return bool
     * @since 0.3.0
     */
    public function succeeded(): bool
    {
        if ($this->errors !== [] || ($this->provisioning !== null && !$this->provisioning->succeeded())) {
            return false;
        }

        foreach ($this->modules as $module) {
            if ($module->failed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a serialization-safe maintenance outcome.
     *
     * @return array<string, mixed>
     * @since 0.3.0
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'succeeded' => $this->succeeded(),
            'due' => $this->due,
            'started_at' => $this->startedAt->format(DATE_ATOM),
            'completed_at' => $this->completedAt->format(DATE_ATOM),
            'skip_reason' => $this->skipReason,
            'errors' => $this->errors,
            'provisioning' => $this->provisioning?->toArray(),
            'modules' => array_map(
                static fn (MaintenanceModuleResult $module): array => $module->toArray(),
                $this->modules,
            ),
        ];
    }
}
