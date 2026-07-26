<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Configuration;

use GetBible\Scripture\Configuration\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies configuration defaults, accessors, and rejected input shapes.
 *
 * @since 1.0.0
 */
final class ConfigurationEdgeCasesTest extends TestCase
{
    /**
     * Verifies every normalized setting remains available through its accessor.
     *
     * @return void
     * @since 1.0.0
     */
    public function testExposesCompleteNormalizedConfiguration(): void
    {
        $configuration = Configuration::fromEnvironment([
            'module_path' => ' /srv/sword ',
            'cache_path' => '/srv/scripture-cache///',
            'refresh_interval' => 'P14D',
            'auto_refresh' => 0,
            'lock_timeout' => 17,
            'modules' => [' WEB ', 'KJV', 'WEB'],
            'provisioning_enabled' => 1,
            'install_all' => true,
        ]);

        self::assertSame('/srv/sword', $configuration->modulePath());
        self::assertSame('/srv/scripture-cache', $configuration->cachePath());
        self::assertSame('P14D', $configuration->refreshIntervalSpec());
        self::assertSame(14, $configuration->refreshInterval()->d);
        self::assertFalse($configuration->autoRefresh());
        self::assertSame(17, $configuration->lockTimeout());
        self::assertSame(['WEB', 'KJV'], $configuration->modules());
        self::assertTrue($configuration->provisioningEnabled());
        self::assertTrue($configuration->installAll());
        self::assertSame(
            '/srv/scripture-cache/maintenance/state.json',
            $configuration->maintenanceStatePath(),
        );
    }

    /**
     * Verifies callers cannot mutate the immutable configuration through Registry.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRegistryReturnsDefensiveCopy(): void
    {
        $configuration = Configuration::fromEnvironment([
            'cache_path' => '/original/cache',
        ]);
        $registry = $configuration->registry();
        $registry->set('cache_path', '/mutated/cache');

        self::assertSame('/original/cache', $configuration->cachePath());
        self::assertSame('/mutated/cache', $registry->get('cache_path'));
    }

    /**
     * Verifies XDG cache policy provides the deterministic default.
     *
     * @return void
     * @since 1.0.0
     */
    public function testUsesXdgDefaultCachePath(): void
    {
        $previousXdg = getenv('XDG_CACHE_HOME');
        $previousCache = getenv('GETBIBLE_SCRIPTURE_CACHE_PATH');
        putenv('XDG_CACHE_HOME=/var/cache/example');
        putenv('GETBIBLE_SCRIPTURE_CACHE_PATH');

        try {
            self::assertSame(
                '/var/cache/example/getbible/scripture',
                Configuration::fromEnvironment()->cachePath(),
            );
        } finally {
            $this->restoreEnvironment('XDG_CACHE_HOME', $previousXdg);
            $this->restoreEnvironment('GETBIBLE_SCRIPTURE_CACHE_PATH', $previousCache);
        }
    }

    /**
     * Verifies accepted textual boolean forms are strict and symmetric.
     *
     * @param string $value Candidate value.
     * @param bool $expected Parsed value.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('booleanProvider')]
    public function testParsesSupportedBooleanStrings(string $value, bool $expected): void
    {
        self::assertSame($expected, Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
            'auto_refresh' => $value,
        ])->autoRefresh());
    }

    /**
     * Supplies accepted boolean text.
     *
     * @return iterable<string, array{string, bool}>
     * @since 1.0.0
     */
    public static function booleanProvider(): iterable
    {
        yield 'true' => ['true', true];
        yield 'yes' => ['YES', true];
        yield 'on' => [' on ', true];
        yield 'false' => ['false', false];
        yield 'no' => ['NO', false];
        yield 'off' => [' off ', false];
    }

    /**
     * Verifies malformed boolean values are rejected.
     *
     * @param bool|int|string|array<array-key, mixed> $value Invalid value.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidBooleanProvider')]
    public function testRejectsInvalidBooleanValues(bool|int|string|array $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
            'auto_refresh' => $value,
        ]);
    }

    /**
     * Supplies malformed boolean values.
     *
     * @return iterable<string, array{bool|int|string|array<array-key, mixed>}>
     * @since 1.0.0
     */
    public static function invalidBooleanProvider(): iterable
    {
        yield 'integer' => [2];
        yield 'text' => ['occasionally'];
        yield 'array' => [['true']];
    }

    /**
     * Verifies malformed lock timeouts are rejected.
     *
     * @param bool|int|string|array<array-key, mixed> $value Invalid value.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidTimeoutProvider')]
    public function testRejectsInvalidLockTimeout(bool|int|string|array $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
            'lock_timeout' => $value,
        ]);
    }

    /**
     * Supplies malformed positive integers.
     *
     * @return iterable<string, array{bool|int|string|array<array-key, mixed>}>
     * @since 1.0.0
     */
    public static function invalidTimeoutProvider(): iterable
    {
        yield 'zero integer' => [0];
        yield 'negative integer' => [-1];
        yield 'leading zero' => ['01'];
        yield 'decimal' => ['1.5'];
        yield 'boolean' => [true];
        yield 'array' => [[30]];
    }

    /**
     * Verifies malformed module lists are rejected.
     *
     * @param bool|int|string|array<array-key, mixed> $value Invalid value.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidModulesProvider')]
    public function testRejectsInvalidModuleLists(bool|int|string|array $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Configuration::fromEnvironment([
            'cache_path' => '/srv/cache',
            'modules' => $value,
        ]);
    }

    /**
     * Supplies malformed module configurations.
     *
     * @return iterable<string, array{bool|int|string|array<array-key, mixed>}>
     * @since 1.0.0
     */
    public static function invalidModulesProvider(): iterable
    {
        yield 'boolean' => [true];
        yield 'integer' => [1];
        yield 'empty item' => ['KJV,'];
        yield 'nul byte' => [["KJV\0"]];
        yield 'non-string item' => [['KJV', 1]];
    }

    /**
     * Restores one process environment variable.
     *
     * @param string $name Variable name.
     * @param string|false $value Previous value.
     *
     * @return void
     * @since 1.0.0
     */
    private function restoreEnvironment(string $name, string|false $value): void
    {
        if (is_string($value)) {
            putenv($name . '=' . $value);

            return;
        }

        putenv($name);
    }
}
