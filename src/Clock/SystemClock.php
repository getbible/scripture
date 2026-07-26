<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Clock;

/**
 * Supplies the operating system's current time.
 *
 * @since 0.1.0
 */
final class SystemClock implements ClockInterface
{
    /**
     * Returns the current UTC time.
     *
     * @return \DateTimeImmutable
     * @since 0.1.0
     */
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
