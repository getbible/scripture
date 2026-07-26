<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Snapshot;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Domain\Translation;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Infrastructure\Lock\FileModuleRootLock;
use GetBible\Scripture\Snapshot\TranslationSnapshotManager;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Verifies lazy warming, indexing, and Scripture object hydration.
 *
 * @since 0.1.0
 */
final class TranslationSnapshotManagerTest extends TestCase
{
    /**
     * Temporary cache root.
     *
     * @var string
     * @since 0.1.0
     */
    private string $cachePath;

    /**
     * Creates an isolated temporary cache root.
     *
     * @return void
     * @since 0.1.0
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/getbible-scripture-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->cachePath, 0700, true));
    }

    /**
     * Removes the isolated cache root.
     *
     * @return void
     * @since 0.1.0
     */
    protected function tearDown(): void
    {
        $this->deleteDirectory($this->cachePath);
    }

    /**
     * Verifies one extraction warms a lazy translation and is reused.
     *
     * @return void
     * @since 0.1.0
     */
    public function testTranslationIsWarmedOnceAndQueriedLazily(): void
    {
        $fixture = __DIR__ . '/../../Fixtures/test-bible.ndjson';
        $lines = file($fixture);
        self::assertIsArray($lines);
        $moduleRecord = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($moduleRecord);
        $metadata = TranslationMetadata::fromRecord($moduleRecord);

        $catalog = new class ($metadata) implements ModuleCatalogInterface {
            /**
             * Creates a fixed test catalog.
             *
             * @param TranslationMetadata $metadata Fixed module.
             */
            public function __construct(private TranslationMetadata $metadata)
            {
            }

            /**
             * Returns the fixed module.
             *
             * @return list<TranslationMetadata>
             */
            public function translations(): array
            {
                return [$this->metadata];
            }

            /**
             * Resolves the fixed module.
             *
             * @param string $module Module identifier.
             *
             * @return TranslationMetadata
             */
            public function translation(string $module): TranslationMetadata
            {
                if ($module !== $this->metadata->name()->bytes()) {
                    throw new \RuntimeException('Unexpected test module.');
                }

                return $this->metadata;
            }

            /**
             * Clears no state.
             *
             * @return void
             */
            public function clear(): void
            {
            }
        };

        $extractor = new class ($fixture) implements ModuleExtractorInterface {
            /**
             * Number of extraction calls.
             *
             * @var int
             */
            public int $calls = 0;

            /**
             * Creates a fixture-backed extractor.
             *
             * @param string $fixture Fixture path.
             */
            public function __construct(private string $fixture)
            {
            }

            /**
             * Returns a test module root.
             *
             * @return string
             */
            public function modulePath(): string
            {
                return '/test/modules';
            }

            /**
             * Rejects an unused list operation.
             *
             * @param resource $destination Destination.
             *
             * @return int
             */
            public function streamModules(mixed $destination): int
            {
                throw new \LogicException('Not used in this test.');
            }

            /**
             * Copies the deterministic fixture.
             *
             * @param string $module Module identifier.
             * @param resource $destination Destination.
             * @param int $artifactChunkSize Artifact chunk size.
             *
             * @return int
             */
            public function streamModule(
                string $module,
                mixed $destination,
                int $artifactChunkSize = 1048576,
            ): int {
                ++$this->calls;
                $contents = file_get_contents($this->fixture);

                if ($contents === false || fwrite($destination, $contents) !== strlen($contents)) {
                    throw new \RuntimeException('Unable to copy test fixture.');
                }

                return strlen($contents);
            }
        };

        $clock = new class () implements ClockInterface {
            /**
             * Returns a deterministic UTC time.
             *
             * @return \DateTimeImmutable
             */
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
            }
        };

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
            new FileModuleRootLock($configuration),
        );

        $translation = new Translation('TestBible', $manager->get('TestBible'));
        $verse = $translation->book('John')->chapter(1)->verse(1);

        self::assertSame('Word', $verse->stripped()?->requireUtf8());
        self::assertSame('strong:G3056', $verse->officialAttributes()->types()[0]
            ->lists()[0]
            ->values()[0]
            ->value()
            ->requireUtf8());
        self::assertCount(1, $translation->book('John')->chapter(1)->introductions());
        self::assertSame(
            'Public Domain',
            $translation->configEntriesNamed('DistributionLicense')[0]->value()->requireUtf8(),
        );

        $manager->get('TestBible');
        self::assertSame(1, $extractor->calls);
    }

    /**
     * Recursively removes a controlled test directory.
     *
     * @param string $path Controlled temporary path.
     *
     * @return void
     * @since 0.1.0
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
