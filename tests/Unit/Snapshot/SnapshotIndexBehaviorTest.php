<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Snapshot;

use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Exception\ReferenceNotFoundException;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Tests\Support\SnapshotFixtureFactory;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the complete public snapshot query and integrity surface.
 *
 * @since 1.0.0
 */
final class SnapshotIndexBehaviorTest extends TestCase
{
    /**
     * Isolated cache root.
     *
     * @var string
     * @since 1.0.0
     */
    private string $cachePath;

    /**
     * Creates an isolated cache root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/getbible-snapshot-index-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->cachePath, 0700, true));
    }

    /**
     * Removes the isolated cache root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        $this->deleteDirectory($this->cachePath);
    }

    /**
     * Verifies metadata, index enumeration, hydration, and freshness accessors.
     *
     * @return void
     * @since 1.0.0
     */
    public function testExposesCompleteValidatedSnapshotSurface(): void
    {
        $snapshot = SnapshotFixtureFactory::create($this->cachePath);
        $metadata = $snapshot->metadata();
        $book = $snapshot->bookMetadata('2:4');

        self::assertSame('TestBible', $metadata->name()->requireUtf8());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $snapshot->generationId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $snapshot->indexSha256());
        self::assertSame(['2:4'], $snapshot->bookKeys());
        self::assertSame(2, $book['testament']);
        self::assertSame(4, $book['position']);
        self::assertSame('John', $book['name']->requireUtf8());
        self::assertSame('John', $book['abbreviation']->requireUtf8());
        self::assertSame('KJV', $book['versification']->requireUtf8());
        self::assertSame([1], $snapshot->chapterNumbers('2:4'));
        self::assertSame([1], $snapshot->verseNumbers('2:4', 1));
        self::assertSame('Word', $snapshot->verse('2:4', 1, 1)->stripped()?->requireUtf8());
        self::assertCount(1, $snapshot->verses('2:4', 1));
        self::assertCount(1, $snapshot->verses('2:4', 1, 1, 1));
        self::assertSame([], $snapshot->verses('2:4', 1, 2, 3));
        self::assertCount(1, $snapshot->configEntries());
        self::assertCount(1, $snapshot->configEntriesNamed('DistributionLicense'));
        self::assertSame([], $snapshot->configEntriesNamed('Missing'));
        self::assertSame([], $snapshot->introductions());
        self::assertSame([], $snapshot->introductions('2:4'));
        self::assertCount(1, $snapshot->introductions('2:4', 1));
        self::assertFileExists($snapshot->rawExportPath());
        self::assertSame('2026-07-26T12:00:00+00:00', $snapshot->activatedAt()->format(DATE_ATOM));
        self::assertSame('2026-08-26T12:00:00+00:00', $snapshot->expiresAt()->format(DATE_ATOM));
        self::assertFalse($snapshot->isExpired(new \DateTimeImmutable('2026-08-26T11:59:59+00:00')));
        self::assertTrue($snapshot->isExpired(new \DateTimeImmutable('2026-08-26T12:00:00+00:00')));
    }

    /**
     * Verifies absent references are reported through the domain exception.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsAbsentBookChapterAndVerseReferences(): void
    {
        $snapshot = SnapshotFixtureFactory::create($this->cachePath);

        foreach (
            [
                static fn (): mixed => $snapshot->bookMetadata('2:99'),
                static fn (): mixed => $snapshot->chapterNumbers('2:99'),
                static fn (): mixed => $snapshot->verseNumbers('2:4', 99),
                static fn (): mixed => $snapshot->verse('2:4', 1, 99),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('An absent snapshot reference must fail.');
            } catch (ReferenceNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Verifies records are authenticated again when lazily hydrated.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsRecordModifiedAfterSnapshotOpen(): void
    {
        $snapshot = SnapshotFixtureFactory::create($this->cachePath);
        $modulePath = $snapshot->rawExportPath();
        $contents = file_get_contents($modulePath);
        self::assertIsString($contents);
        $modified = str_replace('"V29yZA=="', '"V29yZB=="', $contents);
        self::assertSame(strlen($contents), strlen($modified));
        self::assertNotFalse(file_put_contents($modulePath, $modified));

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('digest');

        $snapshot->verse('2:4', 1, 1);
    }

    /**
     * Verifies pointer-bound digest and immutable stream integrity checks.
     *
     * @return void
     * @since 1.0.0
     */
    public function testOpenRejectsDigestAndStreamTampering(): void
    {
        $snapshot = SnapshotFixtureFactory::create($this->cachePath);
        $generationPath = dirname($snapshot->rawExportPath());
        $activatedAt = $snapshot->activatedAt();
        $expiresAt = $snapshot->expiresAt();
        unset($snapshot);

        try {
            SnapshotIndex::open($generationPath, $activatedAt, $expiresAt, str_repeat('0', 64));
            self::fail('A mismatched pointer digest must fail.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('digest', $exception->getMessage());
        }

        self::assertNotFalse(file_put_contents($generationPath . '/module.ndjson', 'tamper', FILE_APPEND));

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('integrity manifest');

        SnapshotIndex::open($generationPath, $activatedAt, $expiresAt);
    }

    /**
     * Verifies invalid generation identities, incomplete files, and malformed indexes fail closed.
     *
     * @return void
     * @since 1.0.0
     */
    public function testOpenRejectsInvalidAndIncompleteGenerations(): void
    {
        $activatedAt = new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $expiresAt = new \DateTimeImmutable('2026-08-26T12:00:00+00:00');

        try {
            SnapshotIndex::open($this->cachePath . '/invalid', $activatedAt, $expiresAt);
            self::fail('An invalid generation identity was accepted.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('identity', $exception->getMessage());
        }

        $generationPath = $this->cachePath
            . '/translations/TestBible/generations/'
            . str_repeat('a', 64);
        self::assertTrue(mkdir($generationPath, 0700, true));
        self::assertNotFalse(file_put_contents($generationPath . '/index.json', "{}\n"));

        try {
            SnapshotIndex::open($generationPath, $activatedAt, $expiresAt);
            self::fail('An incomplete generation was accepted.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('incomplete', $exception->getMessage());
        }

        self::assertNotFalse(file_put_contents($generationPath . '/module.ndjson', "x\n"));
        self::assertNotFalse(file_put_contents($generationPath . '/index.json', "{invalid\n"));

        try {
            SnapshotIndex::open($generationPath, $activatedAt, $expiresAt);
            self::fail('Malformed snapshot index JSON was accepted.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('index JSON is invalid', $exception->getMessage());
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }

        self::assertNotFalse(file_put_contents($generationPath . '/index.json', "{}\n"));

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('Snapshot index must be a JSON object');
        SnapshotIndex::open($generationPath, $activatedAt, $expiresAt);
    }

    /**
     * Verifies lazily accessed nested book and verse index structures are validated.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMalformedNestedBookAndVerseIndexes(): void
    {
        $snapshot = SnapshotFixtureFactory::create($this->cachePath);
        $generationPath = dirname($snapshot->rawExportPath());
        $activatedAt = $snapshot->activatedAt();
        $expiresAt = $snapshot->expiresAt();
        unset($snapshot);

        $index = $this->readIndex($generationPath);
        $books = StructuredData::object($index['books'] ?? null, 'Fixture books');
        $book = StructuredData::object($books['2:4'] ?? null, 'Fixture book');
        $book['testament'] = 'invalid';
        $books['2:4'] = $book;
        $index['books'] = $books;
        $snapshot = $this->writeAndOpenIndex($generationPath, $index, $activatedAt, $expiresAt);

        try {
            $snapshot->bookMetadata('2:4');
            self::fail('Malformed nested book metadata was accepted.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('metadata is invalid', $exception->getMessage());
        }

        unset($snapshot);
        $index = $this->readIndex($generationPath);
        $books = StructuredData::object($index['books'] ?? null, 'Fixture books');
        $book = StructuredData::object($books['2:4'] ?? null, 'Fixture book');
        $book['testament'] = 2;
        $chapters = StructuredData::map($book['chapters'] ?? null, 'Fixture chapters');
        $chapter = StructuredData::object($chapters['1'] ?? null, 'Fixture chapter');
        $verses = StructuredData::object($chapter['verses'] ?? null, 'Fixture verses');
        $location = $verses['1:0'] ?? null;
        $chapter['verses'] = ['invalid' => $location];
        $chapters['1'] = $chapter;
        $book['chapters'] = $chapters;
        $books['2:4'] = $book;
        $index['books'] = $books;
        $snapshot = $this->writeAndOpenIndex($generationPath, $index, $activatedAt, $expiresAt);

        try {
            $snapshot->verses('2:4', 1);
            self::fail('A malformed verse coordinate was accepted.');
        } catch (ContractException $exception) {
            self::assertStringContainsString('coordinate is invalid', $exception->getMessage());
        }

        unset($snapshot);
        $index = $this->readIndex($generationPath);
        $books = StructuredData::object($index['books'] ?? null, 'Fixture books');
        $book = StructuredData::object($books['2:4'] ?? null, 'Fixture book');
        $chapters = StructuredData::map($book['chapters'] ?? null, 'Fixture chapters');
        $chapter = StructuredData::object($chapters['1'] ?? null, 'Fixture chapter');
        $chapter['verses'] = ['1:0' => 'invalid'];
        $chapters['1'] = $chapter;
        $book['chapters'] = $chapters;
        $books['2:4'] = $book;
        $index['books'] = $books;
        $snapshot = $this->writeAndOpenIndex($generationPath, $index, $activatedAt, $expiresAt);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('verse index is invalid');
        $snapshot->verses('2:4', 1);
    }

    /**
     * Reads a fixture index as a mutable test map.
     *
     * @param string $generationPath Controlled generation path.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    private function readIndex(string $generationPath): array
    {
        $json = file_get_contents($generationPath . '/index.json');
        self::assertIsString($json);
        $index = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return StructuredData::object($index, 'Fixture snapshot index');
    }

    /**
     * Atomically replaces and opens a deliberately modified fixture index.
     *
     * @param string $generationPath Controlled generation path.
     * @param array<string, mixed> $index Modified index.
     * @param \DateTimeImmutable $activatedAt Activation time.
     * @param \DateTimeImmutable $expiresAt Expiration time.
     *
     * @return SnapshotIndex
     * @since 1.0.0
     */
    private function writeAndOpenIndex(
        string $generationPath,
        array $index,
        \DateTimeImmutable $activatedAt,
        \DateTimeImmutable $expiresAt,
    ): SnapshotIndex {
        $json = json_encode(
            $index,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
        self::assertSame(
            strlen($json),
            file_put_contents($generationPath . '/index.json', $json),
        );

        return SnapshotIndex::open(
            $generationPath,
            $activatedAt,
            $expiresAt,
            hash('sha256', $json),
        );
    }

    /**
     * Recursively removes a controlled test directory.
     *
     * @param string $path Controlled temporary path.
     *
     * @return void
     * @since 1.0.0
     */
    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
