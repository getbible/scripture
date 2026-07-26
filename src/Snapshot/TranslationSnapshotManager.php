<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Snapshot;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Contract\ContractV1ValidatorInterface;
use GetBible\Scripture\Event\EventName;
use GetBible\Scripture\Event\LifecycleEventDispatcher;
use GetBible\Scripture\Exception\ContractException;
use GetBible\Scripture\Infrastructure\Lock\BoundedFileLock;
use GetBible\Scripture\Infrastructure\Lock\GenerationLease;
use GetBible\Scripture\Infrastructure\Sword\ModuleExtractorInterface;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Module\ModuleIdentifier;
use Joomla\Event\DispatcherInterface;
use Joomla\Filesystem\Folder;

/**
 * Builds and activates immutable translation generations under a process lock.
 *
 * @since 0.1.0
 */
final class TranslationSnapshotManager implements SnapshotManagerInterface
{
    /**
     * Number of unleased immutable generations retained per module.
     *
     * @since 1.0.0
     */
    private const RETAIN_GENERATIONS = 2;

    /**
     * Age after which interrupted staging and pointer files are scavenged.
     *
     * @since 1.0.0
     */
    private const STALE_TEMPORARY_SECONDS = 3600;

    /**
     * Maximum process-local open snapshot readers.
     *
     * @since 1.0.0
     */
    private const SNAPSHOT_CACHE_LIMIT = 32;

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
        $module = ModuleIdentifier::normalize($module);
        $snapshot = $this->openCurrent($module, $this->snapshots[$module] ?? null);

        if (
            $snapshot !== null
            && (!$this->configuration->autoRefresh() || !$snapshot->isExpired($this->clock->now()))
        ) {
            return $this->remember($module, $snapshot);
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
        $module = ModuleIdentifier::normalize($module);
        LifecycleEventDispatcher::dispatch(
            $this->dispatcher,
            EventName::REFRESH_STARTED,
            ['module' => $module],
        );

        try {
            $snapshot = $this->warm($module, true);
            LifecycleEventDispatcher::dispatch(
                $this->dispatcher,
                EventName::REFRESH_COMPLETED,
                ['module' => $module, 'snapshot' => $snapshot],
            );

            return $snapshot;
        } catch (\Throwable $exception) {
            LifecycleEventDispatcher::dispatch(
                $this->dispatcher,
                EventName::REFRESH_FAILED,
                ['module' => $module, 'exception' => $exception],
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

        $lock = new BoundedFileLock(
            $moduleRoot . '/warm.lock',
            $this->configuration->lockTimeout(),
        );

        return $lock->synchronized(LOCK_EX, function () use ($module, $moduleRoot, $force): SnapshotIndex {
            $this->scavengeInterruptedWrites($moduleRoot);

            if (!$force) {
                $current = $this->openCurrent($module, $this->snapshots[$module] ?? null);

                if (
                    $current !== null
                    && (!$this->configuration->autoRefresh() || !$current->isExpired($this->clock->now()))
                ) {
                    return $this->remember($module, $current);
                }

                if ($current === null) {
                    $recovered = $this->recoverGeneration($module, $moduleRoot);

                    if (
                        $recovered !== null
                        && (
                            !$this->configuration->autoRefresh()
                            || !$recovered->isExpired($this->clock->now())
                        )
                    ) {
                        $this->activate($moduleRoot, $recovered);

                        return $this->remember($module, $recovered);
                    }
                }
            }

            LifecycleEventDispatcher::dispatch(
                $this->dispatcher,
                EventName::WARM_STARTED,
                ['module' => $module],
            );

            try {
                $snapshot = $this->buildGeneration($module, $moduleRoot);
                $this->remember($module, $snapshot);
                LifecycleEventDispatcher::dispatch(
                    $this->dispatcher,
                    EventName::WARM_COMPLETED,
                    ['module' => $module, 'snapshot' => $snapshot],
                );

                return $snapshot;
            } catch (\Throwable $exception) {
                LifecycleEventDispatcher::dispatch(
                    $this->dispatcher,
                    EventName::WARM_FAILED,
                    ['module' => $module, 'exception' => $exception],
                );

                throw $exception;
            }
        });
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

            if ($written < 0) {
                throw new ContractException('Native module extraction returned an invalid byte count.');
            }

            if (fflush($stream) === false) {
                throw new \RuntimeException('Unable to flush the staged native module stream.');
            }

            if (function_exists('fsync') && fsync($stream) === false) {
                throw new \RuntimeException('Unable to synchronize the staged native module stream.');
            }

            $stats = fstat($stream);

            if (!is_array($stats) || $stats['size'] !== $written) {
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
            $moduleSha256 = hash_file('sha256', $moduleFile);

            if (!is_string($moduleSha256)) {
                throw new \RuntimeException('Unable to hash the staged native module stream.');
            }

            $index['module_file_size'] = $written;
            $index['module_file_sha256'] = $moduleSha256;
            $indexJson = json_encode(
                $index,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
            $indexSha256 = hash('sha256', $indexJson);
            $this->writeSynchronized($staging . '/index.json', $indexJson);
            fclose($stream);
            $stream = null;

            $generation = $result->streamSha256();
            $destination = $moduleRoot . '/generations/' . $generation;

            if (is_dir($destination)) {
                try {
                    $existingIndex = file_get_contents($destination . '/index.json');

                    if (!is_string($existingIndex)) {
                        throw new ContractException('Existing snapshot generation index is unreadable.');
                    }

                    $snapshot = SnapshotIndex::open(
                        $destination,
                        $now,
                        $expires,
                        hash('sha256', $existingIndex),
                    );
                    Folder::delete($staging);
                } catch (ContractException) {
                    $this->repairGeneration($moduleRoot, $generation, $staging, $destination);
                    $snapshot = SnapshotIndex::open($destination, $now, $expires, $indexSha256);
                }
            } elseif (!rename($staging, $destination)) {
                throw new \RuntimeException('Unable to atomically commit the staged snapshot generation.');
            } else {
                $snapshot = SnapshotIndex::open($destination, $now, $expires, $indexSha256);
            }

            $this->activate($moduleRoot, $snapshot);

            try {
                $this->cleanupGenerations($moduleRoot, $generation);
            } catch (\Throwable) {
                // Cleanup is retried after a later activation and cannot invalidate this committed generation.
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
     * @param SnapshotIndex|null $cached Optional process-local reader.
     *
     * @return SnapshotIndex|null
     * @since 0.1.0
     */
    private function openCurrent(string $module, ?SnapshotIndex $cached = null): ?SnapshotIndex
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
            || !is_string($pointer['index_sha256'] ?? null)
            || preg_match('/^[0-9a-f]{64}$/D', $pointer['index_sha256']) !== 1
            || !is_string($pointer['activated_at'] ?? null)
            || !is_string($pointer['expires_at'] ?? null)
        ) {
            return null;
        }

        try {
            $activated = $this->parseTimestamp($pointer['activated_at']);
            $expires = $this->parseTimestamp($pointer['expires_at']);

            if (
                $cached !== null
                && $cached->generationId() === $pointer['generation']
                && $cached->indexSha256() === $pointer['index_sha256']
                && $cached->activatedAt() == $activated
                && $cached->expiresAt() == $expires
            ) {
                return $cached;
            }

            return SnapshotIndex::open(
                $moduleRoot . '/generations/' . $pointer['generation'],
                $activated,
                $expires,
                $pointer['index_sha256'],
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
     * Atomically activates a verified snapshot reader.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     * @param SnapshotIndex $snapshot Verified generation.
     *
     * @return void
     * @since 1.0.0
     */
    private function activate(string $moduleRoot, SnapshotIndex $snapshot): void
    {
        $pointer = [
            'format' => 'getbible.scripture.current/v1',
            'generation' => $snapshot->generationId(),
            'index_sha256' => $snapshot->indexSha256(),
            'activated_at' => $snapshot->activatedAt()->format(DATE_ATOM),
            'expires_at' => $snapshot->expiresAt()->format(DATE_ATOM),
        ];

        try {
            $pointerJson = json_encode(
                $pointer,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n";
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Unable to serialize the active snapshot pointer.', 0, $exception);
        }

        $temporary = $moduleRoot . '/current.' . bin2hex(random_bytes(8)) . '.tmp';
        $this->writeSynchronized($temporary, $pointerJson);

        if (!rename($temporary, $moduleRoot . '/current.json')) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to atomically activate the snapshot generation.');
        }
    }

    /**
     * Finds the newest complete cached generation after pointer corruption.
     *
     * @param string $module Exact installed module identifier.
     * @param string $moduleRoot Controlled per-module cache root.
     *
     * @return SnapshotIndex|null
     * @since 1.0.0
     */
    private function recoverGeneration(string $module, string $moduleRoot): ?SnapshotIndex
    {
        $generations = $this->generationDirectories($moduleRoot);

        foreach ($generations as $generation => $modifiedAt) {
            $path = $moduleRoot . '/generations/' . $generation;
            $json = @file_get_contents($path . '/index.json');

            if (!is_string($json)) {
                continue;
            }

            $activated = (new \DateTimeImmutable('@' . $modifiedAt))
                ->setTimezone(new \DateTimeZone('UTC'));
            $expires = $activated->add($this->configuration->refreshInterval());

            try {
                $snapshot = SnapshotIndex::open(
                    $path,
                    $activated,
                    $expires,
                    hash('sha256', $json),
                );

                if ($snapshot->metadata()->name()->bytes() !== $module) {
                    continue;
                }

                return $snapshot;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Replaces a corrupt same-hash generation when it has no active readers.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     * @param string $generation Content-addressed generation identifier.
     * @param string $staging Complete staged generation.
     * @param string $destination Corrupt committed generation.
     *
     * @return void
     * @since 1.0.0
     */
    private function repairGeneration(
        string $moduleRoot,
        string $generation,
        string $staging,
        string $destination,
    ): void {
        $repaired = GenerationLease::cleanup(
            $moduleRoot,
            $generation,
            function () use ($destination, $staging, $generation): void {
                $quarantine = dirname($destination) . '/.corrupt-'
                    . $generation . '-' . bin2hex(random_bytes(6));

                if (!rename($destination, $quarantine)) {
                    throw new \RuntimeException('Unable to quarantine a corrupt snapshot generation.');
                }

                if (!rename($staging, $destination)) {
                    if (!rename($quarantine, $destination)) {
                        throw new \RuntimeException(
                            'Unable to replace or restore a corrupt snapshot generation.',
                        );
                    }

                    throw new \RuntimeException('Unable to replace a corrupt snapshot generation.');
                }

                Folder::delete($quarantine);
            },
        );

        if (!$repaired) {
            throw new \RuntimeException(
                'A corrupt snapshot generation cannot be repaired while it has active readers.',
            );
        }
    }

    /**
     * Deletes unleased historical generations after successful activation.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     * @param string $current Active generation identifier.
     *
     * @return void
     * @since 1.0.0
     */
    private function cleanupGenerations(string $moduleRoot, string $current): void
    {
        $generations = $this->generationDirectories($moduleRoot);
        $retained = [$current => true];

        foreach (array_keys($generations) as $generation) {
            if (isset($retained[$generation])) {
                continue;
            }

            if (count($retained) < self::RETAIN_GENERATIONS) {
                $retained[$generation] = true;
                continue;
            }

            GenerationLease::cleanup(
                $moduleRoot,
                $generation,
                static function () use ($moduleRoot, $generation): void {
                    $path = $moduleRoot . '/generations/' . $generation;

                    if (is_dir($path)) {
                        Folder::delete($path);
                    }
                },
            );
        }
    }

    /**
     * Returns committed generation directories newest first.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     *
     * @return array<string, int>
     * @since 1.0.0
     */
    private function generationDirectories(string $moduleRoot): array
    {
        $path = $moduleRoot . '/generations';

        if (!is_dir($path)) {
            return [];
        }

        $generations = [];

        foreach (new \DirectoryIterator($path) as $item) {
            if (
                $item->isDot()
                || !$item->isDir()
                || $item->isLink()
                || preg_match('/^[0-9a-f]{64}$/D', $item->getFilename()) !== 1
            ) {
                continue;
            }

            $generations[$item->getFilename()] = $item->getMTime();
        }

        arsort($generations, SORT_NUMERIC);

        return $generations;
    }

    /**
     * Removes abandoned staging, quarantine, and pointer files.
     *
     * @param string $moduleRoot Controlled per-module cache root.
     *
     * @return void
     * @since 1.0.0
     */
    private function scavengeInterruptedWrites(string $moduleRoot): void
    {
        $cutoff = time() - self::STALE_TEMPORARY_SECONDS;
        $generations = $moduleRoot . '/generations';

        if (is_dir($generations)) {
            foreach (new \DirectoryIterator($generations) as $item) {
                $name = $item->getFilename();

                if (
                    $item->isDot()
                    || !$item->isDir()
                    || $item->isLink()
                    || $item->getMTime() > $cutoff
                    || (
                        !str_starts_with($name, '.staging-')
                        && !str_starts_with($name, '.corrupt-')
                    )
                ) {
                    continue;
                }

                Folder::delete($item->getPathname());
            }
        }

        foreach (glob($moduleRoot . '/current.*.tmp') ?: [] as $temporary) {
            $modifiedAt = filemtime($temporary);

            if (is_int($modifiedAt) && $modifiedAt <= $cutoff && is_file($temporary) && !is_link($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Parses an exact RFC 3339 timestamp emitted by this package.
     *
     * @param string $value Serialized timestamp.
     *
     * @return \DateTimeImmutable
     * @since 1.0.0
     */
    private function parseTimestamp(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof \DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format(DATE_ATOM) !== $value
        ) {
            throw new ContractException('Snapshot pointer timestamp is invalid.');
        }

        return $date;
    }

    /**
     * Retains a bounded least-recently-used process-local snapshot reader.
     *
     * @param string $module Exact module identifier.
     * @param SnapshotIndex $snapshot Verified snapshot reader.
     *
     * @return SnapshotIndex
     * @since 1.0.0
     */
    private function remember(string $module, SnapshotIndex $snapshot): SnapshotIndex
    {
        unset($this->snapshots[$module]);
        $this->snapshots[$module] = $snapshot;

        if (count($this->snapshots) > self::SNAPSHOT_CACHE_LIMIT) {
            array_shift($this->snapshots);
        }

        return $snapshot;
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
