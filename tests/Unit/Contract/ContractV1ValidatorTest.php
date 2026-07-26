<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Contract;

use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Exception\ContractException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies complete v1 stream acceptance and tamper rejection.
 *
 * @since 0.1.0
 */
final class ContractV1ValidatorTest extends TestCase
{
    /**
     * Verifies the deterministic Bible fixture.
     *
     * @return void
     * @since 0.1.0
     */
    public function testCompleteFixtureIsAccepted(): void
    {
        $stream = fopen(__DIR__ . '/../../Fixtures/test-bible.ndjson', 'rb');
        self::assertIsResource($stream);

        try {
            $result = (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }

        self::assertSame('extract', $result->command());
        self::assertSame('TestBible', $result->modules()[0]->name()->requireUtf8());
        self::assertSame(
            '12dfe5252316bc73232da8b9b0ebf5ca18c7839daa6b11189206d0a5e1ee6686',
            $result->streamSha256(),
        );
    }

    /**
     * Verifies that changing one authoritative field invalidates the footer.
     *
     * @return void
     * @since 0.1.0
     */
    public function testStreamDigestTamperingIsRejected(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsString($contents);
        $tampered = str_replace('"Public Domain"', '"Public domain"', $contents);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $tampered);
        rewind($stream);

        $this->expectException(ContractException::class);

        try {
            (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Verifies complete regular-artifact validation.
     *
     * @return void
     * @since 0.1.0
     */
    public function testRegularArtifactIsAccepted(): void
    {
        $records = $this->fixtureRecords();
        array_push($records, ...$this->regularArtifactRecords('mods.d/test.dat', 'content'));
        $stream = $this->stream($records);

        try {
            $result = (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }

        self::assertSame(1, $result->footer()['artifacts']);
        self::assertSame(7, $result->footer()['artifact_bytes']);
    }

    /**
     * Verifies that stable artifact metadata must agree with streamed bytes.
     *
     * @return void
     * @since 0.1.0
     */
    public function testStableArtifactExpectedSizeMismatchIsRejected(): void
    {
        $records = $this->fixtureRecords();
        $artifact = $this->regularArtifactRecords('mods.d/test.dat', 'content');
        $artifact[0]['size_expected'] = 8;
        array_push($records, ...$artifact);
        $stream = $this->stream($records);

        $this->expectException(ContractException::class);

        try {
            (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Verifies that artifact groups cannot be split by diagnostics.
     *
     * @return void
     * @since 0.1.0
     */
    public function testDiagnosticInsideArtifactGroupIsRejected(): void
    {
        $records = $this->fixtureRecords();
        $artifact = $this->regularArtifactRecords('mods.d/test.dat', 'content');
        array_splice($artifact, 1, 0, [[
            'code' => 'test.info',
            'message' => $this->byteValue('Test information'),
            'severity' => 'info',
            'type' => 'diagnostic',
        ]]);
        array_push($records, ...$artifact);
        $stream = $this->stream($records);

        $this->expectException(ContractException::class);

        try {
            (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Verifies that a short artifact chunk must be final.
     *
     * @return void
     * @since 0.1.0
     */
    public function testChunkAfterShortChunkIsRejected(): void
    {
        $records = $this->fixtureRecords();
        $artifact = $this->regularArtifactRecords('mods.d/test.dat', 'ab');
        $artifact[1]['data'] = $this->byteValue('a');
        array_splice($artifact, 2, 0, [[
            'artifact_id' => 0,
            'data' => $this->byteValue('b'),
            'index' => 1,
            'type' => 'artifact_chunk',
        ]]);
        array_push($records, ...$artifact);
        $stream = $this->stream($records);

        $this->expectException(ContractException::class);

        try {
            (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Verifies that artifact paths cannot traverse their extraction root.
     *
     * @return void
     * @since 0.1.0
     */
    public function testUnsafeArtifactPathIsRejected(): void
    {
        $records = $this->fixtureRecords();
        array_push($records, ...$this->regularArtifactRecords('../test.dat', 'content'));
        $stream = $this->stream($records);

        $this->expectException(ContractException::class);

        try {
            (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Verifies that list operations cannot contain extract-only records.
     *
     * @return void
     * @since 0.1.0
     */
    public function testListStreamRejectsExtractRecords(): void
    {
        $records = $this->fixtureRecords();
        $records[0]['command'] = 'list';
        unset($records[0]['artifact_chunk_size']);
        $stream = $this->stream($records);

        $this->expectException(ContractException::class);

        try {
            (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Loads non-footer records from the deterministic fixture.
     *
     * @return list<array<string, mixed>>
     * @since 0.1.0
     */
    private function fixtureRecords(): array
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson', FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $records = [];

        foreach ($lines as $line) {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($record);

            if (($record['type'] ?? null) !== 'footer') {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Creates a complete regular-artifact record group.
     *
     * @param string $path Root-relative artifact path.
     * @param string $bytes Artifact bytes.
     *
     * @return list<array<string, mixed>>
     * @since 0.1.0
     */
    private function regularArtifactRecords(string $path, string $bytes): array
    {
        return [
            [
                'artifact_id' => 0,
                'file_type' => 'regular',
                'mode' => 420,
                'path' => $this->byteValue($path),
                'role' => 'module_data',
                'size_expected' => strlen($bytes),
                'type' => 'artifact_begin',
            ],
            [
                'artifact_id' => 0,
                'data' => $this->byteValue($bytes),
                'index' => 0,
                'type' => 'artifact_chunk',
            ],
            [
                'artifact_id' => 0,
                'sha256' => hash('sha256', $bytes),
                'size' => strlen($bytes),
                'stable' => true,
                'type' => 'artifact_end',
            ],
        ];
    }

    /**
     * Creates an exact byte envelope for generated contract records.
     *
     * @param string $bytes Exact bytes.
     *
     * @return array{base64: string, encoding: string, sha256: string, size: int, utf8: string}
     * @since 0.1.0
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

    /**
     * Serializes records with an internally consistent footer.
     *
     * @param list<array<string, mixed>> $records Non-footer records.
     *
     * @return resource
     * @since 0.1.0
     */
    private function stream(array $records): mixed
    {
        $lines = [];
        $counts = [];
        $diagnostics = ['error' => 0, 'info' => 0, 'warning' => 0];
        $entries = 0;
        $artifacts = 0;
        $artifactBytes = 0;

        foreach ($records as $sequence => $record) {
            $record['sequence'] = $sequence;
            $type = $record['type'];
            self::assertIsString($type);
            $counts[$type] = ($counts[$type] ?? 0) + 1;

            if ($type === 'entry') {
                ++$entries;
            } elseif ($type === 'artifact_end') {
                $size = $record['size'] ?? null;
                self::assertIsInt($size);
                ++$artifacts;
                $artifactBytes += $size;
            } elseif ($type === 'diagnostic') {
                $severity = $record['severity'] ?? null;
                self::assertIsString($severity);
                self::assertArrayHasKey($severity, $diagnostics);
                ++$diagnostics[$severity];
            }

            $lines[] = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
        }

        $footer = [
            'artifact_bytes' => $artifactBytes,
            'artifacts' => $artifacts,
            'counts' => $counts,
            'diagnostics' => $diagnostics,
            'entries' => $entries,
            'sequence' => count($records),
            'stream_sha256' => hash('sha256', implode('', $lines)),
            'success' => $diagnostics['error'] === 0,
            'type' => 'footer',
        ];
        $lines[] = json_encode(
            $footer,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, implode('', $lines));
        rewind($stream);

        return $stream;
    }
}
