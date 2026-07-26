<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable lexical segment of an entry's authoritative raw bytes.
 *
 * @since 0.1.0
 */
final class AnnotationSegment
{
    /**
     * Creates an annotation segment.
     *
     * @param string $kind Segment kind.
     * @param string $interpretation Producer interpretation.
     * @param ByteValue $raw Exact segment bytes.
     *
     * @since 0.1.0
     */
    private function __construct(
        private string $kind,
        private string $interpretation,
        private ByteValue $raw,
    ) {
    }

    /**
     * Validates and creates a segment from a contract object.
     *
     * @param array<array-key, mixed> $segment Candidate segment.
     * @param int $index Segment index for diagnostics.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromArray(array $segment, int $index): self
    {
        $segment = StructuredData::object(
            $segment,
            sprintf('Annotation segment %d', $index),
        );
        $kind = $segment['kind'] ?? null;
        $interpretation = $segment['interpretation'] ?? null;
        $raw = $segment['raw'] ?? null;

        if (!is_string($kind) || !in_array($kind, ['text', 'markup', 'entity'], true)) {
            throw new ContractException(sprintf('Annotation segment %d has an invalid kind.', $index));
        }

        $expected = $kind === 'text' ? 'not_applicable' : 'uninterpreted';

        if ($interpretation !== $expected) {
            throw new ContractException(sprintf(
                'Annotation segment %d has invalid interpretation "%s".',
                $index,
                is_scalar($interpretation) ? (string) $interpretation : get_debug_type($interpretation),
            ));
        }

        if (!is_array($raw)) {
            throw new ContractException(sprintf('Annotation segment %d has no byte value.', $index));
        }

        return new self($kind, $expected, ByteValue::fromArray($raw, "annotation_segments[$index].raw"));
    }

    /**
     * Returns `text`, `markup`, or `entity`.
     *
     * @return string
     * @since 0.1.0
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * Returns the producer's interpretation classification.
     *
     * @return string
     * @since 0.1.0
     */
    public function interpretation(): string
    {
        return $this->interpretation;
    }

    /**
     * Returns the exact segment bytes.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function raw(): ByteValue
    {
        return $this->raw;
    }
}
