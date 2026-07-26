<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Sword;

use GetBible\Scripture\Contract\StructuredData;

/**
 * Deterministic in-process substitute for native-runtime unit tests.
 *
 * @since 1.0.0
 */
final class Engine
{
    /**
     * Extract fixture path.
     *
     * @var string
     * @since 1.0.0
     */
    public static string $fixturePath = '';

    /**
     * Whether metadata access should fail.
     *
     * @var bool
     * @since 1.0.0
     */
    public static bool $throwMetadata = false;

    /**
     * Creates a fake engine for a resolved module root.
     *
     * @param string|null $modulePath Explicit module root.
     *
     * @since 1.0.0
     */
    public function __construct(private ?string $modulePath = null)
    {
    }

    /**
     * Returns the supported ABI.
     *
     * @return int
     * @since 1.0.0
     */
    public static function abiVersion(): int
    {
        self::guardMetadata();

        return 1;
    }

    /**
     * Returns the supported stream contract.
     *
     * @return string
     * @since 1.0.0
     */
    public static function contractIdentifier(): string
    {
        self::guardMetadata();

        return 'getbiblesword.ndjson/v1';
    }

    /**
     * Returns the embedded product version.
     *
     * @return string
     * @since 1.0.0
     */
    public static function productVersion(): string
    {
        self::guardMetadata();

        return '0.3.0';
    }

    /**
     * Returns the fake resolved SWORD root.
     *
     * @return string
     * @since 1.0.0
     */
    public function modulePath(): string
    {
        return $this->modulePath ?? '/test/sword';
    }

    /**
     * Writes a valid list stream containing the fixture translation.
     *
     * @param resource $destination Writable stream.
     *
     * @return int
     * @since 1.0.0
     */
    public function streamModules(mixed $destination): int
    {
        $lines = file(self::$fixturePath);

        if (!is_array($lines)) {
            throw new \RuntimeException('Unable to read the fake native fixture.');
        }

        $header = StructuredData::object(
            json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR),
            'Fixture header',
        );
        $module = StructuredData::object(
            json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR),
            'Fixture module',
        );
        $header['command'] = 'list';
        unset($header['artifact_chunk_size']);
        $header['sequence'] = 0;
        $module['sequence'] = 1;
        $serialized = [
            json_encode(
                $header,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n",
            json_encode(
                $module,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n",
        ];
        $footer = [
            'artifact_bytes' => 0,
            'artifacts' => 0,
            'counts' => ['header' => 1, 'module' => 1],
            'diagnostics' => ['error' => 0, 'info' => 0, 'warning' => 0],
            'entries' => 0,
            'sequence' => 2,
            'stream_sha256' => hash('sha256', implode('', $serialized)),
            'success' => true,
            'type' => 'footer',
        ];
        $serialized[] = json_encode(
            $footer,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";

        return $this->write($destination, implode('', $serialized));
    }

    /**
     * Writes the complete fixture extraction stream.
     *
     * @param string $module Exact module identifier.
     * @param resource $destination Writable stream.
     * @param int $artifactChunkSize Requested chunk size.
     *
     * @return int
     * @since 1.0.0
     */
    public function streamModule(
        string $module,
        mixed $destination,
        int $artifactChunkSize = 1048576,
    ): int {
        if ($module !== 'TestBible' || $artifactChunkSize < 1) {
            throw new \InvalidArgumentException('Unexpected fake extraction request.');
        }

        $contents = file_get_contents(self::$fixturePath);

        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to read the fake native fixture.');
        }

        return $this->write($destination, $contents);
    }

    /**
     * Throws the configured metadata failure.
     *
     * @return void
     * @since 1.0.0
     */
    private static function guardMetadata(): void
    {
        if (self::$throwMetadata) {
            throw new \RuntimeException('native metadata failed');
        }
    }

    /**
     * Writes complete contents to a destination.
     *
     * @param resource $destination Writable stream.
     * @param string $contents Complete stream bytes.
     *
     * @return int
     * @since 1.0.0
     */
    private function write(mixed $destination, string $contents): int
    {
        $written = fwrite($destination, $contents);

        if ($written !== strlen($contents)) {
            throw new \RuntimeException('Unable to write the fake native stream.');
        }

        return $written;
    }
}
