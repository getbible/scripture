<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Contract;

use GetBible\Scripture\Domain\TranslationMetadata;

/**
 * Immutable summary of a successfully validated complete v1 stream.
 *
 * @since 0.1.0
 */
final class ValidationResult
{
    /**
     * Creates a complete validation result.
     *
     * @param string $command Native command.
     * @param list<TranslationMetadata> $modules Ordered module metadata.
     * @param array<string, mixed> $footer Validated footer record.
     *
     * @since 0.1.0
     */
    public function __construct(
        private string $command,
        private array $modules,
        private array $footer,
    ) {
    }

    /**
     * Returns `list` or `extract`.
     *
     * @return string
     * @since 0.1.0
     */
    public function command(): string
    {
        return $this->command;
    }

    /**
     * Returns ordered validated module metadata.
     *
     * @return list<TranslationMetadata>
     * @since 0.1.0
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * Returns the validated footer record.
     *
     * @return array<string, mixed>
     * @since 0.1.0
     */
    public function footer(): array
    {
        return $this->footer;
    }

    /**
     * Returns the verified pre-footer stream digest.
     *
     * @return string
     * @since 0.1.0
     */
    public function streamSha256(): string
    {
        $sha256 = $this->footer['stream_sha256'] ?? null;

        if (!is_string($sha256)) {
            throw new \LogicException('Validated footer state has no stream digest.');
        }

        return $sha256;
    }
}
