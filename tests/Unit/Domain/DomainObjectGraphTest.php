<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Domain;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Domain\Book;
use GetBible\Scripture\Domain\Chapter;
use GetBible\Scripture\Domain\Translation;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Exception\InvalidReferenceException;
use GetBible\Scripture\Exception\ReferenceNotFoundException;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Snapshot\TranslationSnapshotManager;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the lazy Translation, Book, and Chapter navigation surface.
 *
 * @since 1.0.0
 */
final class DomainObjectGraphTest extends TestCase
{
    /**
     * Isolated snapshot cache.
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
        $this->cachePath = sys_get_temp_dir() . '/getbible-domain-test-' . bin2hex(random_bytes(8));
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
     * Verifies complete navigation and snapshot metadata exposure.
     *
     * @return void
     * @since 1.0.0
     */
    public function testTranslationGraphExposesEveryNavigationMethod(): void
    {
        [$translation] = $this->translation();

        self::assertSame('TestBible', $translation->moduleName());
        self::assertSame('TestBible', $translation->metadata()->name()->requireUtf8());
        self::assertCount(1, $translation->books());
        self::assertSame('John', $translation->book('john')->name()->requireUtf8());
        self::assertSame('John', $translation->bookByPosition(2, 4)->abbreviation()->requireUtf8());
        self::assertSame('Word', $translation->verses('John', 1, 1, 1)[0]->stripped()?->requireUtf8());
        self::assertCount(1, $translation->configEntries());
        self::assertSame(
            'Public Domain',
            $translation->configEntriesNamed('DistributionLicense')[0]->value()->requireUtf8(),
        );
        self::assertSame([], $translation->configEntriesNamed('Unknown'));
        self::assertSame([], $translation->introductions());
        self::assertFileExists($translation->rawExportPath());
        self::assertSame('2026-07-26T12:00:00+00:00', $translation->activatedAt()->format(DATE_ATOM));
        self::assertSame('2026-08-26T12:00:00+00:00', $translation->expiresAt()->format(DATE_ATOM));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $translation->generationId());

        $book = $translation->books()[0];
        self::assertSame($translation, $book->translation());
        self::assertSame(2, $book->testament());
        self::assertSame(4, $book->position());
        self::assertSame('John', $book->name()->requireUtf8());
        self::assertSame('John', $book->abbreviation()->requireUtf8());
        self::assertSame('KJV', $book->versification()->requireUtf8());
        self::assertCount(1, $book->chapters());
        self::assertSame([], $book->introductions());

        $chapter = $book->chapter(1);
        self::assertSame($book, $chapter->book());
        self::assertSame(1, $chapter->number());
        self::assertSame('John 1:1', $chapter->verse(1)->key()->requireUtf8());
        self::assertCount(1, $chapter->verses());
        self::assertCount(1, $chapter->verses(1, 1));
        self::assertCount(1, $chapter->introductions());
    }

    /**
     * Verifies missing books, chapters, verses, and invalid ranges fail clearly.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInvalidReferencesFailAtTheirOwningLevel(): void
    {
        [$translation, $snapshot] = $this->translation();
        $book = $translation->book('John');
        $chapter = $book->chapter(1);

        $operations = [
            static fn () => $translation->book('Genesis'),
            static fn () => $translation->bookByPosition(1, 1),
            static fn () => $book->chapter(2),
            static fn () => $chapter->verse(2),
            static fn () => $chapter->verse(1, 256),
            static fn () => $chapter->verses(2, 1),
            static fn () => new Chapter($book, $snapshot, '2:4', 0),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('An invalid Scripture reference was accepted.');
            } catch (InvalidReferenceException | ReferenceNotFoundException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies parent/snapshot ownership invariants reject mismatches.
     *
     * @return void
     * @since 1.0.0
     */
    public function testTranslationRejectsSnapshotFromAnotherModule(): void
    {
        [, $snapshot] = $this->translation();

        $this->expectException(\InvalidArgumentException::class);
        new Translation('DifferentBible', $snapshot);
    }

    /**
     * Verifies Book and Chapter reject parent objects from another snapshot.
     *
     * @return void
     * @since 1.0.0
     */
    public function testBookAndChapterRejectParentOwnershipMismatch(): void
    {
        [$translation, $snapshot] = $this->translation();
        $translationProperty = new \ReflectionProperty($translation, 'moduleName');
        $translationProperty->setValue($translation, 'DifferentBible');

        try {
            new Book($translation, $snapshot, '2:4');
            self::fail('A Book accepted a Translation from another snapshot.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('parent Translation', $exception->getMessage());
        }

        $translationProperty->setValue($translation, 'TestBible');
        $book = new Book($translation, $snapshot, '2:4');
        $metadataProperty = new \ReflectionProperty($book, 'metadata');
        $metadata = $metadataProperty->getValue($book);
        self::assertIsArray($metadata);
        $metadata['testament'] = 1;
        $metadataProperty->setValue($book, $metadata);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('parent Book');
        new Chapter($book, $snapshot, '2:4', 1);
    }

    /**
     * Builds a deterministic translation over one real immutable snapshot.
     *
     * @return array{Translation, SnapshotIndex}
     * @since 1.0.0
     */
    private function translation(): array
    {
        $fixture = __DIR__ . '/../../Fixtures/test-bible.ndjson';
        $lines = file($fixture);
        self::assertIsArray($lines);
        $record = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        $metadata = TranslationMetadata::fromRecord(
            StructuredData::object($record, 'Fixture module'),
        );
        $catalog = $this->createStub(ModuleCatalogInterface::class);
        $catalog->method('translations')->willReturn([$metadata]);
        $catalog->method('translation')->willReturn($metadata);
        $extractor = $this->createStub(ModuleExtractorInterface::class);
        $extractor->method('modulePath')->willReturn('/test/modules');
        $extractor->method('streamModule')->willReturnCallback(
            static function (
                string $module,
                mixed $destination,
                int $artifactChunkSize = 1048576,
            ) use ($fixture): int {
                $contents = file_get_contents($fixture);

                if (!is_string($contents) || fwrite($destination, $contents) !== strlen($contents)) {
                    throw new \RuntimeException('Unable to copy the deterministic fixture.');
                }

                return strlen($contents);
            },
        );
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-07-26T12:00:00+00:00'));
        $lock = $this->createStub(ModuleRootLockInterface::class);
        $lock->method('read')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $lock->method('write')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $configuration = Configuration::fromEnvironment([
            'module_path' => '/test/modules',
            'cache_path' => $this->cachePath,
            'refresh_interval' => 'P1M',
            'auto_refresh' => true,
        ]);
        $manager = new TranslationSnapshotManager(
            $configuration,
            $clock,
            $catalog,
            $extractor,
            new ContractV1Validator(),
            new Dispatcher(),
            $lock,
        );
        $snapshot = $manager->get('TestBible');

        return [new Translation('TestBible', $snapshot), $snapshot];
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
