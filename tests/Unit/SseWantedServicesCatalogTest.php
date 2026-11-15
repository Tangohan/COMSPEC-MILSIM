<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseUsClassification;
use App\Support\SseWantedCatalog;
use App\Support\SseWantedRealNames;
use App\Support\SseWantedServices;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue des services demandeurs (config/sse_wanted_services.php) : tout est FICTIF. Test statique de contenu : aucun nom
 * d'organisation réelle ni sigle réel (liste de refus de SseWantedRealNames), aucune coordonnée réelle, aucun vocabulaire de police
 * française, des codes stables et uniques, des textes complets en français et en anglais.
 */
final class SseWantedServicesCatalogTest extends TestCase
{
    /** Noms et sigles que la liste de refus DOIT connaître (le garde-fou ne se vide jamais par accident). */
    private const MUST_BE_REFUSED = [
        'CIA', 'FBI', 'NSA', 'DIA', 'JSOC', 'DEA', 'ATF', 'Interpol', 'Europol', 'NCIS', 'SOCOM', 'CENTCOM', 'AFRICOM', 'DEVGRU', 'Delta Force',
        'Navy SEALs', 'Department of Defense', 'Department of State', 'Secret Service', 'US Marshals', 'Homeland Security', 'DHS', 'MI6', 'GCHQ',
        'Mossad', 'NATO', 'Pentagon', 'Coast Guard', 'Scotland Yard', 'DGSE', 'Gendarmerie nationale', 'Police nationale',
    ];

    /** Vocabulaire de la police française : jamais dans le catalogue (forces spéciales américaines). */
    private const FRENCH_POLICE = [
        'commissariat', 'gendarmerie', 'gendarme', 'brigade', 'préfecture', 'prefecture', 'procureur', 'garde à vue', 'police judiciaire',
        'officier de police', 'commissaire', 'police nationale', 'police municipale', 'tribunal', 'parquet', 'juge d’instruction', 'mandat d’arrêt',
        'plainte', 'cartouche', 'casier judiciaire',
    ];

    public function testTheCatalogueShipsAboutFortyServicesGroupedByFamily(): void
    {
        $all = SseWantedServices::catalogue();
        self::assertGreaterThanOrEqual(40, count($all));
        self::assertLessThanOrEqual(60, count($all));
        $families = SseWantedServices::families();
        self::assertGreaterThanOrEqual(8, count($families));
        $used = [];
        foreach ($all as $s) {
            self::assertArrayHasKey($s['family'], $families, $s['code']);
            $used[$s['family']] = ($used[$s['family']] ?? 0) + 1;
        }
        self::assertSame(array_keys($families), array_values(array_intersect(array_keys($families), array_keys($used))), 'Chaque famille porte au moins un service');
    }

    public function testCodesAreStableUniqueAndShapedLikeSvc(): void
    {
        $codes = array_keys(SseWantedServices::catalogue());
        self::assertSame(count($codes), count(array_unique($codes)));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^SVC-[A-Z0-9]{2,12}(?:-[A-Z0-9]{1,10})?$/', $code);
        }
        self::assertContains('SVC-JIFC', $codes, 'Le code du premier service ne change jamais');
        self::assertContains('SVC-SOTF-ID', $codes);
        self::assertContains('SVC-TTC-EA', $codes);
        // Les codes de la communauté ne peuvent jamais heurter un code livré.
        self::assertSame('CSV-', substr(SseWantedServices::freshCode('JIFC', $codes), 0, 4));
    }

    public function testEveryServiceIsCompleteInFrenchAndEnglish(): void
    {
        $palette = SseWantedServices::palette();
        foreach (SseWantedServices::catalogue() as $code => $s) {
            foreach (['fr', 'en', 'auth_fr', 'auth_en', 'ac', 'radio', 'tel', 'mail', 'ins_fr', 'ins_en'] as $field) {
                self::assertNotSame('', trim((string) $s[$field]), $code . ' : ' . $field);
            }
            self::assertNotSame($s['fr'], $s['en'], $code . ' : le nom anglais est traduit');
            self::assertNotSame([], $s['roles'], $code);
            self::assertNotSame([], $s['types'], $code);
            self::assertNotSame([], $s['threats'], $code);
            self::assertArrayHasKey($s['color'], $palette, $code);
            self::assertContains($s['emblem'], SseWantedServices::emblems(), $code);
            self::assertContains($s['max'], SseUsClassification::codes(), $code);
            self::assertLessThanOrEqual(SseWantedServices::classRank($s['max']), SseWantedServices::classRank($s['class']), $code . ' : la valeur par défaut ne dépasse pas le maximum');
            self::assertArrayHasKey($s['thr'], SseWantedCatalog::THREATS, $code);
            foreach ($s['types'] as $type) {
                self::assertArrayHasKey($type, SseWantedCatalog::TYPES, $code . ' : type d’avis connu');
            }
            foreach ($s['roles'] as $role) {
                self::assertArrayHasKey($role, SseWantedServices::ROLES, $code);
            }
            self::assertGreaterThan(0, $s['exp'], $code);
            self::assertNotSame([], $s['lang'], $code);
        }
    }

    public function testNoServiceNamesARealOrganisationOrAcronym(): void
    {
        foreach (SseWantedServices::catalogue() as $code => $s) {
            $text = implode(' | ', [$s['fr'], $s['en'], $s['ac'], $s['auth_fr'], $s['auth_en'], $s['radio'], $s['mail'], $s['ins_fr'], $s['ins_en'], $code]);
            self::assertNull(SseWantedRealNames::find($text), $code . ' : ' . (string) SseWantedRealNames::find($text));
            foreach (SseWantedRealNames::forbidden() as $forbidden) {
                self::assertDoesNotMatchRegularExpression('/(?<![a-z0-9])' . preg_quote($forbidden, '/') . '(?![a-z0-9])/i', $text, $code . ' ne cite pas « ' . $forbidden . ' »');
            }
        }
        foreach (SseWantedServices::families() as $key => $f) {
            self::assertNull(SseWantedRealNames::find($f['fr'] . ' ' . $f['en']), $key);
        }
    }

    public function testTheRefusalListKnowsTheRealNamesAndAcronymsThatMustNeverPass(): void
    {
        foreach (self::MUST_BE_REFUSED as $name) {
            self::assertNotNull(SseWantedRealNames::find($name), $name . ' doit être refusé');
            self::assertNotNull(SseWantedRealNames::find('Bureau lié à la ' . $name . ' (fictif)'), $name . ' refusé au milieu d’une phrase');
            self::assertNotNull(SseWantedRealNames::find(strtolower($name)), $name . ' refusé sans tenir compte de la casse');
        }
        self::assertNotNull(SseWantedRealNames::find('Task Force Delta Force'), 'Un nom composé est refusé');
        // Des mots courants ne sont pas refusés par erreur.
        self::assertNull(SseWantedRealNames::find('Un raid nocturne sur le port de Kestrel'));
        self::assertNull(SseWantedRealNames::find('Joint Intelligence Fusion Cell'));
    }

    public function testNoFrenchPoliceVocabularyAndNoRealContactDetails(): void
    {
        $file = dirname(__DIR__, 2) . '/config/sse_wanted_services.php';
        $source = mb_strtolower((string) file_get_contents($file));
        foreach (self::FRENCH_POLICE as $word) {
            self::assertStringNotContainsString(mb_strtolower($word), $source, 'Vocabulaire de police française : « ' . $word . ' »');
        }
        foreach (SseWantedServices::catalogue() as $code => $s) {
            self::assertMatchesRegularExpression('/^\+1 555 01\d{2}$/', $s['tel'], $code . ' : téléphone fictif (ligne 555 01xx)');
            self::assertMatchesRegularExpression('/^[a-z0-9._+-]+@[a-z0-9.-]+\.invalid$/', $s['mail'], $code . ' : courriel fictif (.invalid)');
            self::assertStringNotContainsString('http', $s['radio'] . $s['ins_fr'] . $s['ins_en'], $code);
        }
        // Ni sceau ni logo : seulement une couleur de la palette et une forme géométrique générique.
        foreach (SseWantedServices::emblems() as $shape) {
            self::assertContains($shape, ['hex', 'circle', 'diamond', 'bars', 'chevron', 'cross', 'ring']);
        }
        foreach (SseWantedServices::palette() as $hex) {
            self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $hex);
        }
    }

    public function testRolesFamiliesAndDefaultsFitSpecialForcesVocabulary(): void
    {
        $names = implode(' ', array_map(static fn (array $s): string => $s['fr'] . ' ' . $s['en'], SseWantedServices::catalogue()));
        foreach (['Joint Intelligence Fusion Cell', 'Special Operations Task Force', 'Theater Targeting Cell', 'Coalition Liaison Office', 'Force Protection Center'] as $expected) {
            self::assertStringContainsString($expected, $names);
        }
        $families = SseWantedServices::families();
        foreach (['coord', 'command', 'ct', 'cint', 'fp', 'mil', 'theatre', 'allies', 'civil'] as $family) {
            self::assertArrayHasKey($family, $families);
        }
    }
}
