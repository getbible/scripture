<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Catalog;

use GetBible\Scripture\Contract\ContractV1ValidatorInterface;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Exception\TranslationNotFoundException;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;

/**
 * Process-local catalog backed by a validated native list stream.
 *
 * @since 0.1.0
 */
final class ModuleCatalog implements ModuleCatalogInterface
{
    /**
     * Cached installed Bible modules.
     *
     * @var list<TranslationMetadata>|null
     * @since 0.1.0
     */
    private ?array $translations = null;

    /**
     * Creates the catalog service.
     *
     * @param ModuleExtractorInterface $extractor Native stream adapter.
     * @param ContractV1ValidatorInterface $validator Stream validator.
     *
     * @since 0.1.0
     */
    public function __construct(
        private ModuleExtractorInterface $extractor,
        private ContractV1ValidatorInterface $validator,
    ) {
    }

    /**
     * Returns all installed Bible modules.
     *
     * @return list<TranslationMetadata>
     * @since 0.1.0
     */
    public function translations(): array
    {
        if ($this->translations !== null) {
            return $this->translations;
        }

        $stream = tmpfile();

        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create a temporary module-list stream.');
        }

        try {
            $written = $this->extractor->streamModules($stream);

            if (fflush($stream) === false || fseek($stream, 0) !== 0) {
                throw new \RuntimeException('Unable to rewind the native module-list stream.');
            }

            $stats = fstat($stream);

            if (!is_array($stats) || ($stats['size'] ?? null) !== $written) {
                throw new ContractException('Native module-list byte count does not match its stream.');
            }

            $result = $this->validator->validate($stream);
        } finally {
            fclose($stream);
        }

        if ($result->command() !== 'list') {
            throw new ContractException('The native module catalog emitted a non-list stream.');
        }

        $this->translations = array_values(array_filter(
            $result->modules(),
            static fn (TranslationMetadata $metadata): bool => $metadata->isBible(),
        ));

        return $this->translations;
    }

    /**
     * Returns one exact installed Bible module.
     *
     * @param string $module Exact native module identifier.
     *
     * @return TranslationMetadata
     * @since 0.1.0
     */
    public function translation(string $module): TranslationMetadata
    {
        foreach ($this->translations() as $translation) {
            if ($translation->name()->bytes() === $module) {
                return $translation;
            }
        }

        throw new TranslationNotFoundException(sprintf(
            'Installed Bible translation "%s" was not found.',
            $module,
        ));
    }

    /**
     * Clears the process-local catalog.
     *
     * @return void
     * @since 0.1.0
     */
    public function clear(): void
    {
        $this->translations = null;
    }
}
