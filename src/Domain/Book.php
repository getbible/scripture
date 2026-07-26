<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Exception\InvalidReferenceException;
use GetBible\Scripture\Exception\ReferenceNotFoundException;
use GetBible\Scripture\Snapshot\SnapshotIndex;

/**
 * Immutable lazy book view within a translation snapshot.
 *
 * @since 0.1.0
 */
final class Book
{
    /**
     * Verified book metadata.
     *
     * @var array{testament: int, position: int, name: ByteValue, abbreviation: ByteValue, versification: ByteValue}
     * @since 0.1.0
     */
    private array $metadata;

    /**
     * Creates a lazy book.
     *
     * @param Translation $translation Parent translation.
     * @param SnapshotIndex $snapshot Immutable snapshot reader.
     * @param string $bookKey Compound testament/book key.
     *
     * @since 0.1.0
     */
    public function __construct(
        private Translation $translation,
        private SnapshotIndex $snapshot,
        private string $bookKey,
    ) {
        $this->metadata = $snapshot->bookMetadata($bookKey);
    }

    /**
     * Returns the parent translation.
     *
     * @return Translation
     * @since 0.1.0
     */
    public function translation(): Translation
    {
        return $this->translation;
    }

    /**
     * Returns the SWORD testament position.
     *
     * @return int
     * @since 0.1.0
     */
    public function testament(): int
    {
        return $this->metadata['testament'];
    }

    /**
     * Returns the SWORD book position in its versification/testament.
     *
     * @return int
     * @since 0.1.0
     */
    public function position(): int
    {
        return $this->metadata['position'];
    }

    /**
     * Returns the exact book name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function name(): ByteValue
    {
        return $this->metadata['name'];
    }

    /**
     * Returns the exact book abbreviation.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function abbreviation(): ByteValue
    {
        return $this->metadata['abbreviation'];
    }

    /**
     * Returns the exact versification identifier.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function versification(): ByteValue
    {
        return $this->metadata['versification'];
    }

    /**
     * Returns ordered lazy chapter objects.
     *
     * @return list<Chapter>
     * @since 0.1.0
     */
    public function chapters(): array
    {
        return array_map(
            fn (int $number): Chapter => new Chapter($this, $this->snapshot, $this->bookKey, $number),
            $this->snapshot->chapterNumbers($this->bookKey),
        );
    }

    /**
     * Returns one chapter.
     *
     * @param int $number Positive chapter number.
     *
     * @return Chapter
     * @since 0.1.0
     */
    public function chapter(int $number): Chapter
    {
        if ($number < 1) {
            throw new InvalidReferenceException('Chapter numbers must be positive.');
        }

        if (!in_array($number, $this->snapshot->chapterNumbers($this->bookKey), true)) {
            throw new ReferenceNotFoundException(sprintf(
                'Chapter %d is not present in book "%s".',
                $number,
                $this->name()->utf8() ?? $this->abbreviation()->bytes(),
            ));
        }

        return new Chapter($this, $this->snapshot, $this->bookKey, $number);
    }

    /**
     * Returns book-level introductions.
     *
     * @return list<Introduction>
     * @since 0.1.0
     */
    public function introductions(): array
    {
        return $this->snapshot->introductions($this->bookKey);
    }
}
