<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseIdentityGeneratorService;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdWorkshopService;
use App\Services\Sse\SseMrzService;
use App\Support\SseIdCountryCatalog;
use App\Support\SseIdRealCountryCatalog as RC;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Pays réels stylisés (univers réaliste-simulation) : codes OACI de la MRZ (Allemagne « D<< »), numéros génériques,
 * import réservé à l'univers choisi, règle du code ISO réel, garde-fous de l'aperçu et de l'export (filigrane, mention,
 * bandeau), paquet du serveur de jeu, migration des anciens pays imposés, parité avec le mod SSE.
 */
final class SseIdRealCountryTest extends TestCase
{
    private const ICAO = [
        'GBR' => 'GBR', 'GRC' => 'GRC', 'USA' => 'USA', 'DEU' => 'D', 'AUT' => 'AUT', 'COL' => 'COL', 'FRA' => 'FRA',
        'BEL' => 'BEL', 'ESP' => 'ESP', 'POL' => 'POL', 'UKR' => 'UKR', 'RUS' => 'RUS', 'IRQ' => 'IRQ', 'SYR' => 'SYR', 'AFG' => 'AFG',
    ];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseIdentitySqlite::open();
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testCatalogCoversTheRequestedCountriesWithIcaoCodes(): void
    {
        self::assertSame(array_keys(self::ICAO), array_keys(RC::COUNTRIES));
        foreach (self::ICAO as $code => $mrz) {
            $c = RC::COUNTRIES[$code];
            self::assertSame($mrz, $c['mrz'], $code);
            self::assertTrue(SseIdCountryCatalog::isRealCode($code), $code . ' : code ISO réel');
            self::assertArrayHasKey($c['zone'], RC::ZONES);
            self::assertArrayHasKey($c['region'], RC::POOLS, $code . ' : réservoir de noms');
            foreach ($c['languages'] as $l) {
                self::assertArrayHasKey($l, SseIdCountryCatalog::LANGUAGES, $code . ' ' . $l);
            }
            self::assertArrayHasKey($c['region'], SseIdCountryCatalog::REGIONS);
            self::assertNotSame('', $c['name_native']);
            self::assertNotSame('', $c['authority']);
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $c['colors'][0]);
            self::assertStringNotContainsString('<path', RC::flagSvg($code), 'drapeau simple : bandes seulement');
        }
        self::assertSame(['passport', 'driving_license'], array_keys(RC::COUNTRIES['GBR']['validity']), 'pas de carte d’identité britannique');
        self::assertSame('americas', RC::zone('USA'));
        self::assertSame('europe', RC::zone('DEU'));
        self::assertSame('other', RC::zone('IRQ'));
        self::assertSame('Bundesrepublik Deutschland', RC::COUNTRIES['DEU']['authority']);
    }

    public function testMrzUsesTheIcaoCodeOfEachCountryAndGermanyIsD(): void
    {
        foreach (self::ICAO as $code => $mrz) {
            $number = SseIdentityGeneratorService::documentNumber(RC::numberFormat($code), 1234, 'x');
            $identity = ['last_name' => 'Müller', 'first_name' => 'Jürgen', 'nationality' => $code, 'birth_date' => '1985-03-02', 'sex' => 'M'];
            $pass = SseMrzService::forDocument(['doc_type' => 'passport', 'country_code' => $code, 'doc_number' => $number, 'expires_on' => '2034-03-01'], $identity, $mrz);
            $pad = str_pad($mrz, 3, '<');
            self::assertSame('P<' . $pad, substr($pass[0], 0, 5), $code);
            self::assertSame($pad, substr($pass[1], 10, 3), $code . ' : nationalité');
            self::assertTrue(SseMrzService::verify($pass)['valid'], $code);
            $card = SseMrzService::forDocument(['doc_type' => 'id_card', 'country_code' => $code, 'doc_number' => $number, 'expires_on' => '2034-03-01'], $identity, $mrz);
            self::assertSame('I<' . $pad, substr($card[0], 0, 5), $code);
            self::assertSame($pad, substr($card[1], 15, 3), $code);
            self::assertTrue(SseMrzService::verify($card)['valid'], $code);
        }
        $de = SseMrzService::forDocument(['doc_type' => 'passport', 'country_code' => 'DEU', 'doc_number' => 'DEK482913', 'expires_on' => '2034-03-01'],
            ['last_name' => 'Müller', 'first_name' => 'Jürgen', 'nationality' => 'DEU', 'birth_date' => '1985-03-02', 'sex' => 'M'], 'D');
        self::assertSame('P<D<<MULLER<<JURGEN', substr($de[0], 0, 19));
        // Nationalité allemande sur un papier d'un autre pays : code OACI aussi.
        $at = SseMrzService::forDocument(['doc_type' => 'passport', 'country_code' => 'AUT', 'doc_number' => 'ATK482913', 'expires_on' => '2034-03-01'],
            ['last_name' => 'Huber', 'first_name' => 'Anna', 'nationality' => 'DEU', 'birth_date' => '1985-03-02', 'sex' => 'F'], 'AUT');
        self::assertSame('D<<', substr($at[1], 10, 3));
    }

    public function testNumbersFollowTheGenericGameFormatNotARealOne(): void
    {
        $real = ['/^\d{9}$/', '/^[A-Z]\d{8}$/', '/^\d{2}[A-Z]{2}\d{5}$/', '/^[CFGHJKLMNPRTVWXYZ][CFGHJKLMNPRTVWXYZ0-9]{8}$/', '/^[A-Z]{2}\d{7}$/', '/^[A-Z]{3}\d{6}$/'];
        foreach (array_keys(RC::COUNTRIES) as $code) {
            $fmt = RC::numberFormat($code);
            self::assertSame(RC::COUNTRIES[$code]['iso2'] . '@######', $fmt);
            self::assertSame('', SseIdCountryCatalog::formatProblem($fmt), $code);
            for ($seed = 1; $seed < 40; $seed++) {
                $n = SseIdentityGeneratorService::documentNumber($fmt, $seed * 7919);
                self::assertSame(9, strlen($n), 'tient dans la MRZ');
                if ($code !== 'DEU') {
                    // L'Allemagne : préfixe « DE », lettres absentes des vrais numéros allemands.
                    self::assertMatchesRegularExpression('/^[A-Z]{3}\d{6}$/', $n);
                }
                foreach (array_slice($real, 0, 3) as $re) {
                    self::assertDoesNotMatchRegularExpression($re, $n, $code . ' ' . $n);
                }
            }
        }
        self::assertDoesNotMatchRegularExpression($real[3], SseIdentityGeneratorService::documentNumber(RC::numberFormat('DEU'), 5), 'D et E jamais dans un vrai numéro allemand');
    }

    public function testRealCodesNeedTheRealisticSimulationSetAndUniverse(): void
    {
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('DEU', 'contemporary'));
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('DEU', RC::UNIVERSE), 'univers réaliste-simulation : pas de création à la main');
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('KRV', RC::UNIVERSE), 'univers réservé au jeu stylisé');
        self::assertSame('', SseIdCountryCatalog::codeProblem('DEU', RC::UNIVERSE, true));
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('NLD', RC::UNIVERSE, true), 'pays hors du jeu stylisé');
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('XXA', RC::UNIVERSE, true), 'code OACI réservé');
        self::assertSame('', SseIdCountryCatalog::mrzProblem('D', RC::UNIVERSE, true));
        self::assertNotSame('', SseIdCountryCatalog::mrzProblem('D', 'contemporary'), '« D » réel refusé pour un pays fictif');
        self::assertSame('', SseIdCountryCatalog::codeProblem('ALT', 'contemporary'), 'pays fictifs inchangés');

        // Formulaire manuel : un code réel reste refusé, même dans l'univers réaliste-simulation.
        $input = ['code' => 'GRC', 'name' => 'Grèce', 'number_format' => 'GR@######', 'theme' => RC::UNIVERSE, 'fictional' => true, 'doc_types' => ['passport'], 'validity' => ['passport' => 5]];
        self::assertNotSame([], SseIdentityRegistryController::validateCountry($input, null, static fn (): bool => false)['errors']);
    }

    public function testImportRequiresTheUniverseAndTheCommitment(): void
    {
        $ok = SseIdentityRegistryController::validateRealImport(['universe' => RC::UNIVERSE, 'guardrails' => true, 'codes' => ['gbr', 'DEU', 'NLD', 'DEU']]);
        self::assertSame([], $ok['errors']);
        self::assertSame(['GBR', 'DEU'], $ok['codes']);
        self::assertNotSame([], SseIdentityRegistryController::validateRealImport(['universe' => 'contemporary', 'guardrails' => true, 'codes' => ['GBR']])['errors'], 'univers requis');
        self::assertNotSame([], SseIdentityRegistryController::validateRealImport(['universe' => RC::UNIVERSE, 'guardrails' => false, 'codes' => ['GBR']])['errors'], 'engagement requis');
        self::assertNotSame([], SseIdentityRegistryController::validateRealImport(['universe' => RC::UNIVERSE, 'guardrails' => true, 'codes' => ['NLD']])['errors']);
    }

    public function testRealCountriesAreProposedNeverImposedAndImportedOnRequest(): void
    {
        $repo = new SseIdCountryRepository();
        self::assertSame(['ALT', 'LVN', 'MLD', 'TNA'], array_column($repo->listForTenant(1), 'code'), 'seulement les pays fictifs par défaut');
        foreach (SseIdCountryRepository::DEFAULTS as $d) {
            self::assertFalse(RC::has($d['code']));
        }

        $out = $repo->importRealSet(1, ['GBR', 'DEU', 'USA', 'COL', 'AUT', 'GRC'], 7);
        self::assertSame(['GBR', 'DEU', 'USA', 'COL', 'AUT', 'GRC'], $out['added']);
        $de = $repo->findByCode(1, 'DEU');
        self::assertNotNull($de);
        self::assertTrue($de['real_sim']);
        self::assertFalse($de['fictional']);
        self::assertSame(RC::UNIVERSE, $de['theme']);
        self::assertSame('D', $de['mrz_code']);
        self::assertSame('DE@######', $de['number_format']);
        self::assertSame('europe', $de['zone']);
        self::assertSame(['de', 'en', 'fr'], $de['languages']);
        self::assertSame(['id_card' => 10, 'passport' => 10, 'driving_license' => 15, 'military_id' => 15], $de['validity']);
        self::assertSame(['passport', 'driving_license', 'military_id'], $repo->findByCode(1, 'GBR')['doc_types']);
        self::assertNull($repo->findByCode(2, 'DEU'), 'autre communauté : rien d’imposé');

        // Rejouer : déjà présent ; archivé : rétabli ; édition : univers, MRZ et format verrouillés, pas de drapeau.
        self::assertSame(['DEU'], $repo->importRealSet(1, ['DEU'])['kept']);
        $repo->setArchived((int) $de['id'], 1, true);
        self::assertSame(['DEU'], $repo->importRealSet(1, ['DEU'])['restored']);
        $res = SseIdentityRegistryController::validateCountry(
            ['name' => 'Allemagne', 'mrz_code' => 'DEU', 'number_format' => '#########', 'theme' => 'contemporary', 'fictional' => true,
                'doc_types' => ['passport'], 'validity' => ['passport' => 10]],
            $de,
            static fn (): bool => true
        );
        self::assertSame([], $res['errors']);
        self::assertSame('D', $res['data']['mrz_code']);
        self::assertSame('DE@######', $res['data']['number_format']);
        self::assertSame(RC::UNIVERSE, $res['data']['theme']);
        self::assertNull($res['data']['flag_path']);
        $repo->update((int) $de['id'], 1, $res['data']);
        $after = $repo->findByCode(1, 'DEU');
        self::assertTrue($after['real_sim']);
        self::assertFalse($after['fictional']);

        // Pays fictif qui aurait le même code (univers imaginaire) : conflit, rien d'écrasé.
        $repo->create(1, ['code' => 'POL', 'name' => 'Pôle Nord', 'mrz_code' => 'POL', 'number_format' => 'PN######', 'theme' => 'fantasy', 'doc_types' => ['id_card']]);
        self::assertSame(['POL'], $repo->importRealSet(1, ['POL'])['conflicts']);
        self::assertFalse($repo->findByCode(1, 'POL')['real_sim']);
    }

    public function testGeneratorDrawsNamesFromTheLanguagePools(): void
    {
        $gen = new SseIdentityGeneratorService();
        $repo = new SseIdCountryRepository();
        $repo->importRealSet(1, ['COL', 'RUS', 'DEU']);
        $col = $gen->generate((array) $repo->findByCode(1, 'COL'), 4242, 'F');
        self::assertContains($col['first_name'], RC::POOLS['COLOMBIA']['f']);
        self::assertContains($col['last_name'], RC::POOLS['COLOMBIA']['last']);
        self::assertContains($col['birth_place'], RC::POOLS['COLOMBIA']['places']);
        self::assertSame('COL', $col['nationality']);
        $ru = $gen->generate((array) $repo->findByCode(1, 'RUS'), 4242, 'F');
        self::assertStringEndsWith('a', $ru['last_name'], 'nom russe au féminin');
        self::assertContains(substr($ru['last_name'], 0, -1), RC::POOLS['RUSSIA_ID']['last']);
        $de = $gen->generate((array) $repo->findByCode(1, 'DEU'), 99, 'M');
        self::assertContains($de['first_name'], RC::POOLS['GERMANY']['m']);
    }

    public function testPreviewAndExportKeepTheSimulationMarks(): void
    {
        $repo = new SseIdCountryRepository();
        $repo->importRealSet(1, ['USA']);
        $us = (array) $repo->findByCode(1, 'USA');
        $doc = ['doc_type' => 'passport', 'country_code' => 'USA', 'doc_number' => 'USK482913', 'issued_on' => '2026-01-01', 'expires_on' => '2036-01-01', 'layout' => 'modern', 'print' => []];
        $identity = ['last_name' => 'Smith', 'first_name' => 'John', 'sex' => 'M', 'birth_date' => '1980-01-01', 'nationality' => 'USA'];
        $preview = SseIdWorkshopService::previewModel($doc, $identity, $us);
        self::assertTrue($preview['country']['real_sim']);
        self::assertSame('americas', $preview['country']['zone']);
        self::assertStringStartsWith('data:image/svg+xml;base64,', (string) $preview['country']['flag_url']);
        self::assertSame('P<USASMITH<<JOHN', substr($preview['mrz'][0], 0, 16));

        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        ob_start();
        require dirname(__DIR__, 2) . '/views/atak/sse/papers/_doc_preview.php';
        $html = (string) ob_get_clean();
        $faces = substr_count($html, 'class="idw-face ');
        self::assertSame(5, $faces);
        self::assertSame($faces, substr_count($html, 'class="idw-watermark"'), 'filigrane sur chaque face');
        self::assertSame($faces, substr_count($html, RC::FACE_MENTION), 'mention sur chaque face');
        self::assertStringContainsString('idw-zone--americas', $html);

        $css = self::read('public/assets/css/sse-id-workshop.css');
        self::assertMatchesRegularExpression('/\.idw-watermark \{[^}]*inset: -40%;[^}]*rotate\(-24deg\)/s', $css, 'diagonale sur toute la surface');
        $js = self::read('public/assets/js/sse-id-workshop.js');
        self::assertStringContainsString("var BAND = 'DOCUMENT DE JEU – SANS VALEUR", $js);
        self::assertLessThan(strpos($js, "canvas.toDataURL('image/png')"), strpos($js, 'stampGameMarks(canvas);'), 'marques incrustées avant l’export');
        self::assertStringContainsString("ctx.fillText('SIMULATION", $js);
    }

    public function testImportScreenAndFormsCarryTheGuardrails(): void
    {
        $view = self::read('views/atak/sse/papers/country_real_import.php');
        self::assertStringContainsString('name="universe" value="<?= $h(RC::UNIVERSE) ?>" required', $view);
        self::assertStringContainsString('name="guardrails" value="1" required', $view);
        self::assertStringContainsString('name="codes[]"', $view);
        self::assertStringContainsString('\App\Core\Csrf::field()', $view);
        self::assertStringContainsString('idr-spec__wm', $view);
        self::assertStringContainsString('RC::FACE_MENTION', $view);
        self::assertStringContainsString("url('atak/sse/papiers/pays/reels')", self::read('views/atak/sse/papers/countries.php'));
        self::assertStringContainsString('Ajouter des pays réels (simulation)', self::read('views/atak/sse/papers/countries.php'));
        $form = self::read('views/atak/sse/papers/country_form.php');
        self::assertStringContainsString('<?php if ($real): ?>', $form);
        self::assertStringContainsString('Aucune image téléversée : pas d’armoiries', $form);
        $ctrl = self::read('app/Controllers/Web/SseIdentityRegistryController.php');
        self::assertStringContainsString("\$flag = !empty(\$existing['real_sim']) ? null : \$this->storeFlag(", $ctrl, 'pas de drapeau téléversé pour un pays réel');

        $catalog = SseIdentityRegistryController::realCatalogPreview();
        self::assertCount(count(RC::COUNTRIES), $catalog);
        foreach ($catalog as $p) {
            self::assertTrue(SseMrzService::verify($p['mrz'])['valid'], $p['code']);
            self::assertSame(str_pad((string) $p['mrz_code'], 3, '<'), substr($p['mrz'][0], 2, 3), $p['code']);
        }
    }

    public function testPackCarriesTheRealCountriesForTheGameServer(): void
    {
        (new SseIdCountryRepository())->importRealSet(1, ['DEU', 'GBR']);
        $persons = $this->createStub(SsePersonRepository::class);
        $watch = $this->createStub(SseWatchlistRepository::class);
        $pack = (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons))->build(1, 1);
        $by = array_column($pack['countries'], null, 'code');
        self::assertSame('D', $by['DEU']['mrz_code']);
        self::assertTrue($by['DEU']['real_sim']);
        self::assertFalse($by['DEU']['fictional']);
        self::assertSame('europe', $by['DEU']['zone']);
        self::assertSame('Deutschland', $by['DEU']['name_native']);
        self::assertSame(['passport' => 10, 'driving_license' => 10, 'military_id' => 10], $by['GBR']['validity']);
        self::assertSame(['#1C2541', '#C8102E'], $by['GBR']['colors']);
        self::assertSame(['id_card', 'passport', 'driving_license', 'military_id'], $by['DEU']['doc_types']);
        self::assertFalse($by['ALT']['real_sim']);
        self::assertSame('', $by['ALT']['zone']);
    }

    public function testMigrationConvertsCountriesImposedByTheOldDefaults(): void
    {
        $pdo = SseIdentitySqlite::open();
        $now = gmdate('Y-m-d H:i:s');
        $pdo->exec("INSERT INTO sse_id_countries (tenant_id, code, name, name_en, mrz_code, number_format, authority, region, validity_years, fictional, active, created_at)
            VALUES (2, 'DEU', 'Allemagne', 'Germany', 'DEU', 'DE########', 'Bureau des documents de simulation', 'EUROPE', 10, 1, 1, '$now'),
                   (2, 'USA', 'États-Unis', 'United States', 'USA', 'US########', 'Bureau des documents de simulation', 'NORTH_AMERICA', 10, 1, 1, '$now')");
        $pdo->exec("INSERT INTO sse_id_documents (tenant_id, identity_id, doc_type, country_code, doc_number, workflow, created_at) VALUES (2, 1, 'passport', 'USA', 'US12345678', 'issued', '$now')");
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_real_countries_migration.php';
        $migrate($pdo);
        $migrate($pdo);
        $rows = $pdo->query("SELECT code, real_sim, fictional, theme, mrz_code, number_format, authority, active FROM sse_id_countries WHERE tenant_id = 2 AND code IN ('DEU', 'USA') ORDER BY code")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame(['DEU', 'USA'], array_column($rows, 'code'));
        self::assertSame('D', $rows[0]['mrz_code']);
        self::assertSame('DE@######', $rows[0]['number_format']);
        self::assertSame('Bundesrepublik Deutschland', $rows[0]['authority']);
        self::assertSame(RC::UNIVERSE, $rows[0]['theme']);
        self::assertSame(1, (int) $rows[0]['real_sim']);
        self::assertSame(0, (int) $rows[0]['fictional']);
        self::assertSame('US########', $rows[1]['number_format'], 'papiers déjà émis : format gardé');
        self::assertSame(1, (int) $rows[1]['active'], 'pas d’archivage d’office');
    }

    public function testMigrationStepIsRegistered(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'file' => '3910_atak_sse_identity_real_countries.php'", $registry);
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/3910_atak_sse_identity_real_countries.php');
        self::assertStringContainsString('atak_sse_identity_real_countries_migration.php', self::read('app/Repositories/SseIdentityRepository.php'));
    }

    public function testModCarriesTheSameCountriesAndGuardrails(): void
    {
        $sqf = self::read('mod/@COMSPEC_SSE/addons/identity/functions/fn_idRealCountries.sqf');
        foreach (RC::COUNTRIES as $code => $c) {
            self::assertStringContainsString('["' . $code . '", "' . $c['mrz'] . '", ', $sqf, $code);
            self::assertStringContainsString('"' . RC::numberFormat($code) . '"', $sqf);
            foreach (['m', 'f', 'last', 'places'] as $k) {
                self::assertStringContainsString('["' . implode('", "', RC::POOLS[$c['region']][$k]) . '"]', $sqf, $code . ' ' . $k);
            }
        }
        $countries = self::read('mod/@COMSPEC_SSE/addons/identity/functions/fn_idCountries.sqf');
        // Mod : pays réels du registre CfgComspecCountries proposés d'office (France, Allemagne, États-Unis… d'abord) ; les pays
        // stylisés du site restent le repli d'un pays importé par le paquet Athena et absent du registre.
        self::assertStringContainsString('[] call comspec_sse_fnc_idRealCountries', $countries);
        self::assertStringContainsString('configFile >> "CfgComspecCountries"', $countries);
        self::assertStringContainsString('class idRealCountries {};', self::read('mod/@COMSPEC_SSE/addons/identity/config.cpp'));
        $mrz = self::read('mod/@COMSPEC_SSE/addons/identity/functions/fn_idMrz.sqf');
        self::assertStringContainsString('getOrDefault ["mrzIssuer", ""]', $mrz);
        $viewer = self::read('mod/@COMSPEC_SSE/addons/identity/functions/fn_idViewerOnLoad.sqf');
        self::assertStringContainsString('"eu", "am", "ot"', $viewer);
        foreach (['eu', 'am', 'ot'] as $z) {
            foreach (["tpl_card_{$z}_recto_co", "tpl_card_{$z}_verso_co", "tpl_passport_{$z}_co", "tpl_license_{$z}_recto_co"] as $f) {
                self::assertFileExists(dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/identity/data/' . $f . '.paa');
            }
        }
    }
}
