<?php

// SPDX-License-Identifier: GPL-2.0-only

declare(strict_types=1);

namespace GetBible\Scripture\Tests\Unit\Domain;

use GetBible\Scripture\Contract\StructuredData;
use GetBible\Scripture\Domain\ConfigEntry;
use GetBible\Scripture\Domain\Introduction;
use GetBible\Scripture\Domain\TranslationMetadata;
use GetBible\Scripture\Domain\Verse;
use GetBible\Scripture\Exception\ContractException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies immutable domain hydration from validated contract records.
 *
 * @since 1.0.0
 */
final class DomainHydrationTest extends TestCase
{
    /**
     * Verifies every translation metadata layer remains available.
     *
     * @return void
     * @since 1.0.0
     */
    public function testTranslationMetadataExposesCompleteModuleRecord(): void
    {
        $record = $this->record(1);
        $metadata = TranslationMetadata::fromRecord($record);

        self::assertSame('bible', $metadata->classification());
        self::assertTrue($metadata->isBible());
        self::assertSame('TestBible', $metadata->name()->requireUtf8());
        self::assertSame('Test Bible', $metadata->description()->requireUtf8());
        self::assertSame('RawText', $metadata->driver()->requireUtf8());
        self::assertSame('en', $metadata->language()->requireUtf8());
        self::assertSame('Biblical Texts', $metadata->swordType()->requireUtf8());
        self::assertSame('ltr', $metadata->direction()->name());
        self::assertSame('utf8', $metadata->encoding()->name());
        self::assertSame('osis', $metadata->markup()->name());
        self::assertSame($record, $metadata->contractRecord());
    }

    /**
     * Verifies supported non-Bible metadata is represented without coercion.
     *
     * @return void
     * @since 1.0.0
     */
    public function testTranslationMetadataPreservesNonBibleClassification(): void
    {
        $record = $this->record(1);
        $record['classification'] = 'commentary';
        $metadata = TranslationMetadata::fromRecord($record);

        self::assertSame('commentary', $metadata->classification());
        self::assertFalse($metadata->isBible());
    }

    /**
     * Verifies invalid metadata type, classification, and required values fail.
     *
     * @return void
     * @since 1.0.0
     */
    public function testTranslationMetadataRejectsMalformedRecords(): void
    {
        $record = $this->record(1);

        foreach (
            [
                array_replace($record, ['type' => 'entry']),
                array_replace($record, ['classification' => 'unsupported']),
                array_replace($record, ['name' => null]),
                array_replace($record, ['direction' => null]),
            ] as $invalid
        ) {
            try {
                TranslationMetadata::fromRecord($invalid);
                self::fail('Malformed translation metadata was accepted.');
            } catch (ContractException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies ordered configuration values preserve exact bytes.
     *
     * @return void
     * @since 1.0.0
     */
    public function testConfigEntryExposesOrderNameAndValue(): void
    {
        $entry = ConfigEntry::fromRecord($this->record(2));

        self::assertSame(0, $entry->ordinal());
        self::assertSame('DistributionLicense', $entry->name()->requireUtf8());
        self::assertSame('Public Domain', $entry->value()->requireUtf8());
    }

    /**
     * Verifies configuration entries reject negative and incomplete records.
     *
     * @return void
     * @since 1.0.0
     */
    public function testConfigEntryRejectsMalformedRecords(): void
    {
        $record = $this->record(2);

        foreach (
            [
                array_replace($record, ['type' => 'entry']),
                array_replace($record, ['ordinal' => -1]),
                array_replace($record, ['name' => null]),
                array_replace($record, ['value' => null]),
            ] as $invalid
        ) {
            try {
                ConfigEntry::fromRecord($invalid);
                self::fail('Malformed configuration entry was accepted.');
            } catch (ContractException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies every ordinary verse layer remains available.
     *
     * @return void
     * @since 1.0.0
     */
    public function testVerseExposesEveryContractLayer(): void
    {
        $record = $this->record(4);
        $verse = Verse::fromRecord($record);

        self::assertSame(1, $verse->ordinal());
        self::assertSame('John 1:1', $verse->key()->requireUtf8());
        self::assertSame('John.1.1', $verse->scope()->osisReference()->requireUtf8());
        self::assertSame('<w lemma="strong:G3056">Word</w>', $verse->raw()->requireUtf8());
        self::assertSame('<w lemma="strong:G3056">Word</w>', $verse->rendered()?->requireUtf8());
        self::assertSame('Word', $verse->stripped()?->requireUtf8());
        self::assertCount(3, $verse->annotationSegments());
        self::assertSame(
            'strong:G3056',
            $verse->officialAttributes()->types()[0]->lists()[0]->values()[0]->value()->requireUtf8(),
        );
        self::assertSame($record, $verse->contractRecord());
    }

    /**
     * Verifies unavailable projections are represented by null.
     *
     * @return void
     * @since 1.0.0
     */
    public function testVerseSupportsUnavailableProjections(): void
    {
        $record = $this->record(4);
        $record['projections_available'] = false;
        $record['rendered_default'] = null;
        $record['stripped'] = null;
        $verse = Verse::fromRecord($record);

        self::assertNull($verse->rendered());
        self::assertNull($verse->stripped());
    }

    /**
     * Verifies a verse cannot accept an introduction or inconsistent layers.
     *
     * @return void
     * @since 1.0.0
     */
    public function testVerseRejectsIntroductionAndInconsistentLayers(): void
    {
        $verseRecord = $this->record(4);

        foreach (
            [
                $this->record(3),
                array_replace($verseRecord, ['type' => 'module']),
                array_replace($verseRecord, ['ordinal' => -1]),
                array_replace($verseRecord, ['scope' => null]),
                array_replace($verseRecord, ['annotation_segments' => [null]]),
                array_replace($verseRecord, ['projections_available' => 'yes']),
                array_replace($verseRecord, ['annotation_segments' => []]),
                array_replace($verseRecord, ['stripped' => null]),
                array_replace($verseRecord, ['projections_available' => false]),
            ] as $invalid
        ) {
            try {
                Verse::fromRecord($invalid);
                self::fail('An invalid ordinary verse was accepted.');
            } catch (ContractException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Verifies every chapter-introduction layer remains available.
     *
     * @return void
     * @since 1.0.0
     */
    public function testIntroductionExposesEveryContractLayer(): void
    {
        $record = $this->record(3);
        $introduction = Introduction::fromRecord($record);

        self::assertSame('chapter', $introduction->scope()->introductionScope());
        self::assertSame('John 1', $introduction->key()->requireUtf8());
        self::assertSame('<title>John</title>', $introduction->raw()->requireUtf8());
        self::assertSame('<title>John</title>', $introduction->rendered()?->requireUtf8());
        self::assertSame('John', $introduction->stripped()?->requireUtf8());
        self::assertCount(3, $introduction->annotationSegments());
        self::assertSame([], $introduction->officialAttributes()->types());
        self::assertSame($record, $introduction->contractRecord());
    }

    /**
     * Verifies an introduction supports unavailable projections.
     *
     * @return void
     * @since 1.0.0
     */
    public function testIntroductionSupportsUnavailableProjections(): void
    {
        $record = $this->record(3);
        $record['projections_available'] = false;
        $record['rendered_default'] = null;
        $record['stripped'] = null;
        $introduction = Introduction::fromRecord($record);

        self::assertNull($introduction->rendered());
        self::assertNull($introduction->stripped());
    }

    /**
     * Verifies an introduction cannot accept a verse or inconsistent layers.
     *
     * @return void
     * @since 1.0.0
     */
    public function testIntroductionRejectsVerseAndInconsistentLayers(): void
    {
        $introductionRecord = $this->record(3);

        foreach (
            [
                $this->record(4),
                array_replace($introductionRecord, ['type' => 'module']),
                array_replace($introductionRecord, ['annotation_segments' => [null]]),
                array_replace($introductionRecord, ['annotation_segments' => []]),
                array_replace($introductionRecord, ['stripped' => null]),
                array_replace($introductionRecord, ['projections_available' => false]),
            ] as $invalid
        ) {
            try {
                Introduction::fromRecord($invalid);
                self::fail('An invalid introduction was accepted.');
            } catch (ContractException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * Loads one deterministic decoded fixture record.
     *
     * @param int $line Zero-based fixture line.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     */
    private function record(int $line): array
    {
        $lines = file(__DIR__ . '/../../Fixtures/test-bible.ndjson');
        self::assertIsArray($lines);
        $record = json_decode($lines[$line], true, 512, JSON_THROW_ON_ERROR);

        return StructuredData::object($record, 'Fixture record');
    }
}
