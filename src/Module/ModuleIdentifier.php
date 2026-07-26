<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Module;

/**
 * Normalizes traversal-safe CrossWire SWORD module identifiers.
 *
 * SWORD module identifiers are configuration section names and are therefore
 * deliberately restricted to a small portable ASCII alphabet. Keeping this
 * rule in one value utility prevents provisioning, maintenance, catalog, and
 * snapshot paths from interpreting the same identifier differently.
 *
 * @since 1.0.0
 */
final class ModuleIdentifier
{
    /**
     * Maximum accepted identifier length.
     *
     * @since 1.0.0
     */
    private const MAX_LENGTH = 128;

    /**
     * Trims and validates one module identifier.
     *
     * @param string $identifier Candidate module identifier.
     *
     * @return string Canonical identifier.
     * @since 1.0.0
     */
    public static function normalize(string $identifier): string
    {
        $identifier = trim($identifier, " \t\r\n");

        if (
            $identifier === ''
            || $identifier === '.'
            || $identifier === '..'
            || strlen($identifier) > self::MAX_LENGTH
            || preg_match('/^[A-Za-z0-9_.+-]+$/D', $identifier) !== 1
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid SWORD module identifier "%s".',
                self::printable($identifier),
            ));
        }

        return $identifier;
    }

    /**
     * Creates a bounded printable diagnostic for an invalid identifier.
     *
     * @param string $identifier Invalid identifier bytes.
     *
     * @return string
     * @since 1.0.0
     */
    private static function printable(string $identifier): string
    {
        $printable = preg_replace('/[^\x20-\x7E]/', '?', $identifier);

        if (!is_string($printable)) {
            return '<invalid>';
        }

        return strlen($printable) > self::MAX_LENGTH
            ? substr($printable, 0, self::MAX_LENGTH) . '...'
            : $printable;
    }

    /**
     * Prevents instantiation of this validation utility.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }
}
