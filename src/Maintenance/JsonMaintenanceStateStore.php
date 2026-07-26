<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Contract\StructuredData;
use Joomla\Filesystem\Folder;

/**
 * Stores maintenance state as a synchronized, atomically replaced JSON file.
 *
 * @since 0.3.0
 */
final class JsonMaintenanceStateStore implements MaintenanceStateStoreInterface
{
    /**
     * Absolute state-file path.
     *
     * @var string
     * @since 0.3.0
     */
    private string $path;

    /**
     * Creates the durable JSON state store.
     *
     * @param Configuration $configuration Runtime configuration.
     *
     * @since 0.3.0
     */
    public function __construct(Configuration $configuration)
    {
        $this->path = $configuration->maintenanceStatePath();
    }

    /**
     * Loads state or returns empty state when no run has completed.
     *
     * @return MaintenanceState
     * @since 0.3.0
     */
    public function load(): MaintenanceState
    {
        $json = @file_get_contents($this->path);

        if ($json === false) {
            return MaintenanceState::empty();
        }

        try {
            $state = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Unable to decode durable maintenance state.', 0, $exception);
        }

        return MaintenanceState::fromArray(
            StructuredData::object($state, 'Durable maintenance state'),
        );
    }

    /**
     * Atomically replaces the durable maintenance state.
     *
     * @param MaintenanceState $state New state.
     *
     * @return void
     * @since 0.3.0
     */
    public function save(MaintenanceState $state): void
    {
        $directory = dirname($this->path);

        if (!Folder::create($directory, 0750)) {
            throw new \RuntimeException(sprintf('Unable to create maintenance state directory "%s".', $directory));
        }

        try {
            $json = json_encode(
                $state->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n";
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Unable to encode durable maintenance state.', 0, $exception);
        }

        $temporary = $this->path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $stream = fopen($temporary, 'x+b');

        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to create temporary maintenance state.');
        }

        $failure = null;

        try {
            $written = fwrite($stream, $json);

            if (
                $written !== strlen($json)
                || fflush($stream) === false
                || (function_exists('fsync') && fsync($stream) === false)
            ) {
                throw new \RuntimeException('Unable to synchronize temporary maintenance state.');
            }
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            fclose($stream);
        }

        if ($failure !== null) {
            @unlink($temporary);

            throw $failure;
        }

        if (!rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to atomically activate maintenance state.');
        }
    }
}
