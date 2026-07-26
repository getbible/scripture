<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Event;

use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;

/**
 * Emits observational lifecycle events without changing operation outcomes.
 *
 * Lifecycle listeners are integration observers, not transaction
 * participants. Their failures are therefore contained instead of being
 * allowed to abort work or misreport already-committed state. The return value
 * lets a direct caller forward a listener failure to its own logger.
 *
 * @since 1.0.0
 */
final class LifecycleEventDispatcher
{
    /**
     * Dispatches an event and contains any listener failure.
     *
     * @param DispatcherInterface $dispatcher Joomla event dispatcher.
     * @param string $name Stable event name.
     * @param array<string, mixed> $arguments Event arguments.
     *
     * @return \Throwable|null Listener failure, when one occurred.
     * @since 1.0.0
     */
    public static function dispatch(
        DispatcherInterface $dispatcher,
        string $name,
        array $arguments,
    ): ?\Throwable {
        try {
            $dispatcher->dispatch($name, new Event($name, $arguments));

            return null;
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    /**
     * Prevents instantiation of this event-boundary utility.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }
}
