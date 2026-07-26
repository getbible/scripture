<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Exception\ContractException;

/**
 * Immutable, lossless, verified representation of a v1 byte envelope.
 *
 * @since 0.1.0
 */
final class ByteValue
{
    /**
     * Authoritative decoded bytes.
     *
     * @var string
     * @since 0.1.0
     */
    private string $bytes;

    /**
     * SHA-256 digest of the decoded bytes.
     *
     * @var string
     * @since 0.1.0
     */
    private string $sha256;

    /**
     * Optional exact UTF-8 projection.
     *
     * @var string|null
     * @since 0.1.0
     */
    private ?string $utf8;

    /**
     * Original verified envelope.
     *
     * @var array{base64: string, encoding: string, sha256: string, size: int, utf8?: string}
     * @since 0.1.0
     */
    private array $envelope;

    /**
     * Creates a verified byte value.
     *
     * @param string $bytes Authoritative bytes.
     * @param string $sha256 Verified digest.
     * @param string|null $utf8 Exact UTF-8 projection.
     * @param array{base64: string, encoding: string, sha256: string, size: int, utf8?: string} $envelope
     *        Original envelope.
     *
     * @since 0.1.0
     */
    private function __construct(string $bytes, string $sha256, ?string $utf8, array $envelope)
    {
        $this->bytes = $bytes;
        $this->sha256 = $sha256;
        $this->utf8 = $utf8;
        $this->envelope = $envelope;
    }

    /**
     * Validates and creates a byte value from a decoded JSON object.
     *
     * @param array<string, mixed> $value Candidate envelope.
     * @param string $context Human-readable field context.
     *
     * @return self
     * @since 0.1.0
     */
    public static function fromArray(array $value, string $context = 'byte value'): self
    {
        $allowed = ['base64', 'encoding', 'sha256', 'size', 'utf8'];
        $unknown = array_diff(array_keys($value), $allowed);

        if ($unknown !== []) {
            throw new ContractException(sprintf(
                '%s contains unsupported member(s): %s.',
                $context,
                implode(', ', $unknown),
            ));
        }

        $base64 = $value['base64'] ?? null;
        $encoding = $value['encoding'] ?? null;
        $sha256 = $value['sha256'] ?? null;
        $size = $value['size'] ?? null;
        $utf8 = $value['utf8'] ?? null;

        if (!is_string($base64) || $encoding !== 'base64') {
            throw new ContractException(sprintf('%s has an invalid Base64 encoding envelope.', $context));
        }

        if (!is_string($sha256) || preg_match('/^[0-9a-f]{64}$/D', $sha256) !== 1) {
            throw new ContractException(sprintf('%s has an invalid SHA-256 digest.', $context));
        }

        if (!is_int($size) || $size < 0) {
            throw new ContractException(sprintf('%s has an invalid decoded size.', $context));
        }

        if ($utf8 !== null && !is_string($utf8)) {
            throw new ContractException(sprintf('%s has a non-string UTF-8 projection.', $context));
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false || base64_encode($bytes) !== $base64) {
            throw new ContractException(sprintf('%s is not canonical Base64.', $context));
        }

        if (strlen($bytes) !== $size) {
            throw new ContractException(sprintf('%s decoded size does not match its envelope.', $context));
        }

        $actualHash = hash('sha256', $bytes);

        if (!hash_equals($sha256, $actualHash)) {
            throw new ContractException(sprintf('%s decoded digest does not match its envelope.', $context));
        }

        if ($utf8 !== null && $utf8 !== $bytes) {
            throw new ContractException(sprintf('%s UTF-8 projection is not byte-identical.', $context));
        }

        /** @var array{base64: string, encoding: string, sha256: string, size: int, utf8?: string} $value */
        return new self($bytes, $sha256, $utf8, $value);
    }

    /**
     * Returns the authoritative decoded bytes.
     *
     * @return string
     * @since 0.1.0
     */
    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * Returns the optional exact UTF-8 projection.
     *
     * @return string|null
     * @since 0.1.0
     */
    public function utf8(): ?string
    {
        return $this->utf8;
    }

    /**
     * Returns the exact UTF-8 projection or throws when it is unavailable.
     *
     * @return string
     * @since 0.1.0
     */
    public function requireUtf8(): string
    {
        if ($this->utf8 === null) {
            throw new \UnexpectedValueException('This byte value has no exact UTF-8 projection.');
        }

        return $this->utf8;
    }

    /**
     * Returns the decoded byte length.
     *
     * @return int
     * @since 0.1.0
     */
    public function size(): int
    {
        return strlen($this->bytes);
    }

    /**
     * Returns the verified SHA-256 digest.
     *
     * @return string
     * @since 0.1.0
     */
    public function sha256(): string
    {
        return $this->sha256;
    }

    /**
     * Returns the original verified envelope.
     *
     * @return array{base64: string, encoding: string, sha256: string, size: int, utf8?: string}
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return $this->envelope;
    }
}
