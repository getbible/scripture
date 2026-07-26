<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Contract\AnnotationSegment;
use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\OfficialAttributes;
use GetBible\Scripture\Contract\VerseScope;
use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable module, testament, book, or chapter introduction entry.
 *
 * @since 0.1.0
 */
final class Introduction
{
    /**
     * Creates a complete introduction.
     *
     * @param VerseScope $scope Introduction scope.
     * @param ByteValue $key Exact native key.
     * @param ByteValue $raw Authoritative raw bytes.
     * @param ByteValue|null $rendered Default SWORD rendering.
     * @param ByteValue|null $stripped Stripped projection.
     * @param list<AnnotationSegment> $segments Ordered lexical segments.
     * @param OfficialAttributes $attributes Ordered official attributes.
     * @param array<string, mixed> $record Original record.
     *
     * @since 0.1.0
     */
    private function __construct(
        private VerseScope $scope,
        private ByteValue $key,
        private ByteValue $raw,
        private ?ByteValue $rendered,
        private ?ByteValue $stripped,
        private array $segments,
        private OfficialAttributes $attributes,
        private array $record,
    ) {
    }

    /**
     * Validates and hydrates an introduction from an entry record.
     *
     * @param array<string, mixed> $record Entry record.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromRecord(array $record): self
    {
        if (($record['type'] ?? null) !== 'entry'
            || !is_array($record['scope'] ?? null)
            || !is_array($record['key'] ?? null)
            || !is_array($record['raw'] ?? null)
            || !is_array($record['annotation_segments'] ?? null)
            || !is_array($record['official_attributes'] ?? null)
            || !is_bool($record['projections_available'] ?? null)
        ) {
            throw new ContractException('Introduction record is invalid.');
        }

        $scope = VerseScope::fromArray($record['scope']);

        if ($scope->isVerse()) {
            throw new ContractException('An ordinary verse cannot be hydrated as an Introduction.');
        }

        $raw = ByteValue::fromArray($record['raw'], 'entry.raw');
        $segments = [];
        $reconstructed = '';

        foreach ($record['annotation_segments'] as $index => $segment) {
            if (!is_array($segment)) {
                throw new ContractException(sprintf('Annotation segment %d is invalid.', $index));
            }

            $object = AnnotationSegment::fromArray($segment, $index);
            $segments[] = $object;
            $reconstructed .= $object->raw()->bytes();
        }

        if ($reconstructed !== $raw->bytes()) {
            throw new ContractException('Introduction annotation segments do not reconstruct entry.raw.');
        }

        $available = $record['projections_available'];

        return new self(
            $scope,
            ByteValue::fromArray($record['key'], 'entry.key'),
            $raw,
            self::projection($record['rendered_default'] ?? null, 'rendered_default', $available),
            self::projection($record['stripped'] ?? null, 'stripped', $available),
            $segments,
            OfficialAttributes::fromArray($record['official_attributes']),
            $record,
        );
    }

    /**
     * Returns the exact introduction scope.
     *
     * @return VerseScope
     * @since 0.1.0
     */
    public function scope(): VerseScope
    {
        return $this->scope;
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
     * Returns authoritative raw bytes.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function raw(): ByteValue
    {
        return $this->raw;
    }

    /**
     * Returns default-rendered bytes when available.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    public function rendered(): ?ByteValue
    {
        return $this->rendered;
    }

    /**
     * Returns stripped bytes when available.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    public function stripped(): ?ByteValue
    {
        return $this->stripped;
    }

    /**
     * Returns ordered lexical annotation segments.
     *
     * @return list<AnnotationSegment>
     * @since 0.1.0
     */
    public function annotationSegments(): array
    {
        return $this->segments;
    }

    /**
     * Returns complete ordered official attributes.
     *
     * @return OfficialAttributes
     * @since 0.1.0
     */
    public function officialAttributes(): OfficialAttributes
    {
        return $this->attributes;
    }

    /**
     * Returns the original validated record.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    public function contractRecord(): array
    {
        return $this->record;
    }

    /**
     * Validates an optional projection.
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
                throw new ContractException(sprintf('entry.%s must be null.', $field));
            }

            return null;
        }

        if (!is_array($value)) {
            throw new ContractException(sprintf('entry.%s must be a byte value.', $field));
        }

        return ByteValue::fromArray($value, 'entry.' . $field);
    }
}
