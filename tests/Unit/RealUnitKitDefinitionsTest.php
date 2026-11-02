<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\I18n\GeneratedLabels;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use App\Services\OrganizationCatalog\RealUnitKitDefinitions;
use App\Support\HtmlTranslator;
use App\Support\I18n\UntranslatedFrenchScanner;
use PHPUnit\Framework\TestCase;

/**
 * Modèles inspirés d’unités réelles : arbre cohérent, identifiants uniques, chaque unité sourcée, libellés FR/EN,
 * TSOC et commandements demandés présents, rien de non public.
 */
final class RealUnitKitDefinitionsTest extends TestCase
{
    protected function tearDown(): void
    {
        GeneratedLabels::useRepository(null);
        HtmlTranslator::reset();
    }

    public function testKitsAreStructuredSourcedAndTenantAgnostic(): void
    {
        $kits = RealUnitKitDefinitions::kits();
        self::assertGreaterThanOrEqual(20, count($kits));
        $codes = array_column($kits, 'code');
        self::assertSame($codes, RealUnitKitDefinitions::codes());
        self::assertSame(count($codes), count(array_unique($codes)));
        self::assertSame([], array_intersect($codes, OrganizationKitDefinitions::genericCodes()));
        $sources = RealUnitKitDefinitions::sources();
        foreach ($sources as $key => [$title, $url]) {
            self::assertNotSame('', trim($title), $key);
            self::assertMatchesRegularExpression('~^https://[^\s]+$~', $url, $key);
        }
        $types = array_keys((require dirname(__DIR__, 2) . '/app/Config/units.php')['types']);
        foreach ($kits as $kit) {
            $code = (string) $kit['code'];
            self::assertStringStartsWith(RealUnitKitDefinitions::PREFIX, $code);
            self::assertTrue(OrganizationKitDefinitions::isRealUnits($kit));
            self::assertStringNotContainsString('"tenant_id"', (string) json_encode($kit));
            self::assertNotSame('', trim((string) $kit['title']), $code);
            self::assertNotSame('', trim((string) $kit['summary']), $code);
            self::assertContains($kit['origin']['country'], ['US', 'FR', 'DE', 'GB', 'MY'], $code);
            self::assertNotSame('', (string) $kit['origin']['branch'], $code);
            self::assertNotSame('', (string) $kit['origin']['command'], $code);
            self::assertNotEmpty($kit['sources'], $code);
            self::assertSame([], $kit['roles'], $code);
            self::assertGreaterThan(0, RealUnitKitDefinitions::jobCatalogCount($kit), $code . ' : emplois sourcés liés');
            $keys = array_column($kit['units'], 'key');
            self::assertSame(count($keys), count(array_unique($keys)), $code);
            $roots = 0;
            $kitSources = array_column($kit['sources'], 'key');
            foreach ($kit['units'] as $unit) {
                $parent = $unit['parent_key'];
                if ($parent === null) {
                    $roots++;
                } else {
                    self::assertContains($parent, $keys, $code . ':' . $unit['key']);
                    self::assertLessThan(array_search($unit['key'], $keys, true), array_search($parent, $keys, true), 'parent avant enfant');
                }
                self::assertSame($unit['key'], $unit['slug']);
                self::assertMatchesRegularExpression('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', (string) $unit['slug']);
                self::assertNotSame('', trim((string) $unit['name']));
                self::assertLessThanOrEqual(20, mb_strlen((string) $unit['code']), $unit['slug']);
                self::assertNotSame('', trim((string) $unit['code']), $unit['slug']);
                self::assertContains($unit['type'], $types, $unit['slug']);
                self::assertContains($unit['echelon'], RealUnitKitDefinitions::echelons(), $unit['slug']);
                self::assertNotEmpty($unit['sources'], 'Unité sans source : ' . $unit['slug']);
                foreach ($unit['sources'] as $s) {
                    self::assertArrayHasKey($s, $sources, $unit['slug']);
                    self::assertContains($s, $kitSources, $code);
                }
            }
            self::assertSame(1, $roots, $code . ' : une seule racine');
        }
    }

    public function testRequestedCommandsArePresent(): void
    {
        $byCode = array_column(RealUnitKitDefinitions::kits(), null, 'code');
        $codesIn = static fn (string $kit): array => array_column($byCode[RealUnitKitDefinitions::PREFIX . $kit]['units'], 'code');
        foreach (['USSOCOM', 'JSOC', 'SOCAFRICA', 'SOCCENT', 'SOCEUR', 'SOCKOR', 'SOCNORTH', 'SOCPAC', 'SOCSOUTH', 'USASOC', 'NSWC', 'AFSOC', 'MARSOC'] as $c) {
            self::assertContains($c, $codesIn('us_ussocom'), $c);
        }
        foreach (['1st SFC(A)', '1st SFG(A)', '3rd SFG(A)', '5th SFG(A)', '7th SFG(A)', '10th SFG(A)', '19th SFG(A)', '20th SFG(A)', '75th RGR', '1/75', '2/75', '3/75', '160th SOAR(A)', '4th POG(A)', '8th POG(A)', '95th CA BDE', 'USAJFKSWCS'] as $c) {
            self::assertContains($c, $codesIn('us_usasoc'), $c);
        }
        foreach (['NSWG-1', 'NSWG-2', 'NSWG-4', 'NSWG-8', 'NSWG-11', 'ST-1', 'SDVT-1', 'SBT-12'] as $c) {
            self::assertContains($c, $codesIn('us_nswc'), $c);
        }
        foreach (['720th STG', '724th STG', '24th STS'] as $c) {
            self::assertContains($c, $codesIn('us_afsoc'), $c);
        }
        foreach (['MRR', '1st MRB', '2nd MRB', '3rd MRB', 'MSOT'] as $c) {
            self::assertContains($c, $codesIn('us_marsoc'), $c);
        }
        foreach (['COS', 'CAST', '1er RPIMa', '13e RDP', '4e RHFS', 'FORFUSCO', 'BFSA', 'CPA 10', 'CPA 20', 'CPA 30', 'EH 1/67', 'ET 3/61'] as $c) {
            self::assertContains($c, $codesIn('fr_cos'), $c);
        }
        self::assertCount(8, $codesIn('fr_forfusco'), 'FORFUSCO et ses sept commandos');
        self::assertContains('GIGN', $codesIn('fr_gign'));
        foreach (['DSK', 'KSK', 'LLBrig 1', 'FschJgRgt 26', 'FschJgRgt 31'] as $c) {
            self::assertContains($c, $codesIn('de_dsk'), $c);
        }
        self::assertContains('KSM', $codesIn('de_ksm'));
        self::assertContains('HSG 64', $codesIn('de_hsg64'));
        // Une même unité reprise par deux modèles garde son adresse courte et son nom.
        $ussocom = array_column($byCode[RealUnitKitDefinitions::PREFIX . 'us_ussocom']['units'], 'name', 'slug');
        $usasoc = array_column($byCode[RealUnitKitDefinitions::PREFIX . 'us_usasoc']['units'], 'name', 'slug');
        self::assertSame($ussocom['us-usasoc'], $usasoc['us-usasoc']);
    }

    public function testNothingNonPublicOrExcluded(): void
    {
        $corpus = (string) json_encode(RealUnitKitDefinitions::kits(), JSON_UNESCAPED_UNICODE);
        foreach (['Delta', 'DEVGRU', 'Development Group', 'CPIS', 'Special Activities', 'SREC', 'Combat Applications'] as $banned) {
            self::assertStringNotContainsString($banned, $corpus, $banned);
        }
    }

    public function testLabelsAreTranslatedAndKitTextsHaveEnglish(): void
    {
        GeneratedLabels::useRepository(null, dirname(__DIR__, 2));
        $fr = GeneratedLabels::catalog('fr')['unit'] ?? [];
        $en = GeneratedLabels::catalog('en')['unit'] ?? [];
        foreach (RealUnitKitDefinitions::slugs() as $slug) {
            self::assertSame(RealUnitKitDefinitions::officialName($slug), $fr[$slug] ?? null, 'Libellé FR : ' . $slug);
            self::assertSame(RealUnitKitDefinitions::englishLabel($slug), $en[$slug] ?? null, 'Libellé EN : ' . $slug);
        }
        self::assertSame('Special Forces Command (KSK)', $en['de-ksk']);
        self::assertSame('Commandement des forces spéciales', RealUnitKitDefinitions::translation('de-ksk', 'fr'));
        self::assertNull(RealUnitKitDefinitions::translation('fr-cos', 'fr'));
        self::assertNull(RealUnitKitDefinitions::translation('us-socpac', 'en'));
        foreach (RealUnitKitDefinitions::kits() as $kit) {
            self::assertTrue(UntranslatedFrenchScanner::isTranslated((string) $kit['title']), 'Titre sans anglais : ' . $kit['title']);
            self::assertTrue(UntranslatedFrenchScanner::isTranslated((string) $kit['summary']), 'Résumé sans anglais : ' . $kit['code']);
        }
        foreach (RealUnitKitDefinitions::echelons() as $echelon) {
            self::assertNotSame('', RealUnitKitDefinitions::echelonLabel($echelon, 'fr'));
            self::assertNotSame('', RealUnitKitDefinitions::echelonLabel($echelon, 'en'));
        }
    }

    public function testDisplayGroupsAndSearch(): void
    {
        $groups = OrganizationKitDefinitions::displayGroups(OrganizationKitDefinitions::officialKits(), 'fr');
        self::assertSame(['generic', 'US', 'FR', 'DE', 'GB', 'MY'], array_column($groups, 'key'));
        self::assertSame('États-Unis', $groups[1]['label']);
        self::assertSame(['joint', 'army', 'navy', 'air_force', 'marine_corps'], array_column($groups[1]['branches'], 'key'));
        self::assertSame(count(OrganizationKitDefinitions::officialKits()), array_sum(array_column($groups, 'count')));
        $en = OrganizationKitDefinitions::displayGroups(OrganizationKitDefinitions::officialKits(), 'en');
        self::assertSame('Generic templates', $en[0]['label']);
        self::assertSame('Germany', $en[3]['label']);
        $private = OrganizationKitDefinitions::displayGroups([['visibility' => 'private', 'definition' => ['units' => []]]], 'fr');
        self::assertSame('private', $private[0]['key']);

        $kit = array_column(RealUnitKitDefinitions::kits(), null, 'code')[RealUnitKitDefinitions::PREFIX . 'us_ussocom'];
        $search = OrganizationKitDefinitions::searchText($kit);
        foreach (['socafrica', 'commandement des opérations spéciales pour l’afrique', 'états-unis', 'united states', 'interarmées'] as $needle) {
            self::assertStringContainsString($needle, $search);
        }
    }
}
