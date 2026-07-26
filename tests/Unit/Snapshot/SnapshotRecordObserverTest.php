<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Snapshot;

use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Snapshot\SnapshotRecordObserver;
use PHPUnit\Framework\TestCase;

/**
 * Verifies snapshot index consistency across records from the same book.
 *
 * @since 1.0.0
 */
final class SnapshotRecordObserverTest extends TestCase
{
    /**
     * Verifies later entries cannot silently change a book's versification.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsConflictingBookMetadata(): void
    {
        $records = $this->records();
        $observer = new SnapshotRecordObserver();
        $observer->onRecord($records[1], 0, 100);
        $observer->onRecord($records[3], 100, 100);
        $scope = StructuredData::object($records[4]['scope'] ?? null, 'Fixture scope');
        $scope['versification'] = $this->byteValue('LXX');
        $records[4]['scope'] = $scope;

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Book metadata changes');

        $observer->onRecord($records[4], 200, 100);
    }

    /**
     * Verifies different books cannot expose the same lookup alias.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsAmbiguousBookAlias(): void
    {
        $records = $this->records();
        $observer = new SnapshotRecordObserver();
        $observer->onRecord($records[1], 0, 100);
        $observer->onRecord($records[3], 100, 100);
        $scope = StructuredData::object($records[4]['scope'] ?? null, 'Fixture scope');
        $scope['book'] = 5;
        $records[4]['scope'] = $scope;

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('ambiguous');

        $observer->onRecord($records[4], 200, 100);
    }

    /**
     * Verifies module-, book-, and chapter-level introductions are indexed separately.
     *
     * @return void
     * @since 1.0.0
     */
    public function testIndexesIntroductionScopes(): void
    {
        $records = $this->records();
        $observer = new SnapshotRecordObserver();
        $observer->onRecord($records[1], 0, 100);

        $moduleIntroduction = $records[3];
        $moduleScope = StructuredData::object(
            $moduleIntroduction['scope'] ?? null,
            'Fixture module scope',
        );
        $moduleScope['testament'] = 0;
        $moduleScope['book'] = 0;
        $moduleScope['chapter'] = 0;
        $moduleScope['verse'] = 0;
        $moduleScope['intro_scope'] = 'module';
        unset($moduleScope['book_name'], $moduleScope['book_abbreviation']);
        $moduleIntroduction['scope'] = $moduleScope;
        $observer->onRecord($moduleIntroduction, 100, 100);

        $bookIntroduction = $records[3];
        $bookScope = StructuredData::object(
            $bookIntroduction['scope'] ?? null,
            'Fixture book scope',
        );
        $bookScope['chapter'] = 0;
        $bookScope['verse'] = 0;
        $bookScope['intro_scope'] = 'book';
        $bookIntroduction['scope'] = $bookScope;
        $observer->onRecord($bookIntroduction, 200, 100);
        $observer->onRecord($records[3], 300, 100);

        $index = $observer->index(
            str_repeat('a', 64),
            new \DateTimeImmutable('2026-07-26T12:00:00+00:00'),
        );
        $books = StructuredData::object($index['books'] ?? null, 'Generated books');
        $book = StructuredData::object($books['2:4'] ?? null, 'Generated book');
        $chapters = StructuredData::map($book['chapters'] ?? null, 'Generated chapters');
        $chapter = StructuredData::object($chapters['1'] ?? null, 'Generated chapter');

        self::assertCount(1, StructuredData::list($index['introductions'] ?? null, 'Module introductions'));
        self::assertCount(1, StructuredData::list($book['introductions'] ?? null, 'Book introductions'));
        self::assertCount(1, StructuredData::list($chapter['introductions'] ?? null, 'Chapter introductions'));
    }

    /**
     * Verifies duplicate verse coordinates cannot overwrite an earlier record.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsDuplicateVerseCoordinate(): void
    {
        $records = $this->records();
        $observer = new SnapshotRecordObserver();
        $observer->onRecord($records[1], 0, 100);
        $observer->onRecord($records[4], 100, 100);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Duplicate verse coordinate');

        $observer->onRecord($records[4], 200, 100);
    }

    /**
     * Verifies snapshots require exactly a Bible module before finalization.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMissingAndNonBibleModule(): void
    {
        try {
            (new SnapshotRecordObserver())->index(
                str_repeat('a', 64),
                new \DateTimeImmutable('2026-07-26T12:00:00+00:00'),
            );
            self::fail('A snapshot without module metadata was accepted.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('requires one Bible module', $exception->getMessage());
        }

        $records = $this->records();
        $records[1]['classification'] = 'commentary';

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('classification="bible"');
        (new SnapshotRecordObserver())->onRecord($records[1], 0, 100);
    }

    /**
     * Loads decoded fixture records.
     *
     * @return list<array<string, mixed>>
     * @since 1.0.0
     */
    private function records(): array
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);
        $records = [];

        foreach ($lines as $line) {
            $records[] = StructuredData::object(
                json_decode($line, true, 512, JSON_THROW_ON_ERROR),
                'Fixture record',
            );
        }

        return $records;
    }

    /**
     * Creates a verified byte envelope.
     *
     * @param string $bytes Exact bytes.
     *
     * @return array{base64: string, encoding: string, sha256: string, size: int, utf8: string}
     * @since 1.0.0
     */
    private function byteValue(string $bytes): array
    {
        return [
            'base64' => base64_encode($bytes),
            'encoding' => 'base64',
            'sha256' => hash('sha256', $bytes),
            'size' => strlen($bytes),
            'utf8' => $bytes,
        ];
    }
}
