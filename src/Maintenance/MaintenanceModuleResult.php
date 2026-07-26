<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

/**
 * Immutable outcome for one translation snapshot maintenance action.
 *
 * @since 0.3.0
 */
final class MaintenanceModuleResult
{
    /**
     * Snapshot was opened or warmed for initialization.
     *
     * @since 0.3.0
     */
    public const STATUS_READY = 'ready';

    /**
     * Snapshot was forcibly rebuilt.
     *
     * @since 0.3.0
     */
    public const STATUS_REFRESHED = 'refreshed';

    /**
     * Module was intentionally not processed.
     *
     * @since 0.3.0
     */
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Module could not be processed.
     *
     * @since 0.3.0
     */
    public const STATUS_FAILED = 'failed';

    /**
     * Creates one module outcome.
     *
     * @param string $module Exact module identifier.
     * @param string $status Final status.
     * @param string $message Human-readable diagnostic.
     *
     * @since 0.3.0
     */
    public function __construct(
        private string $module,
        private string $status,
        private string $message = '',
    ) {
        if (trim($module) === '') {
            throw new \InvalidArgumentException('A maintenance module result requires an identifier.');
        }

        if (!in_array(
            $status,
            [self::STATUS_READY, self::STATUS_REFRESHED, self::STATUS_SKIPPED, self::STATUS_FAILED],
            true,
        )) {
            throw new \InvalidArgumentException(sprintf('Unsupported maintenance module status "%s".', $status));
        }
    }

    /**
     * Returns the exact module identifier.
     *
     * @return string
     * @since 0.3.0
     */
    public function module(): string
    {
        return $this->module;
    }

    /**
     * Returns the final status.
     *
     * @return string
     * @since 0.3.0
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Returns the diagnostic message.
     *
     * @return string
     * @since 0.3.0
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Reports whether this module failed.
     *
     * @return bool
     * @since 0.3.0
     */
    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Returns a serialization-safe module outcome.
     *
     * @return array{module: string, status: string, message: string}
     * @since 0.3.0
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
