<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Event;

/**
 * Stable Joomla event names emitted by Scripture lifecycle services.
 *
 * @since 0.1.0
 */
final class EventName
{
    /**
     * Emitted before a translation warm starts.
     *
     * @since 0.1.0
     */
    public const WARM_STARTED = 'onGetBibleScriptureWarmStarted';

    /**
     * Emitted after a translation warm is activated.
     *
     * @since 0.1.0
     */
    public const WARM_COMPLETED = 'onGetBibleScriptureWarmCompleted';

    /**
     * Emitted after a translation warm fails.
     *
     * @since 0.1.0
     */
    public const WARM_FAILED = 'onGetBibleScriptureWarmFailed';

    /**
     * Emitted before a forced translation refresh starts.
     *
     * @since 0.1.0
     */
    public const REFRESH_STARTED = 'onGetBibleScriptureRefreshStarted';

    /**
     * Emitted after a forced translation refresh completes.
     *
     * @since 0.1.0
     */
    public const REFRESH_COMPLETED = 'onGetBibleScriptureRefreshCompleted';

    /**
     * Emitted after a forced translation refresh fails.
     *
     * @since 0.1.0
     */
    public const REFRESH_FAILED = 'onGetBibleScriptureRefreshFailed';

    /**
     * Emitted before a native module provisioning operation starts.
     *
     * @since 0.2.0
     */
    public const PROVISIONING_STARTED = 'onGetBibleScriptureProvisioningStarted';

    /**
     * Emitted after a native module provisioning operation completes.
     *
     * @since 0.2.0
     */
    public const PROVISIONING_COMPLETED = 'onGetBibleScriptureProvisioningCompleted';

    /**
     * Emitted after a native module provisioning operation fails.
     *
     * @since 0.2.0
     */
    public const PROVISIONING_FAILED = 'onGetBibleScriptureProvisioningFailed';

    /**
     * Emitted before a complete initialization or refresh run starts.
     *
     * @since 0.3.0
     */
    public const MAINTENANCE_STARTED = 'onGetBibleScriptureMaintenanceStarted';

    /**
     * Emitted after a complete initialization or refresh run completes.
     *
     * @since 0.3.0
     */
    public const MAINTENANCE_COMPLETED = 'onGetBibleScriptureMaintenanceCompleted';

    /**
     * Emitted when a complete initialization or refresh run throws.
     *
     * @since 0.3.0
     */
    public const MAINTENANCE_FAILED = 'onGetBibleScriptureMaintenanceFailed';

    /**
     * Prevents instantiation of this constants-only class.
     *
     * @since 0.1.0
     */
    private function __construct()
    {
    }
}
