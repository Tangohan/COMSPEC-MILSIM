<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseFieldNoteCatalog;
use App\Support\SseFieldNoteTagCatalog as Cat;
use App\Support\SseWantedRealNames;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue livré des étiquettes de style américain des fiches de renseignement : codes uniques, familles connues, libellés
 * et descriptions en français et en anglais, couleurs sobres, aucune organisation réelle, catégories existantes inchangées.
 */
final class SseFieldNoteTagCatalogTest extends TestCase
{
    public function testEveryDeliveredTagIsCompleteInBothLanguages(): void
    {
        $delivered = Cat::delivered();
        self::assertGreaterThan(90, count($delivered));
        $codes = [];
        foreach ($delivered as $key => $tag) {
            self::assertSame($key, $tag['key']);
            self::assertTrue(Cat::hasFamily($tag['family']), $tag['code']);
            self::assertTrue(Cat::FAMILIES[$tag['family']]['pick'], $tag['code']);
            self::assertArrayHasKey($tag['color'], Cat::COLORS, $tag['code']);
            self::assertContains($tag['qualifier'], ['', 'number', 'text', 'date'], $tag['code']);
            foreach (['label_fr', 'label_en', 'desc_fr', 'desc_en'] as $field) {
                self::assertNotSame('', trim($tag[$field]), $tag['code'] . ' ' . $field);
                self::assertLessThan(Cat::DESCRIPTION_MAX + 1, mb_strlen($tag[$field]), $tag['code'] . ' ' . $field);
            }
            self::assertLessThan(Cat::LABEL_MAX + 1, mb_strlen($tag['label_fr']), $tag['code']);
            self::assertLessThan(Cat::SLUG_MAX + 1, strlen($key), $tag['code']);
            self::assertTrue(Cat::isValidCode($tag['code']), $tag['code']);
            self::assertNotContains($tag['code'], $codes, 'code en double : ' . $tag['code']);
            $codes[] = $tag['code'];
            // Une ligne de description : pas de retour à la ligne.
            self::assertStringNotContainsString("\n", $tag['desc_fr'] . $tag['desc_en']);
        }
        self::assertSame(count(Cat::TAGS) + count(SseFieldNoteCatalog::THEMES), count($delivered), 'aucune clé ne se recouvre');
    }

    public function testFamiliesCarryTheRequestedDoctrine(): void
    {
        $counts = Cat::countsByFamily();
        self::assertSame(['discipline' => 13, 'requirement' => 8, 'product' => 13, 'theme' => 15, 'framework' => 13, 'dissemination' => 10, 'validity' => 6, 'category' => 17, 'precedence' => 4, 'evaluation' => 12], $counts);
        $codes = array_column(Cat::delivered(), 'family', 'code');
        foreach ([
            'discipline' => ['HUMINT', 'SIGINT', 'GEOINT', 'IMINT', 'MASINT', 'OSINT', 'TECHINT', 'CI', 'DOMEX', 'DOCEX', 'BIOMETRICS', 'FORENSICS', 'WTI'],
            'requirement' => ['PIR', 'SIR', 'EEI', 'EEFI', 'CCIR', 'IR', 'RFI'],
            'product' => ['SPOTREP', 'SALUTE', 'SITREP', 'INTREP', 'INTSUM', 'IIR', 'TACREP', 'DISUM', 'BDA', 'PATROL DEBRIEF', 'SSE REPORT', 'TARGET PACKET', 'LINK ANALYSIS'],
            'theme' => ['IED', 'C-IED', 'WEAPONS CACHE', 'FACILITATION', 'FINANCING', 'RECRUITMENT', 'HVI', 'INSIDER THREAT', 'SMUGGLING', 'UAS', 'EW', 'CBRN', 'INFRASTRUCTURE', 'POPULATION', 'KEY LEADERS'],
            'dissemination' => ['NOFORN', 'REL TO', 'FOUO', 'CUI', 'ORCON', 'PROPIN', 'LIMDIS', 'HANDLE VIA', 'DISPLAY ONLY', 'RELIDO'],
            'validity' => ['TIME-SENSITIVE', 'PERISHABLE', 'HISTORICAL', 'UNCONFIRMED', 'CORROBORATED', 'SINGLE SOURCE'],
        ] as $family => $expected) {
            foreach ($expected as $code) {
                self::assertSame($family, $codes[$code] ?? null, $code);
            }
        }
        self::assertSame(['FLASH', 'IMMEDIATE', 'PRIORITY', 'ROUTINE'], array_keys(Cat::PRECEDENCES));
        // La priorité de traitement suit l'urgence existante : mêmes clés stockées.
        foreach (Cat::PRECEDENCES as $code => $def) {
            self::assertArrayHasKey($def['urgency'], SseFieldNoteCatalog::URGENCIES, $code);
            self::assertSame($code, Cat::defaultPrecedence($def['urgency']));
        }
        self::assertSame('Reliable', Cat::reliabilityUs('A'));
        self::assertSame('Cannot be judged', Cat::reliabilityUs('F'));
        self::assertSame('Confirmed', Cat::credibilityUs(1));
        self::assertSame('Cannot be judged', Cat::credibilityUs(6));
        // Compléments libres : numéro du besoin, liste REL TO, échéance, canal.
        $delivered = Cat::delivered();
        self::assertSame('number', $delivered['pir']['qualifier']);
        self::assertSame('text', $delivered['rel-to']['qualifier']);
        self::assertSame('text', $delivered['handle-via']['qualifier']);
        self::assertSame('date', $delivered['perishable']['qualifier']);
    }

    public function testExistingCategoriesAndTypesKeepTheirStoredKeys(): void
    {
        self::assertSame(['FRM', 'FRO', 'FRC', 'FRA', 'FRT'], array_keys(SseFieldNoteCatalog::KINDS));
        foreach (SseFieldNoteCatalog::THEMES as $code => $def) {
            $tag = Cat::delivered()[Cat::categoryKey($code)] ?? null;
            self::assertNotNull($tag, $code);
            self::assertSame($code, $tag['code']);
            self::assertSame($def['label'], $tag['label_fr']);
            self::assertSame('category', $tag['family']);
            self::assertNotSame('', $tag['desc_en']);
        }
        foreach (Cat::KIND_SUGGESTIONS as $kind => $suggested) {
            self::assertArrayHasKey($kind, SseFieldNoteCatalog::KINDS);
            foreach ($suggested as $code) {
                self::assertArrayHasKey(Cat::slug($code), Cat::delivered(), $kind . ' ' . $code);
            }
        }
        // Le référentiel envoyé au jeu garde sa forme.
        self::assertSame(['body_max_length', 'title_max_length', 'attachments_max', 'themes_max', 'default_kind', 'default_urgency', 'kinds', 'themes', 'urgencies', 'sources'], array_keys(SseFieldNoteCatalog::clientCatalog()));
    }

    public function testNoRealOrganisationAndNoFictionWording(): void
    {
        $text = '';
        foreach (Cat::delivered() as $tag) {
            $line = implode(' | ', [$tag['code'], $tag['label_fr'], $tag['label_en'], $tag['desc_fr'], $tag['desc_en']]);
            self::assertNull(SseWantedRealNames::find($line), $line);
            $text .= ' ' . mb_strtolower($line);
        }
        foreach (Cat::FAMILIES as $family) {
            $text .= ' ' . mb_strtolower(implode(' ', [$family['fr'], $family['en'], $family['hint_fr'], $family['hint_en']]));
        }
        foreach (Cat::PRECEDENCES as $def) {
            self::assertNull(SseWantedRealNames::find(implode(' ', $def)));
        }
        foreach (['fictif', 'fiction', 'roleplay', 'jeu de rôle', 'simulation', 'imaginaire'] as $word) {
            self::assertStringNotContainsString($word, $text, $word);
        }
    }

    public function testCodesAndSlugs(): void
    {
        self::assertSame('ROUTE WATCH', Cat::normalizeCode('  route   watch '));
        self::assertSame('route-watch', Cat::slug('ROUTE WATCH'));
        self::assertSame('c-ied', Cat::slug('C-IED'));
        self::assertTrue(Cat::isValidCode('C-IED'));
        self::assertFalse(Cat::isValidCode('A'));
        self::assertFalse(Cat::isValidCode('<B>'));
        self::assertFalse(Cat::isValidCode('ÉCOLE'));
        self::assertSame('slate', Cat::normalizeColor('rose-fluo'));
        self::assertSame('cat-armement', Cat::categoryKey('ARMEMENT'));
        self::assertNotContains('precedence', Cat::pickFamilies());
        self::assertNotContains('evaluation', Cat::pickFamilies());
    }
}
