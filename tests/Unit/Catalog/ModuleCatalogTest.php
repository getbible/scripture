<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Catalog;

use GetBible\Scripture\Catalog\ModuleCatalog;
use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies catalog extraction locking, validation, and invalidation.
 *
 * @since 1.0.0
 */
final class ModuleCatalogTest extends TestCase
{
    /**
     * Verifies list extraction occurs under a shared module-root lock.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCatalogListIsReadLockedAndCacheCanBeCleared(): void
    {
        $lock = new class () implements ModuleRootLockInterface {
            /**
             * Whether a shared callback is active.
             *
             * @var bool
             */
            public bool $reading = false;

            /**
             * Shared lock acquisitions.
             *
             * @var int
             */
            public int $reads = 0;

            /**
             * Executes a shared callback.
             *
             * @template T
             * @param callable(): T $operation Callback.
             * @return T
             */
            public function read(callable $operation): mixed
            {
                ++$this->reads;
                $this->reading = true;

                try {
                    return $operation();
                } finally {
                    $this->reading = false;
                }
            }

            /**
             * Executes an exclusive callback.
             *
             * @template T
             * @param callable(): T $operation Callback.
             * @return T
             */
            public function write(callable $operation): mixed
            {
                return $operation();
            }
        };
        $stream = $this->listStream();
        $extractor = new class (
            $stream,
            static fn (): bool => $lock->reading,
        ) implements ModuleExtractorInterface {
            /**
             * Native list calls.
             *
             * @var int
             */
            public int $calls = 0;

            /**
             * Creates the deterministic extractor.
             *
             * @param string $stream Complete list stream.
             * @param \Closure(): bool $isReading Shared-lock state.
             */
            public function __construct(
                private string $stream,
                private \Closure $isReading,
            ) {
            }

            /**
             * Returns the fake module root.
             *
             * @return string
             */
            public function modulePath(): string
            {
                return '/test/modules';
            }

            /**
             * Writes the fixture under the asserted read lock.
             *
             * @param resource $destination Destination.
             * @return int
             */
            public function streamModules(mixed $destination): int
            {
                if (($this->isReading)() !== true) {
                    throw new \RuntimeException('Catalog extraction was not read locked.');
                }

                ++$this->calls;
                $written = fwrite($destination, $this->stream);

                if ($written !== strlen($this->stream)) {
                    throw new \RuntimeException('Unable to write the catalog fixture.');
                }

                return $written;
            }

            /**
             * Rejects unused module extraction.
             *
             * @param string $module Module identifier.
             * @param resource $destination Destination.
             * @param int $artifactChunkSize Chunk size.
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
        $catalog = new ModuleCatalog($extractor, new ContractV1Validator(), $lock);

        self::assertSame('TestBible', $catalog->translations()[0]->name()->requireUtf8());
        self::assertSame('TestBible', $catalog->translation(' TestBible ')->name()->requireUtf8());
        self::assertSame(1, $extractor->calls);
        self::assertSame(1, $lock->reads);

        $catalog->clear();
        $catalog->translations();

        self::assertSame(2, $extractor->calls);
        self::assertSame(2, $lock->reads);
    }

    /**
     * Builds a complete deterministic list operation stream.
     *
     * @return string
     * @since 1.0.0
     */
    private function listStream(): string
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);
        $header = StructuredData::object(
            json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR),
            'Fixture header',
        );
        $module = StructuredData::object(
            json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR),
            'Fixture module',
        );
        $header['command'] = 'list';
        unset($header['artifact_chunk_size']);
        $header['sequence'] = 0;
        $module['sequence'] = 1;
        $serialized = [
            json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
            json_encode($module, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        ];
        $footer = [
            'artifact_bytes' => 0,
            'artifacts' => 0,
            'counts' => ['header' => 1, 'module' => 1],
            'diagnostics' => ['error' => 0, 'info' => 0, 'warning' => 0],
            'entries' => 0,
            'sequence' => 2,
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
}
