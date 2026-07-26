<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\EnumValue;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable native metadata describing one installed SWORD module.
 *
 * @since 0.1.0
 */
final class TranslationMetadata
{
    /**
     * Supported producer classifications.
     *
     * @var list<string>
     * @since 0.1.0
     */
    private const CLASSIFICATIONS = [
        'bible',
        'commentary',
        'dictionary_or_lexicon',
        'general_book',
        'devotional',
        'resource',
        'unknown',
    ];

    /**
     * Creates complete module metadata.
     *
     * @param string $classification Convenience classification.
     * @param ByteValue $name Exact module identifier.
     * @param ByteValue $description Exact description.
     * @param ByteValue $driver Exact ModDrv.
     * @param ByteValue $language Exact language identifier.
     * @param ByteValue $swordType Exact authoritative SWORD type.
     * @param EnumValue $direction Native direction.
     * @param EnumValue $encoding Native encoding.
     * @param EnumValue $markup Native markup.
     * @param array<string, mixed> $record Original record.
     *
     * @since 0.1.0
     */
    private function __construct(
        private string $classification,
        private ByteValue $name,
        private ByteValue $description,
        private ByteValue $driver,
        private ByteValue $language,
        private ByteValue $swordType,
        private EnumValue $direction,
        private EnumValue $encoding,
        private EnumValue $markup,
        private array $record,
    ) {
    }

    /**
     * Validates and creates metadata from a module record.
     *
     * @param array<array-key, mixed> $record Module record.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromRecord(array $record): self
    {
        $record = StructuredData::object($record, 'Module record');

        if (($record['type'] ?? null) !== 'module') {
            throw new ContractException('Translation metadata requires a module record.');
        }

        $classification = $record['classification'] ?? null;

        if (!is_string($classification) || !in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new ContractException('Module classification is invalid.');
        }

        return new self(
            $classification,
            self::byteValue($record, 'name'),
            self::byteValue($record, 'description'),
            self::byteValue($record, 'driver'),
            self::byteValue($record, 'language'),
            self::byteValue($record, 'sword_type'),
            self::enumValue($record, 'direction'),
            self::enumValue($record, 'encoding'),
            self::enumValue($record, 'markup'),
            $record,
        );
    }

    /**
     * Returns the producer convenience classification.
     *
     * @return string
     * @since 0.1.0
     */
    public function classification(): string
    {
        return $this->classification;
    }

    /**
     * Indicates whether this module is a Bible translation.
     *
     * @return bool
     * @since 0.1.0
     */
    public function isBible(): bool
    {
        return $this->classification === 'bible';
    }

    /**
     * Returns the exact module identifier.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function name(): ByteValue
    {
        return $this->name;
    }

    /**
     * Returns the exact module description.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function description(): ByteValue
    {
        return $this->description;
    }

    /**
     * Returns the exact native module driver.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function driver(): ByteValue
    {
        return $this->driver;
    }

    /**
     * Returns the exact module language identifier.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function language(): ByteValue
    {
        return $this->language;
    }

    /**
     * Returns the authoritative exact SWORD module type.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function swordType(): ByteValue
    {
        return $this->swordType;
    }

    /**
     * Returns the native direction.
     *
     * @return EnumValue
     * @since 0.1.0
     */
    public function direction(): EnumValue
    {
        return $this->direction;
    }

    /**
     * Returns the native encoding.
     *
     * @return EnumValue
     * @since 0.1.0
     */
    public function encoding(): EnumValue
    {
        return $this->encoding;
    }

    /**
     * Returns the native markup.
     *
     * @return EnumValue
     * @since 0.1.0
     */
    public function markup(): EnumValue
    {
        return $this->markup;
    }

    /**
     * Returns the original validated module record.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    public function contractRecord(): array
    {
        return $this->record;
    }

    /**
     * Extracts a required byte envelope.
     *
     * @param array<string, mixed> $record Module record.
     * @param string $field Field name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    private static function byteValue(array $record, string $field): ByteValue
    {
        $value = $record[$field] ?? null;

        if (!is_array($value)) {
            throw new ContractException(sprintf('Module field "%s" is not a byte value.', $field));
        }

        return ByteValue::fromArray($value, 'module.' . $field);
    }

    /**
     * Extracts a required native enumeration.
     *
     * @param array<string, mixed> $record Module record.
     * @param string $field Field name.
     *
     * @return EnumValue
     * @since 0.1.0
     */
    private static function enumValue(array $record, string $field): EnumValue
    {
        $value = $record[$field] ?? null;

        if (!is_array($value)) {
            throw new ContractException(sprintf('Module field "%s" is not an enum value.', $field));
        }

        return EnumValue::fromArray($value, 'module.' . $field);
    }
}
