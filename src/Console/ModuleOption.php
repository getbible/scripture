<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Console;

/**
 * Validates repeatable Joomla Console module options.
 *
 * @since 0.3.0
 */
final class ModuleOption
{
    /**
     * Returns a validated list from Symfony Console input.
     *
     * @param mixed $value Raw option value.
     *
     * @return list<string>
     * @since 0.3.0
     */
    public static function normalize(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException('The module option must resolve to a list.');
        }

        $modules = [];

        foreach ($value as $module) {
            if (!is_string($module)) {
                throw new \UnexpectedValueException('Every module option must be a string.');
            }

            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * Prevents instantiation of this utility.
     *
     * @since 0.3.0
     */
    private function __construct()
    {
    }
}
