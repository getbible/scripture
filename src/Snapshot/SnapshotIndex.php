<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Snapshot;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Domain\ConfigEntry;
use GetBible\Scripture\Domain\Introduction;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Domain\Verse;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Exception\ReferenceNotFoundException;

/**
 * Reads immutable translation objects by exact offset from one snapshot.
 *
 * @since 0.1.0
 */
final class SnapshotIndex
{
    /**
     * Open module stream resource.
     *
     * @var resource|null
     * @since 0.1.0
     */
    private mixed $stream = null;

    /**
     * Creates a verified snapshot reader.
     *
     * @param string $generationPath Generation directory.
     * @param array<string, mixed> $index Decoded index.
     * @param \DateTimeImmutable $activatedAt Activation time.
     * @param \DateTimeImmutable $expiresAt Freshness deadline.
     *
     * @since 0.1.0
     */
    private function __construct(
        private string $generationPath,
        private array $index,
        private \DateTimeImmutable $activatedAt,
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Opens and minimally verifies one immutable generation.
     *
     * @param string $generationPath Generation directory.
     * @param \DateTimeImmutable $activatedAt Activation time.
     * @param \DateTimeImmutable $expiresAt Freshness deadline.
     *
     * @return self
     * @since 0.1.0
     */
    public static function open(
        string $generationPath,
        \DateTimeImmutable $activatedAt,
        \DateTimeImmutable $expiresAt,
    ): self {
        $indexPath = $generationPath . '/index.json';
        $modulePath = $generationPath . '/module.ndjson';
        $json = file_get_contents($indexPath);

        if ($json === false || !is_file($modulePath)) {
            throw new ContractException(sprintf('Snapshot generation "%s" is incomplete.', $generationPath));
        }

        try {
            $index = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ContractException('Snapshot index JSON is invalid.', 0, $exception);
        }

        if (!is_array($index)
            || ($index['format'] ?? null) !== 'getbible.scripture.snapshot/v1'
            || !is_string($index['stream_sha256'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $index['stream_sha256']) !== 1
            || !is_array($index['module'] ?? null)
            || !is_array($index['config_entries'] ?? null)
            || !is_array($index['introductions'] ?? null)
            || !is_array($index['books'] ?? null)
        ) {
            throw new ContractException('Snapshot index structure is invalid.');
        }

        return new self($generationPath, $index, $activatedAt, $expiresAt);
    }

    /**
     * Closes the module stream when the snapshot reader is released.
     *
     * @since 0.1.0
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Returns the validated translation metadata.
     *
     * @return TranslationMetadata
     * @since 0.1.0
     */
    public function metadata(): TranslationMetadata
    {
        return TranslationMetadata::fromRecord($this->index['module']);
    }

    /**
     * Returns all ordered interpreted configuration entries.
     *
     * @return list<ConfigEntry>
     * @since 0.1.0
     */
    public function configEntries(): array
    {
        $entries = [];

        foreach ($this->index['config_entries'] as $record) {
            if (!is_array($record)) {
                throw new ContractException('Snapshot configuration index is invalid.');
            }

            $entries[] = ConfigEntry::fromRecord($record);
        }

        return $entries;
    }

    /**
     * Returns configuration entries with an exact byte name.
     *
     * @param string $name Exact configuration name.
     *
     * @return list<ConfigEntry>
     * @since 0.1.0
     */
    public function configEntriesNamed(string $name): array
    {
        return array_values(array_filter(
            $this->configEntries(),
            static fn (ConfigEntry $entry): bool => $entry->name()->bytes() === $name,
        ));
    }

    /**
     * Returns ordered compound book keys.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function bookKeys(): array
    {
        return array_keys($this->index['books']);
    }

    /**
     * Returns verified book metadata used by lazy Book objects.
     *
     * @param string $bookKey Compound testament/book key.
     *
     * @return array{testament: int, position: int, name: ByteValue, abbreviation: ByteValue, versification: ByteValue}
     * @since 0.1.0
     */
    public function bookMetadata(string $bookKey): array
    {
        $book = $this->bookData($bookKey);

        if (!is_int($book['testament'] ?? null)
            || !is_int($book['position'] ?? null)
            || !is_array($book['name'] ?? null)
            || !is_array($book['abbreviation'] ?? null)
            || !is_array($book['versification'] ?? null)
        ) {
            throw new ContractException(sprintf('Snapshot book "%s" metadata is invalid.', $bookKey));
        }

        return [
            'testament' => $book['testament'],
            'position' => $book['position'],
            'name' => ByteValue::fromArray($book['name'], "books.$bookKey.name"),
            'abbreviation' => ByteValue::fromArray($book['abbreviation'], "books.$bookKey.abbreviation"),
            'versification' => ByteValue::fromArray($book['versification'], "books.$bookKey.versification"),
        ];
    }

    /**
     * Returns ordered chapter numbers for a book.
     *
     * @param string $bookKey Compound testament/book key.
     *
     * @return list<int>
     * @since 0.1.0
     */
    public function chapterNumbers(string $bookKey): array
    {
        $book = $this->bookData($bookKey);

        if (!is_array($book['chapters'] ?? null)) {
            throw new ContractException(sprintf('Snapshot book "%s" chapters are invalid.', $bookKey));
        }

        return array_map('intval', array_keys($book['chapters']));
    }

    /**
     * Returns ordered unique verse numbers for a chapter.
     *
     * @param string $bookKey Compound testament/book key.
     * @param int $chapter Chapter number.
     *
     * @return list<int>
     * @since 0.1.0
     */
    public function verseNumbers(string $bookKey, int $chapter): array
    {
        $chapterData = $this->chapterData($bookKey, $chapter);

        if (!is_array($chapterData['verses'] ?? null)) {
            throw new ContractException('Snapshot chapter verses are invalid.');
        }

        $numbers = [];

        foreach (array_keys($chapterData['verses']) as $key) {
            $parts = explode(':', (string) $key, 2);
            $numbers[] = (int) $parts[0];
        }

        $numbers = array_values(array_unique($numbers));
        sort($numbers, SORT_NUMERIC);

        return $numbers;
    }

    /**
     * Hydrates one verse by exact coordinate.
     *
     * @param string $bookKey Compound testament/book key.
     * @param int $chapter Chapter number.
     * @param int $verse Verse number.
     * @param int $suffix SWORD suffix byte.
     *
     * @return Verse
     * @since 0.1.0
     */
    public function verse(string $bookKey, int $chapter, int $verse, int $suffix = 0): Verse
    {
        $chapterData = $this->chapterData($bookKey, $chapter);
        $verseKey = $verse . ':' . $suffix;
        $location = $chapterData['verses'][$verseKey] ?? null;

        if (!is_array($location)) {
            throw new ReferenceNotFoundException(sprintf(
                'Verse %s %d:%d suffix %d is not present.',
                $bookKey,
                $chapter,
                $verse,
                $suffix,
            ));
        }

        return Verse::fromRecord($this->readRecord($location));
    }

    /**
     * Hydrates an inclusive ordered verse range, retaining suffix variants.
     *
     * @param string $bookKey Compound testament/book key.
     * @param int $chapter Chapter number.
     * @param int|null $start First verse, or null for the first available verse.
     * @param int|null $end Last verse, or null for the last available verse.
     *
     * @return list<Verse>
     * @since 0.1.0
     */
    public function verses(
        string $bookKey,
        int $chapter,
        ?int $start = null,
        ?int $end = null,
    ): array {
        $chapterData = $this->chapterData($bookKey, $chapter);
        $locations = $chapterData['verses'] ?? null;

        if (!is_array($locations)) {
            throw new ContractException('Snapshot chapter verses are invalid.');
        }

        $coordinates = [];

        foreach ($locations as $key => $location) {
            if (!is_string($key) || !is_array($location)) {
                throw new ContractException('Snapshot verse index is invalid.');
            }

            $parts = explode(':', $key, 2);

            if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
                throw new ContractException('Snapshot verse coordinate is invalid.');
            }

            $verse = (int) $parts[0];
            $suffix = (int) $parts[1];

            if (($start !== null && $verse < $start) || ($end !== null && $verse > $end)) {
                continue;
            }

            $coordinates[] = ['verse' => $verse, 'suffix' => $suffix, 'location' => $location];
        }

        usort(
            $coordinates,
            static fn (array $left, array $right): int => [$left['verse'], $left['suffix']]
                <=> [$right['verse'], $right['suffix']],
        );

        $verses = [];

        foreach ($coordinates as $coordinate) {
            $verses[] = Verse::fromRecord($this->readRecord($coordinate['location']));
        }

        return $verses;
    }

    /**
     * Returns introductions attached to a book, chapter, or module.
     *
     * @param string|null $bookKey Optional compound book key.
     * @param int|null $chapter Optional chapter number.
     *
     * @return list<Introduction>
     * @since 0.1.0
     */
    public function introductions(?string $bookKey = null, ?int $chapter = null): array
    {
        if ($bookKey === null) {
            $locations = $this->index['introductions'];
        } elseif ($chapter === null) {
            $locations = $this->bookData($bookKey)['introductions'] ?? null;
        } else {
            $locations = $this->chapterData($bookKey, $chapter)['introductions'] ?? null;
        }

        if (!is_array($locations)) {
            throw new ContractException('Snapshot introduction index is invalid.');
        }

        $introductions = [];

        foreach ($locations as $location) {
            if (!is_array($location)) {
                throw new ContractException('Snapshot introduction location is invalid.');
            }

            $introductions[] = Introduction::fromRecord($this->readRecord($location));
        }

        return $introductions;
    }

    /**
     * Returns the raw validated native export path.
     *
     * @return string
     * @since 0.1.0
     */
    public function rawExportPath(): string
    {
        return $this->generationPath . '/module.ndjson';
    }

    /**
     * Returns the activation time.
     *
     * @return \DateTimeImmutable
     * @since 0.1.0
     */
    public function activatedAt(): \DateTimeImmutable
    {
        return $this->activatedAt;
    }

    /**
     * Returns the freshness deadline.
     *
     * @return \DateTimeImmutable
     * @since 0.1.0
     */
    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Indicates whether the generation is stale at a supplied time.
     *
     * @param \DateTimeImmutable $now Current time.
     *
     * @return bool
     * @since 0.1.0
     */
    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /**
     * Returns one indexed book object.
     *
     * @param string $bookKey Compound testament/book key.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    private function bookData(string $bookKey): array
    {
        $book = $this->index['books'][$bookKey] ?? null;

        if (!is_array($book)) {
            throw new ReferenceNotFoundException(sprintf('Book "%s" is not present.', $bookKey));
        }

        return $book;
    }

    /**
     * Returns one indexed chapter object.
     *
     * @param string $bookKey Compound testament/book key.
     * @param int $chapter Chapter number.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    private function chapterData(string $bookKey, int $chapter): array
    {
        $book = $this->bookData($bookKey);
        $chapterData = $book['chapters'][(string) $chapter] ?? null;

        if (!is_array($chapterData)) {
            throw new ReferenceNotFoundException(sprintf(
                'Chapter %d is not present in book "%s".',
                $chapter,
                $bookKey,
            ));
        }

        return $chapterData;
    }

    /**
     * Reads one exact serialized entry record.
     *
     * @param array<string, mixed> $location Indexed offset and length.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    private function readRecord(array $location): array
    {
        $offset = $location['offset'] ?? null;
        $length = $location['length'] ?? null;

        if (!is_int($offset) || $offset < 0 || !is_int($length) || $length < 2) {
            throw new ContractException('Snapshot record location is invalid.');
        }

        if (!is_resource($this->stream)) {
            $this->stream = fopen($this->rawExportPath(), 'rb');

            if (!is_resource($this->stream)) {
                throw new ContractException('Unable to open the snapshot module stream.');
            }
        }

        if (fseek($this->stream, $offset) !== 0) {
            throw new ContractException('Unable to seek to a snapshot record.');
        }

        $line = fread($this->stream, $length);

        if ($line === false || strlen($line) !== $length || !str_ends_with($line, "\n")) {
            throw new ContractException('Unable to read the complete snapshot record.');
        }

        try {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ContractException('Snapshot record JSON is invalid.', 0, $exception);
        }

        if (!is_array($record) || array_is_list($record) || ($record['type'] ?? null) !== 'entry') {
            throw new ContractException('Snapshot location does not reference an entry record.');
        }

        return $record;
    }
}
