<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Contract;

use GetBible\Scripture\Contract\AnnotationSegment;
use GetBible\Scripture\Contract\ByteValue;
use GetBible\Scripture\Contract\EnumValue;
use GetBible\Scripture\Contract\OfficialAttributes;
use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Contract\ValidationResult;
use GetBible\Scripture\Exception\ContractException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the small immutable values at the contract boundary.
 *
 * @since 1.0.0
 */
final class ContractValueObjectsTest extends TestCase
{
    /**
     * Verifies decoded JSON shape guards preserve accepted values.
     *
     * @return void
     * @since 1.0.0
     */
    public function testStructuredDataGuardsReturnAcceptedShapes(): void
    {
        self::assertSame(['name' => 'KJV'], StructuredData::object(['name' => 'KJV'], 'object'));
        self::assertSame(['KJV', 'WEB'], StructuredData::list(['KJV', 'WEB'], 'list'));
        self::assertSame([1 => 'one'], StructuredData::map([1 => 'one'], 'map'));
    }

    /**
     * Verifies each decoded JSON shape guard rejects another shape.
     *
     * @return void
     * @since 1.0.0
     */
    public function testStructuredDataGuardsRejectWrongShapes(): void
    {
        foreach (
            [
                static fn (): array => StructuredData::object([], 'object'),
                static fn (): array => StructuredData::list(['name' => 'KJV'], 'list'),
                static fn (): array => StructuredData::map('KJV', 'map'),
            ] as $guard
        ) {
            try {
                $guard();
                self::fail('The invalid decoded JSON shape was accepted.');
            } catch (ContractException $exception) {
                self::assertStringContainsString('JSON', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies associative objects cannot conceal integer keys.
     *
     * @return void
     * @since 1.0.0
     */
    public function testStructuredObjectRejectsMixedKeyTypes(): void
    {
        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('non-string object key');
        StructuredData::object([1 => 'one', 'name' => 'KJV'], 'object');
    }

    /**
     * Verifies SWORD enumerations retain the native code and producer name.
     *
     * @return void
     * @since 1.0.0
     */
    public function testEnumValueExposesCodeAndName(): void
    {
        $value = EnumValue::fromArray(['code' => 7, 'name' => 'osis'], 'markup');

        self::assertSame(7, $value->code());
        self::assertSame('osis', $value->name());
    }

    /**
     * Verifies unsigned-byte limits and required enum names.
     *
     * @return void
     * @since 1.0.0
     */
    public function testEnumValueRejectsInvalidMembers(): void
    {
        foreach (
            [
                ['code' => -1, 'name' => 'invalid'],
                ['code' => 256, 'name' => 'invalid'],
                ['code' => 1, 'name' => null],
            ] as $value
        ) {
            try {
                EnumValue::fromArray($value, 'enum');
                self::fail('The invalid SWORD enumeration was accepted.');
            } catch (ContractException $exception) {
                self::assertStringContainsString('enumeration', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies lexical segments expose exact validated bytes.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAnnotationSegmentExposesValidatedValues(): void
    {
        $segment = AnnotationSegment::fromArray([
            'kind' => 'entity',
            'interpretation' => 'uninterpreted',
            'raw' => $this->bytes('&amp;'),
        ], 2);

        self::assertSame('entity', $segment->kind());
        self::assertSame('uninterpreted', $segment->interpretation());
        self::assertSame('&amp;', $segment->raw()->requireUtf8());
    }

    /**
     * Verifies segment kind, interpretation, and byte value are all required.
     *
     * @return void
     * @since 1.0.0
     */
    public function testAnnotationSegmentRejectsInvalidMembers(): void
    {
        foreach (
            [
                ['kind' => 'unknown', 'interpretation' => 'uninterpreted', 'raw' => $this->bytes('x')],
                ['kind' => 'text', 'interpretation' => 'uninterpreted', 'raw' => $this->bytes('x')],
                ['kind' => 'markup', 'interpretation' => 'uninterpreted', 'raw' => null],
            ] as $segment
        ) {
            try {
                AnnotationSegment::fromArray($segment, 0);
                self::fail('The invalid annotation segment was accepted.');
            } catch (ContractException $exception) {
                self::assertStringContainsString('Annotation segment', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies all three ordered official-attribute levels remain addressable.
     *
     * @return void
     * @since 1.0.0
     */
    public function testOfficialAttributesPreserveOrderedLevels(): void
    {
        $attributes = OfficialAttributes::fromArray([[
            'name' => $this->bytes('Footnote'),
            'lists' => [[
                'name' => $this->bytes('Word'),
                'values' => [[
                    'name' => $this->bytes('Lemma'),
                    'value' => $this->bytes('strong:G3056'),
                ]],
            ]],
        ]]);
        $type = $attributes->types()[0];
        $list = $type->lists()[0];
        $value = $list->values()[0];

        self::assertSame('Footnote', $type->name()->requireUtf8());
        self::assertSame('Word', $list->name()->requireUtf8());
        self::assertSame('Lemma', $value->name()->requireUtf8());
        self::assertSame('strong:G3056', $value->value()->requireUtf8());
    }

    /**
     * Verifies the attribute map requires ordered, complete nested objects.
     *
     * @return void
     * @since 1.0.0
     */
    public function testOfficialAttributesRejectMalformedLevels(): void
    {
        foreach (
            [
                ['named' => []],
                [['name' => null, 'lists' => []]],
                [['name' => $this->bytes('Type'), 'lists' => 'invalid']],
                [['name' => $this->bytes('Type'), 'lists' => [['name' => null, 'values' => []]]]],
                [[
                    'name' => $this->bytes('Type'),
                    'lists' => [['name' => $this->bytes('List'), 'values' => [['name' => null]]]],
                ]],
            ] as $attributes
        ) {
            try {
                OfficialAttributes::fromArray($attributes);
                self::fail('The malformed official attribute map was accepted.');
            } catch (ContractException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies validation summaries expose every immutable field.
     *
     * @return void
     * @since 1.0.0
     */
    public function testValidationResultExposesFooterDigest(): void
    {
        $footer = ['stream_sha256' => str_repeat('a', 64), 'success' => true];
        $result = new ValidationResult('list', [], $footer);

        self::assertSame('list', $result->command());
        self::assertSame([], $result->modules());
        self::assertSame($footer, $result->footer());
        self::assertSame(str_repeat('a', 64), $result->streamSha256());
    }

    /**
     * Verifies an impossible corrupt validated footer fails explicitly.
     *
     * @return void
     * @since 1.0.0
     */
    public function testValidationResultRejectsMissingDigestOnAccess(): void
    {
        $result = new ValidationResult('list', [], []);

        $this->expectException(\LogicException::class);
        $result->streamSha256();
    }

    /**
     * Creates an exact UTF-8 byte envelope.
     *
     * @param string $value Exact value.
     *
     * @return array{base64: string, encoding: string, sha256: string, size: int, utf8: string}
     * @since 1.0.0
     */
    private function bytes(string $value): array
    {
        return [
            'base64' => base64_encode($value),
            'encoding' => 'base64',
            'sha256' => hash('sha256', $value),
            'size' => strlen($value),
            'utf8' => $value,
        ];
    }
}
