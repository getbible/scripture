<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Event\EventName;
use GetBible\Scripture\Infrastructure\Lock\ModuleRootLockInterface;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;

/**
 * Serializes module mutation and invalidates process caches after success.
 *
 * @since 0.2.0
 */
final class ProvisioningCoordinator implements ProvisioningCoordinatorInterface
{
    /**
     * Creates the module lifecycle coordinator.
     *
     * @param ModuleProvisionerInterface $provisioner Native provisioning backend.
     * @param ModuleRootLockInterface $lock Application-wide module root lock.
     * @param ModuleCatalogInterface $catalog Installed module catalog.
     * @param SnapshotManagerInterface $snapshots Snapshot lifecycle.
     * @param DispatcherInterface $dispatcher Joomla event dispatcher.
     *
     * @since 0.2.0
     */
    public function __construct(
        private ModuleProvisionerInterface $provisioner,
        private ModuleRootLockInterface $lock,
        private ModuleCatalogInterface $catalog,
        private SnapshotManagerInterface $snapshots,
        private DispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Returns exact injected backend capabilities.
     *
     * @return ProvisioningCapabilities
     * @since 0.2.0
     */
    public function capabilities(): ProvisioningCapabilities
    {
        return $this->provisioner->capabilities();
    }

    /**
     * Installs selected Bible translations.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function install(array $modules): ProvisioningResult
    {
        $modules = $this->normalizeModules($modules, false);

        return $this->execute(
            'install-selected',
            $modules,
            fn (): ProvisioningResult => $this->provisioner->installTranslations($modules),
        );
    }

    /**
     * Installs all policy-approved Bible translations.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function installAll(): ProvisioningResult
    {
        return $this->execute(
            'install-all',
            [],
            fn (): ProvisioningResult => $this->provisioner->installAllTranslations(),
        );
    }

    /**
     * Refreshes selected or all installed Bible translations.
     *
     * @param list<string> $modules Exact identifiers, or an empty list for every installed translation.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function refresh(array $modules = []): ProvisioningResult
    {
        $modules = $this->normalizeModules($modules, true);

        return $this->execute(
            'refresh',
            $modules,
            fn (): ProvisioningResult => $this->provisioner->refreshTranslations($modules),
        );
    }

    /**
     * Removes one installed Bible translation.
     *
     * @param string $module Exact module identifier.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function remove(string $module): ProvisioningResult
    {
        $modules = $this->normalizeModules([$module], false);

        return $this->execute(
            'remove',
            $modules,
            fn (): ProvisioningResult => $this->provisioner->removeTranslation($modules[0]),
        );
    }

    /**
     * Runs one backend mutation under the exclusive application lock.
     *
     * @param string $operation Stable operation name.
     * @param list<string> $modules Requested module identifiers.
     * @param callable(): ProvisioningResult $callback Backend operation.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    private function execute(string $operation, array $modules, callable $callback): ProvisioningResult
    {
        $this->dispatcher->dispatch(
            EventName::PROVISIONING_STARTED,
            new Event(EventName::PROVISIONING_STARTED, [
                'operation' => $operation,
                'modules' => $modules,
                'capabilities' => $this->capabilities(),
            ]),
        );

        try {
            $result = $this->lock->write($callback);

            $this->catalog->clear();
            $this->snapshots->clear();
            $this->dispatcher->dispatch(
                EventName::PROVISIONING_COMPLETED,
                new Event(EventName::PROVISIONING_COMPLETED, [
                    'operation' => $operation,
                    'modules' => $modules,
                    'result' => $result,
                ]),
            );

            return $result;
        } catch (\Throwable $exception) {
            $this->dispatcher->dispatch(
                EventName::PROVISIONING_FAILED,
                new Event(EventName::PROVISIONING_FAILED, [
                    'operation' => $operation,
                    'modules' => $modules,
                    'exception' => $exception,
                ]),
            );

            throw $exception;
        }
    }

    /**
     * Validates, trims, and de-duplicates module identifiers.
     *
     * @param array<array-key, mixed> $modules Candidate module identifiers.
     * @param bool $allowEmpty Whether an empty list means all installed translations.
     *
     * @return list<string>
     * @since 0.2.0
     */
    private function normalizeModules(array $modules, bool $allowEmpty): array
    {
        $normalized = [];

        foreach ($modules as $module) {
            if (!is_string($module)) {
                throw new \InvalidArgumentException('Module identifiers must be strings.');
            }

            $module = trim($module);

            if ($module === '' || str_contains($module, "\0")) {
                throw new \InvalidArgumentException('Module identifiers must be non-empty strings without NUL bytes.');
            }

            $normalized[$module] = $module;
        }

        if (!$allowEmpty && $normalized === []) {
            throw new \InvalidArgumentException('At least one module identifier is required.');
        }

        return array_values($normalized);
    }
}
