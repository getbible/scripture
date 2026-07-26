<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Support;

use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Snapshot\SnapshotIndex;
use GetBible\Scripture\Snapshot\SnapshotRecordObserver;

/**
 * Creates complete immutable snapshot readers from the contract fixture.
 *
 * @since 1.0.0
 */
final class SnapshotFixtureFactory
{
    /**
     * Creates a verified snapshot below an isolated cache root.
     *
     * @param string $cachePath Controlled temporary cache root.
     * @param \DateTimeImmutable|null $activatedAt Optional activation time.
     * @param \DateTimeImmutable|null $expiresAt Optional freshness deadline.
     *
     * @return SnapshotIndex
     * @since 1.0.0
     */
    public static function create(
        string $cachePath,
        ?\DateTimeImmutable $activatedAt = null,
        ?\DateTimeImmutable $expiresAt = null,
    ): SnapshotIndex {
        $activatedAt ??= new \DateTimeImmutable('2026-07-26T12:00:00+00:00');
        $expiresAt ??= new \DateTimeImmutable('2026-08-26T12:00:00+00:00');
        $contents = file_get_contents(__DIR__ . '/../Fixtures/test-bible.ndjson');

        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to read the snapshot test fixture.');
        }

        $stream = fopen('php://temp', 'w+b');

        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create a snapshot fixture stream.');
        }

        try {
            if (fwrite($stream, $contents) !== strlen($contents) || fseek($stream, 0) !== 0) {
                throw new \RuntimeException('Unable to prepare the snapshot fixture stream.');
            }

            $observer = new SnapshotRecordObserver();
            $result = (new ContractV1Validator())->validate($stream, $observer);
        } finally {
            fclose($stream);
        }

        $index = $observer->index($result->streamSha256(), $activatedAt);
        $index['module_file_size'] = strlen($contents);
        $index['module_file_sha256'] = hash('sha256', $contents);
        $indexJson = json_encode(
            $index,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
        $generationPath = $cachePath
            . '/translations/TestBible/generations/'
            . $result->streamSha256();

        if (!is_dir($generationPath) && !mkdir($generationPath, 0700, true) && !is_dir($generationPath)) {
            throw new \RuntimeException('Unable to create the snapshot fixture generation.');
        }

        if (
            file_put_contents($generationPath . '/module.ndjson', $contents) !== strlen($contents)
            || file_put_contents($generationPath . '/index.json', $indexJson) !== strlen($indexJson)
        ) {
            throw new \RuntimeException('Unable to write the snapshot fixture generation.');
        }

        return SnapshotIndex::open(
            $generationPath,
            $activatedAt,
            $expiresAt,
            hash('sha256', $indexJson),
        );
    }

    /**
     * Prevents instantiation of this fixtures-only utility.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
    }
}
