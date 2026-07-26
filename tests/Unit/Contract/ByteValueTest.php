<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Contract;

use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Exception\ContractException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame('KJV', $value->utf8());
        self::assertSame('KJV', $value->requireUtf8());
        self::assertSame(3, $value->size());
        self::assertSame(
            'f98326ec7971053443d80268b911680a0eec8d4ead3b1d67445b7d534f1b5b2f',
            $value->sha256(),
        );
        self::assertSame('S0pW', $value->toArray()['base64']);
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

    /**
     * Verifies opaque bytes remain accessible without claiming a UTF-8 view.
     *
     * @return void
     * @since 1.0.0
     */
    public function testMissingUtf8ProjectionIsExplicit(): void
    {
        $value = ByteValue::fromArray([
            'base64' => '/w==',
            'encoding' => 'base64',
            'sha256' => hash('sha256', "\xff"),
            'size' => 1,
        ]);

        self::assertNull($value->utf8());
        self::assertSame("\xff", $value->bytes());

        $this->expectException(\UnexpectedValueException::class);
        $value->requireUtf8();
    }

    /**
     * Verifies malformed and internally inconsistent envelopes are rejected.
     *
     * @param array<array-key, mixed> $envelope Invalid envelope.
     *
     * @return void
     * @since 1.0.0
     */
    #[DataProvider('invalidEnvelopes')]
    public function testInvalidEnvelopeIsRejected(array $envelope): void
    {
        $this->expectException(ContractException::class);
        ByteValue::fromArray($envelope, 'fixture');
    }

    /**
     * Returns independently invalid byte envelopes.
     *
     * @return iterable<string, array{array<array-key, mixed>}>
     * @since 1.0.0
     */
    public static function invalidEnvelopes(): iterable
    {
        $valid = [
            'base64' => 'S0pW',
            'encoding' => 'base64',
            'sha256' => hash('sha256', 'KJV'),
            'size' => 3,
            'utf8' => 'KJV',
        ];

        yield 'unknown member' => [$valid + ['extra' => true]];
        yield 'invalid encoding' => [array_replace($valid, ['encoding' => 'hex'])];
        yield 'invalid digest shape' => [array_replace($valid, ['sha256' => 'invalid'])];
        yield 'negative size' => [array_replace($valid, ['size' => -1])];
        yield 'non-string UTF-8' => [array_replace($valid, ['utf8' => 1])];
        yield 'non-canonical Base64' => [array_replace($valid, ['base64' => 'S0pW='])];
        yield 'wrong size' => [array_replace($valid, ['size' => 4])];
        yield 'wrong UTF-8 projection' => [array_replace($valid, ['utf8' => 'kjv'])];
    }
}
