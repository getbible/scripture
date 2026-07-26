<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Snapshot;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Contract\ContractV1ValidatorInterface;
use GetBible\Scripture\Event\EventName;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Filesystem\Folder;

/**
 * Builds and activates immutable translation generations under a process lock.
 *
 * @since 0.1.0
 */
final class TranslationSnapshotManager implements SnapshotManagerInterface
{
    /**
     * Process-local open snapshot objects by module.
     *
     * @var array<string, SnapshotIndex>
     * @since 0.1.0
     */
    private array $snapshots = [];

    /**
     * Creates the snapshot manager.
     *
     * @param Configuration $configuration Runtime configuration.
     * @param ClockInterface $clock Refresh clock.
     * @param ModuleCatalogInterface $catalog Installed module catalog.
     * @param ModuleExtractorInterface $extractor Native stream adapter.
     * @param ContractV1ValidatorInterface $validator Stream validator.
     * @param DispatcherInterface $dispatcher Joomla event dispatcher.
     * @param ModuleRootLockInterface $moduleRootLock SWORD root reader/writer lock.
     *
     * @since 0.1.0
     */
    public function __construct(
        private Configuration $configuration,
        private ClockInterface $clock,
        private ModuleCatalogInterface $catalog,
        private ModuleExtractorInterface $extractor,
        private ContractV1ValidatorInterface $validator,
        private DispatcherInterface $dispatcher,
        private ModuleRootLockInterface $moduleRootLock,
    ) {
    }

    /**
     * Opens a current fresh snapshot or warms a replacement.
     *
     * @param string $module Exact installed module identifier.
     *
     * @return SnapshotIndex
     * @since 0.1.0
     */
    public function get(string $module): SnapshotIndex
    {
        $module = $this->validateModule($module);
        $snapshot = $this->snapshots[$module] ?? $this->openCurrent($module);

        if (
            $snapshot !== null
            && (!$this->configuration->autoRefresh() || !$snapshot->isExpired($this->clock->now()))
        ) {
            return $this->snapshots[$module] = $snapshot;
        }

        return $this->warm($module, false);
    }

    /**
     * Forces a complete installed-module re-export and activation.
     *
     * @param string $module Exact installed module identifier.
     *
     * @return SnapshotIndex
     * @since 0.1.0
     */
    public function refresh(string $module): SnapshotIndex
    {
        $module = $this->validateModule($module);
        $this->dispatcher->dispatch(
            EventName::REFRESH_STARTED,
            new Event(EventName::REFRESH_STARTED, ['module' => $module]),
        );

        try {
            $snapshot = $this->warm($module, true);
            $this->dispatcher->dispatch(
                EventName::REFRESH_COMPLETED,
                new Event(EventName::REFRESH_COMPLETED, ['module' => $module, 'snapshot' => $snapshot]),
            );

            return $snapshot;
        } catch (\Throwable $exception) {
            $this->dispatcher->dispatch(
                EventName::REFRESH_FAILED,
                new Event(EventName::REFRESH_FAILED, ['module' => $module, 'exception' => $exception]),
            );

            throw $exception;
        }
    }

    /**
     * Clears process-local open snapshots.
     *
     * @return void
     * @since 0.1.0
     */
    public function clear(): void
    {
        $this->snapshots = [];
    }

    /**
     * Warms one module under its interprocess lock.
     *
     * @param string $module Exact installed module identifier.
     * @param bool $force Whether an existing fresh snapshot must be replaced.
     *
     * @return SnapshotIndex
     * @since 0.1.0
     */
    private function warm(string $module, bool $force): SnapshotIndex
    {
        $this->catalog->translation($module);
        $moduleRoot = $this->moduleRoot($module);

        if (!Folder::create($moduleRoot . '/generations', 0750)) {
            throw new \RuntimeException(sprintf('Unable to create snapshot root "%s".', $moduleRoot));
        }

        $lock = fopen($moduleRoot . '/warm.lock', 'c+b');

        if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new \RuntimeException(sprintf('Unable to acquire the "%s" snapshot lock.', $module));
        }

        try {
            if (!$force) {
                $current = $this->openCurrent($module);

                if (
                    $current !== null
                    && (!$this->configuration->autoRefresh() || !$current->isExpired($this->clock->now()))
                ) {
                    return $this->snapshots[$module] = $current;
                }
            }

            $this->dispatcher->dispatch(
                EventName::WARM_STARTED,
                new Event(EventName::WARM_STARTED, ['module' => $module]),
            );

            try {
                $snapshot = $this->buildGeneration($module, $moduleRoot);
                $this->snapshots[$module] = $snapshot;
                $this->dispatcher->dispatch(
                    EventName::WARM_COMPLETED,
                    new Event(EventName::WARM_COMPLETED, ['module' => $module, 'snapshot' => $snapshot]),
                );

                return $snapshot;
            } catch (\Throwable $exception) {
                $this->dispatcher->dispatch(
                    EventName::WARM_FAILED,
                    new Event(EventName::WARM_FAILED, ['module' => $module, 'exception' => $exception]),
                );

                throw $exception;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Streams, validates, indexes, and activates one generation.
     *
     * @param string $module Exact installed module identifier.
     * @param string $moduleRoot Module cache root.
     *
     * @return SnapshotIndex
     * @since 0.1.0
     */
    private function buildGeneration(string $module, string $moduleRoot): SnapshotIndex
    {
        $now = $this->clock->now();
        $expires = $now->add($this->configuration->refreshInterval());
        $staging = $moduleRoot . '/generations/.staging-' . getmypid() . '-' . bin2hex(random_bytes(8));

        if (!Folder::create($staging, 0750)) {
            throw new \RuntimeException(sprintf('Unable to create snapshot staging path "%s".', $staging));
        }

        $stream = null;

        try {
            $moduleFile = $staging . '/module.ndjson';
            $stream = fopen($moduleFile, 'w+b');

            if (!is_resource($stream)) {
                throw new \RuntimeException('Unable to create the staged native module stream.');
            }

            $written = $this->moduleRootLock->read(
                fn (): int => $this->extractor->streamModule($module, $stream),
            );

            if (!is_int($written) || $written < 0) {
                throw new ContractException('Native module extraction returned an invalid byte count.');
            }

            if (fflush($stream) === false) {
                throw new \RuntimeException('Unable to flush the staged native module stream.');
            }

            if (function_exists('fsync') && fsync($stream) === false) {
                throw new \RuntimeException('Unable to synchronize the staged native module stream.');
            }

            $stats = fstat($stream);

            if (!is_array($stats) || ($stats['size'] ?? null) !== $written) {
                throw new ContractException('Native module byte count does not match its staged stream.');
            }

            if (fseek($stream, 0) !== 0) {
                throw new \RuntimeException('Unable to rewind the staged native module stream.');
            }

            $observer = new SnapshotRecordObserver();
            $result = $this->validator->validate($stream, $observer);

            if (
                $result->command() !== 'extract'
                || count($result->modules()) !== 1
                || $result->modules()[0]->name()->bytes() !== $module
                || !$result->modules()[0]->isBible()
            ) {
                throw new ContractException('Native extraction does not match the requested Bible module.');
            }

            $index = $observer->index($result->streamSha256(), $now);
            $indexJson = json_encode(
                $index,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
            $this->writeSynchronized($staging . '/index.json', $indexJson);
            fclose($stream);
            $stream = null;

            $generation = $result->streamSha256();
            $destination = $moduleRoot . '/generations/' . $generation;

            if (is_dir($destination)) {
                $snapshot = SnapshotIndex::open($destination, $now, $expires);
                Folder::delete($staging);
            } elseif (!rename($staging, $destination)) {
                throw new \RuntimeException('Unable to atomically commit the staged snapshot generation.');
            } else {
                $snapshot = SnapshotIndex::open($destination, $now, $expires);
            }

            $pointer = [
                'format' => 'getbible.scripture.current/v1',
                'generation' => $generation,
                'activated_at' => $now->format(DATE_ATOM),
                'expires_at' => $expires->format(DATE_ATOM),
            ];
            $pointerJson = json_encode(
                $pointer,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n";
            $pointerTemp = $moduleRoot . '/current.' . bin2hex(random_bytes(8)) . '.tmp';
            $this->writeSynchronized($pointerTemp, $pointerJson);

            if (!rename($pointerTemp, $moduleRoot . '/current.json')) {
                @unlink($pointerTemp);
                throw new \RuntimeException('Unable to atomically activate the snapshot generation.');
            }

            return $snapshot;
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Unable to serialize the snapshot index.', 0, $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (is_dir($staging)) {
                Folder::delete($staging);
            }
        }
    }

    /**
     * Opens the active generation when its pointer is complete and valid.
     *
     * @param string $module Exact installed module identifier.
     *
     * @return SnapshotIndex|null
     * @since 0.1.0
     */
    private function openCurrent(string $module): ?SnapshotIndex
    {
        $moduleRoot = $this->moduleRoot($module);
        $json = @file_get_contents($moduleRoot . '/current.json');

        if ($json === false) {
            return null;
        }

        try {
            $pointer = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (
            !is_array($pointer)
            || ($pointer['format'] ?? null) !== 'getbible.scripture.current/v1'
            || !is_string($pointer['generation'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $pointer['generation']) !== 1
            || !is_string($pointer['activated_at'] ?? null)
            || !is_string($pointer['expires_at'] ?? null)
        ) {
            return null;
        }

        try {
            $activated = new \DateTimeImmutable($pointer['activated_at']);
            $expires = new \DateTimeImmutable($pointer['expires_at']);

            return SnapshotIndex::open(
                $moduleRoot . '/generations/' . $pointer['generation'],
                $activated,
                $expires,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns a traversal-safe per-module cache root.
     *
     * @param string $module Validated module identifier.
     *
     * @return string
     * @since 0.1.0
     */
    private function moduleRoot(string $module): string
    {
        return $this->configuration->cachePath() . '/translations/' . $module;
    }

    /**
     * Validates a traversal-safe native module identifier.
     *
     * @param string $module Candidate identifier.
     *
     * @return string
     * @since 0.1.0
     */
    private function validateModule(string $module): string
    {
        if (
            $module === ''
            || $module === '.'
            || $module === '..'
            || preg_match('/^[A-Za-z0-9_.+-]+$/D', $module) !== 1
        ) {
            throw new \InvalidArgumentException(sprintf('Invalid SWORD module identifier "%s".', $module));
        }

        return $module;
    }

    /**
     * Writes and synchronizes an application-owned file.
     *
     * @param string $path Destination path.
     * @param string $contents Complete file contents.
     *
     * @return void
     * @since 0.1.0
     */
    private function writeSynchronized(string $path, string $contents): void
    {
        $stream = fopen($path, 'xb');

        if (!is_resource($stream)) {
            throw new \RuntimeException(sprintf('Unable to create "%s".', $path));
        }

        try {
            $remaining = $contents;

            while ($remaining !== '') {
                $written = fwrite($stream, $remaining);

                if ($written === false || $written === 0) {
                    throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
                }

                $remaining = substr($remaining, $written);
            }

            if (fflush($stream) === false || (function_exists('fsync') && fsync($stream) === false)) {
                throw new \RuntimeException(sprintf('Unable to synchronize "%s".', $path));
            }
        } finally {
            fclose($stream);
        }
    }
}
