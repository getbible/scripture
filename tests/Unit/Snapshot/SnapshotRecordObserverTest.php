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
