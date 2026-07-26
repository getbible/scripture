<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Contract\AnnotationSegment;
use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\OfficialAttributes;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Contract\VerseScope;
use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable ordinary Bible verse retaining every v1 entry data layer.
 *
 * @since 0.1.0
 */
final class Verse
{
    /**
     * Creates a complete verse.
     *
     * @param int $ordinal Native traversal ordinal.
     * @param ByteValue $key Exact native key.
     * @param VerseScope $scope Exact verse scope.
     * @param ByteValue $raw Authoritative raw entry bytes.
     * @param ByteValue|null $rendered Default SWORD rendering.
     * @param ByteValue|null $stripped Stripped text projection.
     * @param list<AnnotationSegment> $segments Lexical raw segments.
     * @param OfficialAttributes $attributes Official SWORD attributes.
     * @param array<string, mixed> $record Original entry record.
     *
     * @since 0.1.0
     */
    private function __construct(
        private int $ordinal,
        private ByteValue $key,
        private VerseScope $scope,
        private ByteValue $raw,
        private ?ByteValue $rendered,
        private ?ByteValue $stripped,
        private array $segments,
        private OfficialAttributes $attributes,
        private array $record,
    ) {
    }

    /**
     * Validates and hydrates an ordinary verse from an entry record.
     *
     * @param array<array-key, mixed> $record Entry record.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromRecord(array $record): self
    {
        $record = StructuredData::object($record, 'Verse record');

        if (($record['type'] ?? null) !== 'entry') {
            throw new ContractException('Verse hydration requires an entry record.');
        }

        $ordinal = $record['ordinal'] ?? null;
        $key = $record['key'] ?? null;
        $raw = $record['raw'] ?? null;
        $scopeValue = $record['scope'] ?? null;
        $segmentsValue = $record['annotation_segments'] ?? null;
        $attributesValue = $record['official_attributes'] ?? null;
        $available = $record['projections_available'] ?? null;

        if (!is_int($ordinal) || $ordinal < 0 || !is_array($key) || !is_array($raw)) {
            throw new ContractException('Entry ordinal, key, or raw byte value is invalid.');
        }

        if (!is_array($scopeValue) || !is_array($segmentsValue) || !is_array($attributesValue)) {
            throw new ContractException('Entry scope, annotations, or official attributes are invalid.');
        }

        if (!is_bool($available)) {
            throw new ContractException('Entry projections_available must be boolean.');
        }

        $scope = VerseScope::fromArray($scopeValue);

        if (!$scope->isVerse()) {
            throw new ContractException('An introduction cannot be hydrated as an ordinary Verse.');
        }

        $rawValue = ByteValue::fromArray($raw, 'entry.raw');
        $segments = [];
        $reconstructed = '';

        foreach ($segmentsValue as $index => $segment) {
            if (!is_array($segment)) {
                throw new ContractException(sprintf('Annotation segment %d is not an object.', $index));
            }

            $object = AnnotationSegment::fromArray($segment, $index);
            $segments[] = $object;
            $reconstructed .= $object->raw()->bytes();
        }

        if ($reconstructed !== $rawValue->bytes()) {
            throw new ContractException('Annotation segments do not reconstruct entry.raw.');
        }

        $rendered = self::projection($record['rendered_default'] ?? null, 'rendered_default', $available);
        $stripped = self::projection($record['stripped'] ?? null, 'stripped', $available);

        return new self(
            $ordinal,
            ByteValue::fromArray($key, 'entry.key'),
            $scope,
            $rawValue,
            $rendered,
            $stripped,
            $segments,
            OfficialAttributes::fromArray($attributesValue),
            $record,
        );
    }

    /**
     * Returns the native traversal ordinal.
     *
     * @return int
     * @since 0.1.0
     */
    public function ordinal(): int
    {
        return $this->ordinal;
    }

    /**
     * Returns the exact native key.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function key(): ByteValue
    {
        return $this->key;
    }

    /**
     * Returns the exact verse scope.
     *
     * @return VerseScope
     * @since 0.1.0
     */
    public function scope(): VerseScope
    {
        return $this->scope;
    }

    /**
     * Returns authoritative raw entry bytes.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function raw(): ByteValue
    {
        return $this->raw;
    }

    /**
     * Returns the default SWORD rendering when available.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    public function rendered(): ?ByteValue
    {
        return $this->rendered;
    }

    /**
     * Returns the stripped text projection when available.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    public function stripped(): ?ByteValue
    {
        return $this->stripped;
    }

    /**
     * Returns ordered, lossless lexical annotation segments.
     *
     * @return list<AnnotationSegment>
     * @since 0.1.0
     */
    public function annotationSegments(): array
    {
        return $this->segments;
    }

    /**
     * Returns the complete ordered SWORD official attributes.
     *
     * @return OfficialAttributes
     * @since 0.1.0
     */
    public function officialAttributes(): OfficialAttributes
    {
        return $this->attributes;
    }

    /**
     * Returns the original validated contract record.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    public function contractRecord(): array
    {
        return $this->record;
    }

    /**
     * Validates an optional rendered or stripped projection.
     *
     * @param mixed $value Candidate value.
     * @param string $field Field name.
     * @param bool $available Whether projections should exist.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    private static function projection(mixed $value, string $field, bool $available): ?ByteValue
    {
        if (!$available) {
            if ($value !== null) {
                throw new ContractException(sprintf('entry.%s must be null when projections are unavailable.', $field));
            }

            return null;
        }

        if (!is_array($value)) {
            throw new ContractException(sprintf('entry.%s must be a byte value.', $field));
        }

        return ByteValue::fromArray($value, 'entry.' . $field);
    }
}
