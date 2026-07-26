<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Clock;

/**
 * Supplies time to refresh and snapshot policies.
 *
 * @since 0.1.0
 */
interface ClockInterface
{
    /**
     * Returns the current immutable time.
     *
     * @return \DateTimeImmutable
     * @since 0.1.0
     */
    public function now(): \DateTimeImmutable;
}
