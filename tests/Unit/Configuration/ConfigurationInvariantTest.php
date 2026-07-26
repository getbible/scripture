<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Configuration;

use GetBible\Scripture\Configuration\Configuration;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies defensive accessors reject corrupted internal registry state.
 *
 * @since 1.0.0
 */
final class ConfigurationInvariantTest extends TestCase
{
    /**
     * Verifies every typed accessor fails closed after invariant corruption.
     *
     * @param string $setting Registry setting.
     * @param mixed $value Corrupt value.
     * @param \Closure(Configuration): mixed $accessor Accessor under test.
     * @param string $message Expected diagnostic.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('corruptRegistryProvider')]
    public function testAccessorsRejectCorruptRegistry(
        string $setting,
        mixed $value,
        \Closure $accessor,
        string $message,
    ): void {
        $configuration = $this->corrupt($setting, $value);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $accessor($configuration);
    }

    /**
     * Supplies corrupt values for every guarded accessor.
     *
     * @return iterable<string, array{string, mixed, \Closure(Configuration): mixed, string}>
     * @since 1.0.0
     */
    public static function corruptRegistryProvider(): iterable
    {
        yield 'cache path' => [
            'cache_path',
            null,
            static fn (Configuration $configuration): string => $configuration->cachePath(),
            'cache path',
        ];
        yield 'refresh interval' => [
            'refresh_interval',
            '',
            static fn (Configuration $configuration): string => $configuration->refreshIntervalSpec(),
            'refresh interval',
        ];
        yield 'auto refresh' => [
            'auto_refresh',
            'yes',
            static fn (Configuration $configuration): bool => $configuration->autoRefresh(),
            'automatic refresh',
        ];
        yield 'lock timeout' => [
            'lock_timeout',
            0,
            static fn (Configuration $configuration): int => $configuration->lockTimeout(),
            'lock timeout',
        ];
        yield 'module container' => [
            'modules',
            'KJV',
            static fn (Configuration $configuration): mixed => $configuration->modules(),
            'not an array',
        ];
        yield 'module item' => [
            'modules',
            ['KJV', 1],
            static fn (Configuration $configuration): mixed => $configuration->modules(),
            'non-string value',
        ];
        yield 'provisioning policy' => [
            'provisioning_enabled',
            1,
            static fn (Configuration $configuration): bool => $configuration->provisioningEnabled(),
            'provisioning policy',
        ];
        yield 'install all policy' => [
            'install_all',
            1,
            static fn (Configuration $configuration): bool => $configuration->installAll(),
            'all-module installation policy',
        ];
    }

    /**
     * Verifies malformed ISO-8601 syntax is translated to configuration error.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsMalformedRefreshInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO-8601 refresh interval');

        Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
            'refresh_interval' => 'every month',
        ]);
    }

    /**
     * Verifies the filesystem root remains a valid normalized cache root.
     *
     * @return void
     * @since 1.0.0
     */
    public function testPreservesFilesystemRootCachePath(): void
    {
        self::assertSame('/', Configuration::fromEnvironment([
            'cache_path' => '///',
        ])->cachePath());
    }

    /**
     * Verifies temporary storage is used when user cache locations are absent.
     *
     * @return void
     * @since 1.0.0
     */
    public function testFallsBackToTemporaryCacheRoot(): void
    {
        $names = ['GETBIBLE_SCRIPTURE_CACHE_PATH', 'XDG_CACHE_HOME', 'HOME'];
        $previous = [];

        foreach ($names as $name) {
            $previous[$name] = getenv($name);
            putenv($name);
        }

        try {
            self::assertStringStartsWith(
                sys_get_temp_dir() . '/getbible-scripture-',
                Configuration::fromEnvironment()->cachePath(),
            );
        } finally {
            foreach ($previous as $name => $value) {
                if (is_string($value)) {
                    putenv($name . '=' . $value);
                } else {
                    putenv($name);
                }
            }
        }
    }

    /**
     * Creates configuration with one intentionally corrupt registry value.
     *
     * @param string $setting Registry setting.
     * @param mixed $value Corrupt value.
     *
     * @return Configuration
     * @since 1.0.0
     */
    private function corrupt(string $setting, mixed $value): Configuration
    {
        $configuration = Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
        ]);
        $registry = $configuration->registry();
        $registry->set($setting, $value);
        $property = new \ReflectionProperty(Configuration::class, 'registry');
        $property->setValue($configuration, $registry);

        self::assertInstanceOf(Registry::class, $configuration->registry());

        return $configuration;
    }
}
