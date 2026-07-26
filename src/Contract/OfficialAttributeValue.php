<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

/**
 * Immutable leaf value in SWORD's official ordered attribute map.
 *
 * @since 0.1.0
 */
final class OfficialAttributeValue
{
    /**
     * Creates an official attribute value.
     *
     * @param ByteValue $name Exact value name.
     * @param ByteValue $value Exact value content.
     *
     * @since 0.1.0
     */
    public function __construct(
        private ByteValue $name,
        private ByteValue $value,
    ) {
    }

    /**
     * Returns the exact attribute value name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function name(): ByteValue
    {
        return $this->name;
    }

    /**
     * Returns the exact attribute value content.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function value(): ByteValue
    {
        return $this->value;
    }
}
