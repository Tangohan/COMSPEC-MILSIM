<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Services\Sse\SseMrzService;
use App\Support\SseDocumentTypes;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdRealCountryCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Types de papiers : le site est le miroir du registre du mod SSE (CfgComspecDocuments.hpp, extensions,
 * countries.json). Ce test échoue dès que l'un s'écarte de l'autre : relancer `php tools/sse/sync_document_types.php`
 * et, pour un nouveau type intégré, l'ajouter à SseIdentityCatalog::DOC_TYPES.
 */
final class SseDocumentTypesMirrorTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testGeneratedFileMatchesTheMod(): void
    {
        $fromMod = SseDocumentTypes::fromMod(self::root());
        self::assertSame($fromMod, require self::root() . '/config/sse_document_types.php', 'config/sse_document_types.php périmé : php tools/sse/sync_document_types.php');
        self::assertSame($fromMod, SseDocumentTypes::data());

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::root() . '/tools/sse/sync_document_types.php') . ' --check', $out, $code);
        self::assertSame(0, $code, implode("\n", $out));
    }

    public function testBuiltInTypesAreTheCatalogOfTheSite(): void
    {
        $builtIn = array_map(static fn (array $t): string => (string) $t['name'], SseDocumentTypes::builtIn());
        $catalog = SseIdentityCatalog::DOC_TYPES;
        ksort($builtIn);
        ksort($catalog);
        self::assertSame($builtIn, $catalog, 'SseIdentityCatalog::DOC_TYPES doit lister les papiers intégrés du mod, mêmes clés et mêmes libellés');
        self::assertSame('Titre de séjour', $catalog['residence_permit']);
        self::assertSame('Certificat d’immatriculation', $catalog['vehicle_registration']);

        // Les types d'une extension sont au miroir, mais ne sont pas proposés par le site.
        $extensions = array_diff_key(SseDocumentTypes::all(), SseDocumentTypes::builtIn());
        self::assertSame(['press_card', 'laissez_passer'], array_keys($extensions));
        self::assertSame([], array_intersect_key($extensions, $catalog));
        foreach (SseDocumentTypes::grouped() as $group) {
            self::assertSame([], array_intersect_key($extensions, $group['types']));
        }
    }

    public function testTypeLayoutComesFromTheModClasses(): void
    {
        $t = SseDocumentTypes::all();
        // Héritage « class residence_permit: id_card » : catégorie, faces, bande et photo du parent, puce propre.
        self::assertSame(['identity', 2, 'td1', 'verso', true, true], [$t['residence_permit']['category'], $t['residence_permit']['sides'], $t['residence_permit']['mrz'], $t['residence_permit']['mrz_side'], $t['residence_permit']['chip'], $t['residence_permit']['photo']]);
        self::assertSame(['permit', 1, '', false], [$t['vehicle_registration']['category'], $t['vehicle_registration']['sides'], $t['vehicle_registration']['mrz'], $t['vehicle_registration']['photo']]);
        self::assertSame(['travel', 1, 'td3', 'recto', true], [$t['passport']['category'], $t['passport']['sides'], $t['passport']['mrz'], $t['passport']['mrz_side'], $t['passport']['photo']]);
        self::assertFalse($t['laissez_passer']['photo']);
        self::assertSame(1, SseDocumentTypes::sides('passport'));
        self::assertSame(2, SseDocumentTypes::sides('id_card'));
        self::assertSame(2, SseDocumentTypes::sides('inconnu'));

        // Liste de choix : catégories dans l'ordre du sélecteur du mod.
        $groups = SseDocumentTypes::grouped();
        self::assertSame(['identity', 'travel', 'permit'], array_keys($groups));
        self::assertSame(['id_card', 'residence_permit', 'military_id'], array_keys($groups['identity']['types']));
        self::assertSame(['Voyage' => ['passport' => 'Passeport']], array_column(SseDocumentTypes::grouped(['passport', 'press_card', 'inconnu']), 'types', 'label'));

        $parsed = SseDocumentTypes::parseConfig('class CfgComspecDocuments { /* c */ class a { displayName = "L\'A"; sides = 2; mrz = "td1"; class recto { class photo { x = 0; }; class fields { class n { key = "n"; }; }; }; class verso {}; };'
            . ' class b: a { displayName = "B"; countries[] = {"fra", "DEU"}; class recto { class fields {}; }; }; };');
        self::assertSame(['L’A', 2, true, []], [$parsed['a']['name'], $parsed['a']['sides'], $parsed['a']['photo'], $parsed['a']['countries']]);
        self::assertSame(['B', 2, false, ['FRA', 'DEU'], 'td1'], [$parsed['b']['name'], $parsed['b']['sides'], $parsed['b']['photo'], $parsed['b']['countries'], $parsed['b']['mrz']]);
    }

    public function testCountriesServeTheTypesOfTheMod(): void
    {
        self::assertSame(['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'], SseDocumentTypes::servedBy('fra'));
        self::assertNotContains('id_card', SseDocumentTypes::servedBy('GBR'), 'le Royaume-Uni ne délivre pas de carte d’identité');
        self::assertSame(['id_card', 'passport', 'driving_license', 'military_id'], SseDocumentTypes::servedBy('ALT'));
        self::assertSame([], SseDocumentTypes::servedBy('ZZZ'));

        // Pays sans liste enregistrée : registre du mod s'il y est (les pays fictifs restent à 4 types), sinon tous.
        foreach (['ALT', 'TNA', 'LVN', 'MLD'] as $code) {
            self::assertSame(['id_card', 'passport', 'driving_license', 'military_id'], SseIdCountryRepository::hydrate(['code' => $code, 'doc_types' => null])['doc_types'], $code);
        }
        self::assertSame(array_keys(SseIdentityCatalog::DOC_TYPES), SseIdCountryRepository::hydrate(['code' => 'ZZZ', 'doc_types' => ''])['doc_types']);
        self::assertSame(['passport'], SseIdCountryRepository::hydrate(['code' => 'FRA', 'doc_types' => 'passport,inconnu'])['doc_types'], 'une liste enregistrée décide');

        // Import d'un pays réel : les papiers que le mod lui délivre.
        foreach (array_keys(SseIdRealCountryCatalog::COUNTRIES) as $code) {
            self::assertSame(SseDocumentTypes::servedBy($code), SseIdRealCountryCatalog::countryData($code)['doc_types'], $code);
        }
        self::assertContains('residence_permit', SseIdRealCountryCatalog::countryData('FRA')['doc_types']);

        // Ordre du mod : pays complets d'abord, puis le reste du registre, puis les pays hors registre (par nom).
        $sorted = SseDocumentTypes::sortCountries([['code' => 'ZZB', 'name' => 'B'], ['code' => 'ALT', 'name' => 'Altis'], ['code' => 'ZZA', 'name' => 'A'], ['code' => 'LUX', 'name' => 'Luxembourg'], ['code' => 'USA', 'name' => 'États-Unis'], ['code' => 'FRA', 'name' => 'France']]);
        self::assertSame(['FRA', 'USA', 'LUX', 'ALT', 'ZZA', 'ZZB'], array_column($sorted, 'code'));
    }

    public function testMachineReadableZoneFollowsTheType(): void
    {
        $identity = ['last_name' => 'Gyllen', 'first_name' => 'Jake', 'birth_date' => '1990-04-02', 'sex' => 'M', 'nationality' => 'FRA'];
        $doc = ['country_code' => 'FRA', 'doc_number' => 'AB1234567', 'expires_on' => '2036-01-01'];
        $permit = SseMrzService::forDocument($doc + ['doc_type' => 'residence_permit'], $identity, 'FRA');
        self::assertCount(3, $permit);
        self::assertStringStartsWith('IRFRA', $permit[0]);
        self::assertTrue(SseMrzService::verify($permit)['valid']);
        self::assertSame([], SseMrzService::forDocument($doc + ['doc_type' => 'vehicle_registration'], $identity, 'FRA'));
        self::assertStringStartsWith('I<FRA', SseMrzService::forDocument($doc + ['doc_type' => 'id_card'], $identity, 'FRA')[0]);
        self::assertCount(2, SseMrzService::forDocument($doc + ['doc_type' => 'passport'], $identity, 'FRA'));
    }

    public function testStep4410AddsTheNewTypesToImportedRealCountriesOnce(): void
    {
        $registry = (string) file_get_contents(self::root() . '/database/steps/registry.php');
        self::assertStringContainsString("'id' => 'sse_document_types'", $registry);
        self::assertStringContainsString("'file' => '4410_sse_document_types.php', 'rerun' => true]", $registry);
        self::assertLessThan(strpos($registry, '4410_sse_document_types.php'), strpos($registry, '4400_operator_hub_announcement.php'));
        self::assertStringContainsString("/bootstrap/sse_document_types_migration.php'", (string) file_get_contents(self::root() . '/database/steps/4410_sse_document_types.php'));

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        try {
            $repo = new SseIdCountryRepository();
            $repo->importRealSet(1, ['FRA', 'DEU', 'GBR']);
            $repo->listForTenant(1);
            // État d'avant : listes enregistrées à l'import, sans les deux nouveaux types (DEU réduit à la main).
            $pdo->exec("UPDATE sse_id_countries SET doc_types = 'id_card,passport,driving_license,military_id' WHERE code = 'FRA'");
            $pdo->exec("UPDATE sse_id_countries SET doc_types = 'passport' WHERE code = 'DEU'");
            $pdo->exec("UPDATE sse_id_countries SET doc_types = 'passport,driving_license,military_id,vehicle_registration' WHERE code = 'GBR'");
            $types = static fn (string $code): ?string => ($v = $pdo->query("SELECT doc_types FROM sse_id_countries WHERE tenant_id = 1 AND code = '{$code}'")->fetchColumn()) === false ? null : $v;
            $altBefore = $types('ALT');

            $migrate = require self::root() . '/bootstrap/sse_document_types_migration.php';
            self::assertSame(3, $migrate($pdo));
            self::assertSame('id_card,passport,driving_license,military_id,residence_permit,vehicle_registration', $types('FRA'));
            self::assertSame('passport,residence_permit,vehicle_registration', $types('DEU'), 'un type retiré à la main reste retiré');
            self::assertSame('passport,driving_license,military_id,vehicle_registration,residence_permit', $types('GBR'));
            self::assertSame($altBefore, $types('ALT'), 'pays fictif intact');
            self::assertSame(['id_card' => 10, 'passport' => 10, 'driving_license' => 15, 'military_id' => 15, 'residence_permit' => 10, 'vehicle_registration' => 15], $repo->findByCode(1, 'FRA')['validity'], 'titre de séjour : durée de la carte');
            self::assertSame(0, $migrate($pdo), 'rejouée : rien à faire');
            self::assertContains('residence_permit', $repo->findByCode(1, 'FRA')['doc_types']);
            self::assertCount(4, $repo->findByCode(1, 'ALT')['doc_types']);
        } finally {
            SseIdentitySqlite::close();
        }
    }
}
