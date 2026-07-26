<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Service;

use GetBible\Scripture\Domain\Translation;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Domain\Verse;
use GetBible\Scripture\Provisioning\ProvisioningResult;
use GetBible\Scripture\Provisioning\ProvisioningCapabilities;
use GetBible\Scripture\Maintenance\MaintenanceResult;
use GetBible\Scripture\Maintenance\MaintenanceStatus;

/**
 * Primary Bible-only application API.
 *
 * @since 0.1.0
 */
interface ScriptureInterface
{
    /**
     * Installs missing configured modules when supported and warms snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     * @param bool $all Whether every policy-approved translation should be installed.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function initialize(array $modules = [], bool $all = false): MaintenanceResult;

    /**
     * Refreshes remote modules when enabled and rebuilds snapshots.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refresh(array $modules = []): MaintenanceResult;

    /**
     * Refreshes only after the configured durable interval has elapsed.
     *
     * @param list<string> $modules Explicit module targets or configuration defaults.
     *
     * @return MaintenanceResult
     * @since 0.3.0
     */
    public function refreshIfDue(array $modules = []): MaintenanceResult;

    /**
     * Returns current maintenance and native capability status.
     *
     * @return MaintenanceStatus
     * @since 0.3.0
     */
    public function maintenanceStatus(): MaintenanceStatus;

    /**
     * Returns installed Bible translation metadata.
     *
     * @return list<TranslationMetadata>
     * @since 0.1.0
     */
    public function translations(): array;

    /**
     * Opens one installed translation, warming it when necessary.
     *
     * @param string $module Exact native module identifier.
     *
     * @return Translation
     * @since 0.1.0
     */
    public function translation(string $module): Translation;

    /**
     * Returns one verse through a convenience query.
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
    ): Verse;

    /**
     * Returns an inclusive verse range through a convenience query.
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
    ): array;

    /**
     * Forces a validated snapshot refresh from the installed module.
     *
     * @param string $module Exact native module identifier.
     *
     * @return Translation
     * @since 0.1.0
     */
    public function refreshTranslation(string $module): Translation;

    /**
     * Reports whether remote module provisioning is available.
     *
     * @return bool
     * @since 0.1.0
     */
    public function canProvisionModules(): bool;

    /**
     * Returns exact native provisioning capabilities.
     *
     * @return ProvisioningCapabilities
     * @since 0.2.0
     */
    public function provisioningCapabilities(): ProvisioningCapabilities;

    /**
     * Installs selected policy-approved Bible modules when supported.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function installTranslations(array $modules): ProvisioningResult;

    /**
     * Installs every policy-approved Bible module when supported.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function installAllTranslations(): ProvisioningResult;

    /**
     * Refreshes installed remote module files when supported.
     *
     * @return ProvisioningResult
     * @since 0.1.0
     */
    public function refreshModules(): ProvisioningResult;

    /**
     * Refreshes selected remote module files when supported.
     *
     * @param list<string> $modules Exact module identifiers.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function refreshSelectedModules(array $modules): ProvisioningResult;

    /**
     * Removes one installed Bible module when supported.
     *
     * @param string $module Exact module identifier.
     *
     * @return ProvisioningResult
     * @since 0.2.0
     */
    public function removeTranslation(string $module): ProvisioningResult;
}
