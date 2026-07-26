<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

/**
 * Immutable top-level type in SWORD's official ordered attribute map.
 *
 * @since 0.1.0
 */
final class OfficialAttributeType
{
    /**
     * Creates an official attribute type.
     *
     * @param ByteValue $name Exact type name.
     * @param list<OfficialAttributeList> $lists Ordered lists.
     *
     * @since 0.1.0
     */
    public function __construct(
        private ByteValue $name,
        private array $lists,
    ) {
    }

    /**
     * Returns the exact type name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function name(): ByteValue
    {
        return $this->name;
    }

    /**
     * Returns ordered attribute lists.
     *
     * @return list<OfficialAttributeList>
     * @since 0.1.0
     */
    public function lists(): array
    {
        return $this->lists;
    }
}
