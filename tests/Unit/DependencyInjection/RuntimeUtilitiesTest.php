<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\DependencyInjection;

use GetBible\Scripture\Clock\SystemClock;
use GetBible\Scripture\DependencyInjection\ContainerService;
use Joomla\DI\Container;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the system clock and runtime-checked Joomla service resolution.
 *
 * @since 1.0.0
 */
final class RuntimeUtilitiesTest extends TestCase
{
    /**
     * Verifies the system clock returns a current immutable UTC value.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSystemClockReturnsCurrentUtcTime(): void
    {
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = (new SystemClock())->now();
        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    /**
     * Verifies a correctly typed container service is returned unchanged.
     *
     * @return void
     * @since 1.0.0
     */
    public function testContainerServiceReturnsVerifiedType(): void
    {
        $container = new Container();
        $clock = new SystemClock();
        $container->share(SystemClock::class, $clock, true);

        self::assertSame(
            $clock,
            ContainerService::get($container, SystemClock::class),
        );
    }

    /**
     * Verifies an incorrectly bound Joomla service fails at the boundary.
     *
     * @return void
     * @since 1.0.0
     */
    public function testContainerServiceRejectsWrongRuntimeType(): void
    {
        $container = new Container();
        $container->share(\DateTimeInterface::class, new \stdClass(), true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\DateTimeInterface::class);
        ContainerService::get($container, \DateTimeInterface::class);
    }
}
