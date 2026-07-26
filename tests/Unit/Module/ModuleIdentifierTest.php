<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Module;

use GetBible\Scripture\Module\ModuleIdentifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the single traversal-safe module identifier policy.
 *
 * @since 1.0.0
 */
final class ModuleIdentifierTest extends TestCase
{
    /**
     * Verifies normalization of a portable native identifier.
     *
     * @return void
     * @since 1.0.0
     */
    public function testNormalizesPortableIdentifier(): void
    {
        self::assertSame('KJV-1769.1_test+', ModuleIdentifier::normalize(' KJV-1769.1_test+ '));
    }

    /**
     * Verifies path, control, empty, and oversized identifiers are rejected.
     *
     * @param string $identifier Invalid candidate.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidIdentifiers')]
    public function testRejectsUnsafeIdentifier(string $identifier): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ModuleIdentifier::normalize($identifier);
    }

    /**
     * Verifies invalid control bytes are rendered as bounded safe diagnostics.
     *
     * @return void
     * @since 1.0.0
     */
    public function testInvalidIdentifierDiagnosticIsPrintableAndBounded(): void
    {
        try {
            ModuleIdentifier::normalize(str_repeat('A', 129) . "\0");
            self::fail('The oversized identifier was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringNotContainsString("\0", $exception->getMessage());
            self::assertStringEndsWith('...".', $exception->getMessage());
            self::assertLessThan(180, strlen($exception->getMessage()));
        }
    }

    /**
     * Returns invalid identifier candidates.
     *
     * @return iterable<string, array{string}>
     * @since 1.0.0
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'parent' => ['..'];
        yield 'slash' => ['KJV/../../evil'];
        yield 'backslash' => ['KJV\\evil'];
        yield 'control' => ["KJ\nV"];
        yield 'trailing NUL' => ["KJV\0"];
        yield 'unicode' => ['ΚJV'];
        yield 'oversized' => [str_repeat('A', 129)];
    }
}
