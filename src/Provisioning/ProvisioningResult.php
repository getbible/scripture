<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Provisioning;

/**
 * Immutable summary of a completed module provisioning operation.
 *
 * @since 0.1.0
 */
final class ProvisioningResult
{
    /**
     * Installed module identifiers.
     *
     * @var list<string>
     * @since 0.1.0
     */
    private array $installed;

    /**
     * Updated module identifiers.
     *
     * @var list<string>
     * @since 0.1.0
     */
    private array $updated;

    /**
     * Skipped module identifiers.
     *
     * @var list<string>
     * @since 0.1.0
     */
    private array $skipped;

    /**
     * Creates a result from module identifier lists.
     *
     * @param list<string> $installed Installed modules.
     * @param list<string> $updated Updated modules.
     * @param list<string> $skipped Skipped modules.
     *
     * @since 0.1.0
     */
    public function __construct(array $installed, array $updated, array $skipped)
    {
        $this->installed = array_values($installed);
        $this->updated = array_values($updated);
        $this->skipped = array_values($skipped);
    }

    /**
     * Returns installed modules.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function installed(): array
    {
        return $this->installed;
    }

    /**
     * Returns updated modules.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function updated(): array
    {
        return $this->updated;
    }

    /**
     * Returns skipped modules.
     *
     * @return list<string>
     * @since 0.1.0
     */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
