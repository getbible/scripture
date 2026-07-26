<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Infrastructure\Sword;

/**
 * Streams deterministic getBibleSword v1 output from an installed module root.
 *
 * @since 0.1.0
 */
interface ModuleExtractorInterface
{
    /**
     * Returns the resolved native SWORD module root.
     *
     * @return string
     * @since 0.1.0
     */
    public function modulePath(): string;

    /**
     * Streams installed module metadata to a writable PHP resource.
     *
     * @param resource $destination Writable destination.
     *
     * @return int Number of bytes written.
     * @since 0.1.0
     */
    public function streamModules(mixed $destination): int;

    /**
     * Streams one complete installed module to a writable PHP resource.
     *
     * @param string   $module Module identifier.
     * @param resource $destination Writable destination.
     * @param int      $artifactChunkSize Artifact record chunk size.
     *
     * @return int Number of bytes written.
     * @since 0.1.0
     */
    public function streamModule(
        string $module,
        mixed $destination,
        int $artifactChunkSize = 1048576,
    ): int;
}
