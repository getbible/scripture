<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Catalog;

use GetBible\Scripture\Catalog\ModuleCatalog;
use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Exception\TranslationNotFoundException;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies catalog filtering and hostile native-list failure handling.
 *
 * @since 1.0.0
 */
final class ModuleCatalogFailureTest extends TestCase
{
    /**
     * Verifies absent and unsafe requested identifiers are rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testTranslationRejectsAbsentAndUnsafeIdentifiers(): void
    {
        $catalog = $this->catalog($this->listStream([$this->moduleRecord()]));

        try {
            $catalog->translation('Missing');
            self::fail('An absent translation must fail.');
        } catch (TranslationNotFoundException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(\InvalidArgumentException::class);
        $catalog->translation('../TestBible');
    }

    /**
     * Verifies non-Bible modules are not exposed by the Bible-only catalog.
     *
     * @return void
     * @since 1.0.0
     */
    public function testFiltersNonBibleModules(): void
    {
        $module = $this->moduleRecord();
        $module['classification'] = 'dictionary_or_lexicon';

        self::assertSame([], $this->catalog($this->listStream([$module]))->translations());
    }

    /**
     * Verifies unsafe and duplicate native identifiers cannot enter cache keys.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsUnsafeAndDuplicateNativeIdentifiers(): void
    {
        $unsafe = $this->moduleRecord();
        $unsafe['name'] = $this->byteValue('../Bible');

        foreach (
            [
                $this->listStream([$unsafe]),
                $this->listStream([$this->moduleRecord(), $this->moduleRecord()]),
            ] as $stream
        ) {
            try {
                $this->catalog($stream)->translations();
                self::fail('Unsafe or duplicate native identifiers must fail.');
            } catch (ContractException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Verifies the native byte count is authenticated before validation.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMismatchedNativeByteCount(): void
    {
        $stream = $this->listStream([$this->moduleRecord()]);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('byte count');

        $this->catalog($stream, strlen($stream) + 1)->translations();
    }

    /**
     * Creates a fixture-backed catalog.
     *
     * @param string $contents Complete native stream.
     * @param int|null $reportedBytes Optional extractor byte count.
     *
     * @return ModuleCatalog
     * @since 1.0.0
     */
    private function catalog(string $contents, ?int $reportedBytes = null): ModuleCatalog
    {
        $extractor = new class ($contents, $reportedBytes) implements ModuleExtractorInterface {
            /**
             * Creates a deterministic stream extractor.
             *
             * @param string $contents Complete native stream.
             * @param int|null $reportedBytes Optional reported byte count.
             */
            public function __construct(
                private string $contents,
                private ?int $reportedBytes,
            ) {
            }

            /**
             * Returns the fake native module root.
             *
             * @return string
             */
            public function modulePath(): string
            {
                return '/test/modules';
            }

            /**
             * Writes the configured stream.
             *
             * @param resource $destination Destination stream.
             *
             * @return int
             */
            public function streamModules(mixed $destination): int
            {
                if (fwrite($destination, $this->contents) !== strlen($this->contents)) {
                    throw new \RuntimeException('Unable to write the catalog fixture.');
                }

                return $this->reportedBytes ?? strlen($this->contents);
            }

            /**
             * Rejects unused extraction.
             *
             * @param string $module Module identifier.
             * @param resource $destination Destination stream.
             * @param int $artifactChunkSize Artifact chunk size.
             *
             * @return int
             */
            public function streamModule(
                string $module,
                mixed $destination,
                int $artifactChunkSize = 1048576,
            ): int {
                throw new \LogicException('Not used.');
            }
        };
        $lock = new class () implements ModuleRootLockInterface {
            /**
             * Runs one read operation directly.
             *
             * @template T
             * @param callable(): T $operation Operation.
             * @return T
             */
            public function read(callable $operation): mixed
            {
                return $operation();
            }

            /**
             * Runs one write operation directly.
             *
             * @template T
             * @param callable(): T $operation Operation.
             * @return T
             */
            public function write(callable $operation): mixed
            {
                return $operation();
            }
        };

        return new ModuleCatalog($extractor, new ContractV1Validator(), $lock);
    }

    /**
     * Returns the fixture module record.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    private function moduleRecord(): array
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);

        return StructuredData::object(
            json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR),
            'Fixture module',
        );
    }

    /**
     * Builds a deterministic native list stream.
     *
     * @param list<array<string, mixed>> $modules Module records.
     *
     * @return string
     * @since 1.0.0
     */
    private function listStream(array $modules): string
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);
        $header = StructuredData::object(
            json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR),
            'Fixture header',
        );
        $header['command'] = 'list';
        unset($header['artifact_chunk_size']);
        $records = [$header];

        foreach ($modules as $module) {
            $records[] = $module;
        }

        $serialized = [];

        foreach ($records as $sequence => $record) {
            $record['sequence'] = $sequence;
            $serialized[] = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
        }

        $footer = [
            'artifact_bytes' => 0,
            'artifacts' => 0,
            'counts' => ['header' => 1, 'module' => count($modules)],
            'diagnostics' => ['error' => 0, 'info' => 0, 'warning' => 0],
            'entries' => 0,
            'sequence' => count($records),
            'stream_sha256' => hash('sha256', implode('', $serialized)),
            'success' => true,
            'type' => 'footer',
        ];
        $serialized[] = json_encode(
            $footer,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";

        return implode('', $serialized);
    }

    /**
     * Creates a valid byte-value envelope.
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
