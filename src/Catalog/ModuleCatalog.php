<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Catalog;

use GetBible\Scripture\Contract\ContractV1ValidatorInterface;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Exception\TranslationNotFoundException;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Module\ModuleIdentifier;

/**
 * Process-local catalog backed by a validated native list stream.
 *
 * @since 0.1.0
 */
final class ModuleCatalog implements ModuleCatalogInterface
{
    /**
     * Maximum process-local catalog reuse period in nanoseconds.
     *
     * A short bounded cache avoids repeatedly listing modules during one
     * request while ensuring long-running workers observe external changes.
     *
     * @since 1.0.0
     */
    private const CACHE_TTL_NANOSECONDS = 5_000_000_000;

    /**
     * Cached installed Bible modules.
     *
     * @var list<TranslationMetadata>|null
     * @since 0.1.0
     */
    private ?array $translations = null;

    /**
     * Monotonic deadline for the process-local catalog.
     *
     * @var int
     * @since 1.0.0
     */
    private int $expiresAt = 0;

    /**
     * Creates the catalog service.
     *
     * @param ModuleExtractorInterface $extractor Native stream adapter.
     * @param ContractV1ValidatorInterface $validator Stream validator.
     * @param ModuleRootLockInterface $moduleRootLock SWORD root reader/writer lock.
     *
     * @since 0.1.0
     */
    public function __construct(
        private ModuleExtractorInterface $extractor,
        private ContractV1ValidatorInterface $validator,
        private ModuleRootLockInterface $moduleRootLock,
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
        if ($this->translations !== null && hrtime(true) < $this->expiresAt) {
            return $this->translations;
        }

        $stream = tmpfile();

        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create a temporary module-list stream.');
        }

        try {
            $written = $this->moduleRootLock->read(
                fn (): int => $this->extractor->streamModules($stream),
            );

            if (fflush($stream) === false || fseek($stream, 0) !== 0) {
                throw new \RuntimeException('Unable to rewind the native module-list stream.');
            }

            $stats = fstat($stream);

            if (!is_array($stats) || $stats['size'] !== $written) {
                throw new ContractException('Native module-list byte count does not match its stream.');
            }

            $result = $this->validator->validate($stream);
        } finally {
            fclose($stream);
        }

        if ($result->command() !== 'list') {
            throw new ContractException('The native module catalog emitted a non-list stream.');
        }

        $translations = array_values(array_filter(
            $result->modules(),
            static fn (TranslationMetadata $metadata): bool => $metadata->isBible(),
        ));

        $identifiers = [];

        foreach ($translations as $translation) {
            $nativeIdentifier = $translation->name()->bytes();

            try {
                $identifier = ModuleIdentifier::normalize($nativeIdentifier);
            } catch (\InvalidArgumentException $exception) {
                throw new ContractException(
                    'The native catalog contains an unsafe Bible module identifier.',
                    0,
                    $exception,
                );
            }

            if ($identifier !== $nativeIdentifier) {
                throw new ContractException(
                    'The native catalog contains a non-canonical Bible module identifier.',
                );
            }

            if (isset($identifiers[$identifier])) {
                throw new ContractException(sprintf(
                    'The native catalog contains duplicate Bible module "%s".',
                    $identifier,
                ));
            }

            $identifiers[$identifier] = true;
        }

        $this->translations = $translations;
        $this->expiresAt = hrtime(true) + self::CACHE_TTL_NANOSECONDS;

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
        $module = ModuleIdentifier::normalize($module);

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
        $this->expiresAt = 0;
    }
}
