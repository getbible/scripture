<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Contract;

use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Contract\VerseScope;
use GetBible\Scripture\Exception\ContractException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies semantic agreement between verse coordinates and introduction type.
 *
 * @since 1.0.0
 */
final class VerseScopeTest extends TestCase
{
    /**
     * Verifies the deterministic chapter introduction remains valid.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAcceptsConsistentChapterIntroduction(): void
    {
        $scope = VerseScope::fromArray($this->scope(3));

        self::assertSame('chapter', $scope->introductionScope());
        self::assertFalse($scope->isVerse());
    }

    /**
     * Verifies a verse scope cannot describe verse zero.
     *
     * @return void
     * @since 1.0.0
     */
    public function testRejectsVerseScopeAtIntroductionCoordinate(): void
    {
        $scope = $this->scope(3);
        $scope['intro_scope'] = 'verse';

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('do not agree');

        VerseScope::fromArray($scope);
    }

    /**
     * Loads one scope from the deterministic fixture line.
     *
     * @param int $line Zero-based fixture line.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    private function scope(int $line): array
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);
        $record = json_decode($lines[$line], true, 512, JSON_THROW_ON_ERROR);
        $record = StructuredData::object($record, 'Fixture entry');

        return StructuredData::object($record['scope'] ?? null, 'Fixture scope');
    }
}
