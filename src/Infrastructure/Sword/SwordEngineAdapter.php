<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Sword;

use GetBible\Scripture\Exception\NativeEngineException;

/**
 * Adapts the final GetBible Sword extension engine to an injectable interface.
 *
 * @since 0.1.0
 */
final class SwordEngineAdapter implements ModuleExtractorInterface
{
    /**
     * Supported native C ABI version.
     *
     * @since 0.1.0
     */
    private const ABI_VERSION = 1;

    /**
     * Supported native stream contract.
     *
     * @since 0.1.0
     */
    private const CONTRACT = 'getbiblesword.ndjson/v1';

    /**
     * Native extension engine.
     *
     * @var \GetBible\Sword\Engine
     * @since 0.1.0
     */
    private \GetBible\Sword\Engine $engine;

    /**
     * Creates and verifies the native extension adapter.
     *
     * @param string|null $modulePath Explicit SWORD root.
     *
     * @since 0.1.0
     */
    public function __construct(?string $modulePath = null)
    {
        if (!extension_loaded('getbiblesword') || !class_exists(\GetBible\Sword\Engine::class)) {
            throw new NativeEngineException(
                'The getbiblesword PHP extension is required. Install it with: pie install getbible/sword',
            );
        }

        if (\GetBible\Sword\Engine::abiVersion() !== self::ABI_VERSION) {
            throw new NativeEngineException(sprintf(
                'Unsupported getBibleSword ABI %d; expected %d.',
                \GetBible\Sword\Engine::abiVersion(),
                self::ABI_VERSION,
            ));
        }

        if (\GetBible\Sword\Engine::contractIdentifier() !== self::CONTRACT) {
            throw new NativeEngineException(sprintf(
                'Unsupported getBibleSword contract "%s"; expected "%s".',
                \GetBible\Sword\Engine::contractIdentifier(),
                self::CONTRACT,
            ));
        }

        $this->engine = new \GetBible\Sword\Engine($modulePath);
    }

    /**
     * Returns the native engine's resolved SWORD root.
     *
     * @return string
     * @since 0.1.0
     */
    public function modulePath(): string
    {
        return $this->engine->modulePath();
    }

    /**
     * Streams installed modules and translates native failures.
     *
     * @param resource $destination Writable destination.
     *
     * @return int
     * @since 0.1.0
     */
    public function streamModules(mixed $destination): int
    {
        try {
            return $this->engine->streamModules($destination);
        } catch (\GetBible\Sword\Exception $exception) {
            throw new NativeEngineException(
                'Unable to stream installed SWORD modules: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * Streams an installed module and translates native failures.
     *
     * @param string   $module Module identifier.
     * @param resource $destination Writable destination.
     * @param int      $artifactChunkSize Artifact record chunk size.
     *
     * @return int
     * @since 0.1.0
     */
    public function streamModule(
        string $module,
        mixed $destination,
        int $artifactChunkSize = 1048576,
    ): int {
        try {
            return $this->engine->streamModule($module, $destination, $artifactChunkSize);
        } catch (\GetBible\Sword\Exception $exception) {
            throw new NativeEngineException(
                sprintf('Unable to stream SWORD module "%s": %s', $module, $exception->getMessage()),
                $exception->getCode(),
                $exception,
            );
        }
    }
}
