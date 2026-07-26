<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable ordered projection of SWORD's complete three-level attribute map.
 *
 * @since 0.1.0
 */
final class OfficialAttributes
{
    /**
     * Creates an attribute map.
     *
     * @param list<OfficialAttributeType> $types Ordered types.
     *
     * @since 0.1.0
     */
    private function __construct(private array $types)
    {
    }

    /**
     * Validates and creates the complete ordered attribute map.
     *
     * @param array<array-key, mixed> $attributes Candidate types.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromArray(array $attributes): self
    {
        if (!array_is_list($attributes)) {
            throw new ContractException('Official attributes must be an ordered list.');
        }

        $types = [];

        foreach ($attributes as $typeIndex => $type) {
            $type = StructuredData::object(
                $type,
                sprintf('Official attribute type %d', $typeIndex),
            );

            if (!is_array($type['name'] ?? null)) {
                throw new ContractException(sprintf('Official attribute type %d is invalid.', $typeIndex));
            }

            $lists = [];

            foreach (
                StructuredData::list(
                    $type['lists'] ?? null,
                    sprintf('Official attribute type %d lists', $typeIndex),
                ) as $listIndex => $list
            ) {
                $list = StructuredData::object(
                    $list,
                    sprintf('Official attribute type %d list %d', $typeIndex, $listIndex),
                );

                if (!is_array($list['name'] ?? null)) {
                    throw new ContractException(sprintf(
                        'Official attribute type %d list %d is invalid.',
                        $typeIndex,
                        $listIndex,
                    ));
                }

                $values = [];

                foreach (
                    StructuredData::list(
                        $list['values'] ?? null,
                        sprintf('Official attribute type %d list %d values', $typeIndex, $listIndex),
                    ) as $valueIndex => $value
                ) {
                    $value = StructuredData::object(
                        $value,
                        sprintf(
                            'Official attribute type %d list %d value %d',
                            $typeIndex,
                            $listIndex,
                            $valueIndex,
                        ),
                    );

                    if (!is_array($value['name'] ?? null) || !is_array($value['value'] ?? null)) {
                        throw new ContractException(sprintf(
                            'Official attribute type %d list %d value %d is invalid.',
                            $typeIndex,
                            $listIndex,
                            $valueIndex,
                        ));
                    }

                    $values[] = new OfficialAttributeValue(
                        ByteValue::fromArray(
                            $value['name'],
                            "official_attributes[$typeIndex].lists[$listIndex].values[$valueIndex].name",
                        ),
                        ByteValue::fromArray(
                            $value['value'],
                            "official_attributes[$typeIndex].lists[$listIndex].values[$valueIndex].value",
                        ),
                    );
                }

                $lists[] = new OfficialAttributeList(
                    ByteValue::fromArray(
                        $list['name'],
                        "official_attributes[$typeIndex].lists[$listIndex].name",
                    ),
                    $values,
                );
            }

            $types[] = new OfficialAttributeType(
                ByteValue::fromArray($type['name'], "official_attributes[$typeIndex].name"),
                $lists,
            );
        }

        return new self($types);
    }

    /**
     * Returns the complete ordered attribute types.
     *
     * @return list<OfficialAttributeType>
     * @since 0.1.0
     */
    public function types(): array
    {
        return $this->types;
    }
}
