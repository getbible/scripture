<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Snapshot;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Domain\ConfigEntry;
use GetBible\Scripture\Domain\Introduction;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Domain\Verse;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Exception\ReferenceNotFoundException;
use GetBible\Scripture\Infrastructure\Lock\GenerationLease;

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
     * @param string $generation Content-addressed generation identifier.
     * @param string $indexSha256 Verified serialized index digest.
     * @param GenerationLease $lease Active generation reader lease.
     *
     * @since 0.1.0
     */
    private function __construct(
        private string $generationPath,
        private array $index,
        private \DateTimeImmutable $activatedAt,
        private \DateTimeImmutable $expiresAt,
        private string $generation,
        private string $indexSha256,
        private GenerationLease $lease,
    ) {
    }

    /**
     * Opens and minimally verifies one immutable generation.
     *
     * @param string $generationPath Generation directory.
     * @param \DateTimeImmutable $activatedAt Activation time.
     * @param \DateTimeImmutable $expiresAt Freshness deadline.
     * @param string|null $expectedIndexSha256 Optional pointer-bound index digest.
     *
     * @return self
     * @since 0.1.0
     */
    public static function open(
        string $generationPath,
        \DateTimeImmutable $activatedAt,
        \DateTimeImmutable $expiresAt,
        ?string $expectedIndexSha256 = null,
    ): self {
        $generation = basename($generationPath);

        if (
            preg_match('/^[0-9a-f]{64}$/D', $generation) !== 1
            || !is_dir($generationPath)
            || is_link($generationPath)
            || $expiresAt <= $activatedAt
            || (
                $expectedIndexSha256 !== null
                && preg_match('/^[0-9a-f]{64}$/D', $expectedIndexSha256) !== 1
            )
        ) {
            throw new ContractException('Snapshot generation identity or activation interval is invalid.');
        }

        $lease = new GenerationLease(dirname($generationPath, 2), $generation);
        $indexPath = $generationPath . '/index.json';
        $modulePath = $generationPath . '/module.ndjson';

        if (
            !is_file($indexPath)
            || is_link($indexPath)
            || !is_file($modulePath)
            || is_link($modulePath)
        ) {
            throw new ContractException(sprintf('Snapshot generation "%s" is incomplete.', $generationPath));
        }

        $json = file_get_contents($indexPath);

        if (!is_string($json)) {
            throw new ContractException(sprintf('Snapshot generation "%s" is unreadable.', $generationPath));
        }

        $indexSha256 = hash('sha256', $json);

        if ($expectedIndexSha256 !== null && !hash_equals($expectedIndexSha256, $indexSha256)) {
            throw new ContractException('Snapshot index digest does not match its active pointer.');
        }

        try {
            $index = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ContractException('Snapshot index JSON is invalid.', 0, $exception);
        }

        $index = StructuredData::object($index, 'Snapshot index');

        if (
            ($index['format'] ?? null) !== 'getbible.scripture.snapshot/v1'
            || !is_string($index['stream_sha256'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $index['stream_sha256']) !== 1
            || !hash_equals($generation, $index['stream_sha256'])
            || !is_int($index['module_file_size'] ?? null)
            || $index['module_file_size'] < 1
            || !is_string($index['module_file_sha256'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $index['module_file_sha256']) !== 1
        ) {
            throw new ContractException('Snapshot index structure is invalid.');
        }

        $moduleSize = filesize($modulePath);
        $moduleSha256 = hash_file('sha256', $modulePath);

        if (
            !is_int($moduleSize)
            || $moduleSize !== $index['module_file_size']
            || !is_string($moduleSha256)
            || !hash_equals($index['module_file_sha256'], $moduleSha256)
        ) {
            throw new ContractException('Snapshot module stream does not match its integrity manifest.');
        }

        StructuredData::object($index['module'] ?? null, 'Snapshot module metadata');
        StructuredData::list($index['config_entries'] ?? null, 'Snapshot configuration entries');
        StructuredData::list($index['introductions'] ?? null, 'Snapshot introductions');
        StructuredData::object($index['books'] ?? null, 'Snapshot books');

        return new self(
            $generationPath,
            $index,
            $activatedAt,
            $expiresAt,
            $generation,
            $indexSha256,
            $lease,
        );
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

        if (isset($this->lease)) {
            $this->lease->release();
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
        return TranslationMetadata::fromRecord(
            StructuredData::object($this->index['module'] ?? null, 'Snapshot module metadata'),
        );
    }

    /**
     * Returns the content-addressed generation identifier.
     *
     * @return string
     * @since 1.0.0
     */
    public function generationId(): string
    {
        return $this->generation;
    }

    /**
     * Returns the verified serialized index digest.
     *
     * @return string
     * @since 1.0.0
     */
    public function indexSha256(): string
    {
        return $this->indexSha256;
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

        foreach (
            StructuredData::list(
                $this->index['config_entries'] ?? null,
                'Snapshot configuration entries',
            ) as $index => $record
        ) {
            $entries[] = ConfigEntry::fromRecord(
                StructuredData::object($record, sprintf('Snapshot configuration entry %d', $index)),
            );
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
        return array_keys(StructuredData::object($this->index['books'] ?? null, 'Snapshot books'));
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

        if (
            !is_int($book['testament'] ?? null)
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
        $chapters = StructuredData::map(
            $book['chapters'] ?? null,
            sprintf('Snapshot book "%s" chapters', $bookKey),
        );

        return array_map('intval', array_keys($chapters));
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
        $verses = StructuredData::object($chapterData['verses'] ?? null, 'Snapshot chapter verses');

        $numbers = [];

        foreach (array_keys($verses) as $key) {
            $parts = explode(':', $key, 2);
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
        $locations = StructuredData::object($chapterData['verses'] ?? null, 'Snapshot chapter verses');
        $location = $locations[$verseKey] ?? null;

        if (!is_array($location)) {
            throw new ReferenceNotFoundException(sprintf(
                'Verse %s %d:%d suffix %d is not present.',
                $bookKey,
                $chapter,
                $verse,
                $suffix,
            ));
        }

        $object = Verse::fromRecord($this->readRecord($location));
        $scope = $object->scope();
        [$testament, $book] = $this->bookCoordinates($bookKey);

        if (
            $scope->testament() !== $testament
            || $scope->book() !== $book
            || $scope->chapter() !== $chapter
            || $scope->verse() !== $verse
            || $scope->suffix() !== $suffix
        ) {
            throw new ContractException('Snapshot verse location does not match its indexed coordinate.');
        }

        return $object;
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
        $locations = StructuredData::object($chapterData['verses'] ?? null, 'Snapshot chapter verses');

        $coordinates = [];

        foreach ($locations as $key => $location) {
            if (!is_array($location)) {
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
            $object = Verse::fromRecord($this->readRecord($coordinate['location']));
            $scope = $object->scope();
            [$testament, $book] = $this->bookCoordinates($bookKey);

            if (
                $scope->testament() !== $testament
                || $scope->book() !== $book
                || $scope->chapter() !== $chapter
                || $scope->verse() !== $coordinate['verse']
                || $scope->suffix() !== $coordinate['suffix']
            ) {
                throw new ContractException('Snapshot verse range location does not match its indexed coordinate.');
            }

            $verses[] = $object;
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
            $locations = StructuredData::list(
                $this->index['introductions'] ?? null,
                'Snapshot module introductions',
            );
        } elseif ($chapter === null) {
            $locations = StructuredData::list(
                $this->bookData($bookKey)['introductions'] ?? null,
                'Snapshot book introductions',
            );
        } else {
            $locations = StructuredData::list(
                $this->chapterData($bookKey, $chapter)['introductions'] ?? null,
                'Snapshot chapter introductions',
            );
        }

        $introductions = [];

        foreach ($locations as $location) {
            if (!is_array($location)) {
                throw new ContractException('Snapshot introduction location is invalid.');
            }

            $introduction = Introduction::fromRecord($this->readRecord($location));
            $scope = $introduction->scope();

            if ($bookKey === null) {
                $matches = $scope->book() === 0;
            } else {
                [$testament, $book] = $this->bookCoordinates($bookKey);
                $matches = $scope->testament() === $testament
                    && $scope->book() === $book
                    && (
                        ($chapter === null && $scope->chapter() === 0)
                        || (
                            $chapter !== null
                            && $scope->chapter() === $chapter
                            && $scope->verse() === 0
                        )
                    );
            }

            if (!$matches) {
                throw new ContractException(
                    'Snapshot introduction location does not match its indexed coordinate.',
                );
            }

            $introductions[] = $introduction;
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
        $books = StructuredData::object($this->index['books'] ?? null, 'Snapshot books');
        $book = $books[$bookKey] ?? null;

        if (!is_array($book)) {
            throw new ReferenceNotFoundException(sprintf('Book "%s" is not present.', $bookKey));
        }

        return StructuredData::object($book, sprintf('Snapshot book "%s"', $bookKey));
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
        $chapters = StructuredData::map(
            $book['chapters'] ?? null,
            sprintf('Snapshot book "%s" chapters', $bookKey),
        );
        $chapterData = $chapters[(string) $chapter] ?? null;

        if (!is_array($chapterData)) {
            throw new ReferenceNotFoundException(sprintf(
                'Chapter %d is not present in book "%s".',
                $chapter,
                $bookKey,
            ));
        }

        return StructuredData::object(
            $chapterData,
            sprintf('Snapshot book "%s" chapter %d', $bookKey, $chapter),
        );
    }

    /**
     * Reads one exact serialized entry record.
     *
     * @param array<array-key, mixed> $location Indexed offset and length.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    private function readRecord(array $location): array
    {
        $location = StructuredData::object($location, 'Snapshot record location');
        $offset = $location['offset'] ?? null;
        $length = $location['length'] ?? null;
        $sha256 = $location['sha256'] ?? null;

        if (
            !is_int($offset)
            || $offset < 0
            || !is_int($length)
            || $length < 2
            || !is_string($sha256)
            || preg_match('/^[0-9a-f]{64}$/D', $sha256) !== 1
        ) {
            throw new ContractException('Snapshot record location is invalid.');
        }

        if (!is_resource($this->stream)) {
            $stream = fopen($this->rawExportPath(), 'rb');

            if (!is_resource($stream)) {
                throw new ContractException('Unable to open the snapshot module stream.');
            }

            $this->stream = $stream;
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

        $record = StructuredData::object($record, 'Snapshot entry record');

        if (($record['type'] ?? null) !== 'entry') {
            throw new ContractException('Snapshot location does not reference an entry record.');
        }

        try {
            $canonical = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new ContractException('Snapshot record cannot be integrity-checked.', 0, $exception);
        }

        if (!hash_equals($sha256, hash('sha256', $canonical))) {
            throw new ContractException('Snapshot record does not match its indexed digest.');
        }

        return $record;
    }

    /**
     * Parses a validated compound book key.
     *
     * @param string $bookKey Compound testament/book key.
     *
     * @return array{int, int}
     * @since 1.0.0
     */
    private function bookCoordinates(string $bookKey): array
    {
        $parts = explode(':', $bookKey, 2);

        if (
            count($parts) !== 2
            || preg_match('/^[1-9][0-9]*$/D', $parts[0]) !== 1
            || preg_match('/^[1-9][0-9]*$/D', $parts[1]) !== 1
        ) {
            throw new ContractException('Snapshot book key is invalid.');
        }

        return [(int) $parts[0], (int) $parts[1]];
    }
}
