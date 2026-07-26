<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Catalog;

use GetBible\Scripture\Domain\TranslationMetadata;

/**
 * Lists installed modules and resolves Bible translation metadata.
 *
 * @since 0.1.0
 */
interface ModuleCatalogInterface
{
    /**
     * Returns all installed Bible modules in native sorted order.
     *
     * @return list<TranslationMetadata>
     * @since 0.1.0
     */
    public function translations(): array;

    /**
     * Returns one installed Bible module by its exact identifier.
     *
     * @param string $module Exact native module identifier.
     *
     * @return TranslationMetadata
     * @since 0.1.0
     */
    public function translation(string $module): TranslationMetadata;

    /**
     * Clears the process-local module list.
     *
     * @return void
     * @since 0.1.0
     */
    public function clear(): void;
}
