<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable, module-versification-specific scope of a Bible entry.
 *
 * @since 0.1.0
 */
final class VerseScope
{
    /**
     * Creates a fully validated verse-key scope.
     *
     * @param int $testament SWORD testament position.
     * @param int $book SWORD book position.
     * @param int $chapter Chapter position.
     * @param int $verse Verse position.
     * @param int $suffix SWORD suffix byte.
     * @param int $index Active key index.
     * @param string $introductionScope Introduction classification.
     * @param ByteValue|null $bookAbbreviation Exact book abbreviation.
     * @param ByteValue|null $bookName Exact book name.
     * @param ByteValue $osisReference Exact OSIS reference.
     * @param ByteValue $versification Exact versification name.
     *
     * @since 0.1.0
     */
    private function __construct(
        private int $testament,
        private int $book,
        private int $chapter,
        private int $verse,
        private int $suffix,
        private int $index,
        private string $introductionScope,
        private ?ByteValue $bookAbbreviation,
        private ?ByteValue $bookName,
        private ByteValue $osisReference,
        private ByteValue $versification,
    ) {
    }

    /**
     * Validates and creates a verse-key scope from a contract object.
     *
     * @param array<array-key, mixed> $scope Candidate scope.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromArray(array $scope): self
    {
        $scope = StructuredData::object($scope, 'Verse scope');

        if (($scope['type'] ?? null) !== 'verse_key') {
            throw new ContractException('A Bible entry requires scope.type="verse_key".');
        }

        foreach (['testament', 'book', 'chapter', 'verse', 'suffix', 'index'] as $field) {
            if (!isset($scope[$field]) || !is_int($scope[$field]) || $scope[$field] < 0) {
                throw new ContractException(sprintf('Verse scope field "%s" is invalid.', $field));
            }
        }

        if ($scope['suffix'] > 255) {
            throw new ContractException('Verse scope suffix exceeds an unsigned byte.');
        }

        $intro = $scope['intro_scope'] ?? null;

        if (!is_string($intro) || !in_array($intro, ['module', 'testament', 'book', 'chapter', 'verse'], true)) {
            throw new ContractException('Verse scope introduction classification is invalid.');
        }

        $bookAbbreviation = self::optionalByteValue($scope['book_abbreviation'] ?? null, 'book_abbreviation');
        $bookName = self::optionalByteValue($scope['book_name'] ?? null, 'book_name');
        $osis = self::requiredByteValue($scope['osis_reference'] ?? null, 'osis_reference');
        $versification = self::requiredByteValue($scope['versification'] ?? null, 'versification');

        self::validateCoordinates(
            $scope['testament'],
            $scope['book'],
            $scope['chapter'],
            $scope['verse'],
            $intro,
            $bookAbbreviation,
            $bookName,
        );

        return new self(
            $scope['testament'],
            $scope['book'],
            $scope['chapter'],
            $scope['verse'],
            $scope['suffix'],
            $scope['index'],
            $intro,
            $bookAbbreviation,
            $bookName,
            $osis,
            $versification,
        );
    }

    /**
     * Returns the SWORD testament position.
     *
     * @return int
     * @since 0.1.0
     */
    public function testament(): int
    {
        return $this->testament;
    }

    /**
     * Returns the SWORD book position within the versification/testament.
     *
     * @return int
     * @since 0.1.0
     */
    public function book(): int
    {
        return $this->book;
    }

    /**
     * Returns the chapter position.
     *
     * @return int
     * @since 0.1.0
     */
    public function chapter(): int
    {
        return $this->chapter;
    }

    /**
     * Returns the verse position.
     *
     * @return int
     * @since 0.1.0
     */
    public function verse(): int
    {
        return $this->verse;
    }

    /**
     * Returns the SWORD verse suffix byte.
     *
     * @return int
     * @since 0.1.0
     */
    public function suffix(): int
    {
        return $this->suffix;
    }

    /**
     * Returns the active SWORD key index.
     *
     * @return int
     * @since 0.1.0
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Returns the introduction classification.
     *
     * @return string
     * @since 0.1.0
     */
    public function introductionScope(): string
    {
        return $this->introductionScope;
    }

    /**
     * Indicates whether the entry is an ordinary verse.
     *
     * @return bool
     * @since 0.1.0
     */
    public function isVerse(): bool
    {
        return $this->introductionScope === 'verse'
            && $this->book > 0
            && $this->chapter > 0
            && $this->verse > 0;
    }

    /**
     * Returns the exact book abbreviation when applicable.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    public function bookAbbreviation(): ?ByteValue
    {
        return $this->bookAbbreviation;
    }

    /**
     * Returns the exact book name when applicable.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    public function bookName(): ?ByteValue
    {
        return $this->bookName;
    }

    /**
     * Returns the exact OSIS reference.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function osisReference(): ByteValue
    {
        return $this->osisReference;
    }

    /**
     * Returns the exact versification identifier.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    public function versification(): ByteValue
    {
        return $this->versification;
    }

    /**
     * Validates a required byte value.
     *
     * @param mixed $value Candidate value.
     * @param string $field Field name.
     *
     * @return ByteValue
     * @since 0.1.0
     */
    private static function requiredByteValue(mixed $value, string $field): ByteValue
    {
        if (!is_array($value)) {
            throw new ContractException(sprintf('Verse scope field "%s" is not a byte value.', $field));
        }

        return ByteValue::fromArray($value, 'scope.' . $field);
    }

    /**
     * Validates an optional byte value.
     *
     * @param mixed $value Candidate value.
     * @param string $field Field name.
     *
     * @return ByteValue|null
     * @since 0.1.0
     */
    private static function optionalByteValue(mixed $value, string $field): ?ByteValue
    {
        if ($value === null) {
            return null;
        }

        return self::requiredByteValue($value, $field);
    }

    /**
     * Requires coordinates to agree with the producer's introduction scope.
     *
     * @param int $testament Testament position.
     * @param int $book Book position.
     * @param int $chapter Chapter position.
     * @param int $verse Verse position.
     * @param string $introductionScope Introduction classification.
     * @param ByteValue|null $bookAbbreviation Optional book abbreviation.
     * @param ByteValue|null $bookName Optional book name.
     *
     * @return void
     * @since 1.0.0
     */
    private static function validateCoordinates(
        int $testament,
        int $book,
        int $chapter,
        int $verse,
        string $introductionScope,
        ?ByteValue $bookAbbreviation,
        ?ByteValue $bookName,
    ): void {
        $valid = match ($introductionScope) {
            'module' => $testament === 0 && $book === 0 && $chapter === 0 && $verse === 0,
            'testament' => $testament > 0 && $book === 0 && $chapter === 0 && $verse === 0,
            'book' => $testament > 0 && $book > 0 && $chapter === 0 && $verse === 0,
            'chapter' => $testament > 0 && $book > 0 && $chapter > 0 && $verse === 0,
            'verse' => $testament > 0 && $book > 0 && $chapter > 0 && $verse > 0,
            default => false,
        };

        if (!$valid) {
            throw new ContractException('Verse scope coordinates do not agree with intro_scope.');
        }

        if (
            $book > 0
            && (
                $bookAbbreviation === null
                || $bookName === null
                || $bookAbbreviation->bytes() === ''
                || $bookName->bytes() === ''
            )
        ) {
            throw new ContractException('A book-scoped verse key requires book name and abbreviation values.');
        }
    }
}
