<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Configuration;

/**
 * Creates configuration repositories for caller-selected durable paths.
 *
 * @since 1.0.0
 */
interface ConfigurationRepositoryFactoryInterface
{
    /**
     * Creates a repository using an explicit path or the configured environment path.
     *
     * @param string|null $path Explicit path, or null to resolve the environment.
     *
     * @return ConfigurationRepositoryInterface
     * @since 1.0.0
     */
    public function create(?string $path = null): ConfigurationRepositoryInterface;
}
