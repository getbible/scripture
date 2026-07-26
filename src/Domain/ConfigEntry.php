<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable ordered member of SWORD's interpreted configuration multimap.
 *
 * @since 0.1.0
 */
final class ConfigEntry
{
    /**
     * Creates one configuration entry.
     *
     * @param int $ordinal Source order.
     * @param ByteValue $name Exact name.
     * @param ByteValue $value Exact value.
     *
     * @since 0.1.0
     */
    private function __construct(
        private int $ordinal,
        private ByteValue $name,
        private ByteValue $value,
    ) {
    }

    /**
     * Validates and creates an entry from a contract record.
     *
     * @param array<array-key, mixed> $record Configuration entry record.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromRecord(array $record): self
    {
        $record = StructuredData::object($record, 'Configuration entry record');

        if (($record['type'] ?? null) !== 'config_entry') {
            throw new ContractException('ConfigEntry requires a config_entry record.');
        }

        $ordinal = $record['ordinal'] ?? null;
        $name = $record['name'] ?? null;
        $value = $record['value'] ?? null;

        if (!is_int($ordinal) || $ordinal < 0 || !is_array($name) || !is_array($value)) {
            throw new ContractException('Configuration entry record is invalid.');
        }

        return new self(
            $ordinal,
            ByteValue::fromArray($name, 'config_entry.name'),
            ByteValue::fromArray($value, 'config_entry.value'),
        );
    }

    /**
     * Returns the SWORD map order.
     *
     * @return int
     * @since 0.1.0
     */
    public function ordinal(): int
    {
        return $this->ordinal;
    }

    /**
     * Returns the exact configuration name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function name(): ByteValue
    {
        return $this->name;
    }

    /**
     * Returns the exact configuration value.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function value(): ByteValue
    {
        return $this->value;
    }
}
