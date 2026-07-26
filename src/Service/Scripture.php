<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Service;

use GetBible\Scripture\Catalog\ModuleCatalogInterface;
use GetBible\Scripture\Domain\Translation;
use GetBible\Scripture\Domain\Verse;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Provisioning\ProvisioningCoordinatorInterface;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Snapshot\SnapshotManagerInterface;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceServiceInterface;
use GetBible\Scripture\Maintenance\MaintenanceStatus;
use GetBible\Scripture\Module\ModuleIdentifier;

/**
 * Default dependency-injected implementation of the Bible application API.
 *
 * @since 0.1.0
 */
final class Scripture implements ScriptureInterface
{
    /**
     * Maximum process-local Translation objects retained by the facade.
     *
     * @since 1.0.0
     */
    private const TRANSLATION_CACHE_LIMIT = 32;

    /**
     * Process-local translation objects.
     *
     * @var array<string, Translation>
     * @since 0.1.0
     */
    private array $translations = [];

    /**
     * Creates the Scripture service.
     *
     * @param ModuleCatalogInterface $catalog Installed module catalog.
     * @param SnapshotManagerInterface $snapshots Snapshot lifecycle.
     * @param ProvisioningCoordinatorInterface $provisioning Module lifecycle.
     * @param MaintenanceServiceInterface $maintenance Automated maintenance lifecycle.
     *
     * @since 0.1.0
     */
    public function __construct(
        private ModuleCatalogInterface $catalog,
        private SnapshotManagerInterface $snapshots,
        private ProvisioningCoordinatorInterface $provisioning,
        private MaintenanceServiceInterface $maintenance,
    ) {
    }

    /**
     * Installs missing configured modules when supported and warms snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     * @param bool $all Whether every policy-approved translation should be installed.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function initialize(array $modules = [], bool $all = false): MaintenanceResult
    {
        $result = $this->maintenance->initialize($modules, $all);
        $this->translations = [];

        return $result;
    }

    /**
     * Refreshes remote modules when enabled and rebuilds snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refresh(array $modules = []): MaintenanceResult
    {
        $result = $this->maintenance->refresh($modules);
        $this->translations = [];

        return $result;
    }

    /**
     * Refreshes only after the configured durable interval has elapsed.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refreshIfDue(array $modules = []): MaintenanceResult
    {
        $result = $this->maintenance->refreshIfDue($modules);

        if ($result->due()) {
            $this->translations = [];
        }

        return $result;
    }

    /**
     * Returns current maintenance and native capability status.
     *
     * @return MaintenanceStatus
     * @since 0.3.0
     */
    public function maintenanceStatus(): MaintenanceStatus
    {
        return $this->maintenance->status();
    }

    /**
     * Returns installed Bible translation metadata.
     *
     * @return list<\GetBible\Scripture\Domain\TranslationMetadata>
     * @since 0.1.0
     */
    public function translations(): array
    {
        return $this->catalog->translations();
    }

    /**
     * Opens one installed translation.
     *
     * @param string $module Exact native module identifier.
     *
     * @return Translation
     * @since 0.1.0
     */
    public function translation(string $module): Translation
    {
        $module = ModuleIdentifier::normalize($module);
        $snapshot = $this->snapshots->get($module);
        $translation = $this->translations[$module] ?? null;

        if (
            $translation === null
            || $translation->generationId() !== $snapshot->generationId()
            || $translation->activatedAt() != $snapshot->activatedAt()
            || $translation->expiresAt() != $snapshot->expiresAt()
        ) {
            $translation = new Translation($module, $snapshot);
        }

        unset($this->translations[$module]);
        $this->translations[$module] = $translation;

        if (count($this->translations) > self::TRANSLATION_CACHE_LIMIT) {
            array_shift($this->translations);
        }

        return $translation;
    }

    /**
     * Returns one verse.
     *
     * @param string $module Exact native module identifier.
     * @param string $book Book name or abbreviation.
     * @param int $chapter Chapter number.
     * @param int $verse Verse number.
     * @param int $suffix SWORD suffix byte.
     *
     * @return Verse
     * @since 0.1.0
     */
    public function verse(
        string $module,
        string $book,
        int $chapter,
        int $verse,
        int $suffix = 0,
    ): Verse {
        return $this->translation($module)->book($book)->chapter($chapter)->verse($verse, $suffix);
    }

    /**
     * Returns an inclusive verse range.
     *
     * @param string $module Exact native module identifier.
     * @param string $book Book name or abbreviation.
     * @param int $chapter Chapter number.
     * @param int $start First verse.
     * @param int $end Last verse.
     *
     * @return list<Verse>
     * @since 0.1.0
     */
    public function verses(
        string $module,
        string $book,
        int $chapter,
        int $start,
        int $end,
    ): array {
        return $this->translation($module)->verses($book, $chapter, $start, $end);
    }

    /**
     * Forces an installed-module snapshot refresh.
     *
     * @param string $module Exact native module identifier.
     *
     * @return Translation
     * @since 0.1.0
     */
    public function refreshTranslation(string $module): Translation
    {
        $module = ModuleIdentifier::normalize($module);
        $translation = new Translation($module, $this->snapshots->refresh($module));
        unset($this->translations[$module]);
        $this->translations[$module] = $translation;

        if (count($this->translations) > self::TRANSLATION_CACHE_LIMIT) {
            array_shift($this->translations);
        }

        return $translation;
    }

    /**
     * Reports native remote provisioning availability.
     *
     * @return bool
     * @since 0.1.0
     */
    public function canProvisionModules(): bool
    {
        return $this->provisioning->capabilities()->isAvailable();
    }

    /**
     * Returns exact native provisioning capabilities.
     *
     * @return ProvisioningCapabilities
     * @since 0.2.0
     */
    public function provisioningCapabilities(): ProvisioningCapabilities
    {
        return $this->provisioning->capabilities();
    }

    /**
     * Installs selected policy-approved translations when supported.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function installTranslations(array $modules): ProvisioningResult
    {
        $result = $this->provisioning->install($modules);
        $this->translations = [];

        return $result;
    }

    /**
     * Installs all policy-approved translations when supported.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function installAllTranslations(): ProvisioningResult
    {
        $result = $this->provisioning->installAll();
        $this->translations = [];

        return $result;
    }

    /**
     * Refreshes remote module files when supported.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function refreshModules(): ProvisioningResult
    {
        $result = $this->provisioning->refresh();
        $this->translations = [];

        return $result;
    }

    /**
     * Refreshes selected remote module files when supported.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function refreshSelectedModules(array $modules): ProvisioningResult
    {
        $result = $this->provisioning->refresh($modules);
        $this->translations = [];

        return $result;
    }

    /**
     * Removes one installed Bible translation when supported.
     *
     * @param string $module Exact module identifier.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function removeTranslation(string $module): ProvisioningResult
    {
        $result = $this->provisioning->remove($module);
        unset($this->translations[$module]);

        return $result;
    }
}
