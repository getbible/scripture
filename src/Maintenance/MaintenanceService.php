<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Maintenance;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Clock\ClockInterface;
use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Event\EventName;
use GetBible\Scripture\Event\LifecycleEventDispatcher;
use GetBible\Scripture\Infrastructure\Lock\MaintenanceLockInterface;
use GetBible\Scripture\Module\ModuleIdentifier;
use GetBible\Scripture\Provisioning\ProvisioningCoordinatorInterface;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use Joomla\Event\DispatcherInterface;

/**
 * Default production maintenance orchestrator with per-module failure isolation.
 *
 * @since 0.3.0
 */
final class MaintenanceService implements MaintenanceServiceInterface
{
    /**
     * Creates the automated maintenance service.
     *
     * @param Configuration $configuration Runtime configuration and policy.
     * @param ClockInterface $clock Maintenance clock.
     * @param ModuleCatalogInterface $catalog Installed Bible module catalog.
     * @param SnapshotManagerInterface $snapshots Snapshot lifecycle.
     * @param ProvisioningCoordinatorInterface $provisioning Module lifecycle.
     * @param MaintenanceStateStoreInterface $stateStore Durable interval state.
     * @param MaintenanceLockInterface $lock Whole-run interprocess lock.
     * @param DispatcherInterface $dispatcher Joomla event dispatcher.
     *
     * @since 0.3.0
     */
    public function __construct(
        private Configuration $configuration,
        private ClockInterface $clock,
        private ModuleCatalogInterface $catalog,
        private SnapshotManagerInterface $snapshots,
        private ProvisioningCoordinatorInterface $provisioning,
        private MaintenanceStateStoreInterface $stateStore,
        private MaintenanceLockInterface $lock,
        private DispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Installs missing configured modules when permitted and warms snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     * @param bool $all Whether every policy-approved translation should be installed.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function initialize(array $modules = [], bool $all = false): MaintenanceResult
    {
        $modules = $this->normalizeModules($modules);

        return $this->execute(
            'initialize',
            fn (\DateTimeImmutable $startedAt): MaintenanceResult => $this->runInitialize(
                $modules,
                $all || $this->configuration->installAll(),
                $startedAt,
            ),
        );
    }

    /**
     * Refreshes remote modules when enabled and rebuilds their snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refresh(array $modules = []): MaintenanceResult
    {
        $modules = $this->normalizeModules($modules);

        return $this->execute(
            'refresh',
            fn (\DateTimeImmutable $startedAt): MaintenanceResult => $this->runRefresh(
                $modules,
                $startedAt,
                'refresh',
            ),
        );
    }

    /**
     * Refreshes only when the durable interval policy is due.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refreshIfDue(array $modules = []): MaintenanceResult
    {
        $modules = $this->normalizeModules($modules);

        return $this->execute(
            'refresh-if-due',
            function (\DateTimeImmutable $startedAt) use ($modules): MaintenanceResult {
                $state = $this->stateStore->load();

                if (!$state->isDue($startedAt, $this->configuration->refreshInterval())) {
                    return new MaintenanceResult(
                        'refresh-if-due',
                        $startedAt,
                        $this->clock->now(),
                        false,
                        [],
                        null,
                        [],
                        'The configured refresh interval has not elapsed.',
                    );
                }

                return $this->runRefresh($modules, $startedAt, 'refresh-if-due');
            },
        );
    }

    /**
     * Returns current maintenance, capability, and installed-module status.
     *
     * @return MaintenanceStatus
     * @since 0.3.0
     */
    public function status(): MaintenanceStatus
    {
        $now = $this->clock->now();
        $state = $this->stateStore->load();
        $interval = $this->configuration->refreshInterval();

        return new MaintenanceStatus(
            $now,
            $state->isDue($now, $interval),
            $state->nextDueAt($interval),
            $state,
            $this->provisioning->capabilities(),
            $this->configuration->provisioningEnabled(),
            $this->configuration->modules(),
            array_keys($this->installedModules()),
        );
    }

    /**
     * Runs initialization inside the whole-run lock.
     *
     * @param list<string> $requested Explicit module targets.
     * @param bool $all Whether all approved modules should be installed.
     * @param \DateTimeImmutable $startedAt Operation start.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    private function runInitialize(
        array $requested,
        bool $all,
        \DateTimeImmutable $startedAt,
    ): MaintenanceResult {
        $targets = $requested !== [] ? $requested : $this->configuration->modules();
        $errors = [];
        $moduleResults = [];
        $reported = [];
        $provisioning = null;
        $installed = $this->installedModules();

        if ($all) {
            if (!$this->configuration->provisioningEnabled()) {
                $errors[] = 'All-module installation is disabled by runtime provisioning policy.';
            } elseif (!$this->provisioning->capabilities()->canInstallAll()) {
                $errors[] = 'The active native backend cannot install all translations.';
            } else {
                $provisioning = $this->captureProvisioning(
                    fn (): ProvisioningResult => $this->provisioning->installAll(),
                    $errors,
                );
            }
        } else {
            $missing = array_values(array_filter(
                $targets,
                static fn (string $module): bool => !isset($installed[$module]),
            ));

            if ($missing !== []) {
                if (!$this->configuration->provisioningEnabled()) {
                    $errors[] = sprintf(
                        'Missing modules cannot be installed because provisioning is disabled: %s.',
                        implode(', ', $missing),
                    );
                } elseif (!$this->provisioning->capabilities()->canInstallSelected()) {
                    $errors[] = sprintf(
                        'The active native backend cannot install missing modules: %s.',
                        implode(', ', $missing),
                    );
                } else {
                    $provisioning = $this->captureProvisioning(
                        fn (): ProvisioningResult => $this->provisioning->install($missing),
                        $errors,
                    );
                }
            }
        }

        $installed = $this->installedModules();

        if ($targets === []) {
            $targets = array_keys($installed);
        }

        if ($targets === []) {
            $errors[] = 'No installed Bible translations are available to initialize.';
        }

        foreach ($targets as $module) {
            if (!isset($installed[$module])) {
                $moduleResults[] = new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_FAILED,
                    'The translation is not installed.',
                );
                $reported[$module] = true;
                continue;
            }

            try {
                $this->snapshots->get($module);
                $moduleResults[] = new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_READY,
                    'The validated translation snapshot is ready.',
                );
                $reported[$module] = true;
            } catch (\Throwable $exception) {
                $moduleResults[] = new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_FAILED,
                    $exception->getMessage(),
                );
                $reported[$module] = true;
            }
        }

        if ($provisioning !== null) {
            foreach ($provisioning->failed() as $module) {
                if (!isset($reported[$module])) {
                    $moduleResults[] = new MaintenanceModuleResult(
                        $module,
                        MaintenanceModuleResult::STATUS_FAILED,
                        'Native provisioning failed for this translation.',
                    );
                }
            }
        }

        return $this->complete(
            'initialize',
            $startedAt,
            true,
            $moduleResults,
            $provisioning,
            $errors,
        );
    }

    /**
     * Runs remote refresh policy and rebuilds selected snapshots.
     *
     * @param list<string> $requested Explicit module targets.
     * @param \DateTimeImmutable $startedAt Operation start.
     * @param string $operation Stable operation name.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    private function runRefresh(
        array $requested,
        \DateTimeImmutable $startedAt,
        string $operation,
    ): MaintenanceResult {
        $installed = $this->installedModules();
        $targets = $requested !== [] ? $requested : $this->configuration->modules();

        if ($targets === []) {
            $targets = array_keys($installed);
        }

        $errors = [];
        $moduleResults = [];
        $provisioning = null;

        if ($targets === []) {
            $errors[] = 'No installed Bible translations are available to refresh.';
        }

        if ($this->configuration->provisioningEnabled()) {
            if (!$this->provisioning->capabilities()->canRefresh()) {
                $errors[] = 'Remote refresh is enabled by policy but unavailable in the active native backend.';
            } else {
                $provisioning = $this->captureProvisioning(
                    fn (): ProvisioningResult => $this->provisioning->refresh($targets),
                    $errors,
                );
            }
        }

        $installed = $this->installedModules();

        foreach ($targets as $module) {
            if (!isset($installed[$module])) {
                $moduleResults[] = new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_FAILED,
                    'The translation is not installed.',
                );
                continue;
            }

            try {
                $this->snapshots->refresh($module);
                $moduleResults[] = new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_REFRESHED,
                    'The validated translation snapshot was rebuilt.',
                );
            } catch (\Throwable $exception) {
                $moduleResults[] = new MaintenanceModuleResult(
                    $module,
                    MaintenanceModuleResult::STATUS_FAILED,
                    $exception->getMessage(),
                );
            }
        }

        return $this->complete(
            $operation,
            $startedAt,
            true,
            $moduleResults,
            $provisioning,
            $errors,
        );
    }

    /**
     * Calls native provisioning and converts thrown failures into run errors.
     *
     * @param callable(): ProvisioningResult $callback Provisioning operation.
     * @param list<string> $errors Operation errors, updated by reference.
     *
     * @return ProvisioningResult|null
     * @since 0.3.0
     */
    private function captureProvisioning(callable $callback, array &$errors): ?ProvisioningResult
    {
        try {
            $result = $callback();

            if (!$result->succeeded()) {
                $errors[] = sprintf(
                    'Native provisioning failed for: %s.',
                    implode(', ', $result->failed()),
                );
            }

            return $result;
        } catch (\Throwable $exception) {
            $errors[] = 'Native provisioning failed: ' . $exception->getMessage();

            return null;
        }
    }

    /**
     * Persists outcome state and creates the immutable result.
     *
     * @param string $operation Stable operation name.
     * @param \DateTimeImmutable $startedAt Operation start.
     * @param bool $due Whether interval policy required work.
     * @param list<MaintenanceModuleResult> $modules Per-module outcomes.
     * @param ProvisioningResult|null $provisioning Optional provisioning outcome.
     * @param list<string> $errors Operation errors.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    private function complete(
        string $operation,
        \DateTimeImmutable $startedAt,
        bool $due,
        array $modules,
        ?ProvisioningResult $provisioning,
        array $errors,
    ): MaintenanceResult {
        $completedAt = $this->clock->now();
        $result = new MaintenanceResult(
            $operation,
            $startedAt,
            $completedAt,
            $due,
            $modules,
            $provisioning,
            $errors,
        );
        $state = $this->stateStore->load();

        if ($result->succeeded()) {
            $state = $state->succeededAt($completedAt);
        } else {
            $failureMessages = $errors;

            foreach ($modules as $module) {
                if ($module->failed()) {
                    $failureMessages[] = sprintf('%s: %s', $module->module(), $module->message());
                }
            }

            if ($failureMessages === []) {
                $failureMessages[] = 'Maintenance did not complete successfully.';
            }

            $state = $state->failedAt(
                $completedAt,
                implode(' | ', array_values(array_unique($failureMessages))),
            );
        }

        $this->stateStore->save($state);

        return $result;
    }

    /**
     * Executes a maintenance callback under the whole-run lock and emits events.
     *
     * @param string $operation Stable operation name.
     * @param callable(\DateTimeImmutable): MaintenanceResult $callback Operation callback.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    private function execute(string $operation, callable $callback): MaintenanceResult
    {
        $result = $this->lock->run(function () use ($operation, $callback): MaintenanceResult {
            $startedAt = $this->clock->now();
            LifecycleEventDispatcher::dispatch(
                $this->dispatcher,
                EventName::MAINTENANCE_STARTED,
                [
                    'operation' => $operation,
                    'started_at' => $startedAt,
                ],
            );

            try {
                $result = $callback($startedAt);
                LifecycleEventDispatcher::dispatch(
                    $this->dispatcher,
                    EventName::MAINTENANCE_COMPLETED,
                    [
                        'operation' => $operation,
                        'result' => $result,
                    ],
                );

                return $result;
            } catch (\Throwable $exception) {
                LifecycleEventDispatcher::dispatch(
                    $this->dispatcher,
                    EventName::MAINTENANCE_FAILED,
                    [
                        'operation' => $operation,
                        'exception' => $exception,
                    ],
                );

                throw $exception;
            }
        });

        return $result;
    }

    /**
     * Returns installed Bible modules keyed by exact identifier.
     *
     * @return array<string, true>
     * @since 0.3.0
     */
    private function installedModules(): array
    {
        $installed = [];

        foreach ($this->catalog->translations() as $translation) {
            $module = ModuleIdentifier::normalize($translation->name()->bytes());
            $installed[$module] = true;
        }

        return $installed;
    }

    /**
     * Validates, trims, and de-duplicates explicit module identifiers.
     *
     * @param array<array-key, mixed> $modules Candidate identifiers.
     *
     * @return list<string>
     * @since 0.3.0
     */
    private function normalizeModules(array $modules): array
    {
        $normalized = [];

        foreach ($modules as $module) {
            if (!is_string($module)) {
                throw new \InvalidArgumentException('Maintenance module identifiers must be strings.');
            }

            $module = ModuleIdentifier::normalize($module);
            $normalized[$module] = $module;
        }

        return array_values($normalized);
    }
}
