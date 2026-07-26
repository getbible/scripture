<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

/**
 * Immutable outcome for one module within a provisioning operation.
 *
 * @since 0.2.0
 */
final class ModuleProvisioningResult
{
    /**
     * Selected-module installation action.
     *
     * @since 0.2.0
     */
    public const ACTION_INSTALL = 'install';

    /**
     * Installed-module remote refresh action.
     *
     * @since 0.2.0
     */
    public const ACTION_REFRESH = 'refresh';

    /**
     * Installed-module removal action.
     *
     * @since 0.2.0
     */
    public const ACTION_REMOVE = 'remove';

    /**
     * The backend changed persistent module state.
     *
     * @since 0.2.0
     */
    public const STATUS_CHANGED = 'changed';

    /**
     * The backend safely skipped the requested module.
     *
     * @since 0.2.0
     */
    public const STATUS_SKIPPED = 'skipped';

    /**
     * The backend could not complete the requested module.
     *
     * @since 0.2.0
     */
    public const STATUS_FAILED = 'failed';

    /**
     * Creates one validated module outcome.
     *
     * @param string $module Exact module identifier.
     * @param string $action Requested action.
     * @param string $status Final status.
     * @param string $message Human-readable diagnostic.
     * @param array<string, bool|float|int|string|null> $details Machine-readable details.
     *
     * @since 0.2.0
     */
    public function __construct(
        private string $module,
        private string $action,
        private string $status,
        private string $message = '',
        private array $details = [],
    ) {
        if (trim($module) === '') {
            throw new \InvalidArgumentException('A module provisioning result requires a module identifier.');
        }

        if (!in_array($action, [self::ACTION_INSTALL, self::ACTION_REFRESH, self::ACTION_REMOVE], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported module action "%s".', $action));
        }

        if (!in_array($status, [self::STATUS_CHANGED, self::STATUS_SKIPPED, self::STATUS_FAILED], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported module status "%s".', $status));
        }
    }

    /**
     * Returns the exact module identifier.
     *
     * @return string
     * @since 0.2.0
     */
    public function module(): string
    {
        return $this->module;
    }

    /**
     * Returns the requested action.
     *
     * @return string
     * @since 0.2.0
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Returns the final status.
     *
     * @return string
     * @since 0.2.0
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Returns the backend diagnostic.
     *
     * @return string
     * @since 0.2.0
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Returns machine-readable backend details.
     *
     * @return array<string, bool|float|int|string|null>
     * @since 0.2.0
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * Reports whether this module failed.
     *
     * @return bool
     * @since 0.2.0
     */
    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Returns a serialization-safe outcome map.
     *
     * @return array{
     *     module: string,
     *     action: string,
     *     status: string,
     *     message: string,
     *     details: array<string, bool|float|int|string|null>
     * }
     * @since 0.2.0
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'action' => $this->action,
            'status' => $this->status,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
