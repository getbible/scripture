<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Contract;

use GetBible\Scripture\Contract\ContractV1Validator;
use GetBible\Scripture\Contract\RecordObserverInterface;
use GetBible\Scripture\Contract\StructuredData;
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
     * Verifies validated records are reported with exact stream locations.
     *
     * @return void
     * @since 1.0.0
     */
    public function testObserverReceivesEveryValidatedRecord(): void
    {
        $observer = new class () implements RecordObserverInterface {
            /**
             * Observed record types.
             *
             * @var list<string>
             */
            public array $types = [];

            /**
             * Observed byte offsets.
             *
             * @var list<int>
             */
            public array $offsets = [];

            /**
             * Observed serialized lengths.
             *
             * @var list<int>
             */
            public array $lengths = [];

            /**
             * Captures one validated record.
             *
             * @param array<string, mixed> $record Validated record.
             * @param int $offset Record offset.
             * @param int $length Serialized length.
             *
             * @return void
             */
            public function onRecord(array $record, int $offset, int $length): void
            {
                $type = $record['type'] ?? null;

                if (!is_string($type)) {
                    throw new \LogicException('A validated record must have a type.');
                }

                $this->types[] = $type;
                $this->offsets[] = $offset;
                $this->lengths[] = $length;
            }
        };
        $stream = fopen(__DIR__ . '/../../Fixtures/test-bible.ndjson', 'rb');
        self::assertIsResource($stream);

        try {
            (new ContractV1Validator())->validate($stream, $observer);
        } finally {
            fclose($stream);
        }

        self::assertSame(
            ['header', 'module', 'config_entry', 'entry', 'entry', 'footer'],
            $observer->types,
        );
        self::assertSame(0, $observer->offsets[0]);
        self::assertSame(filesize(__DIR__ . '/../../Fixtures/test-bible.ndjson'), array_sum($observer->lengths));
    }

    /**
     * Verifies validation accepts only readable stream resources.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNonStreamInputIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ContractV1Validator())->validate(null);
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
        foreach (['../test.dat', '/absolute.dat', 'directory//test.dat', "test\0.dat"] as $path) {
            $records = $this->fixtureRecords();
            array_push($records, ...$this->regularArtifactRecords($path, 'content'));
            $stream = $this->stream($records);

            try {
                $this->assertRejected($stream);
            } finally {
                fclose($stream);
            }
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
     * Verifies raw configuration sources and diagnostics are validated.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAcceptsConfigurationSourceAndInformationDiagnostic(): void
    {
        $records = $this->fixtureRecords();
        array_splice($records, 2, 0, [[
            'ordinal' => 0,
            'path' => $this->byteValue('mods.d/test.conf'),
            'raw' => $this->byteValue('[TestBible]'),
            'type' => 'config_source',
        ]]);
        $records[] = [
            'code' => 'test.information',
            'message' => $this->byteValue('Validated information'),
            'severity' => 'info',
            'type' => 'diagnostic',
        ];
        $stream = $this->stream($records);

        try {
            $result = (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }

        $footer = $result->footer();
        $diagnostics = StructuredData::object($footer['diagnostics'] ?? null, 'Footer diagnostics');
        $counts = StructuredData::object($footer['counts'] ?? null, 'Footer counts');
        self::assertSame(1, $diagnostics['info']);
        self::assertSame(1, $counts['config_source']);
    }

    /**
     * Verifies generic SWORD keys remain valid for non-Bible entry consumers.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAcceptsGenericSwordKeyEntryScope(): void
    {
        $records = $this->fixtureRecords();
        $records[3]['scope'] = ['index' => 1, 'type' => 'sword_key'];
        $stream = $this->stream($records);

        try {
            $result = (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }

        self::assertSame(2, $result->footer()['entries']);
    }

    /**
     * Verifies directory and symlink artifact semantics.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAcceptsDirectoryAndSymlinkArtifacts(): void
    {
        $records = $this->fixtureRecords();
        array_push($records, ...$this->directoryArtifactRecords(0, 'modules/'));
        array_push($records, ...$this->symlinkArtifactRecords(1, 'modules/current', 'TestBible'));
        $stream = $this->stream($records);

        try {
            $result = (new ContractV1Validator())->validate($stream);
        } finally {
            fclose($stream);
        }

        self::assertSame(2, $result->footer()['artifacts']);
        self::assertSame(strlen('TestBible'), $result->footer()['artifact_bytes']);
    }

    /**
     * Verifies unreadable, truncated, and malformed stream input is rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsUnreadableTruncatedAndMalformedStreams(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'getbible-contract-');
        self::assertIsString($path);
        $unreadable = fopen($path, 'wb');
        self::assertIsResource($unreadable);

        try {
            $this->assertRejected($unreadable, \InvalidArgumentException::class);
        } finally {
            fclose($unreadable);
            unlink($path);
        }

        foreach (['{"type":"header"}', "{invalid json}\n"] as $contents) {
            $stream = $this->rawStream($contents);

            try {
                $this->assertRejected($stream);
            } finally {
                fclose($stream);
            }
        }

        $badSequence = $this->rawStream("{\"sequence\":1,\"type\":\"header\"}\n");

        try {
            $this->assertRejected($badSequence);
        } finally {
            fclose($badSequence);
        }
    }

    /**
     * Verifies complete framing requires one terminal footer.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMissingFooterAndRecordAfterFooter(): void
    {
        $records = $this->fixtureRecords();
        $header = $records[0];
        $header['sequence'] = 0;
        $missingFooter = $this->rawStream(
            json_encode(
                $header,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n",
        );

        try {
            $this->assertRejected($missingFooter);
        } finally {
            fclose($missingFooter);
        }

        $complete = $this->stream($records);
        $contents = stream_get_contents($complete);
        fclose($complete);
        self::assertIsString($contents);
        $afterFooter = [
            'code' => 'late.record',
            'message' => $this->byteValue('Too late'),
            'sequence' => count($records) + 1,
            'severity' => 'info',
            'type' => 'diagnostic',
        ];
        $lateStream = $this->rawStream(
            $contents . json_encode(
                $afterFooter,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n",
        );

        try {
            $this->assertRejected($lateStream);
        } finally {
            fclose($lateStream);
        }
    }

    /**
     * Verifies record ordering, type, and extract cardinality invariants.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsInvalidRecordOrderingAndCardinality(): void
    {
        $fixture = $this->fixtureRecords();
        $duplicateModule = $fixture;
        array_splice($duplicateModule, 2, 0, [$fixture[1]]);
        $missingModule = [$fixture[0]];
        $beforeModule = [$fixture[0], $fixture[2]];
        $unsupported = $fixture;
        $unsupported[2]['type'] = 'future_record';
        $notHeader = $fixture;
        array_shift($notHeader);
        $badHeader = $fixture;
        $badHeader[0]['command'] = 'unknown';
        $incompatibleHeader = $fixture;
        $incompatibleHeader[0]['contract'] = 'getbiblesword.ndjson/v2';
        $invalidChunkSize = $fixture;
        $invalidChunkSize[0]['artifact_chunk_size'] = 100;
        $secondHeader = [$fixture[0], $fixture[0]];
        $outOfPhase = $fixture;
        [$configEntry] = array_splice($outOfPhase, 2, 1);
        array_splice($outOfPhase, 4, 0, [$configEntry]);

        foreach (
            [
                $duplicateModule,
                $missingModule,
                $beforeModule,
                $unsupported,
                $notHeader,
                $badHeader,
                $incompatibleHeader,
                $invalidChunkSize,
                $secondHeader,
                $outOfPhase,
            ] as $records
        ) {
            $stream = $this->stream($records);

            try {
                $this->assertRejected($stream);
            } finally {
                fclose($stream);
            }
        }
    }

    /**
     * Verifies config, entry, and diagnostic record members are strict.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMalformedExtractRecords(): void
    {
        $fixture = $this->fixtureRecords();
        $badConfigSource = $fixture;
        array_splice($badConfigSource, 2, 0, [[
            'ordinal' => 1,
            'path' => $this->byteValue('mods.d/test.conf'),
            'raw' => $this->byteValue('config'),
            'type' => 'config_source',
        ]]);
        $badConfigEntry = $fixture;
        $badConfigEntry[2]['ordinal'] = 1;
        $badEntry = $fixture;
        $badEntry[3]['ordinal'] = -1;
        $badGenericScope = $fixture;
        $badGenericScope[3]['scope'] = ['index' => -1, 'type' => 'sword_key'];
        $unknownScope = $fixture;
        $unknownScope[3]['scope'] = ['index' => 0, 'type' => 'unknown'];
        $badSegments = $fixture;
        $badSegments[3]['annotation_segments'] = [null];
        $mismatchedSegments = $fixture;
        $mismatchedSegments[3]['annotation_segments'] = [];
        $badProjection = $fixture;
        $badProjection[3]['stripped'] = null;
        $unexpectedProjection = $fixture;
        $unexpectedProjection[3]['projections_available'] = false;
        $badDiagnostic = $fixture;
        $badDiagnostic[] = [
            'code' => null,
            'message' => $this->byteValue('Bad diagnostic'),
            'severity' => 'info',
            'type' => 'diagnostic',
        ];

        foreach (
            [
                $badConfigSource,
                $badConfigEntry,
                $badEntry,
                $badGenericScope,
                $unknownScope,
                $badSegments,
                $mismatchedSegments,
                $badProjection,
                $unexpectedProjection,
                $badDiagnostic,
            ] as $records
        ) {
            $stream = $this->stream($records);

            try {
                $this->assertRejected($stream);
            } finally {
                fclose($stream);
            }
        }
    }

    /**
     * Verifies incomplete, duplicated, and invalid artifact groups fail.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsInvalidArtifactGroups(): void
    {
        $fixture = $this->fixtureRecords();
        $chunkWithoutBegin = $fixture;
        $chunkWithoutBegin[] = $this->regularArtifactRecords('test.dat', 'x')[1];
        $endWithoutBegin = $fixture;
        $endWithoutBegin[] = $this->regularArtifactRecords('test.dat', 'x')[2];
        $nestedBegin = $fixture;
        $nestedBegin[] = $this->regularArtifactRecords('first.dat', 'x')[0];
        $nestedBegin[] = $this->regularArtifactRecords('second.dat', 'y')[0];
        $unfinished = $fixture;
        $unfinished[] = $this->regularArtifactRecords('test.dat', 'x')[0];
        $duplicatePath = $fixture;
        array_push($duplicatePath, ...$this->directoryArtifactRecords(0, 'same/'));
        array_push($duplicatePath, ...$this->directoryArtifactRecords(1, 'same/'));
        $invalidBegin = $fixture;
        $invalidBegin[] = [
            'artifact_id' => 0,
            'file_type' => 'device',
            'mode' => 420,
            'path' => $this->byteValue('device'),
            'role' => 'module_data',
            'type' => 'artifact_begin',
        ];
        $invalidRegularSize = $fixture;
        $begin = $this->regularArtifactRecords('invalid-size.dat', 'x')[0];
        $begin['size_expected'] = -1;
        $invalidRegularSize[] = $begin;
        $invalidSymlink = $fixture;
        array_push($invalidSymlink, ...$this->symlinkArtifactRecords(0, 'link', "\0"));
        $emptyChunk = $fixture;
        $emptyArtifact = $this->regularArtifactRecords('empty.dat', '');
        $emptyArtifact[1]['data'] = $this->byteValue('');
        array_push($emptyChunk, ...$emptyArtifact);
        $invalidStable = $fixture;
        $artifact = $this->regularArtifactRecords('unstable.dat', 'content');
        $artifact[2]['stable'] = 'yes';
        array_push($invalidStable, ...$artifact);
        $invalidSize = $fixture;
        $artifact = $this->regularArtifactRecords('wrong-size.dat', 'content');
        $artifact[2]['size'] = 6;
        array_push($invalidSize, ...$artifact);
        $invalidEndShape = $fixture;
        $artifact = $this->regularArtifactRecords('invalid-end.dat', 'content');
        $artifact[2]['sha256'] = 'invalid';
        array_push($invalidEndShape, ...$artifact);
        $oversizedChunk = $fixture;
        $oversized = str_repeat('x', 1048577);
        array_push($oversizedChunk, ...$this->regularArtifactRecords('oversized.dat', $oversized));
        $badEnd = $fixture;
        $artifact = $this->regularArtifactRecords('bad.dat', 'content');
        $artifact[2]['sha256'] = str_repeat('0', 64);
        array_push($badEnd, ...$artifact);

        foreach (
            [
                $chunkWithoutBegin,
                $endWithoutBegin,
                $nestedBegin,
                $unfinished,
                $duplicatePath,
                $invalidBegin,
                $invalidRegularSize,
                $invalidSymlink,
                $emptyChunk,
                $invalidStable,
                $invalidSize,
                $invalidEndShape,
                $oversizedChunk,
                $badEnd,
            ] as $records
        ) {
            $stream = $this->stream($records);

            try {
                $this->assertRejected($stream);
            } finally {
                fclose($stream);
            }
        }
    }

    /**
     * Verifies footer member shapes, names, totals, and success semantics.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMalformedFooterVariants(): void
    {
        $records = $this->fixtureRecords();
        /** @var list<callable(array<string, mixed>): array<string, mixed>> $mutations */
        $mutations = [
            static function (array $footer): array {
                $footer['entries'] = -1;

                return $footer;
            },
            static function (array $footer): array {
                $counts = StructuredData::object($footer['counts'] ?? null, 'Footer counts');
                $counts['entry'] = 'two';
                $footer['counts'] = $counts;

                return $footer;
            },
            static function (array $footer): array {
                $counts = StructuredData::object($footer['counts'] ?? null, 'Footer counts');
                $counts['footer'] = 1;
                $footer['counts'] = $counts;

                return $footer;
            },
            static function (array $footer): array {
                $diagnostics = StructuredData::object(
                    $footer['diagnostics'] ?? null,
                    'Footer diagnostics',
                );
                $diagnostics['notice'] = 0;
                $footer['diagnostics'] = $diagnostics;

                return $footer;
            },
            static function (array $footer): array {
                $diagnostics = StructuredData::object(
                    $footer['diagnostics'] ?? null,
                    'Footer diagnostics',
                );
                $diagnostics['warning'] = -1;
                $footer['diagnostics'] = $diagnostics;

                return $footer;
            },
            static function (array $footer): array {
                $footer['artifact_bytes'] = 1;

                return $footer;
            },
            static function (array $footer): array {
                $footer['success'] = false;

                return $footer;
            },
        ];

        foreach ($mutations as $mutation) {
            $stream = $this->mutateFooter($this->stream($records), $mutation);

            try {
                $this->assertRejected($stream);
            } finally {
                fclose($stream);
            }
        }
    }

    /**
     * Verifies an error diagnostic produces a failed native operation.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsNativeOperationWithErrorDiagnostic(): void
    {
        $records = $this->fixtureRecords();
        $records[] = [
            'code' => 'native.error',
            'message' => $this->byteValue('Native failure'),
            'severity' => 'error',
            'type' => 'diagnostic',
        ];
        $stream = $this->stream($records);

        try {
            $this->assertRejected($stream);
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
            $record = StructuredData::object($record, 'Test fixture record');

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
     * Creates a complete directory-artifact record group.
     *
     * @param int $id Artifact identifier.
     * @param string $path Directory path.
     *
     * @return list<array<string, mixed>>
     * @since 1.0.0
     */
    private function directoryArtifactRecords(int $id, string $path): array
    {
        return [
            [
                'artifact_id' => $id,
                'file_type' => 'directory',
                'mode' => 493,
                'path' => $this->byteValue($path),
                'role' => 'module_data',
                'type' => 'artifact_begin',
            ],
            [
                'artifact_id' => $id,
                'sha256' => hash('sha256', ''),
                'size' => 0,
                'type' => 'artifact_end',
            ],
        ];
    }

    /**
     * Creates a complete symbolic-link artifact record group.
     *
     * @param int $id Artifact identifier.
     * @param string $path Link path.
     * @param string $target Exact link target.
     *
     * @return list<array<string, mixed>>
     * @since 1.0.0
     */
    private function symlinkArtifactRecords(int $id, string $path, string $target): array
    {
        return [
            [
                'artifact_id' => $id,
                'file_type' => 'symlink',
                'mode' => 511,
                'path' => $this->byteValue($path),
                'role' => 'module_data',
                'target' => $this->byteValue($target),
                'type' => 'artifact_begin',
            ],
            [
                'artifact_id' => $id,
                'sha256' => hash('sha256', $target),
                'size' => strlen($target),
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
     * Asserts that validation rejects a stream.
     *
     * @param resource $stream Candidate stream.
     * @param class-string<\Throwable> $exception Expected exception.
     *
     * @return void
     * @since 1.0.0
     */
    private function assertRejected(
        mixed $stream,
        string $exception = ContractException::class,
    ): void {
        try {
            (new ContractV1Validator())->validate($stream);
            self::fail('The malformed contract stream was accepted.');
        } catch (\Throwable $caught) {
            self::assertInstanceOf($exception, $caught);
        }
    }

    /**
     * Opens exact raw stream contents.
     *
     * @param string $contents Exact contents.
     *
     * @return resource
     * @since 1.0.0
     */
    private function rawStream(string $contents): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        self::assertSame(strlen($contents), fwrite($stream, $contents));
        rewind($stream);

        return $stream;
    }

    /**
     * Rewrites only the generated footer for one negative test.
     *
     * @param resource $stream Generated contract stream.
     * @param callable(array<string, mixed>): array<string, mixed> $mutation Footer mutation.
     *
     * @return resource
     * @since 1.0.0
     */
    private function mutateFooter(mixed $stream, callable $mutation): mixed
    {
        $contents = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($contents);
        $lines = explode("\n", rtrim($contents, "\n"));
        $last = array_key_last($lines);
        self::assertIsInt($last);
        $footer = StructuredData::object(
            json_decode($lines[$last], true, 512, JSON_THROW_ON_ERROR),
            'Generated footer',
        );
        $lines[$last] = json_encode(
            $mutation($footer),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return $this->rawStream(implode("\n", $lines) . "\n");
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
            if ($type !== 'diagnostic') {
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }

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
