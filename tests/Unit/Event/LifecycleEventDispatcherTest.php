<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Event;

use GetBible\Scripture\Event\EventName;
use GetBible\Scripture\Event\LifecycleEventDispatcher;
use Joomla\Event\Dispatcher;
use Joomla\Event\Event;
use PHPUnit\Framework\TestCase;

/**
 * Verifies lifecycle observers cannot change committed operation outcomes.
 *
 * @since 1.0.0
 */
final class LifecycleEventDispatcherTest extends TestCase
{
    /**
     * Verifies successful observers receive the event and arguments.
     *
     * @return void
     * @since 1.0.0
     */
    public function testSuccessfulListenerReceivesLifecycleEvent(): void
    {
        $dispatcher = new Dispatcher();
        $received = null;
        $dispatcher->addListener(
            'onTestLifecycle',
            static function (Event $event) use (&$received): void {
                $received = $event->getArgument('module');
            },
        );

        $failure = LifecycleEventDispatcher::dispatch(
            $dispatcher,
            'onTestLifecycle',
            ['module' => 'KJV'],
        );

        self::assertNull($failure);
        self::assertSame('KJV', $received);
    }

    /**
     * Verifies listener failures are returned instead of thrown.
     *
     * @return void
     * @since 1.0.0
     */
    public function testListenerFailureIsContained(): void
    {
        $dispatcher = new Dispatcher();
        $expected = new \RuntimeException('Observer failed.');
        $dispatcher->addListener(
            'onTestLifecycle',
            static function () use ($expected): void {
                throw $expected;
            },
        );

        self::assertSame(
            $expected,
            LifecycleEventDispatcher::dispatch(
                $dispatcher,
                'onTestLifecycle',
                ['module' => 'KJV'],
            ),
        );
    }

    /**
     * Verifies every documented lifecycle event has a unique stable name.
     *
     * @return void
     * @since 1.0.0
     */
    public function testEventNamesAreStableAndUnique(): void
    {
        $names = [
            EventName::WARM_STARTED,
            EventName::WARM_COMPLETED,
            EventName::WARM_FAILED,
            EventName::REFRESH_STARTED,
            EventName::REFRESH_COMPLETED,
            EventName::REFRESH_FAILED,
            EventName::PROVISIONING_STARTED,
            EventName::PROVISIONING_COMPLETED,
            EventName::PROVISIONING_FAILED,
            EventName::MAINTENANCE_STARTED,
            EventName::MAINTENANCE_COMPLETED,
            EventName::MAINTENANCE_FAILED,
        ];

        self::assertCount(12, array_unique($names));

        foreach ($names as $name) {
            self::assertStringStartsWith('onGetBibleScripture', $name);
        }
    }
}
