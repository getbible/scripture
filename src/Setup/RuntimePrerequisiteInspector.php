<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Setup;

/**
 * Reads getBibleSword metadata from the already-loaded PHP extension.
 *
 * This inspector never invokes PIE, a subprocess, a package manager, or a
 * network operation.
 *
 * @since 1.0.0
 */
final class RuntimePrerequisiteInspector implements RuntimePrerequisiteInspectorInterface
{
    /**
     * Returns the exact native extension compatibility report.
     *
     * @return RuntimePrerequisiteReport
     * @since 1.0.0
     */
    public function inspect(): RuntimePrerequisiteReport
    {
        $loaded = extension_loaded('getbiblesword');
        $classAvailable = class_exists(\GetBible\Sword\Engine::class);
        $extensionVersion = $loaded ? phpversion('getbiblesword') : false;

        if (!$loaded || !$classAvailable) {
            return new RuntimePrerequisiteReport(
                $loaded,
                $classAvailable,
                is_string($extensionVersion) ? $extensionVersion : null,
                null,
                null,
                null,
            );
        }

        try {
            return new RuntimePrerequisiteReport(
                true,
                true,
                is_string($extensionVersion) ? $extensionVersion : null,
                \GetBible\Sword\Engine::abiVersion(),
                \GetBible\Sword\Engine::contractIdentifier(),
                \GetBible\Sword\Engine::productVersion(),
            );
        } catch (\Throwable $exception) {
            return new RuntimePrerequisiteReport(
                true,
                true,
                is_string($extensionVersion) ? $extensionVersion : null,
                null,
                null,
                null,
                $exception->getMessage(),
            );
        }
    }
}
