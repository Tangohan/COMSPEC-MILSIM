<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DecorationCatalog;
use PHPUnit\Framework\TestCase;

final class DecorationCatalogTest extends TestCase
{
    public function testCatalogHasExpandedGenericEntriesAndNoOfficialFlag(): void
    {
        $all = DecorationCatalog::all();
        self::assertCount(26, $all);
        self::assertCount(17, DecorationCatalog::ribbons());
        self::assertCount(9, DecorationCatalog::medals());

        $ids = [];
        foreach ($all as $row) {
            self::assertNotSame('', $row['id']);
            self::assertArrayNotHasKey($row['id'], $ids);
            $ids[$row['id']] = true;
            self::assertContains($row['family'], ['GENERIC', 'NATO_INSPIRED', 'CUSTOM']);
            self::assertContains($row['type'], ['ribbon', 'medal']);
            self::assertFalse($row['isOfficialReference']);
            self::assertNotSame('', $row['pattern']);
            self::assertNotSame('', $row['patternClass']);
            self::assertSame(46, $row['cardWidthPx']);
            self::assertSame(16, $row['cardHeightPx']);
            self::assertSame(52, $row['rackWidthPx']);
            self::assertSame(18, $row['rackHeightPx']);
            self::assertSame(34, $row['medalCardPx']);
            self::assertSame(62, $row['medalFichePx']);
            self::assertGreaterThan(0, count($row['colors']));
            self::assertArrayHasKey('imageUrl', $row);
        }

        self::assertNotNull(DecorationCatalog::find('rbn_service_multinational_nato'));
        self::assertSame('NATO_INSPIRED', DecorationCatalog::find('rbn_service_multinational_nato')['family']);
        self::assertSame('NATO_INSPIRED', DecorationCatalog::find('med_service_multinational_nato')['family']);
        self::assertSame('GENERIC', DecorationCatalog::find('rbn_service_distingue')['family']);
        self::assertSame('Catalogue · or', DecorationCatalog::familyLine(DecorationCatalog::find('med_etoile_bravoure') ?? []));
        self::assertSame('Multinationale · couronne stylisée', DecorationCatalog::familyLine(DecorationCatalog::find('med_service_multinational_nato') ?? []));
        self::assertSame('Catalogue', DecorationCatalog::familyLabel('GENERIC'));
        self::assertSame('Créée par l’organisation', DecorationCatalog::familyLabel('CUSTOM'));
        self::assertNotNull(DecorationCatalog::find('rbn_honneur_pourpre'));
        self::assertNotNull(DecorationCatalog::find('rbn_sauvetage'));
        self::assertNotNull(DecorationCatalog::find('med_medaille_honneur'));
    }

    public function testResolveMatchesIdsNamesAndKeepsCustomFallback(): void
    {
        $resolved = DecorationCatalog::resolveLines([
            'rbn_service_distingue',
            'Croix du mérite — échelon or',
            'Placard commémoratif Atlas',
            'rbn_service_distingue',
        ]);
        self::assertCount(3, $resolved);
        self::assertSame('rbn_service_distingue', $resolved[0]['id']);
        self::assertFalse($resolved[0]['isCustom']);
        self::assertSame('med_croix_merite_or', $resolved[1]['id']);
        self::assertTrue($resolved[2]['isCustom']);
        self::assertSame('Placard commémoratif Atlas', $resolved[2]['name']);
        self::assertFalse($resolved[2]['isOfficialReference']);
    }

    public function testMergeRackInputPrefersCatalogIdsThenCustomLines(): void
    {
        $merged = DecorationCatalog::mergeRackInput(
            ['rbn_merite', 'not-a-real-id', 'rbn_merite'],
            "rbn_action_combat\nMention libre\n",
            24,
            160
        );
        self::assertSame(['rbn_merite', 'rbn_action_combat', 'Mention libre'], $merged);
    }

    public function testCautionBannerIsHumanFacing(): void
    {
        self::assertStringContainsString('représentations génériques', DecorationCatalog::CAUTION);
        self::assertStringNotContainsString('GENERIC', DecorationCatalog::CAUTION);
        self::assertStringNotContainsString('NATO_INSPIRED', DecorationCatalog::CAUTION);
        self::assertStringNotContainsString('tioh.army.mil', DecorationCatalog::FOOTER);
        self::assertStringNotContainsString('nato.int', DecorationCatalog::FOOTER);
        self::assertStringNotContainsString('official reproduction', strtolower(DecorationCatalog::CAUTION));
    }

    public function testGlyphSvgsAreVectorOnly(): void
    {
        foreach (['star', 'cross', 'wreath', 'circle'] as $glyph) {
            $svg = DecorationCatalog::glyphSvg($glyph);
            self::assertStringContainsString('<svg', $svg);
            self::assertStringNotContainsString('<img', $svg);
        }
    }

    public function testPatternChoicesAreHumanLabeled(): void
    {
        self::assertArrayHasKey('dk-rb-honor', DecorationCatalog::PATTERN_CHOICES);
        self::assertSame('Pourpre et argent', DecorationCatalog::PATTERN_CHOICES['dk-rb-honor']);
        foreach (DecorationCatalog::PATTERN_CHOICES as $class => $label) {
            self::assertMatchesRegularExpression('/^dk-[a-z0-9_-]+$/i', $class);
            self::assertStringNotContainsString('GENERIC', $label);
            self::assertStringNotContainsString('_', $label);
        }
    }
}
