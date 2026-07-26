<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

/**
 * Immutable request to validate, persist, and optionally warm configuration.
 *
 * @since 1.0.0
 */
final class SetupRequest
{
    /**
     * Creates a setup request.
     *
     * @param string|null $configurationPath Explicit durable configuration path.
     * @param array<string, bool|int|string|list<string>|null> $values Setting overrides.
     * @param bool $warm Whether installed configured translations should be warmed.
     *
     * @since 1.0.0
     */
    public function __construct(
        private ?string $configurationPath,
        private array $values,
        private bool $warm = true,
    ) {
    }

    /**
     * Returns the explicit durable configuration path.
     *
     * @return string|null
     * @since 1.0.0
     */
    public function configurationPath(): ?string
    {
        return $this->configurationPath;
    }

    /**
     * Returns setting overrides.
     *
     * @return array<string, bool|int|string|list<string>|null>
     * @since 1.0.0
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Reports whether installed translations should be warmed.
     *
     * @return bool
     * @since 1.0.0
     */
    public function warm(): bool
    {
        return $this->warm;
    }
}
