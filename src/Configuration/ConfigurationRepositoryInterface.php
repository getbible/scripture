<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Configuration;

/**
 * Loads and atomically persists validated application-owned configuration.
 *
 * @since 1.0.0
 */
interface ConfigurationRepositoryInterface
{
    /**
     * Returns the explicit durable configuration path.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function path(): ?string;

    /**
     * Reports whether a durable configuration file currently exists.
     *
     * @return bool
     * @since 1.0.0
     */
    public function exists(): bool;

    /**
     * Loads the persisted setting map.
     *
     * @return array<string, bool|int|string|list<string>|null>
     * @since 1.0.0
     */
    public function load(): array;

    /**
     * Atomically persists one immutable validated configuration.
     *
     * @param Configuration $configuration Validated configuration.
     *
     * @return void
     * @since 1.0.0
     */
    public function save(Configuration $configuration): void;
}
