<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Snapshot;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\RecordObserverInterface;
use GetBible\Scripture\Contract\VerseScope;
use GetBible\Scripture\Domain\ConfigEntry;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Exception\ContractException;

/**
 * Builds the compact random-access index while records are being validated.
 *
 * @phpstan-type RecordLocation array{offset: int, length: int}
 * @phpstan-type ChapterIndex array{
 *     number: int,
 *     introductions: list<RecordLocation>,
 *     verses: array<string, RecordLocation>
 * }
 * @phpstan-type BookIndex array{
 *     testament: int,
 *     position: int,
 *     name: array{base64: string, encoding: string, sha256: string, size: int, utf8?: string},
 *     abbreviation: array{base64: string, encoding: string, sha256: string, size: int, utf8?: string},
 *     versification: array{base64: string, encoding: string, sha256: string, size: int, utf8?: string},
 *     introductions: list<RecordLocation>,
 *     chapters: array<array-key, ChapterIndex>
 * }
 *
 * @since 0.1.0
 */
final class SnapshotRecordObserver implements RecordObserverInterface
{
    /**
     * Validated module record.
     *
     * @var array<string, mixed>|null
     * @since 0.1.0
     */
    private ?array $module = null;

    /**
     * Ordered configuration entry records.
     *
     * @var list<array<string, mixed>>
     * @since 0.1.0
     */
    private array $configEntries = [];

    /**
     * Indexed books and chapters.
     *
     * @var array<string, BookIndex>
     * @since 0.1.0
     */
    private array $books = [];

    /**
     * Module and testament introduction record locations.
     *
     * @var list<RecordLocation>
     * @since 0.1.0
     */
    private array $introductions = [];

    /**
     * Observes a validated record and retains only query index data.
     *
     * @param array<string, mixed> $record Decoded record.
     * @param int $offset Serialized line offset.
     * @param int $length Serialized line length including LF.
     *
     * @return void
     * @since 0.1.0
     */
    public function onRecord(array $record, int $offset, int $length): void
    {
        $type = $record['type'];

        if ($type === 'module') {
            $metadata = TranslationMetadata::fromRecord($record);

            if (!$metadata->isBible()) {
                throw new ContractException('Scripture snapshots accept only classification="bible".');
            }

            $this->module = $record;

            return;
        }

        if ($type === 'config_entry') {
            ConfigEntry::fromRecord($record);
            $this->configEntries[] = $record;

            return;
        }

        if ($type !== 'entry') {
            return;
        }

        if (!is_array($record['scope'] ?? null)) {
            throw new ContractException('A Bible snapshot entry has no verse-key scope.');
        }

        $scope = VerseScope::fromArray($record['scope']);
        $location = ['offset' => $offset, 'length' => $length];

        if ($scope->book() === 0) {
            $this->introductions[] = $location;

            return;
        }

        $bookKey = $scope->testament() . ':' . $scope->book();

        if (!isset($this->books[$bookKey])) {
            $name = $scope->bookName();
            $abbreviation = $scope->bookAbbreviation();

            if ($name === null || $abbreviation === null) {
                throw new ContractException('A scoped book entry is missing its name or abbreviation.');
            }

            $this->books[$bookKey] = [
                'testament' => $scope->testament(),
                'position' => $scope->book(),
                'name' => $name->toArray(),
                'abbreviation' => $abbreviation->toArray(),
                'versification' => $scope->versification()->toArray(),
                'introductions' => [],
                'chapters' => [],
            ];
        }

        if ($scope->chapter() === 0) {
            $this->books[$bookKey]['introductions'][] = $location;

            return;
        }

        $chapterKey = (string) $scope->chapter();

        if (!isset($this->books[$bookKey]['chapters'][$chapterKey])) {
            $this->books[$bookKey]['chapters'][$chapterKey] = [
                'number' => $scope->chapter(),
                'introductions' => [],
                'verses' => [],
            ];
        }

        if (!$scope->isVerse()) {
            $this->books[$bookKey]['chapters'][$chapterKey]['introductions'][] = $location;

            return;
        }

        $verseKey = $scope->verse() . ':' . $scope->suffix();

        if (isset($this->books[$bookKey]['chapters'][$chapterKey]['verses'][$verseKey])) {
            throw new ContractException(sprintf(
                'Duplicate verse coordinate %s/%s/%s.',
                $bookKey,
                $chapterKey,
                $verseKey,
            ));
        }

        $this->books[$bookKey]['chapters'][$chapterKey]['verses'][$verseKey] = $location;
    }

    /**
     * Creates the final serializable snapshot index.
     *
     * @param string $streamSha256 Verified native stream digest.
     * @param \DateTimeImmutable $generatedAt Snapshot generation time.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    public function index(string $streamSha256, \DateTimeImmutable $generatedAt): array
    {
        if ($this->module === null) {
            throw new ContractException('A Scripture snapshot requires one Bible module record.');
        }

        return [
            'format' => 'getbible.scripture.snapshot/v1',
            'stream_sha256' => $streamSha256,
            'generated_at' => $generatedAt->format(DATE_ATOM),
            'module' => $this->module,
            'config_entries' => $this->configEntries,
            'introductions' => $this->introductions,
            'books' => $this->books,
        ];
    }
}
