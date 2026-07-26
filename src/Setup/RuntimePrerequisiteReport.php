<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

/**
 * Immutable compatibility report for the active getBibleSword PHP runtime.
 *
 * @since 1.0.0
 */
final class RuntimePrerequisiteReport
{
    /**
     * Expected stable C ABI.
     *
     * @since 1.0.0
     */
    public const EXPECTED_ABI = 1;

    /**
     * Expected stable stream contract.
     *
     * @since 1.0.0
     */
    public const EXPECTED_CONTRACT = 'getbiblesword.ndjson/v1';

    /**
     * Creates a complete runtime report.
     *
     * @param bool $extensionLoaded Whether the native extension is loaded.
     * @param bool $engineClassAvailable Whether its Engine class exists.
     * @param string|null $extensionVersion PHP extension package version.
     * @param int|null $abiVersion Native C ABI version.
     * @param string|null $contractIdentifier Native stream contract.
     * @param string|null $productVersion Embedded getBibleSword product version.
     * @param string|null $error Inspection failure, when metadata could not be read.
     *
     * @since 1.0.0
     */
    public function __construct(
        private bool $extensionLoaded,
        private bool $engineClassAvailable,
        private ?string $extensionVersion,
        private ?int $abiVersion,
        private ?string $contractIdentifier,
        private ?string $productVersion,
        private ?string $error = null,
    ) {
        if ($error !== null && trim($error) === '') {
            throw new \InvalidArgumentException('A runtime prerequisite error must be non-empty.');
        }
    }

    /**
     * Reports whether the active runtime is compatible.
     *
     * @return bool
     * @since 1.0.0
     */
    public function ready(): bool
    {
        return $this->extensionLoaded
            && $this->engineClassAvailable
            && $this->extensionVersion !== null
            && $this->extensionVersion !== ''
            && $this->abiVersion === self::EXPECTED_ABI
            && $this->contractIdentifier === self::EXPECTED_CONTRACT
            && $this->productVersion !== null
            && $this->productVersion !== ''
            && $this->error === null;
    }

    /**
     * Reports whether the native extension is loaded.
     *
     * @return bool
     * @since 1.0.0
     */
    public function extensionLoaded(): bool
    {
        return $this->extensionLoaded;
    }

    /**
     * Reports whether the native Engine class is available.
     *
     * @return bool
     * @since 1.0.0
     */
    public function engineClassAvailable(): bool
    {
        return $this->engineClassAvailable;
    }

    /**
     * Returns the loaded PHP extension version.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function extensionVersion(): ?string
    {
        return $this->extensionVersion;
    }

    /**
     * Returns the active native ABI version.
     *
     * @return int|null
     * @since 1.0.0
     */
    public function abiVersion(): ?int
    {
        return $this->abiVersion;
    }

    /**
     * Returns the active native contract identifier.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function contractIdentifier(): ?string
    {
        return $this->contractIdentifier;
    }

    /**
     * Returns the embedded getBibleSword product version.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function productVersion(): ?string
    {
        return $this->productVersion;
    }

    /**
     * Returns the inspection failure.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * Returns a deterministic serialization-safe compatibility report.
     *
     * @return array{
     *     ready: bool,
     *     extension_loaded: bool,
     *     engine_class_available: bool,
     *     extension_version: string|null,
     *     abi: array{expected: int, actual: int|null, compatible: bool},
     *     contract: array{expected: string, actual: string|null, compatible: bool},
     *     product_version: string|null,
     *     error: string|null
     * }
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready(),
            'extension_loaded' => $this->extensionLoaded,
            'engine_class_available' => $this->engineClassAvailable,
            'extension_version' => $this->extensionVersion,
            'abi' => [
                'expected' => self::EXPECTED_ABI,
                'actual' => $this->abiVersion,
                'compatible' => $this->abiVersion === self::EXPECTED_ABI,
            ],
            'contract' => [
                'expected' => self::EXPECTED_CONTRACT,
                'actual' => $this->contractIdentifier,
                'compatible' => $this->contractIdentifier === self::EXPECTED_CONTRACT,
            ],
            'product_version' => $this->productVersion,
            'error' => $this->error,
        ];
    }
}
