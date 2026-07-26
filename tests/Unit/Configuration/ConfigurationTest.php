<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Configuration;

use GetBible\Scripture\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * Verifies production maintenance configuration normalization and policy.
 *
 * @since 0.3.0
 */
final class ConfigurationTest extends TestCase
{
    /**
     * Verifies module lists are trimmed, ordered, and de-duplicated.
     *
     * @return void
     * @since 0.3.0
     */
    public function testNormalizesMaintenanceConfiguration(): void
    {
        $configuration = Configuration::fromEnvironment([
            'cache_path' => '/tmp/getbible-scripture-configuration-test',
            'modules' => ' KJV, WEB, KJV ',
            'provisioning_enabled' => 'yes',
            'install_all' => 'on',
            'lock_timeout' => '45',
        ]);

        self::assertSame(['KJV', 'WEB'], $configuration->modules());
        self::assertTrue($configuration->provisioningEnabled());
        self::assertTrue($configuration->installAll());
        self::assertSame(45, $configuration->lockTimeout());
        self::assertSame(
            '/tmp/getbible-scripture-configuration-test/maintenance/state.json',
            $configuration->maintenanceStatePath(),
        );
    }

    /**
     * Verifies all-module installation requires explicit mutation policy.
     *
     * @return void
     * @since 0.3.0
     */
    public function testRejectsInstallAllWithoutProvisioningPolicy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires explicit provisioning_enabled');

        Configuration::fromEnvironment([
            'cache_path' => '/tmp/getbible-scripture-configuration-test',
            'install_all' => true,
        ]);
    }

    /**
     * Verifies a syntactically valid interval must still advance time.
     *
     * @return void
     * @since 0.3.0
     */
    public function testRejectsNonAdvancingInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must advance time');

        Configuration::fromEnvironment([
            'cache_path' => '/tmp/getbible-scripture-configuration-test',
            'refresh_interval' => 'P0D',
        ]);
    }
}
