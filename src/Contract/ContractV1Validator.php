<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Domain\ConfigEntry;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Exception\ContractException;

/**
 * Independent incremental validator for getBibleSword NDJSON contract v1.
 *
 * @since 0.1.0
 */
final class ContractV1Validator implements ContractV1ValidatorInterface
{
    /**
     * Exact accepted contract identifier.
     *
     * @since 0.1.0
     */
    public const CONTRACT = 'getbiblesword.ndjson/v1';

    /**
     * Maximum serialized line, covering the native maximum artifact chunk.
     *
     * @since 0.1.0
     */
    private const MAX_LINE_BYTES = 67108864;

    /**
     * Accepted record phases, excluding interspersed diagnostics.
     *
     * @var array<string, int>
     * @since 0.1.0
     */
    private const PHASES = [
        'header' => 0,
        'module' => 1,
        'config_source' => 2,
        'config_entry' => 3,
        'entry' => 4,
        'artifact_begin' => 5,
        'artifact_chunk' => 5,
        'artifact_end' => 5,
        'footer' => 6,
    ];

    /**
     * Validates one complete operation stream.
     *
     * @param resource $stream Readable stream.
     * @param RecordObserverInterface|null $observer Optional validated-record observer.
     *
     * @return ValidationResult
     * @since 0.1.0
     */
    public function validate(mixed $stream, ?RecordObserverInterface $observer = null): ValidationResult
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \InvalidArgumentException('Contract validation requires a PHP stream resource.');
        }

        $metadata = stream_get_meta_data($stream);

        if ($metadata['mode'] === '' || !strpbrk($metadata['mode'], 'r+')) {
            throw new \InvalidArgumentException('Contract validation requires a readable stream.');
        }

        $streamHash = hash_init('sha256');
        $expectedSequence = 0;
        $phase = -1;
        $command = null;
        $footer = null;
        $modules = [];
        $recordCounts = [];
        /** @var array{error: int, info: int, warning: int} $diagnostics */
        $diagnostics = ['error' => 0, 'info' => 0, 'warning' => 0];
        $entryOrdinal = 0;
        $configSourceOrdinal = 0;
        $configEntryOrdinal = 0;
        $artifactId = 0;
        $artifactBytes = 0;
        $activeArtifact = null;
        $artifactChunkSize = null;
        $artifactPaths = [];

        while (!feof($stream)) {
            $offset = ftell($stream);

            if (!is_int($offset)) {
                throw new ContractException('Unable to determine the NDJSON stream position.');
            }

            $line = fgets($stream, self::MAX_LINE_BYTES + 1);

            if ($line === false) {
                if (feof($stream)) {
                    break;
                }

                throw new ContractException('Unable to read the NDJSON stream.');
            }

            $length = strlen($line);

            if ($length === 0 || $line[$length - 1] !== "\n") {
                throw new ContractException(sprintf(
                    'NDJSON record at byte %d is truncated or exceeds %d bytes.',
                    $offset,
                    self::MAX_LINE_BYTES,
                ));
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ContractException(
                    sprintf('Invalid JSON record at byte %d: %s', $offset, $exception->getMessage()),
                    0,
                    $exception,
                );
            }

            $record = StructuredData::object(
                $record,
                sprintf('Record at byte %d', $offset),
            );

            $type = $record['type'] ?? null;
            $sequence = $record['sequence'] ?? null;

            if (!is_string($type) || !is_int($sequence) || $sequence !== $expectedSequence) {
                throw new ContractException(sprintf(
                    'Record at byte %d has invalid sequence or type; expected sequence %d.',
                    $offset,
                    $expectedSequence,
                ));
            }

            if (!isset(self::PHASES[$type]) && $type !== 'diagnostic') {
                throw new ContractException(sprintf('Unsupported v1 record type "%s".', $type));
            }

            if ($footer !== null) {
                throw new ContractException('A record appears after the footer.');
            }

            if ($type !== 'diagnostic') {
                $nextPhase = self::PHASES[$type];

                if ($nextPhase < $phase) {
                    throw new ContractException(sprintf('Record type "%s" appears out of phase.', $type));
                }

                $phase = $nextPhase;
            }

            if ($expectedSequence === 0 && $type !== 'header') {
                throw new ContractException('The first record must be a header.');
            }

            switch ($type) {
                case 'header':
                    if ($expectedSequence !== 0) {
                        throw new ContractException('The stream contains more than one header.');
                    }

                    $command = $this->validateHeader($record);
                    $artifactChunkSize = $command === 'extract' ? $record['artifact_chunk_size'] : null;
                    break;

                case 'module':
                    if ($command === 'extract' && $modules !== []) {
                        throw new ContractException('An extract stream contains more than one module record.');
                    }

                    $modules[] = TranslationMetadata::fromRecord($record);
                    break;

                case 'config_source':
                    $this->requireExtractRecord($command, count($modules), $type);
                    $this->validateConfigSource($record, $configSourceOrdinal);
                    ++$configSourceOrdinal;
                    break;

                case 'config_entry':
                    $this->requireExtractRecord($command, count($modules), $type);
                    $entry = ConfigEntry::fromRecord($record);

                    if ($entry->ordinal() !== $configEntryOrdinal) {
                        throw new ContractException('Configuration entry ordinals are not consecutive.');
                    }

                    ++$configEntryOrdinal;
                    break;

                case 'entry':
                    $this->requireExtractRecord($command, count($modules), $type);
                    $this->validateEntry($record, $entryOrdinal);
                    ++$entryOrdinal;
                    break;

                case 'artifact_begin':
                    $this->requireExtractRecord($command, count($modules), $type);

                    if ($activeArtifact !== null) {
                        throw new ContractException('An artifact begins before the active artifact ends.');
                    }

                    if (!is_int($artifactChunkSize)) {
                        throw new ContractException('An artifact appears without an extract chunk size.');
                    }

                    $activeArtifact = $this->validateArtifactBegin(
                        $record,
                        $artifactId,
                        $artifactChunkSize,
                    );

                    if (isset($artifactPaths[$activeArtifact['path']])) {
                        throw new ContractException('Artifact paths must be unique.');
                    }

                    $artifactPaths[$activeArtifact['path']] = true;
                    break;

                case 'artifact_chunk':
                    if ($activeArtifact === null) {
                        throw new ContractException('An artifact chunk appears without an active artifact.');
                    }

                    $bytes = $this->validateArtifactChunk($record, $activeArtifact);
                    hash_update($activeArtifact['hash'], $bytes);
                    $activeArtifact['size'] += strlen($bytes);
                    $activeArtifact['chunk']++;

                    if (strlen($bytes) < $activeArtifact['chunk_size']) {
                        $activeArtifact['short_chunk_seen'] = true;
                    }
                    break;

                case 'artifact_end':
                    if ($activeArtifact === null) {
                        throw new ContractException('An artifact ends without an active artifact.');
                    }

                    $this->validateArtifactEnd($record, $activeArtifact);
                    $artifactBytes += $activeArtifact['size'];
                    $activeArtifact = null;
                    ++$artifactId;
                    break;

                case 'diagnostic':
                    if ($activeArtifact !== null) {
                        throw new ContractException('A diagnostic splits an active artifact group.');
                    }

                    $severity = $this->validateDiagnostic($record);
                    ++$diagnostics[$severity];
                    break;

                case 'footer':
                    if ($activeArtifact !== null) {
                        throw new ContractException('The footer interrupts an active artifact.');
                    }

                    $this->validateFooterShape($record);
                    $footer = $record;
                    break;
            }

            if ($type !== 'footer') {
                $this->validateEmbeddedByteValues($record, $type);
                hash_update($streamHash, $line);
                $recordCounts[$type] = ($recordCounts[$type] ?? 0) + 1;
            }

            $observer?->onRecord($record, $offset, $length);
            ++$expectedSequence;
        }

        if ($command === null || $footer === null) {
            throw new ContractException('The NDJSON stream is missing its header or footer.');
        }

        if ($command === 'extract' && $footer['success'] === true && count($modules) !== 1) {
            throw new ContractException('A successful extract stream requires exactly one module record.');
        }

        $this->validateFooterTotals(
            $footer,
            $recordCounts,
            $diagnostics,
            $entryOrdinal,
            $artifactId,
            $artifactBytes,
            hash_final($streamHash),
        );

        if ($footer['success'] !== true) {
            throw new ContractException(sprintf(
                'The native operation completed with success=false and %d error diagnostic(s).',
                $diagnostics['error'],
            ));
        }

        return new ValidationResult($command, $modules, $footer);
    }

    /**
     * Validates the header and returns its command.
     *
     * @param array<string, mixed> $record Header record.
     *
     * @return 'extract'|'list'
     * @since 0.1.0
     */
    private function validateHeader(array $record): string
    {
        $command = $record['command'] ?? null;

        if (!is_string($command) || !in_array($command, ['list', 'extract'], true)) {
            throw new ContractException('Header command must be "list" or "extract".');
        }

        if (
            ($record['contract'] ?? null) !== self::CONTRACT
            || ($record['contract_version'] ?? null) !== 1
            || ($record['deterministic'] ?? null) !== true
            || ($record['producer'] ?? null) !== 'getBibleSword'
            || !is_string($record['producer_version'] ?? null)
            || !is_string($record['sword_version'] ?? null)
        ) {
            throw new ContractException('Header compatibility or producer metadata is invalid.');
        }

        if ($command === 'extract') {
            $chunkSize = $record['artifact_chunk_size'] ?? null;

            if (!is_int($chunkSize) || $chunkSize < 4096 || $chunkSize > 16777216) {
                throw new ContractException('Extract header artifact chunk size is invalid.');
            }
        }

        return $command;
    }

    /**
     * Requires an extract-only record to follow exactly one module record.
     *
     * @param string|null $command Active stream command.
     * @param int $moduleCount Observed module records.
     * @param string $recordType Candidate record type.
     *
     * @return void
     * @since 0.1.0
     */
    private function requireExtractRecord(?string $command, int $moduleCount, string $recordType): void
    {
        if ($command !== 'extract') {
            throw new ContractException(sprintf('%s is not permitted in a list stream.', $recordType));
        }

        if ($moduleCount !== 1) {
            throw new ContractException(sprintf('%s requires one preceding module record.', $recordType));
        }
    }

    /**
     * Validates a raw configuration source record.
     *
     * @param array<string, mixed> $record Configuration source.
     * @param int $expectedOrdinal Expected source order.
     *
     * @return void
     * @since 0.1.0
     */
    private function validateConfigSource(array $record, int $expectedOrdinal): void
    {
        if (
            ($record['ordinal'] ?? null) !== $expectedOrdinal
            || !is_array($record['path'] ?? null)
            || !is_array($record['raw'] ?? null)
        ) {
            throw new ContractException('Configuration source record is invalid or out of order.');
        }

        $path = ByteValue::fromArray($record['path'], 'config_source.path')->bytes();
        $this->validateRelativePath($path, false, 'config_source.path');
        ByteValue::fromArray($record['raw'], 'config_source.raw');
    }

    /**
     * Validates one logical entry record.
     *
     * @param array<string, mixed> $record Entry record.
     * @param int $expectedOrdinal Expected traversal ordinal.
     *
     * @return void
     * @since 0.1.0
     */
    private function validateEntry(array $record, int $expectedOrdinal): void
    {
        if (
            ($record['ordinal'] ?? null) !== $expectedOrdinal
            || !is_array($record['key'] ?? null)
            || !is_array($record['raw'] ?? null)
            || !is_array($record['scope'] ?? null)
            || !is_array($record['annotation_segments'] ?? null)
            || !is_array($record['official_attributes'] ?? null)
            || !is_bool($record['projections_available'] ?? null)
        ) {
            throw new ContractException('Entry record is invalid or out of order.');
        }

        ByteValue::fromArray($record['key'], 'entry.key');
        $raw = ByteValue::fromArray($record['raw'], 'entry.raw');
        $scope = $record['scope'];

        if (($scope['type'] ?? null) === 'verse_key') {
            VerseScope::fromArray($scope);
        } elseif (($scope['type'] ?? null) === 'sword_key') {
            if (!is_int($scope['index'] ?? null) || $scope['index'] < 0) {
                throw new ContractException('Generic SWORD scope index is invalid.');
            }
        } else {
            throw new ContractException('Entry scope type is invalid.');
        }

        $reconstructed = '';

        foreach ($record['annotation_segments'] as $index => $segment) {
            if (!is_array($segment)) {
                throw new ContractException(sprintf('Annotation segment %d is invalid.', $index));
            }

            $reconstructed .= AnnotationSegment::fromArray($segment, $index)->raw()->bytes();
        }

        if ($reconstructed !== $raw->bytes()) {
            throw new ContractException('Annotation segments do not reconstruct entry.raw.');
        }

        OfficialAttributes::fromArray($record['official_attributes']);
        $available = $record['projections_available'];

        foreach (['rendered_default', 'stripped'] as $field) {
            $value = $record[$field] ?? null;

            if ($available && !is_array($value)) {
                throw new ContractException(sprintf('entry.%s must be a byte value.', $field));
            }

            if (!$available && $value !== null) {
                throw new ContractException(sprintf('entry.%s must be null.', $field));
            }

            if (is_array($value)) {
                ByteValue::fromArray($value, 'entry.' . $field);
            }
        }
    }

    /**
     * Validates an artifact begin record and creates active state.
     *
     * @param array<string, mixed> $record Artifact begin.
     * @param int $expectedId Expected artifact identifier.
     * @param int $chunkSize Header-declared maximum artifact chunk size.
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     hash: \HashContext,
     *     path: string,
     *     size: int,
     *     expected_size: int|null,
     *     chunk_size: int,
     *     short_chunk_seen: bool,
     *     chunk: int
     * }
     * @since 0.1.0
     */
    private function validateArtifactBegin(array $record, int $expectedId, int $chunkSize): array
    {
        $type = $record['file_type'] ?? null;
        $mode = $record['mode'] ?? null;
        $role = $record['role'] ?? null;

        if (
            ($record['artifact_id'] ?? null) !== $expectedId
            || !is_string($type)
            || !in_array($type, ['regular', 'directory', 'symlink'], true)
            || !is_int($mode)
            || $mode < 0
            || $mode > 4095
            || !is_string($role)
            || !is_array($record['path'] ?? null)
        ) {
            throw new ContractException('Artifact begin record is invalid or out of order.');
        }

        $path = ByteValue::fromArray($record['path'], 'artifact_begin.path')->bytes();
        $pathKey = $this->validateRelativePath(
            $path,
            $type === 'directory',
            'artifact_begin.path',
        );
        $hash = hash_init('sha256');
        $size = 0;
        $expectedSize = null;

        if ($type === 'regular') {
            if (!is_int($record['size_expected'] ?? null) || $record['size_expected'] < 0) {
                throw new ContractException('Regular artifact expected size is invalid.');
            }

            $expectedSize = $record['size_expected'];
        } elseif ($type === 'symlink') {
            if (!is_array($record['target'] ?? null)) {
                throw new ContractException('Symlink artifact target is invalid.');
            }

            $target = ByteValue::fromArray($record['target'], 'artifact_begin.target')->bytes();

            if (str_contains($target, "\0")) {
                throw new ContractException('Symlink artifact target contains a NUL byte.');
            }

            hash_update($hash, $target);
            $size = strlen($target);
        }

        return [
            'id' => $expectedId,
            'type' => $type,
            'hash' => $hash,
            'path' => $pathKey,
            'size' => $size,
            'expected_size' => $expectedSize,
            'chunk_size' => $chunkSize,
            'short_chunk_seen' => false,
            'chunk' => 0,
        ];
    }

    /**
     * Validates an artifact data chunk.
     *
     * @param array<string, mixed> $record Artifact chunk.
     * @param array{
     *     id: int,
     *     type: string,
     *     hash: \HashContext,
     *     path: string,
     *     size: int,
     *     expected_size: int|null,
     *     chunk_size: int,
     *     short_chunk_seen: bool,
     *     chunk: int
     * } $state Active artifact.
     *
     * @return string Decoded chunk bytes.
     * @since 0.1.0
     */
    private function validateArtifactChunk(array $record, array $state): string
    {
        if (
            $state['type'] !== 'regular'
            || ($record['artifact_id'] ?? null) !== $state['id']
            || ($record['index'] ?? null) !== $state['chunk']
            || !is_array($record['data'] ?? null)
            || $state['short_chunk_seen']
        ) {
            throw new ContractException('Artifact chunk is invalid or out of order.');
        }

        $bytes = ByteValue::fromArray($record['data'], 'artifact_chunk.data')->bytes();

        if ($bytes === '') {
            throw new ContractException('Artifact chunks must not be empty.');
        }

        if (strlen($bytes) > $state['chunk_size']) {
            throw new ContractException('Artifact chunk exceeds the extract chunk size.');
        }

        return $bytes;
    }

    /**
     * Validates an artifact end record against accumulated state.
     *
     * @param array<string, mixed> $record Artifact end.
     * @param array{
     *     id: int,
     *     type: string,
     *     hash: \HashContext,
     *     path: string,
     *     size: int,
     *     expected_size: int|null,
     *     chunk_size: int,
     *     short_chunk_seen: bool,
     *     chunk: int
     * } $state Active artifact.
     *
     * @return void
     * @since 0.1.0
     */
    private function validateArtifactEnd(array $record, array $state): void
    {
        $size = $record['size'] ?? null;
        $sha256 = $record['sha256'] ?? null;

        if (
            ($record['artifact_id'] ?? null) !== $state['id']
            || !is_int($size)
            || $size < 0
            || !is_string($sha256)
            || preg_match('/^[0-9a-f]{64}$/D', $sha256) !== 1
        ) {
            throw new ContractException('Artifact end record is invalid.');
        }

        $stable = null;

        if ($state['type'] === 'regular') {
            $stable = $record['stable'] ?? null;

            if (!is_bool($stable)) {
                throw new ContractException('Regular artifact stability flag is invalid.');
            }
        }

        $actualHash = hash_final($state['hash']);

        if ($size !== $state['size']) {
            throw new ContractException('Artifact end size does not match accumulated data.');
        }

        if ($stable === true && $size !== $state['expected_size']) {
            throw new ContractException('Stable artifact size does not match its expected size.');
        }

        if (!hash_equals($sha256, $actualHash)) {
            throw new ContractException('Artifact end digest does not match accumulated data.');
        }
    }

    /**
     * Validates a diagnostic and returns its severity.
     *
     * @param array<string, mixed> $record Diagnostic record.
     *
     * @return 'error'|'info'|'warning'
     * @since 0.1.0
     */
    private function validateDiagnostic(array $record): string
    {
        $severity = $record['severity'] ?? null;

        if (
            !is_string($record['code'] ?? null)
            || !is_string($severity)
            || !in_array($severity, ['error', 'info', 'warning'], true)
            || !is_array($record['message'] ?? null)
        ) {
            throw new ContractException('Diagnostic record is invalid.');
        }

        ByteValue::fromArray($record['message'], 'diagnostic.message');

        return $severity;
    }

    /**
     * Validates the footer's standalone member shapes.
     *
     * @param array<string, mixed> $footer Footer record.
     *
     * @return void
     * @since 0.1.0
     */
    private function validateFooterShape(array $footer): void
    {
        if (
            !is_array($footer['counts'] ?? null)
            || !is_array($footer['diagnostics'] ?? null)
            || !is_int($footer['entries'] ?? null)
            || $footer['entries'] < 0
            || !is_int($footer['artifacts'] ?? null)
            || $footer['artifacts'] < 0
            || !is_int($footer['artifact_bytes'] ?? null)
            || $footer['artifact_bytes'] < 0
            || !is_bool($footer['success'] ?? null)
            || !is_string($footer['stream_sha256'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $footer['stream_sha256']) !== 1
        ) {
            throw new ContractException('Footer record shape is invalid.');
        }
    }

    /**
     * Verifies footer totals and digest against observed stream state.
     *
     * @param array<string, mixed> $footer Footer record.
     * @param array<string, int> $recordCounts Observed non-footer counts.
     * @param array{error: int, info: int, warning: int} $diagnostics Observed diagnostics.
     * @param int $entries Observed logical entries.
     * @param int $artifacts Observed completed artifacts.
     * @param int $artifactBytes Observed regular and symlink artifact bytes.
     * @param string $streamSha256 Observed pre-footer digest.
     *
     * @return void
     * @since 0.1.0
     */
    private function validateFooterTotals(
        array $footer,
        array $recordCounts,
        array $diagnostics,
        int $entries,
        int $artifacts,
        int $artifactBytes,
        string $streamSha256,
    ): void {
        $footerCounts = [];
        $serializedCounts = StructuredData::object($footer['counts'] ?? null, 'Footer counts');

        foreach ($serializedCounts as $type => $count) {
            if (!is_int($count) || $count < 0) {
                throw new ContractException('Footer record counts are invalid.');
            }

            $footerCounts[$type] = $count;
        }

        foreach (array_keys($footerCounts) as $type) {
            if (!array_key_exists($type, self::PHASES) || $type === 'footer') {
                throw new ContractException('Footer counts contain an unsupported record type.');
            }
        }

        ksort($footerCounts);
        ksort($recordCounts);

        $footerDiagnostics = [];
        $serializedDiagnostics = StructuredData::object(
            $footer['diagnostics'] ?? null,
            'Footer diagnostics',
        );

        foreach (['error', 'info', 'warning'] as $severity) {
            $count = $serializedDiagnostics[$severity] ?? null;

            if (!is_int($count) || $count < 0) {
                throw new ContractException('Footer diagnostic counts are invalid.');
            }

            $footerDiagnostics[$severity] = $count;
        }

        if (count($serializedDiagnostics) !== count($footerDiagnostics)) {
            throw new ContractException('Footer diagnostics contain unsupported severities.');
        }

        $footerStreamSha256 = $footer['stream_sha256'] ?? null;

        if (!is_string($footerStreamSha256)) {
            throw new ContractException('Footer stream digest is invalid.');
        }

        if (
            $footerCounts !== $recordCounts
            || $footerDiagnostics !== $diagnostics
            || $footer['entries'] !== $entries
            || $footer['artifacts'] !== $artifacts
            || $footer['artifact_bytes'] !== $artifactBytes
            || !hash_equals($footerStreamSha256, $streamSha256)
        ) {
            throw new ContractException('Footer totals or stream digest do not match observed records.');
        }

        if (($diagnostics['error'] > 0) !== ($footer['success'] === false)) {
            throw new ContractException('Footer success does not agree with error diagnostics.');
        }
    }

    /**
     * Validates byte envelopes in additive fields that the consumer does not interpret.
     *
     * @param mixed $value Candidate nested value.
     * @param string $context Human-readable member path.
     *
     * @return void
     * @since 0.1.0
     */
    private function validateEmbeddedByteValues(mixed $value, string $context): void
    {
        if (!is_array($value)) {
            return;
        }

        if (array_key_exists('base64', $value) || ($value['encoding'] ?? null) === 'base64') {
            ByteValue::fromArray($value, $context);

            return;
        }

        foreach ($value as $key => $child) {
            $this->validateEmbeddedByteValues(
                $child,
                is_int($key) ? sprintf('%s[%d]', $context, $key) : $context . '.' . $key,
            );
        }
    }

    /**
     * Validates and normalizes a safe root-relative byte path.
     *
     * @param string $path Exact path bytes.
     * @param bool $allowTrailingSeparator Whether a directory may end in slash.
     * @param string $context Human-readable member path.
     *
     * @return string Normalized path bytes suitable as an identity key.
     * @since 0.1.0
     */
    private function validateRelativePath(string $path, bool $allowTrailingSeparator, string $context): string
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw new ContractException(sprintf('%s is not a safe root-relative path.', $context));
        }

        $normalized = $allowTrailingSeparator && str_ends_with($path, '/')
            ? substr($path, 0, -1)
            : $path;

        foreach (explode('/', $normalized) as $component) {
            if ($component === '' || $component === '.' || $component === '..') {
                throw new ContractException(sprintf('%s is not a normalized relative path.', $context));
            }
        }

        return $normalized;
    }
}
