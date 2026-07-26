<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Domain;

use GetBible\Scripture\Exception\ReferenceNotFoundException;
use GetBible\Scripture\Module\ModuleIdentifier;
use GetBible\Scripture\Snapshot\SnapshotIndex;

/**
 * Immutable lazy view of one complete installed Bible translation.
 *
 * @since 0.1.0
 */
final class Translation
{
    /**
     * Creates a translation over one immutable snapshot.
     *
     * @param string $moduleName Exact native module identifier.
     * @param SnapshotIndex $snapshot Active immutable snapshot.
     *
     * @since 0.1.0
     */
    public function __construct(
        private string $moduleName,
        private SnapshotIndex $snapshot,
    ) {
        $this->moduleName = ModuleIdentifier::normalize($moduleName);

        if ($snapshot->metadata()->name()->bytes() !== $this->moduleName) {
            throw new \InvalidArgumentException(
                'A Translation module identifier must match its snapshot metadata.',
            );
        }
    }

    /**
     * Returns the exact native module identifier.
     *
     * @return string
     * @since 0.1.0
     */
    public function moduleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Returns the translation's native metadata.
     *
     * @return TranslationMetadata
     * @since 0.1.0
     */
    public function metadata(): TranslationMetadata
    {
        return $this->snapshot->metadata();
    }

    /**
     * Returns ordered lazy book objects.
     *
     * @return list<Book>
     * @since 0.1.0
     */
    public function books(): array
    {
        return array_map(
            fn (string $key): Book => new Book($this, $this->snapshot, $key),
            $this->snapshot->bookKeys(),
        );
    }

    /**
     * Resolves a book by exact or ASCII-case-insensitive name/abbreviation.
     *
     * @param string $identifier Book name or abbreviation.
     *
     * @return Book
     * @since 0.1.0
     */
    public function book(string $identifier): Book
    {
        foreach ($this->books() as $book) {
            $name = $book->name()->bytes();
            $abbreviation = $book->abbreviation()->bytes();

            if (
                $identifier === $name
                || $identifier === $abbreviation
                || strcasecmp($identifier, $name) === 0
                || strcasecmp($identifier, $abbreviation) === 0
            ) {
                return $book;
            }
        }

        throw new ReferenceNotFoundException(sprintf(
            'Book "%s" is not present in translation "%s".',
            $identifier,
            $this->moduleName,
        ));
    }

    /**
     * Resolves a book by module-versification testament and position.
     *
     * @param int $testament SWORD testament position.
     * @param int $book SWORD book position.
     *
     * @return Book
     * @since 0.1.0
     */
    public function bookByPosition(int $testament, int $book): Book
    {
        $key = $testament . ':' . $book;

        if (!in_array($key, $this->snapshot->bookKeys(), true)) {
            throw new ReferenceNotFoundException(sprintf(
                'Book testament %d position %d is not present in translation "%s".',
                $testament,
                $book,
                $this->moduleName,
            ));
        }

        return new Book($this, $this->snapshot, $key);
    }

    /**
     * Returns an inclusive verse range from a named book and chapter.
     *
     * @param string $book Book name or abbreviation.
     * @param int $chapter Chapter number.
     * @param int $start First verse.
     * @param int $end Last verse.
     *
     * @return list<Verse>
     * @since 0.1.0
     */
    public function verses(string $book, int $chapter, int $start, int $end): array
    {
        return $this->book($book)->chapter($chapter)->verses($start, $end);
    }

    /**
     * Returns all ordered interpreted configuration entries.
     *
     * @return list<ConfigEntry>
     * @since 0.1.0
     */
    public function configEntries(): array
    {
        return $this->snapshot->configEntries();
    }

    /**
     * Returns configuration entries matching one exact name.
     *
     * @param string $name Exact configuration name.
     *
     * @return list<ConfigEntry>
     * @since 0.1.0
     */
    public function configEntriesNamed(string $name): array
    {
        return $this->snapshot->configEntriesNamed($name);
    }

    /**
     * Returns module- and testament-level introductions.
     *
     * @return list<Introduction>
     * @since 0.1.0
     */
    public function introductions(): array
    {
        return $this->snapshot->introductions();
    }

    /**
     * Returns the complete validated native export path.
     *
     * @return string
     * @since 0.1.0
     */
    public function rawExportPath(): string
    {
        return $this->snapshot->rawExportPath();
    }

    /**
     * Returns the snapshot activation time.
     *
     * @return \DateTimeImmutable
     * @since 0.1.0
     */
    public function activatedAt(): \DateTimeImmutable
    {
        return $this->snapshot->activatedAt();
    }

    /**
     * Returns the snapshot freshness deadline.
     *
     * @return \DateTimeImmutable
     * @since 0.1.0
     */
    public function expiresAt(): \DateTimeImmutable
    {
        return $this->snapshot->expiresAt();
    }

    /**
     * Returns the immutable content-addressed snapshot generation identifier.
     *
     * @return string
     * @since 1.0.0
     */
    public function generationId(): string
    {
        return $this->snapshot->generationId();
    }
}
