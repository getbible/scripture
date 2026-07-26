<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Configuration;

/**
 * Creates restrictive JSON configuration repositories.
 *
 * @since 1.0.0
 */
final class JsonConfigurationRepositoryFactory implements ConfigurationRepositoryFactoryInterface
{
    /**
     * Creates a repository using an explicit path or the configured environment path.
     *
     * @param string|null $path Explicit path, or null to resolve the environment.
     *
     * @return ConfigurationRepositoryInterface
     * @since 1.0.0
     */
    public function create(?string $path = null): ConfigurationRepositoryInterface
    {
        return new JsonConfigurationRepository(ConfigurationPath::resolve($path));
    }
}
