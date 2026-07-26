<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\ConfigurationRepositoryFactoryInterface;

/**
 * Safe application-owned setup orchestrator.
 *
 * Native package installation is intentionally outside this service. It never
 * invokes PIE, subprocesses, package managers, privilege escalation, or network
 * operations.
 *
 * @since 1.0.0
 */
final class SetupService implements SetupServiceInterface
{
    /**
     * Creates the setup orchestrator.
     *
     * @param ConfigurationRepositoryFactoryInterface $repositories Repository factory.
     * @param RuntimePrerequisiteInspectorInterface $inspector Read-only runtime inspector.
     * @param ApplicationWarmerInterface $warmer Installed-module warmer.
     *
     * @since 1.0.0
     */
    public function __construct(
        private ConfigurationRepositoryFactoryInterface $repositories,
        private RuntimePrerequisiteInspectorInterface $inspector,
        private ApplicationWarmerInterface $warmer,
    ) {
    }

    /**
     * Inspects the already-loaded native runtime without changing it.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport
    {
        return $this->inspector->inspect();
    }

    /**
     * Resolves current persisted and environment-backed configuration.
     *
     * @param string|null $configurationPath Explicit durable path.
     *
     * @return Configuration
     * @since 1.0.0
     */
    public function configuration(?string $configurationPath = null): Configuration
    {
        return Configuration::fromPersisted(
            $this->repositories->create($configurationPath)->load(),
        );
    }

    /**
     * Validates, atomically persists, and optionally warms configuration.
     *
     * Persisted settings are applied before warming so a failed warm remains
     * reproducible and can be retried with the same configuration.
     *
     * @param SetupRequest $request Setup request.
     *
     * @return SetupResult
     * @since 1.0.0
     */
    public function apply(SetupRequest $request): SetupResult
    {
        $repository = $this->repositories->create($request->configurationPath());
        $path = $repository->path();

        if ($path === null) {
            throw new \InvalidArgumentException(
                'Supply an absolute configuration path or set GETBIBLE_SCRIPTURE_CONFIG_PATH.',
            );
        }

        $configuration = Configuration::fromLayers(
            $repository->load(),
            $request->values(),
        );
        $repository->save($configuration);
        $runtime = $this->inspector->inspect();
        $maintenance = null;
        $errors = [];

        if ($request->warm()) {
            if (!$runtime->ready()) {
                $errors[] = 'The active PHP runtime is not ready for Scripture warming.';
            } else {
                try {
                    $maintenance = $this->warmer->warm($configuration, $configuration->modules());
                } catch (\Throwable $exception) {
                    $errors[] = 'Scripture warming failed: ' . $exception->getMessage();
                }
            }
        }

        return new SetupResult(
            $path,
            $configuration,
            $runtime,
            $request->warm(),
            $maintenance,
            $errors,
        );
    }
}
