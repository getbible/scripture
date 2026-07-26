<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\DependencyInjection;

use Joomla\DI\Container;

/**
 * Resolves and runtime-verifies typed services from Joomla DI.
 *
 * @since 0.3.0
 */
final class ContainerService
{
    /**
     * Returns one service matching its requested class or interface.
     *
     * @template T of object
     *
     * @param Container $container Joomla dependency injection container.
     * @param class-string<T> $class Requested class or interface.
     *
     * @return T
     * @since 0.3.0
     */
    public static function get(Container $container, string $class): object
    {
        $service = $container->get($class);

        if (!$service instanceof $class) {
            throw new \LogicException(sprintf(
                'The Joomla container returned an invalid "%s" service.',
                $class,
            ));
        }

        return $service;
    }

    /**
     * Prevents instantiation of this resolver.
     *
     * @since 0.3.0
     */
    private function __construct()
    {
    }
}
