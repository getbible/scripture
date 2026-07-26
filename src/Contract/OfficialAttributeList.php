<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

/**
 * Immutable ordered list within one SWORD official attribute type.
 *
 * @since 0.1.0
 */
final class OfficialAttributeList
{
    /**
     * Creates an official attribute list.
     *
     * @param ByteValue $name Exact list name.
     * @param list<OfficialAttributeValue> $values Ordered values.
     *
     * @since 0.1.0
     */
    public function __construct(
        private ByteValue $name,
        private array $values,
    ) {
    }

    /**
     * Returns the exact list name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function name(): ByteValue
    {
        return $this->name;
    }

    /**
     * Returns ordered list values.
     *
     * @return list<OfficialAttributeValue>
     * @since 0.1.0
     */
    public function values(): array
    {
        return $this->values;
    }
}
