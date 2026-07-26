<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Configuration;

use GetBible\Scripture\Configuration\Configuration;
use GetBible\Scripture\Configuration\JsonConfigurationRepository;
use PHPUnit\Framework\TestCase;

/**
 * Verifies restrictive and atomic application configuration persistence.
 *
 * @since 1.0.0
 */
final class JsonConfigurationRepositoryTest extends TestCase
{
    /**
     * Isolated test directory.
     *
     * @var string
     * @since 1.0.0
     */
    private string $directory;

    /**
     * Creates an isolated configuration root.
     *
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . '/getbible-scripture-configuration-repository-'
            . bin2hex(random_bytes(8));
    }

    /**
     * Removes isolated configuration files.
     *
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        $path = $this->directory . '/configuration.json';

        if (is_file($path) || is_link($path)) {
            unlink($path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * Verifies validated settings survive a restrictive atomic round trip.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRoundTripsRestrictiveConfiguration(): void
    {
        $path = $this->directory . '/configuration.json';
        $repository = new JsonConfigurationRepository($path);
        $configuration = Configuration::fromEnvironment([
            'module_path' => '/srv/sword',
            'cache_path' => '/srv/cache',
            'refresh_interval' => 'P7D',
            'auto_refresh' => false,
            'lock_timeout' => 45,
            'modules' => ['KJV', 'WEB'],
        ]);

        self::assertFalse($repository->exists());

        $repository->save($configuration);

        self::assertTrue($repository->exists());
        self::assertSame(0600, fileperms($path) & 0777);
        self::assertSame(
            [
                'module_path' => '/srv/sword',
                'cache_path' => '/srv/cache',
                'refresh_interval' => 'P7D',
                'auto_refresh' => false,
                'lock_timeout' => 45,
                'modules' => ['KJV', 'WEB'],
                'provisioning_enabled' => false,
                'install_all' => false,
            ],
            $repository->load(),
        );
    }

    /**
     * Verifies corrupt persisted configuration is rejected.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsCorruptConfiguration(): void
    {
        self::assertTrue(mkdir($this->directory, 0700, true));
        $path = $this->directory . '/configuration.json';
        self::assertNotFalse(file_put_contents($path, '{"format":"invalid"}'));
        $repository = new JsonConfigurationRepository($path);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('document format is invalid');

        $repository->load();
    }

    /**
     * Verifies persistence remains unavailable without an explicit path.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRequiresExplicitPathForPersistence(): void
    {
        $repository = new JsonConfigurationRepository(null);

        self::assertSame([], $repository->load());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('configuration path must be supplied');

        $repository->save(Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
        ]));
    }
}
