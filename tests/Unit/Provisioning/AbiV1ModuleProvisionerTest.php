<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Provisioning;

use GetBible\Scripture\Exception\ProvisioningUnavailableException;
use GetBible\Scripture\Provisioning\AbiV1ModuleProvisioner;
use PHPUnit\Framework\TestCase;

/**
 * Verifies ABI v1 rejects every unavailable mutating operation.
 *
 * @since 1.0.0
 */
final class AbiV1ModuleProvisionerTest extends TestCase
{
    /**
     * Verifies the backend advertises the exact non-mutating ABI.
     *
     * @return void
     * @since 1.0.0
     */
    public function testCapabilitiesIdentifyAbiV1(): void
    {
        $capabilities = (new AbiV1ModuleProvisioner())->capabilities();

        self::assertSame('getbiblesword.ndjson/v1', $capabilities->contract());
        self::assertFalse($capabilities->isAvailable());
    }

    /**
     * Verifies every mutation fails with the stable actionable exception.
     *
     * @return void
     * @since 1.0.0
     */
    public function testEveryMutationIsRejected(): void
    {
        $provisioner = new AbiV1ModuleProvisioner();
        $operations = [
            static fn () => $provisioner->installTranslations(['KJV']),
            static fn () => $provisioner->installAllTranslations(),
            static fn () => $provisioner->refreshTranslations(['KJV']),
            static fn () => $provisioner->removeTranslation('KJV'),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('ABI v1 unexpectedly accepted a mutating operation.');
            } catch (ProvisioningUnavailableException $exception) {
                self::assertStringContainsString('preinstalled SWORD modules', $exception->getMessage());
            }
        }
    }
}
