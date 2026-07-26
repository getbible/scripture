<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Console;

use GetBible\Scripture\Console\ModuleOption;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies command module-option shape validation.
 *
 * @since 1.0.0
 */
final class ModuleOptionTest extends TestCase
{
    /**
     * Verifies ordered module options are returned unchanged.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNormalizesStringList(): void
    {
        self::assertSame(['KJV', 'WEB'], ModuleOption::normalize(['KJV', 'WEB']));
        self::assertSame([], ModuleOption::normalize([]));
    }

    /**
     * Verifies invalid Symfony option shapes are rejected.
     *
     * @param mixed $value Invalid option value.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidValueProvider')]
    public function testRejectsInvalidOptionShape(mixed $value): void
    {
        $this->expectException(\UnexpectedValueException::class);

        ModuleOption::normalize($value);
    }

    /**
     * Supplies invalid option shapes.
     *
     * @return iterable<string, array{mixed}>
     * @since 1.0.0
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'scalar' => ['KJV'];
        yield 'non-string item' => [['KJV', 1]];
    }
}
