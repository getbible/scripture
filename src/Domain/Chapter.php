<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Exception\InvalidReferenceException;
use GetBible\Scripture\Snapshot\SnapshotIndex;

/**
 * Immutable lazy chapter view within a book.
 *
 * @since 0.1.0
 */
final class Chapter
{
    /**
     * Creates a lazy chapter.
     *
     * @param Book $book Parent book.
     * @param SnapshotIndex $snapshot Immutable snapshot reader.
     * @param string $bookKey Compound testament/book key.
     * @param int $number Positive chapter number.
     *
     * @since 0.1.0
     */
    public function __construct(
        private Book $book,
        private SnapshotIndex $snapshot,
        private string $bookKey,
        private int $number,
    ) {
    }

    /**
     * Returns the parent book.
     *
     * @return Book
     * @since 0.1.0
     */
    public function book(): Book
    {
        return $this->book;
    }

    /**
     * Returns the chapter number.
     *
     * @return int
     * @since 0.1.0
     */
    public function number(): int
    {
        return $this->number;
    }

    /**
     * Returns one exact verse and optional suffix.
     *
     * @param int $number Positive verse number.
     * @param int $suffix SWORD suffix byte.
     *
     * @return Verse
     * @since 0.1.0
     */
    public function verse(int $number, int $suffix = 0): Verse
    {
        if ($number < 1 || $suffix < 0 || $suffix > 255) {
            throw new InvalidReferenceException('Verse number or suffix is invalid.');
        }

        return $this->snapshot->verse($this->bookKey, $this->number, $number, $suffix);
    }

    /**
     * Returns all verses or an inclusive range, ordered by verse and suffix.
     *
     * @param int|null $start Optional first verse.
     * @param int|null $end Optional last verse.
     *
     * @return list<Verse>
     * @since 0.1.0
     */
    public function verses(?int $start = null, ?int $end = null): array
    {
        if (($start !== null && $start < 1)
            || ($end !== null && $end < 1)
            || ($start !== null && $end !== null && $end < $start)
        ) {
            throw new InvalidReferenceException('Verse range must be positive and ascending.');
        }

        return $this->snapshot->verses($this->bookKey, $this->number, $start, $end);
    }

    /**
     * Returns chapter-level introductions.
     *
     * @return list<Introduction>
     * @since 0.1.0
     */
    public function introductions(): array
    {
        return $this->snapshot->introductions($this->bookKey, $this->number);
    }
}
