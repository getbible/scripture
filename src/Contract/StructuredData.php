<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Exception\ContractException;

/**
 * Narrows untrusted decoded JSON values into documented object and list shapes.
 *
 * @since 0.3.0
 */
final class StructuredData
{
    /**
     * Requires an associative JSON object with string keys.
     *
     * @param mixed $value Candidate decoded value.
     * @param string $context Human-readable field context.
     *
     * @return array<string, mixed>
     * @since 0.3.0
     */
    public static function object(mixed $value, string $context): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new ContractException(sprintf('%s must be a JSON object.', $context));
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                throw new ContractException(sprintf('%s contains a non-string object key.', $context));
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Requires an ordered JSON list.
     *
     * @param mixed $value Candidate decoded value.
     * @param string $context Human-readable field context.
     *
     * @return list<mixed>
     * @since 0.3.0
     */
    public static function list(mixed $value, string $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ContractException(sprintf('%s must be a JSON list.', $context));
        }

        return $value;
    }

    /**
     * Requires a decoded map whose JSON keys may normalize to integers.
     *
     * PHP converts integer-like JSON object keys to integer array keys when
     * decoding into associative arrays. This guard is reserved for schemas,
     * such as chapter maps, that deliberately use numeric object keys.
     *
     * @param mixed $value Candidate decoded value.
     * @param string $context Human-readable field context.
     *
     * @return array<array-key, mixed>
     * @since 0.3.0
     */
    public static function map(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new ContractException(sprintf('%s must be a JSON map.', $context));
        }

        return $value;
    }

    /**
     * Prevents instantiation of this validation utility.
     *
     * @since 0.3.0
     */
    private function __construct()
    {
    }
}
