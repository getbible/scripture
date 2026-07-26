<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Contract;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Exception\ContractException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies lossless v1 byte-envelope handling.
 *
 * @since 0.1.0
 */
final class ByteValueTest extends TestCase
{
    /**
     * Verifies canonical Base64, size, digest, and UTF-8 access.
     *
     * @return void
     * @since 0.1.0
     */
    public function testVerifiedByteValueExposesAuthoritativeBytes(): void
    {
        $value = ByteValue::fromArray([
            'base64' => 'S0pW',
            'encoding' => 'base64',
            'sha256' => 'f98326ec7971053443d80268b911680a0eec8d4ead3b1d67445b7d534f1b5b2f',
            'size' => 3,
            'utf8' => 'KJV',
        ]);

        self::assertSame('KJV', $value->bytes());
        self::assertSame('KJV', $value->requireUtf8());
        self::assertSame(3, $value->size());
    }

    /**
     * Verifies that digest tampering is rejected.
     *
     * @return void
     * @since 0.1.0
     */
    public function testDigestMismatchIsRejected(): void
    {
        $this->expectException(ContractException::class);

        ByteValue::fromArray([
            'base64' => 'S0pW',
            'encoding' => 'base64',
            'sha256' => str_repeat('0', 64),
            'size' => 3,
            'utf8' => 'KJV',
        ]);
    }
}
