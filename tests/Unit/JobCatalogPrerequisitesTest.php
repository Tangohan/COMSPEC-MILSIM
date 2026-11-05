<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\JobCatalogController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Services\Personnel\JobPrerequisiteService;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Support\JobCatalog\JobCatalog;
use App\Support\Migrations\SchemaInspector;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Catalogue d’emplois de référence (forces spéciales américaines, françaises, allemandes, chinoises et russes) et prérequis d’un emploi : doctrines, heures de
 * jeu / de vol mesurées, qualifications, compétences, contrôle à l’attribution et dérogation motivée.
 */
final class JobCatalogPrerequisitesTest extends TestCase
{
    private PDO $pdo;
    private mixed $previousPdo;
    private array $globals;
    private array $permissions;

    protected function setUp(): void
    {
        JobCatalog::useDirectory(null);
        $this->globals = [$_GET, $_POST, $_SERVER, $_SESSION ?? []];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
        $this->pdo->exec("CREATE TABLE tenants(id INTEGER PRIMARY KEY, settings TEXT); INSERT INTO tenants VALUES(14,'{}'),(15,'{}');
            CREATE TABLE users(id INTEGER PRIMARY KEY, display_name TEXT); INSERT INTO users VALUES(5,'Viper'),(7,'Chef');
            CREATE TABLE audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,action TEXT,entity_type TEXT,entity_id INTEGER,old_value TEXT,new_value TEXT,created_at TEXT);
            CREATE TABLE personnel_job_roles(id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT);
            INSERT INTO personnel_job_roles VALUES(1,14,'Pilote MH-60'),(2,14,'Opérateur'),(3,15,'Autre communauté');
            CREATE TABLE personnel_job_role_requirements(id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, job_role_id INTEGER, criteria_json TEXT, updated_by INTEGER, updated_at TEXT, UNIQUE(tenant_id, job_role_id));
            CREATE TABLE personnel_job_role_waivers(id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, job_role_id INTEGER, user_id INTEGER, reason TEXT, missing_json TEXT, granted_by INTEGER, granted_at TEXT, revoked_by INTEGER, revoked_at TEXT, revoke_reason TEXT);
            CREATE TABLE user_arma_playtime(tenant_id INTEGER, user_id INTEGER, total_seconds INTEGER, server_seconds INTEGER, zeus_seconds INTEGER);
            INSERT INTO user_arma_playtime VALUES(14,5,180000,36000,0);
            CREATE TABLE atak_pilot_time(tenant_id INTEGER, user_id INTEGER, vehicle_class TEXT, vehicle_label TEXT, vehicle_kind TEXT, seconds INTEGER);
            INSERT INTO atak_pilot_time VALUES(14,5,'RHS_UH60M','UH-60M','heli',54000),(14,5,'RHS_C130J','C-130J','plane',7200),(15,5,'RHS_UH60M','UH-60M','heli',999999);
            CREATE TABLE document_versions(id INTEGER PRIMARY KEY, document_id INTEGER, is_current INTEGER);
            INSERT INTO document_versions VALUES(31,30,0),(32,30,1),(41,40,1);
            CREATE TABLE document_views(tenant_id INTEGER, document_id INTEGER, version_id INTEGER, user_id INTEGER);
            INSERT INTO document_views VALUES(14,30,32,5),(14,40,41,5);
            CREATE TABLE document_acknowledgments(tenant_id INTEGER, user_id INTEGER, document_id INTEGER, version_id INTEGER, signed_at TEXT);
            INSERT INTO document_acknowledgments VALUES(14,5,40,41,'2026-10-01');
            CREATE TABLE personnel_qualifications(id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, definition_id INTEGER, status TEXT, expires_at TEXT);
            INSERT INTO personnel_qualifications VALUES(1,14,5,8,'valid',NULL),(2,14,5,9,'expired',NULL),(3,14,5,10,'valid','2001-01-01');
            CREATE TABLE competency_requirement_links(tenant_id INTEGER, requirement_id INTEGER, target_type TEXT, target_ref TEXT);
            INSERT INTO competency_requirement_links VALUES(14,70,'qualification','8'),(14,71,'qualification','9');");
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        [$_GET, $_POST, $_SERVER, $_SESSION] = $this->globals;
        Gate::getInstance()->setPermissions($this->permissions);
        JobCatalog::useDirectory(null);
    }

    public function testShippedCatalogIsValidUniqueSourcedAndCopyable(): void
    {
        $packs = JobCatalog::packs();
        self::assertSame(JobCatalog::PACKS, array_keys($packs), 'Chaque pack inscrit est livré et lisible.');
        $all = JobCatalog::rawEntries();
        $ids = array_column($all, 'id');
        self::assertSame(count($ids), count(array_unique($ids)), 'Identifiant en double entre les pays.');
        foreach ($packs as $name => $pack) {
            self::assertArrayHasKey((string) $pack['country'], JobCatalog::COUNTRIES, $name . ' : pays sans nom affiché.');
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $pack['compiled_at']);
            self::assertNotSame('', trim((string) ($pack['sources_note']['fr'] ?? '')));
            self::assertNotSame('', trim((string) ($pack['sources_note']['en'] ?? '')));
            $entries = array_values(array_filter($all, static fn (array $e): bool => $e['pack'] === $name));
            // Bornes par pack : un pays couvre toutes ses armées (80 à 150) ; l’OTAN ne liste que les fonctions
            // interalliées standardisées et décrites publiquement, moins nombreuses (60 à 120) ; Chine : moins de
            // sources ouvertes détaillées sur les billets (voir sources_note du pack), seuil plus bas (60 à 150).
            [$min, $max] = match ($name) {
                'nato' => [60, 120],
                'cn_sof' => [60, 150],
                default => [80, 150],
            };
            self::assertGreaterThanOrEqual($min, count($entries), $name);
            self::assertLessThanOrEqual($max, count($entries), $name);
            $prefix = strtolower((string) $pack['country']) . '-';
            $problems = [];
            foreach ($entries as $entry) {
                if (!str_starts_with((string) $entry['id'], $prefix)) {
                    $problems[] = $entry['id'] . ' : préfixe du pays attendu ' . $prefix;
                }
                foreach (JobCatalog::problems($entry) as $p) {
                    $problems[] = $entry['id'] . ' : ' . $p;
                }
                if (($entry['abbreviation'] ?? '') !== '') {
                    // L’abrégé proposé passe tel quel la règle des fonctions (2 à 8 lettres ou chiffres).
                    self::assertSame($entry['abbreviation'], JobRoleEnrichmentService::normalizeAbbreviation($entry['abbreviation']));
                }
            }
            self::assertSame([], $problems, $name);
            self::assertContains('aviation', array_column($entries, 'type'), $name);
            self::assertContains('command', array_column($entries, 'type'), $name);
        }
        // Toutes les branches attendues sont couvertes.
        $us = array_count_values(array_column(array_filter($all, static fn (array $e): bool => $e['country'] === 'US'), 'branch'));
        foreach (['army', 'navy', 'air_force', 'marine_corps'] as $branch) {
            self::assertGreaterThanOrEqual(10, $us[$branch] ?? 0, 'US ' . $branch);
        }
    }

    public function testNatoPackListsCombinedFunctionsUnderItsOwnCategory(): void
    {
        $pack = JobCatalog::packs()['nato'] ?? null;
        self::assertIsArray($pack);
        self::assertSame(['NATO', 'en'], [$pack['country'], $pack['official_lang']]);
        self::assertSame('OTAN', JobCatalog::countryLabel('NATO', 'fr'));
        self::assertSame('NATO', JobCatalog::countryLabel('NATO', 'en'));
        self::assertContains(['code' => 'NATO', 'label' => 'OTAN', 'count' => count($pack['entries'])], JobCatalog::countries('fr'));
        $entries = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'NATO'));
        self::assertSame(count($pack['entries']), count($entries));
        foreach ($entries as $entry) {
            self::assertStringStartsWith('nato-', (string) $entry['id']);
            self::assertNotSame('', trim((string) ($entry['formation'] ?? '')), $entry['id'] . ' : unité courte manquante.');
        }
        $types = array_count_values(array_column($entries, 'type'));
        foreach (JobCatalog::TYPES as $type) {
            self::assertGreaterThan(0, $types[$type] ?? 0, 'OTAN : type ' . $type);
        }
        // Fonctions interalliées attendues, trouvées par leur abrégé et filtrées sur la catégorie OTAN.
        $abbrs = array_column($entries, 'abbreviation');
        foreach (['JTAC', 'JFO'] as $abbr) {
            self::assertContains($abbr, $abbrs);
            $hits = JobCatalog::search($abbr, ['country' => 'NATO'], 'fr');
            self::assertNotSame([], $hits);
            self::assertSame($abbr, $hits[0]['abbreviation']);
            self::assertSame('OTAN', $hits[0]['country_label']);
        }
        self::assertSame([], array_filter(JobCatalog::search('', ['country' => 'NATO'], 'en'), static fn (array $e): bool => $e['country'] !== 'NATO'));
        self::assertSame('Interarmées et interalliés', JobCatalog::branchLabel('NATO', 'joint', 'fr'));
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/job-catalog.css');
        self::assertStringContainsString('.jobcat__flag[data-flag="NATO"]', $css, 'emblème OTAN dessiné en CSS');
    }

    public function testFrenchPackCoversTheSpecialForcesOfEachServiceWithFrenchOfficialNames(): void
    {
        $pack = JobCatalog::packs()['fr_sof'] ?? null;
        self::assertIsArray($pack);
        self::assertSame('FR', $pack['country']);
        self::assertSame('fr', $pack['official_lang']);
        $entries = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'FR'));
        $branches = array_count_values(array_column($entries, 'branch'));
        foreach (['army', 'navy', 'air_force'] as $branch) {
            self::assertGreaterThanOrEqual(10, $branches[$branch] ?? 0, 'FR ' . $branch);
        }
        $formations = array_unique(array_column($entries, 'formation'));
        foreach (['1er RPIMa', '13e RDP', '4e RHFS', 'Commando Hubert', 'Commando Kieffer', 'CPA 10', 'EH 1/67 Pyrénées', 'ET 3/61 Poitou'] as $unit) {
            self::assertContains($unit, $formations, 'Unité absente du catalogue français.');
        }
        foreach ($entries as $entry) {
            self::assertNotSame('', trim((string) ($entry['formation'] ?? '')), $entry['id'] . ' : unité courte manquante.');
        }
        // Nom officiel : l’appellation française, montrée au lecteur anglophone sous le nom traduit.
        $first = $entries[0];
        $en = JobCatalog::find((string) $first['id'], 'en');
        self::assertSame($first['name']['fr'], $en['official_name']);
        self::assertSame($first['name']['en'], $en['name']);
        self::assertSame('France', $en['country_label']);
    }

    public function testGermanPackCoversKskDskKsmAndLuftwaffeWithGermanOfficialNames(): void
    {
        $pack = JobCatalog::packs()['de_sof'] ?? null;
        self::assertIsArray($pack);
        self::assertSame('DE', $pack['country']);
        self::assertSame('de', $pack['official_lang']);
        $entries = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'DE'));
        $branches = array_count_values(array_column($entries, 'branch'));
        foreach (['army' => 40, 'navy' => 15, 'air_force' => 10, 'joint' => 3] as $branch => $min) {
            self::assertGreaterThanOrEqual($min, $branches[$branch] ?? 0, 'DE ' . $branch);
        }
        foreach (['KSK', 'DSK', 'EinsFltl 1', 'Luftwaffe'] as $command) {
            self::assertContains($command, array_column($entries, 'command'), 'Commandement absent du catalogue allemand.');
        }
        $formations = array_unique(array_column($entries, 'formation'));
        foreach (['Kommandokompanie', 'KSK Unterstützung', 'FschJgRgt 26', 'FschJgRgt 31', 'Fernspähkompanie 1', 'GebJgBrig 23', 'KSM', 'Seebataillon', 'HSG 64', 'ObjSRgt'] as $unit) {
            self::assertContains($unit, $formations, 'Unité absente du catalogue allemand.');
        }
        foreach ($entries as $entry) {
            self::assertNotSame('', trim((string) ($entry['formation'] ?? '')), $entry['id'] . ' : unité courte manquante.');
            // Nom officiel allemand obligatoire, montré dans la fiche sous le nom traduit.
            self::assertNotSame('', trim((string) ($entry['name']['de'] ?? '')), $entry['id'] . ' : nom officiel allemand manquant.');
            self::assertLessThanOrEqual(120, mb_strlen((string) $entry['name']['de']), $entry['id']);
            $official = array_filter($entry['sources'], static fn (array $s): bool => preg_match('~wikipedia\.org~', (string) $s['url']) !== 1);
            self::assertNotSame([], $official, $entry['id'] . ' : Wikipédia ne peut pas être la seule source.');
        }
        $byId = array_column($entries, null, 'id');
        $ksm = JobCatalog::find('de-marine-ksm-kampfschwimmer', 'fr');
        self::assertIsArray($ksm);
        self::assertSame('Kampfschwimmerunteroffizier', $ksm['official_name']);
        self::assertNotSame($ksm['official_name'], $ksm['name']);
        self::assertSame('Deutsche Marine (marine)', $ksm['branch_label']);
        self::assertSame('Allemagne', $ksm['country_label']);
        $en = JobCatalog::find('de-heer-ksk-kommandofeldwebel', 'en');
        self::assertSame($byId['de-heer-ksk-kommandofeldwebel']['name']['de'], $en['official_name']);
        self::assertSame($byId['de-heer-ksk-kommandofeldwebel']['name']['en'], $en['name']);
        self::assertSame('German Army (Heer)', $en['branch_label']);
        self::assertSame('Germany', $en['country_label']);
        // Recherche sans tréma dans le vrai catalogue.
        $hits = array_column(JobCatalog::search('fallschirmjager', ['country' => 'DE'], 'fr'), 'id');
        self::assertNotEmpty($hits);
        self::assertSame($hits, array_column(JobCatalog::search('Fallschirmjäger', ['country' => 'DE'], 'fr'), 'id'));
        self::assertSame($hits, array_column(JobCatalog::search('fallschirmjaeger', ['country' => 'DE'], 'fr'), 'id'));
        self::assertContains('de-marine-ksm-kampfschwimmer', array_column(JobCatalog::search('kampfschwimmer', [], 'en'), 'id'));
        // Noms par langue : officiel d’abord, puis la langue du lecteur, puis l’anglais (US : anglais officiel + français).
        $kf = $byId['de-heer-ksk-kommandofeldwebel']['name'];
        self::assertSame(['de' => $kf['de'], 'fr' => $kf['fr'], 'en' => $kf['en']], JobCatalog::find('de-heer-ksk-kommandofeldwebel', 'fr')['names']);
        self::assertSame(['de' => $kf['de'], 'en' => $kf['en'], 'fr' => $kf['fr']], JobCatalog::find('de-heer-ksk-kommandofeldwebel', 'en')['names']);
        $us = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'US'))[0];
        self::assertSame(['en', 'fr'], array_keys(JobCatalog::find((string) $us['id'], 'fr')['names']));
        $fr = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'FR'))[0];
        self::assertSame(['fr', 'en'], array_keys(JobCatalog::find((string) $fr['id'], 'en')['names']));
        self::assertSame(['fr', 'en'], array_keys(JobCatalog::find((string) $fr['id'], 'fr')['names']));
        // Recherche dans les trois langues, quelle que soit la langue du lecteur.
        foreach ([$kf['de'], $kf['fr'], $kf['en']] as $q) {
            foreach (['fr', 'en'] as $reader) {
                self::assertContains('de-heer-ksk-kommandofeldwebel', array_column(JobCatalog::search($q, ['country' => 'DE'], $reader), 'id'), $q . ' / ' . $reader);
            }
        }
    }

    public function testRussianPackHasCyrillicOfficialNamesTransliterationAndSecurityForcesApart(): void
    {
        $pack = JobCatalog::packs()['ru_sof'] ?? null;
        self::assertIsArray($pack);
        self::assertSame('RU', $pack['country']);
        self::assertSame('ru', $pack['official_lang']);
        $entries = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'RU'));
        $branches = array_count_values(array_column($entries, 'branch'));
        // Forces terrestres et VDV, infanterie de marine, marine, aviation de l’armée, sécurité intérieure à part, KSSO.
        foreach (['army' => 40, 'marine_corps' => 15, 'navy' => 8, 'air_force' => 8, 'gendarmerie' => 8, 'joint' => 3] as $branch => $min) {
            self::assertGreaterThanOrEqual($min, $branches[$branch] ?? 0, 'RU ' . $branch);
        }
        foreach (['KSSO', 'GU GSh', 'VDV', 'VKS', 'Rosgvardia', 'FSB'] as $command) {
            self::assertContains($command, array_column($entries, 'command'), 'Commandement absent du catalogue russe.');
        }
        $formations = array_unique(array_column($entries, 'formation'));
        foreach (['2 OBrSpN', '22 OBrSpN', '45 OBrSpN VDV', '76 DShD', '55 DMP', '810 OBrMP', '42 OMRP', 'PDSS', 'SOBR', 'TsSN FSB Alfa'] as $unit) {
            self::assertContains($unit, $formations, 'Unité absente du catalogue russe.');
        }
        foreach ($entries as $entry) {
            $id = (string) $entry['id'];
            self::assertNotSame('', trim((string) ($entry['formation'] ?? '')), $id . ' : unité courte manquante.');
            self::assertDoesNotMatchRegularExpression('/\p{Cyrillic}/u', (string) $entry['formation'], $id . ' : unité courte en latin.');
            // Nom officiel en cyrillique et sa translittération latine, toujours dans la même table que la recherche.
            $ru = (string) ($entry['name']['ru'] ?? '');
            $latin = (string) ($entry['name']['ru-Latn'] ?? '');
            self::assertMatchesRegularExpression('/\p{Cyrillic}/u', $ru, $id . ' : nom officiel russe en cyrillique.');
            self::assertLessThanOrEqual(120, mb_strlen($ru), $id);
            self::assertDoesNotMatchRegularExpression('/\p{Cyrillic}/u', $latin, $id . ' : translittération en latin.');
            self::assertSame(JobCatalog::transliterate($ru), mb_strtolower($latin), $id . ' : translittération.');
            self::assertSame('', (string) $entry['code'], $id . ' : aucun code VUS publié.');
            // Source faible marquée sur l’entrée et sur son titre ; sinon au moins une source hors Wikipédia.
            if (($entry['source_quality'] ?? '') === 'weak') {
                self::assertNotSame([], array_filter($entry['sources'], static fn (array $s): bool => str_contains((string) $s['title'], '[source faible]')), $id);
            } else {
                self::assertArrayNotHasKey('source_quality', $entry, $id);
                self::assertNotSame([], array_filter($entry['sources'], static fn (array $s): bool => preg_match('~wikipedia\.org~', (string) $s['url']) !== 1), $id . ' : Wikipédia ne peut pas être la seule source.');
            }
        }
        $weak = count(array_filter($entries, static fn (array $e): bool => ($e['source_quality'] ?? '') === 'weak'));
        self::assertLessThanOrEqual(intdiv(count($entries), 4), $weak, 'Au plus un quart d’entrées à source faible.');
        // Nom officiel russe en titre, translittération puis traductions ; armée et pays traduits.
        $byId = array_column($entries, null, 'id');
        $diver = JobCatalog::find('ru-vmf-42omrp-vodolaz-razvedchik', 'fr');
        self::assertSame('Водолаз-разведчик', $diver['official_name']);
        self::assertSame(['ru', 'ru-Latn', 'fr', 'en'], array_keys($diver['names']));
        self::assertSame('Vodolaz-razvedchik', $diver['names']['ru-Latn']);
        self::assertSame('VMF (marine)', $diver['branch_label']);
        self::assertSame('Russie', $diver['country_label']);
        $en = JobCatalog::find('ru-fsb-tssn-alfa-sotrudnik', 'en');
        self::assertSame(['ru', 'ru-Latn', 'en', 'fr'], array_keys($en['names']));
        self::assertSame('Internal security (Rosgvardia, FSB), not armed forces', $en['branch_label']);
        self::assertSame('Russia', $en['country_label']);
        // Recherche : cyrillique, translittération, français et anglais, quel que soit le lecteur.
        $kg = $byId['ru-vmf-561omrp-boevoy-plovets']['name'];
        foreach ([$kg['ru'], mb_strtoupper($kg['ru']), $kg['ru-Latn'], 'boevoy plovets', $kg['fr'], $kg['en']] as $q) {
            foreach (['fr', 'en'] as $reader) {
                self::assertContains('ru-vmf-561omrp-boevoy-plovets', array_column(JobCatalog::search($q, ['country' => 'RU'], $reader), 'id'), $q . ' / ' . $reader);
            }
        }
        $snipers = array_column(JobCatalog::search('снайпер', ['country' => 'RU'], 'fr'), 'id');
        self::assertContains('ru-rosgv-sobr-snaiper', $snipers);
        self::assertContains('ru-vdv-11odshbr-snaiper', $snipers);
        self::assertSame($snipers, array_column(JobCatalog::search('snayper', ['country' => 'RU'], 'fr'), 'id'));
        // Mot cyrillique cherché aussi sous sa translittération : « спецназ » trouve les étiquettes « Spetsnaz ».
        $spetsnaz = array_column(JobCatalog::search('spetsnaz', ['country' => 'RU'], 'fr'), 'id');
        self::assertGreaterThan(10, count($spetsnaz));
        self::assertEqualsCanonicalizing($spetsnaz, array_column(JobCatalog::search('спецназ', ['country' => 'RU'], 'fr'), 'id'));
        self::assertContains('ru-vdv-83odshbr-pulemetchik', array_column(JobCatalog::search('пулеметчик', [], 'en'), 'id'), 'ё tapé е.');
        self::assertContains('ru-vdv-83odshbr-pulemetchik', array_column(JobCatalog::search('pulemetchik', [], 'en'), 'id'));
        self::assertContains('ru-vks-aa-ka52-komandir-ekipazha', array_column(JobCatalog::search('Ка-52', [], 'fr'), 'id'));
        self::assertContains('ru-vks-aa-ka52-komandir-ekipazha', array_column(JobCatalog::search('ka 52', [], 'fr'), 'id'));
        self::assertSame([], array_column(JobCatalog::search('spetsnaz', ['country' => 'DE'], 'fr'), 'id'));
        self::assertSame(['RU' => 'Russie'], array_intersect_key(JobCatalog::facets('fr')['country'], ['RU' => true]));
    }

    public function testChinesePackHasOfficialNamesInCharactersAndPinyinAndIsFoundEitherWay(): void
    {
        $pack = JobCatalog::packs()['cn_sof'] ?? null;
        self::assertIsArray($pack);
        self::assertSame('CN', $pack['country']);
        self::assertSame('zh', $pack['official_lang']);
        $entries = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'CN'));
        $branches = array_count_values(array_column($entries, 'branch'));
        foreach (['army' => 20, 'marine_corps' => 8, 'air_force' => 8, 'gendarmerie' => 6] as $branch => $min) {
            self::assertGreaterThanOrEqual($min, $branches[$branch] ?? 0, 'CN ' . $branch);
        }
        foreach ($entries as $entry) {
            $id = (string) $entry['id'];
            self::assertNotSame('', trim((string) ($entry['formation'] ?? '')), $id . ' : unité courte manquante.');
            // Nom officiel en caractères chinois (simplifiés) et sa romanisation pinyin avec tons.
            self::assertMatchesRegularExpression('/\p{Han}/u', (string) ($entry['name']['zh'] ?? ''), $id . ' : nom chinois manquant.');
            self::assertLessThanOrEqual(120, mb_strlen((string) $entry['name']['zh']), $id);
            $py = (string) ($entry['name']['zh-Latn'] ?? '');
            self::assertMatchesRegularExpression('/^[\p{Latin}0-9 \'\-()·.,]+$/u', $py, $id . ' : pinyin manquant ou hors alphabet latin.');
            self::assertMatchesRegularExpression('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ]/u', $py, $id . ' : pinyin sans tons.');
            $official = array_filter($entry['sources'], static fn (array $s): bool => preg_match('~wikipedia\.org~', (string) $s['url']) !== 1);
            self::assertNotSame([], $official, $id . ' : Wikipédia ne peut pas être la seule source.');
        }
        // Officiel en chinois, pinyin (« zh-Latn ») juste après, puis la langue du lecteur et l’anglais.
        $first = $entries[0];
        $fr = JobCatalog::find((string) $first['id'], 'fr');
        self::assertSame($first['name']['zh'], $fr['official_name']);
        self::assertSame(['zh', 'zh-Latn', 'fr', 'en'], array_keys($fr['names']));
        self::assertSame(['zh', 'zh-Latn', 'en', 'fr'], array_keys(JobCatalog::find((string) $first['id'], 'en')['names']));
        self::assertSame('Chine', $fr['country_label']);
        self::assertSame('China', JobCatalog::find((string) $first['id'], 'en')['country_label']);
        // Recherche par caractères, pinyin avec et sans tons, d’un seul tenant, français et anglais.
        $flat = JobCatalog::normalize((string) $first['name']['zh-Latn']);
        foreach ([$first['name']['zh'], mb_substr((string) $first['name']['zh'], 0, 2), $first['name']['zh-Latn'], $flat, str_replace(' ', '', $flat), $first['name']['fr'], $first['name']['en']] as $q) {
            foreach (['fr', 'en'] as $reader) {
                self::assertContains($first['id'], array_column(JobCatalog::search((string) $q, ['country' => 'CN'], $reader), 'id'), $q . ' / ' . $reader);
            }
        }
    }

    public function testChineseNamesAreSearchedByCharactersAndPinyinWithOrWithoutTones(): void
    {
        $dir = sys_get_temp_dir() . '/jobcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $branch, string $formation, array $name): array => [
            'id' => $id, 'branch' => $branch, 'command' => '南部战区', 'formation' => $formation, 'type' => 'operator',
            'code' => '', 'code_system' => '', 'abbreviation' => '', 'icon' => 'crosshair', 'name' => $name,
            'unit' => ['fr' => $formation . ' (unité)', 'en' => $formation . ' (unit)'], 'description' => ['fr' => 'Texte.', 'en' => 'Text.'],
            'qualifications' => ['fr' => [], 'en' => []], 'billets' => ['fr' => [], 'en' => []],
            'tags' => ['fr' => ['Forces spéciales'], 'en' => ['Special Forces']], 'sources' => [['url' => 'https://eng.chinamil.com.cn/x', 'title' => 'x']],
        ];
        file_put_contents($dir . '/cn_sof.json', (string) json_encode(['country' => 'CN', 'official_lang' => 'zh', 'label' => ['fr' => 'FS CN', 'en' => 'CN SOF'], 'compiled_at' => '2026-11-02', 'sources_note' => ['fr' => 'n', 'en' => 'n'], 'entries' => [
            $entry('cn-army-sof-sniper', 'army', '特战旅', ['zh' => '狙击手', 'zh-Latn' => 'jūjīshǒu', 'fr' => 'Tireur d’élite', 'en' => 'Sniper']),
            $entry('cn-pap-snow-leopard-operator', 'gendarmerie', '雪豹突击队', ['zh' => '特战队员', 'zh-Latn' => 'tèzhàn duìyuán', 'fr' => 'Opérateur des forces spéciales', 'en' => 'Special operations member']),
            $entry('cn-mc-brigade-commander', 'marine_corps', '陆战旅', ['zh' => '旅长', 'zh-Latn' => 'lǚzhǎng', 'fr' => 'Commandant de brigade', 'en' => 'Brigade commander']),
        ]]));
        JobCatalog::useDirectory($dir);
        try {
            $ids = static fn (string $q, string $lang = 'fr'): array => array_column(JobCatalog::search($q, [], $lang), 'id');
            self::assertSame([['code' => 'CN', 'label' => 'Chine', 'count' => 3]], JobCatalog::countries('fr'));
            self::assertSame('China', JobCatalog::countries('en')[0]['label']);
            foreach (['狙击手', '狙击', 'jūjīshǒu', 'jujishou', 'JUJISHOU', 'tireur', 'sniper'] as $q) {
                self::assertSame(['cn-army-sof-sniper'], $ids($q), $q);
            }
            foreach (['特战队员', '雪豹', 'tèzhàn duìyuán', 'tezhan duiyuan', 'tezhanduiyuan', 'tezhan', 'special operations member'] as $q) {
                self::assertSame(['cn-pap-snow-leopard-operator'], $ids($q, 'en'), $q);
            }
            // « ü » : avec ou sans tréma, ou « v » comme au clavier.
            foreach (['旅长', 'lǚzhǎng', 'lüzhang', 'luzhang', 'lvzhang'] as $q) {
                self::assertSame(['cn-mc-brigade-commander'], $ids($q), $q);
            }
            self::assertSame(['zh' => '狙击手', 'zh-Latn' => 'jūjīshǒu', 'fr' => 'Tireur d’élite', 'en' => 'Sniper'], JobCatalog::find('cn-army-sof-sniper', 'fr')['names']);
            self::assertSame('狙击手', JobCatalog::find('cn-army-sof-sniper', 'en')['official_name']);
            self::assertSame('Sniper', JobCatalog::find('cn-army-sof-sniper', 'en')['name']);
            self::assertSame('Police armée du peuple (武警部队)', JobCatalog::find('cn-pap-snow-leopard-operator', 'fr')['branch_label']);
            self::assertSame('PLA Navy Marine Corps (海军陆战队)', JobCatalog::find('cn-mc-brigade-commander', 'en')['branch_label']);
            self::assertSame(['南部战区'], array_keys(JobCatalog::facets('fr', ['country' => 'CN'])['command']));
        } finally {
            unlink($dir . '/cn_sof.json');
            rmdir($dir);
        }
    }

    public function testContentIsServedInTheReadersLanguageAndSearchCoversCodesNamesUnitsAndFilters(): void
    {
        $dir = sys_get_temp_dir() . '/jobcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $branch, string $code, string $abbr, string $en, string $fr, string $unit): array => [
            'id' => $id, 'branch' => $branch, 'command' => $branch === 'navy' ? 'NSWC' : 'USASOC', 'type' => 'operator',
            'code' => $code, 'code_system' => 'MOS', 'abbreviation' => $abbr, 'icon' => 'crosshair',
            'name' => ['en' => $en, 'fr' => $fr], 'unit' => ['en' => $unit, 'fr' => $unit . ' (FR)'],
            'description' => ['en' => 'English text.', 'fr' => 'Texte français.'],
            'qualifications' => ['en' => ['Airborne'], 'fr' => ['Brevet parachutiste']], 'billets' => ['en' => [], 'fr' => []],
            'tags' => ['en' => ['Special Forces'], 'fr' => ['Forces spéciales']], 'sources' => [['url' => 'https://www.goarmy.com/x', 'title' => 'x']],
        ];
        file_put_contents($dir . '/us_sof.json', json_encode(['country' => 'US', 'label' => ['fr' => 'FS US', 'en' => 'US SOF'], 'compiled_at' => '2026-11-02', 'sources_note' => ['fr' => 'n', 'en' => 'n'], 'entries' => [
            $entry('us-army-18b', 'army', '18B', 'WPN', 'Special Forces Weapons Sergeant', 'Sergent armement des forces spéciales', 'Special Forces Groups'),
            $entry('us-army-18c', 'army', '18C', 'ENG', 'Special Forces Engineer Sergeant', 'Sergent génie des forces spéciales', 'Special Forces Groups'),
            $entry('us-navy-so', 'navy', 'SO', 'SEAL', 'Special Warfare Operator', 'Opérateur des forces spéciales navales', 'SEAL Teams'),
        ]]));
        JobCatalog::useDirectory($dir);
        try {
            self::assertSame('Sergent armement des forces spéciales', JobCatalog::find('us-army-18b', 'fr')['name']);
            self::assertSame('Special Forces Weapons Sergeant', JobCatalog::find('us-army-18b', 'en')['name']);
            self::assertSame('Special Forces Weapons Sergeant', JobCatalog::find('us-army-18b', 'fr')['official_name']);
            self::assertSame(['Forces spéciales'], JobCatalog::find('us-army-18b', 'fr')['tags']);
            self::assertSame('Texte français.', JobCatalog::find('us-army-18b', 'fr-FR')['description']);
            self::assertSame('English text.', JobCatalog::find('us-army-18b', 'en')['description']);
            $ids = static fn (array $hits): array => array_column($hits, 'id');
            self::assertSame('us-army-18b', $ids(JobCatalog::search('18b', [], 'fr'))[0]);
            self::assertSame('us-army-18b', $ids(JobCatalog::search('18 B', [], 'fr'))[0]);
            self::assertSame(['us-army-18c'], $ids(JobCatalog::search('genie', [], 'fr')), 'Sans accent, en français.');
            self::assertSame(['us-army-18c'], $ids(JobCatalog::search('engineer', [], 'fr')), 'Nom officiel anglais cherché aussi.');
            self::assertSame(['us-navy-so'], $ids(JobCatalog::search('seal', [], 'en')));
            self::assertSame(['us-navy-so'], $ids(JobCatalog::search('', ['branch' => 'navy'], 'fr')));
            self::assertSame([], $ids(JobCatalog::search('seal', ['command' => 'USASOC'], 'fr')));
            self::assertSame(['us-army-18b', 'us-army-18c'], $ids(JobCatalog::search('forces groups', [], 'fr')));
            self::assertSame(['army' => 'US Army', 'navy' => 'US Navy'], JobCatalog::facets('en')['branch']);
        } finally {
            unlink($dir . '/us_sof.json');
            rmdir($dir);
        }
    }

    public function testCountriesFiltersAndSearchSpanEveryPack(): void
    {
        $dir = sys_get_temp_dir() . '/jobcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $branch, string $command, string $formation, string $type, string $abbr, string $fr, string $en, array $qualFr, array $qualEn): array => [
            'id' => $id, 'branch' => $branch, 'command' => $command, 'formation' => $formation, 'type' => $type,
            'code' => '', 'code_system' => '', 'abbreviation' => $abbr, 'icon' => 'parachute',
            'name' => ['fr' => $fr, 'en' => $en], 'unit' => ['fr' => $formation . ' (unité)', 'en' => $formation . ' (unit)'],
            'description' => ['fr' => 'Texte.', 'en' => 'Text.'], 'qualifications' => ['fr' => $qualFr, 'en' => $qualEn],
            'billets' => ['fr' => [], 'en' => []], 'tags' => ['fr' => ['Forces spéciales'], 'en' => ['Special Forces']],
            'sources' => [['url' => 'https://www.defense.gouv.fr/x', 'title' => 'x']],
        ];
        $pack = static fn (string $country, array $entries, string $official = 'en'): string => (string) json_encode(['country' => $country, 'official_lang' => $official, 'label' => ['fr' => 'FS ' . $country, 'en' => $country . ' SOF'], 'compiled_at' => '2026-11-02', 'sources_note' => ['fr' => 'n', 'en' => 'n'], 'entries' => $entries]);
        file_put_contents($dir . '/us_sof.json', $pack('US', [
            $entry('us-navy-so', 'navy', 'NSWC', '', 'operator', 'SO', 'Opérateur de guerre spéciale', 'Special Warfare Operator', ['Brevet parachutiste'], ['Basic Airborne Course']),
            $entry('us-army-18b', 'army', 'USASOC', '', 'operator', 'WPN', 'Sergent armement', 'Weapons Sergeant', ['Brevet parachutiste'], ['Basic Airborne Course']),
        ]));
        file_put_contents($dir . '/fr_sof.json', $pack('FR', [
            $entry('fr-marine-hubert-nageur', 'navy', 'FORFUSCO', 'Commando Hubert', 'operator', 'NC', 'Nageur de combat', 'Combat Swimmer', ['Brevet de nageur de combat', 'Brevet parachutiste'], ['Combat swimmer badge', 'Parachutist badge']),
            $entry('fr-terre-1rpima-ce', 'army', 'CFST', '1er RPIMa', 'command', 'CE', 'Chef d’équipe forces spéciales', 'Special Forces Team Leader', ['BSAP', 'Chuteur opérationnel'], ['BSAP', 'Military free-fall']),
            $entry('fr-air-cpa10-jtac', 'air_force', 'BFSA', 'CPA 10', 'operator', 'JTAC', 'Contrôleur aérien avancé', 'Joint Terminal Attack Controller', ['Qualification JTAC'], ['JTAC qualification']),
        ], 'fr'));
        JobCatalog::useDirectory($dir);
        try {
            $ids = static fn (array $hits): array => array_column($hits, 'id');
            self::assertSame([['code' => 'US', 'label' => 'États-Unis', 'count' => 2], ['code' => 'FR', 'label' => 'France', 'count' => 3]], JobCatalog::countries('fr'));
            self::assertSame('United States', JobCatalog::countries('en')[0]['label']);
            // Recherche tous pays : brevet en français, nom anglais, unité courte, sans accents.
            self::assertEqualsCanonicalizing(['us-navy-so', 'us-army-18b', 'fr-marine-hubert-nageur'], $ids(JobCatalog::search('brevet parachutiste', [], 'fr')));
            self::assertSame(['fr-marine-hubert-nageur'], $ids(JobCatalog::search('combat swimmer', [], 'fr')));
            self::assertSame(['fr-air-cpa10-jtac'], $ids(JobCatalog::search('cpa 10', [], 'en')));
            self::assertSame(['fr-terre-1rpima-ce'], $ids(JobCatalog::search('chef d equipe', [], 'en')));
            self::assertSame(['fr-terre-1rpima-ce'], $ids(JobCatalog::search('chuteur', [], 'fr')), 'Les brevets sont cherchés.');
            self::assertSame(['fr-terre-1rpima-ce'], $ids(JobCatalog::search('', ['country' => 'FR', 'branch' => 'army'], 'fr')));
            self::assertSame(['us-navy-so', 'fr-marine-hubert-nageur'], $ids(JobCatalog::search('', ['branch' => 'navy'], 'fr')), 'Même armée dans deux pays.');
            self::assertSame(['fr-marine-hubert-nageur'], $ids(JobCatalog::search('', ['country' => 'FR', 'branch' => 'navy', 'formation' => 'Commando Hubert', 'type' => 'operator'], 'fr')));
            self::assertSame([], $ids(JobCatalog::search('', ['country' => 'US', 'formation' => 'Commando Hubert'], 'fr')));
            self::assertSame([], $ids(JobCatalog::search('jtac', ['country' => 'US'], 'fr')));
            // Libellés d’armée par pays ; libellé générique quand l’armée existe dans deux pays.
            self::assertSame('Marine nationale', JobCatalog::find('fr-marine-hubert-nageur', 'fr')['branch_label']);
            self::assertSame('French Navy', JobCatalog::find('fr-marine-hubert-nageur', 'en')['branch_label']);
            self::assertSame('US Navy', JobCatalog::find('us-navy-so', 'en')['branch_label']);
            $facets = JobCatalog::facets('fr');
            self::assertSame(['US' => 'États-Unis', 'FR' => 'France'], $facets['country']);
            self::assertSame('Marines', $facets['branch']['navy']);
            self::assertSame('Armée de l’Air et de l’Espace', $facets['branch']['air_force']);
            self::assertSame(['1er RPIMa' => '1er RPIMa', 'Commando Hubert' => 'Commando Hubert', 'CPA 10' => 'CPA 10'], $facets['formation']);
            $fr = JobCatalog::facets('en', ['country' => 'FR']);
            self::assertSame('French Navy', $fr['branch']['navy']);
            self::assertSame(['BFSA', 'CFST', 'FORFUSCO'], array_keys($fr['command']));
            // Nom officiel dans la langue du pays ; l’autre langue reste cherchable par la fenêtre.
            $nc = JobCatalog::find('fr-marine-hubert-nageur', 'en');
            self::assertSame('Combat Swimmer', $nc['name']);
            self::assertSame('Nageur de combat', $nc['official_name']);
            self::assertStringContainsString('Brevet de nageur de combat', $nc['alt']);
            self::assertSame('Commando Hubert', $nc['formation']);
            self::assertSame('Weapons Sergeant', JobCatalog::find('us-army-18b', 'fr')['official_name']);
        } finally {
            unlink($dir . '/us_sof.json');
            unlink($dir . '/fr_sof.json');
            rmdir($dir);
        }
    }

    public function testThirdCountryHasItsCountersFiltersOfficialGermanNameAndUmlautFreeSearch(): void
    {
        $dir = sys_get_temp_dir() . '/jobcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $branch, string $command, string $formation, string $type, array $name, array $qualDe = []): array => [
            'id' => $id, 'branch' => $branch, 'command' => $command, 'formation' => $formation, 'type' => $type,
            'code' => '', 'code_system' => '', 'abbreviation' => '', 'icon' => 'parachute', 'name' => $name,
            'unit' => ['fr' => $formation . ' (unité)', 'en' => $formation . ' (unit)'], 'description' => ['fr' => 'Texte.', 'en' => 'Text.'],
            'qualifications' => ['fr' => $qualDe, 'en' => $qualDe], 'billets' => ['fr' => [], 'en' => []],
            'tags' => ['fr' => ['Forces spéciales'], 'en' => ['Special Forces']], 'sources' => [['url' => 'https://www.bundeswehr.de/x', 'title' => 'x']],
        ];
        $pack = static fn (string $country, string $official, array $entries): string => (string) json_encode(['country' => $country, 'official_lang' => $official, 'label' => ['fr' => 'FS ' . $country, 'en' => $country . ' SOF'], 'compiled_at' => '2026-11-02', 'sources_note' => ['fr' => 'n', 'en' => 'n'], 'entries' => $entries]);
        file_put_contents($dir . '/us_sof.json', $pack('US', 'en', [
            $entry('us-navy-so', 'navy', 'NSWC', 'SEAL Teams', 'operator', ['fr' => 'Opérateur de guerre spéciale', 'en' => 'Special Warfare Operator']),
        ]));
        file_put_contents($dir . '/fr_sof.json', $pack('FR', 'fr', [
            $entry('fr-marine-hubert-nageur', 'navy', 'FORFUSCO', 'Commando Hubert', 'operator', ['fr' => 'Nageur de combat', 'en' => 'Combat Swimmer']),
        ]));
        file_put_contents($dir . '/de_sof.json', $pack('DE', 'de', [
            $entry('de-heer-fschjg26-fallschirmjaeger', 'army', 'DSK', 'FschJgRgt 26', 'operator', ['de' => 'Fallschirmjäger', 'fr' => 'Parachutiste (Fallschirmjäger)', 'en' => 'Paratrooper'], ['Einzelkämpferlehrgang']),
            $entry('de-marine-ksm-kampfschwimmer', 'navy', 'EinsFltl 1', 'KSM', 'operator', ['de' => 'Kampfschwimmer', 'fr' => 'Nageur de combat (KSM)', 'en' => 'Combat Swimmer (KSM)'], ['Kampfschwimmerlehrgang']),
            $entry('de-lw-hsg64-pilot', 'air_force', 'Luftwaffe', 'HSG 64', 'aviation', ['de' => 'Luftfahrzeugführer H145M LUH SOF', 'fr' => 'Pilote H145M LUH SOF', 'en' => 'H145M LUH SOF pilot']),
            $entry('de-heer-gebjg-bergfuehrer', 'army', 'DSK', 'GebJgBrig 23', 'support', ['de' => 'Heeresbergführer', 'fr' => 'Guide de haute montagne', 'en' => 'Army mountain guide'], ['Heeresbergführerausbildung, Straße des Gebirges']),
        ]));
        JobCatalog::useDirectory($dir);
        try {
            $ids = static fn (array $hits): array => array_column($hits, 'id');
            // Compteurs : un pays par pack, dans l’ordre de COUNTRIES.
            self::assertSame([['code' => 'US', 'label' => 'États-Unis', 'count' => 1], ['code' => 'FR', 'label' => 'France', 'count' => 1], ['code' => 'DE', 'label' => 'Allemagne', 'count' => 4]], JobCatalog::countries('fr'));
            self::assertSame('Germany', JobCatalog::countries('en')[2]['label']);
            self::assertSame(['US' => 'États-Unis', 'FR' => 'France', 'DE' => 'Allemagne'], JobCatalog::facets('fr')['country']);
            // Filtres en cascade : armées, commandements et unités du seul pays choisi ; armée commune à trois pays = libellé générique.
            self::assertSame('Marines', JobCatalog::facets('fr')['branch']['navy']);
            $de = JobCatalog::facets('fr', ['country' => 'DE']);
            self::assertSame(['army' => 'Heer (armée de terre)', 'navy' => 'Deutsche Marine (marine)', 'air_force' => 'Luftwaffe (armée de l’air)'], $de['branch']);
            self::assertSame(['DSK', 'EinsFltl 1', 'Luftwaffe'], array_keys($de['command']));
            self::assertSame(['FschJgRgt 26', 'GebJgBrig 23'], array_keys(JobCatalog::facets('fr', ['country' => 'DE', 'branch' => 'army', 'command' => 'DSK'])['formation']));
            self::assertSame(['de-marine-ksm-kampfschwimmer'], $ids(JobCatalog::search('', ['country' => 'DE', 'branch' => 'navy'], 'fr')));
            self::assertSame(['us-navy-so', 'fr-marine-hubert-nageur', 'de-marine-ksm-kampfschwimmer'], $ids(JobCatalog::search('', ['branch' => 'navy'], 'fr')));
            self::assertSame('German Navy (Deutsche Marine)', JobCatalog::find('de-marine-ksm-kampfschwimmer', 'en')['branch_label']);
            // Nom officiel allemand montré sous le nom traduit, dans les deux langues du site.
            $fr = JobCatalog::find('de-heer-fschjg26-fallschirmjaeger', 'fr');
            self::assertSame('Parachutiste (Fallschirmjäger)', $fr['name']);
            self::assertSame('Fallschirmjäger', $fr['official_name']);
            self::assertSame('de', JobCatalog::rawEntries()[2]['official_lang']);
            self::assertSame('Paratrooper', JobCatalog::find('de-heer-fschjg26-fallschirmjaeger', 'en')['name']);
            self::assertSame('Fallschirmjäger', JobCatalog::find('de-heer-fschjg26-fallschirmjaeger', 'en')['official_name']);
            // Trémas et ß : sans tréma, avec la graphie « ae », avec ou sans ß, en majuscules ; le nom allemand est cherché.
            foreach (['fallschirmjager', 'fallschirmjaeger', 'FALLSCHIRMJÄGER', 'Fallschirmjäger', 'einzelkampfer', 'einzelkaempfer'] as $q) {
                self::assertSame(['de-heer-fschjg26-fallschirmjaeger'], $ids(JobCatalog::search($q, [], 'fr')), $q);
            }
            foreach (['heeresbergfuhrer', 'heeresbergfuehrer', 'strasse', 'straße', 'STRASSE'] as $q) {
                self::assertSame(['de-heer-gebjg-bergfuehrer'], $ids(JobCatalog::search($q, [], 'en')), $q);
            }
            self::assertSame(['de-lw-hsg64-pilot'], $ids(JobCatalog::search('luftfahrzeugfuhrer', [], 'en')), 'Nom officiel allemand cherché.');
            self::assertSame(['de-marine-ksm-kampfschwimmer'], $ids(JobCatalog::search('kampfschwimmer', ['country' => 'DE'], 'en')));
            self::assertSame([], $ids(JobCatalog::search('kampfschwimmer', ['country' => 'FR'], 'en')));
            self::assertSame('strasse', JobCatalog::normalize('Straße'));
            self::assertSame('fallschirmjager', JobCatalog::normalize('Fallschirmjäger'));
        } finally {
            foreach (['us_sof', 'fr_sof', 'de_sof'] as $file) {
                unlink($dir . '/' . $file . '.json');
            }
            rmdir($dir);
        }
    }

    public function testRussianCountryHasCyrillicOfficialNamesTransliterationAndCyrillicOrLatinSearch(): void
    {
        $dir = sys_get_temp_dir() . '/jobcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $branch, string $command, string $formation, string $type, array $name, string $unitFr, array $qualFr = []): array => [
            'id' => $id, 'branch' => $branch, 'command' => $command, 'formation' => $formation, 'type' => $type,
            'code' => '', 'code_system' => '', 'abbreviation' => '', 'icon' => 'crosshair', 'name' => $name,
            'unit' => ['fr' => $unitFr, 'en' => $formation . ' (unit)'], 'description' => ['fr' => 'Texte.', 'en' => 'Text.'],
            'qualifications' => ['fr' => $qualFr, 'en' => $qualFr], 'billets' => ['fr' => [], 'en' => []],
            'tags' => ['fr' => ['Forces spéciales'], 'en' => ['Special Forces']], 'sources' => [['url' => 'https://www.mil.ru/x', 'title' => 'x']],
        ];
        $pack = static fn (string $country, string $official, array $entries): string => (string) json_encode(['country' => $country, 'official_lang' => $official, 'label' => ['fr' => 'FS ' . $country, 'en' => $country . ' SOF'], 'compiled_at' => '2026-11-02', 'sources_note' => ['fr' => 'n', 'en' => 'n'], 'entries' => $entries]);
        file_put_contents($dir . '/us_sof.json', $pack('US', 'en', [$entry('us-army-18b', 'army', 'USASOC', '1st SFG', 'operator', ['fr' => 'Sergent armement', 'en' => 'Weapons Sergeant'], '1st SFG')]));
        file_put_contents($dir . '/fr_sof.json', $pack('FR', 'fr', [$entry('fr-terre-1rpima-ce', 'army', 'CFST', '1er RPIMa', 'command', ['fr' => 'Chef d’équipe', 'en' => 'Team Leader'], '1er RPIMa')]));
        file_put_contents($dir . '/de_sof.json', $pack('DE', 'de', [$entry('de-marine-ksm-kampfschwimmer', 'navy', 'EinsFltl 1', 'KSM', 'operator', ['de' => 'Kampfschwimmer', 'fr' => 'Nageur de combat', 'en' => 'Combat Swimmer'], 'KSM')]));
        file_put_contents($dir . '/ru_sof.json', $pack('RU', 'ru', [
            $entry('ru-gu-2obrspn-snaiper', 'army', 'GU GSh', '2 OBrSpN', 'operator', ['ru' => 'Разведчик-снайпер', 'ru-Latn' => 'Razvedchik-snayper', 'fr' => 'Éclaireur tireur d’élite', 'en' => 'Reconnaissance sniper'], '2e brigade (2-я отдельная бригада специального назначения)', ['Parachutisme (парашютно-десантная подготовка)']),
            $entry('ru-vdv-45obrspn-pulemetchik', 'army', 'VDV', '45 OBrSpN VDV', 'operator', ['ru' => 'Разведчик-пулемётчик', 'ru-Latn' => 'Razvedchik-pulemetchik', 'fr' => 'Éclaireur mitrailleur', 'en' => 'Reconnaissance machine gunner'], '45e brigade spetsnaz des VDV (45-я гвардейская бригада)'),
            $entry('ru-mp-155obrmp-strelok', 'marine_corps', 'Tikhookeansky flot', '155 OBrMP', 'operator', ['ru' => 'Стрелок', 'ru-Latn' => 'Strelok', 'fr' => 'Fusilier marin', 'en' => 'Naval infantry rifleman'], '155e brigade (155-я отдельная бригада морской пехоты)'),
            $entry('ru-fsb-alfa-sotrudnik', 'gendarmerie', 'FSB', 'TsSN FSB Alfa', 'operator', ['ru' => 'Сотрудник управления «А»', 'ru-Latn' => 'Sotrudnik upravleniya «A»', 'fr' => 'Opérateur de la direction A', 'en' => 'Directorate A operator'], 'Direction A du TsSN (Управление «А» ЦСН ФСБ)'),
        ]));
        JobCatalog::useDirectory($dir);
        try {
            $ids = static fn (array $hits): array => array_column($hits, 'id');
            // Compteurs : quatre pays, dans l’ordre de COUNTRIES.
            self::assertSame(['US', 'FR', 'DE', 'RU'], array_column(JobCatalog::countries('fr'), 'code'));
            self::assertSame([1, 1, 1, 4], array_column(JobCatalog::countries('fr'), 'count'));
            self::assertSame('Russie', JobCatalog::countries('fr')[3]['label']);
            self::assertSame('Russia', JobCatalog::countries('en')[3]['label']);
            self::assertSame('Russie', JobCatalog::facets('fr')['country']['RU']);
            // Armées russes : libellés propres au pays ; la sécurité intérieure est classée à part.
            $ru = JobCatalog::facets('fr', ['country' => 'RU']);
            self::assertSame(['army', 'marine_corps', 'gendarmerie'], array_keys($ru['branch']));
            self::assertSame('Sécurité intérieure (Rosgvardia, FSB), hors armées', $ru['branch']['gendarmerie']);
            self::assertSame('Naval Infantry (Morskaya pekhota)', JobCatalog::find('ru-mp-155obrmp-strelok', 'en')['branch_label']);
            self::assertSame(['FSB', 'GU GSh', 'Tikhookeansky flot', 'VDV'], array_keys($ru['command']));
            // Nom officiel en cyrillique en titre, translittération juste après, puis la langue du lecteur et l’anglais.
            $fr = JobCatalog::find('ru-gu-2obrspn-snaiper', 'fr');
            self::assertSame('Разведчик-снайпер', $fr['official_name']);
            self::assertSame('Éclaireur tireur d’élite', $fr['name']);
            self::assertSame(['ru', 'ru-Latn', 'fr', 'en'], array_keys($fr['names']));
            self::assertSame(['ru', 'ru-Latn', 'en', 'fr'], array_keys(JobCatalog::find('ru-gu-2obrspn-snaiper', 'en')['names']));
            self::assertSame('Razvedchik-snayper', $fr['names']['ru-Latn']);
            // Recherche : cyrillique (casse, ё/е), translittération, français et anglais, quel que soit le lecteur.
            foreach (['снайпер', 'СНАЙПЕР', 'Разведчик-снайпер', 'snayper', 'razvedchik snayper', 'tireur d elite', 'sniper'] as $q) {
                foreach (['fr', 'en'] as $reader) {
                    self::assertSame(['ru-gu-2obrspn-snaiper'], $ids(JobCatalog::search($q, [], $reader)), $q . ' / ' . $reader);
                }
            }
            foreach (['пулемётчик', 'пулеметчик', 'pulemetchik', 'machine gunner'] as $q) {
                self::assertSame(['ru-vdv-45obrspn-pulemetchik'], $ids(JobCatalog::search($q, [], 'fr')), $q);
            }
            // Unité et brevets en cyrillique trouvés aussi en latin (translittération automatique).
            self::assertSame(['ru-mp-155obrmp-strelok'], $ids(JobCatalog::search('morskoy pekhoty', [], 'fr')));
            self::assertSame(['ru-mp-155obrmp-strelok'], $ids(JobCatalog::search('морской пехоты', [], 'en')));
            self::assertSame(['ru-gu-2obrspn-snaiper'], $ids(JobCatalog::search('parashyutno', [], 'en')));
            self::assertSame(['ru-fsb-alfa-sotrudnik'], $ids(JobCatalog::search('alfa', ['country' => 'RU'], 'fr')));
            self::assertSame(['ru-gu-2obrspn-snaiper', 'ru-vdv-45obrspn-pulemetchik'], $ids(JobCatalog::search('razvedchik', ['country' => 'RU', 'branch' => 'army'], 'fr')));
            self::assertSame([], $ids(JobCatalog::search('снайпер', ['country' => 'DE'], 'fr')));
            self::assertSame('spetsnaz shchit', JobCatalog::normalize(JobCatalog::transliterate('Спецназ ЩИТ')));
            self::assertSame('снаипер', JobCatalog::normalize('Снайпер'), 'Cyrillique gardé, sans signe diacritique.');
        } finally {
            foreach (['us_sof', 'fr_sof', 'de_sof', 'ru_sof'] as $file) {
                unlink($dir . '/' . $file . '.json');
            }
            rmdir($dir);
        }
    }

    public function testCatalogueEndpointAnswersJsonForTheWorkspaceOnly(): void
    {
        $_SESSION = ['tenant_id' => 14, 'user_id' => 7];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['q' => 'pararescue'];
        Gate::getInstance()->setPermissions([]);
        $denied = (new JobCatalogController())->catalogue(new Request());
        self::assertSame(403, $denied->statusCode());
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        $body = json_decode((new JobCatalogController())->catalogue(new Request())->body(), true);
        self::assertTrue($body['ok']);
        self::assertNotEmpty($body['entries']);
        self::assertSame('air_force', $body['entries'][0]['branch']);
        self::assertArrayHasKey('branch', $body['facets']);
        self::assertSame(['US', 'FR', 'DE', 'CN', 'RU', 'GB', 'MY', 'NATO'], array_column($body['countries'], 'code'));
        $_GET = ['q' => 'nageur de combat', 'country' => 'FR'];
        $body = json_decode((new JobCatalogController())->catalogue(new Request())->body(), true);
        self::assertNotEmpty($body['entries']);
        self::assertSame(['FR'], array_values(array_unique(array_column($body['entries'], 'country'))));
    }

    public function testCriteriaAreNormalizedAndMeasuredFromRealSources(): void
    {
        $items = JobPrerequisiteService::normalize([
            ['type' => 'metric:flight_hours', 'min' => '10', 'scope' => 'vclass:RHS_UH60M'],
            ['type' => 'metric:flight_hours', 'min' => '10', 'scope' => 'vclass:RHS_UH60M'],
            ['type' => 'metric:playtime_hours', 'min' => '40,5', 'scope' => ''],
            ['type' => 'metric:playtime_hours', 'min' => '20', 'scope' => 'server'],
            ['type' => 'metric:flight_hours', 'min' => '5', 'scope' => 'kind:plane'],
            ['type' => 'doctrine', 'document_id' => 30, 'mode' => 'ack'],
            ['type' => 'doctrine', 'document_id' => 40, 'mode' => 'ack'],
            ['type' => 'doctrine', 'document_id' => 30, 'mode' => 'read'],
            ['type' => 'qualification', 'definition_id' => 8],
            ['type' => 'qualification', 'definition_id' => 10],
            ['type' => 'competency', 'requirement_id' => 70],
            ['type' => 'competency', 'requirement_id' => 99],
            ['type' => 'metric', 'metric' => 'inconnue', 'min' => 3],
            ['type' => 'metric:playtime_hours', 'min' => 0],
            ['type' => 'doctrine', 'document_id' => 0],
        ]);
        // Doublon, mesure inconnue, seuil nul et référence vide écartés ; doctrine 30 lue et 30 signée sont deux critères.
        self::assertCount(11, $items);
        $service = new JobPrerequisiteService($this->pdo);
        self::assertTrue($service->schemaReady());
        $eval = $service->evaluate(14, 5, $items);
        $met = array_map(static fn (array $r): ?bool => $r['met'], $eval['items']);
        self::assertSame([true, true, false, false, false, true, true, true, false, true, null], $met);
        self::assertSame(15.0, $eval['items'][0]['current'], 'Heures UH-60 de la communauté seulement.');
        self::assertSame(10.0, $eval['items'][2]['current']);
        self::assertSame(0.5, round($eval['items'][2]['progress'], 1));
        self::assertSame(['met' => 6, 'total' => 10, 'complete' => false], ['met' => $eval['met'], 'total' => $eval['total'], 'complete' => $eval['complete']]);
        self::assertSame(['label' => 'Heures de vol', 'value' => '10', 'unit' => 'h', 'target' => 'UH-60M', 'content' => true], JobPrerequisiteService::describe($items[0], ['aircraft' => [['id' => 'vclass:RHS_UH60M', 'label' => 'UH-60M']]]));
        self::assertSame('Hélicoptères', JobPrerequisiteService::scopeLabel('flight_hours', 'kind:heli'));
        self::assertSame(['doctrines' => [], 'qualifications' => [], 'competencies' => [], 'aircraft' => [['id' => 'vclass:RHS_UH60M', 'label' => 'UH-60M'], ['id' => 'vclass:RHS_C130J', 'label' => 'C-130J']]], $service->options(14));
    }

    public function testAssignmentGateAlwaysWarnsAndRecordsAnOptionalRevocableAuditedWaiver(): void
    {
        $service = new JobPrerequisiteService($this->pdo);
        $service->saveRequirements(14, 1, JobPrerequisiteService::normalize([
            ['type' => 'metric:flight_hours', 'min' => 100, 'scope' => 'kind:heli'],
            ['type' => 'qualification', 'definition_id' => 8],
        ]), 7);
        self::assertCount(2, $service->requirements(14, 1));
        self::assertSame([], $service->requirements(15, 1), 'Une autre communauté ne voit pas ces prérequis.');

        // Emploi sans prérequis : rien à contrôler.
        self::assertTrue($service->gate(14, 5, [2], '', true, true, 7)['allowed']);
        // Indicatif : avertissement, pas de dérogation.
        $warn = $service->gate(14, 5, [1], '', true, false, 7);
        self::assertTrue($warn['allowed']);
        self::assertSame([1], $warn['warned']);
        self::assertCount(1, $warn['unmet'][1]);
        // Même un ancien réglage bloquant ne refuse jamais une affectation.
        self::assertTrue($service->gate(14, 5, [1], '', true, true, 7)['allowed']);
        self::assertTrue($service->gate(14, 5, [1], 'Ancien pilote confirmé', false, true, 7)['allowed']);
        try {
            $service->gate(14, 5, [1], 'abc', true, true, 7);
            self::fail('Motif trop court accepté.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
        // Dérogation motivée : enregistrée, puis l’emploi passe sans nouveau motif.
        $waived = $service->gate(14, 5, [1], '  Ancien pilote   confirmé ', true, true, 7);
        self::assertTrue($waived['allowed']);
        self::assertSame([1], $waived['waived']);
        $active = $service->activeWaiversForUser(14, 5);
        self::assertSame('Ancien pilote confirmé', $active[1]['reason']);
        self::assertStringContainsString('flight_hours', (string) $active[1]['missing_json']);
        self::assertTrue($service->gate(14, 5, [1], '', false, true, 7)['allowed']);
        self::assertSame([], $service->activeWaiversForUser(15, 5));

        $row = $service->revokeWaiver(14, (int) $active[1]['id'], 7, 'Heures non confirmées');
        self::assertSame(1, (int) $row['job_role_id']);
        self::assertNull($service->revokeWaiver(14, (int) $active[1]['id'], 7, ''), 'Déjà révoquée.');
        self::assertNull($service->revokeWaiver(15, (int) $active[1]['id'], 7, ''), 'Autre communauté.');
        self::assertSame([], $service->activeWaiversForUser(14, 5));
        self::assertTrue($service->gate(14, 5, [1], '', true, true, 7)['allowed']);
        self::assertSame('Heures non confirmées', $service->waiversForRole(14, 1)[0]['revoke_reason']);

        $actions = $this->pdo->query("SELECT action FROM audit_logs WHERE entity_type = 'personnel_job_role' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['personnel.job_role.prerequisites', 'personnel.job_role.waiver_granted', 'personnel.job_role.waiver_revoked'], $actions);
        self::assertStringContainsString('Viper (#5)', (string) $this->pdo->query("SELECT new_value FROM audit_logs WHERE action LIKE '%waiver_granted'")->fetchColumn());

        $settings = PersonnelJobRoleAssignmentsSettings::resolve([]);
        self::assertFalse($settings['prerequisites_blocking'], 'Indicatifs par défaut.');
        self::assertFalse(PersonnelJobRoleAssignmentsSettings::resolve(PersonnelJobRoleAssignmentsSettings::mergePatch([], ['prerequisites_blocking' => true]))['prerequisites_blocking']);
    }

    public function testWithoutTablesNothingIsCheckedAndAssignmentsStayAsBefore(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $service = new JobPrerequisiteService($pdo);
        self::assertFalse($service->schemaReady());
        self::assertSame(['allowed' => true, 'unmet' => [], 'waived' => [], 'warned' => []], $service->gate(14, 5, [1], '', false, true, 7));
        self::assertSame([], $service->requirementsByRole(14));
    }

    public function testMigrationStepCreatesBothTablesOnceAndNeverTouchesExistingJobs(): void
    {
        $root = dirname(__DIR__, 2);
        $run = static function (PDO $pdo) use ($root): string {
            $migrationFlush = static function (): void {
            };
            $migrationEnsurePdo = static function (): void {
            };
            require $root . '/database/steps/_context.php';
            ob_start();
            require $root . '/database/steps/3440_personnel_job_role_prerequisites.php';

            return (string) ob_get_clean();
        };
        $empty = new MysqlDdlSqlitePdo();
        self::assertStringContainsString('rien à faire', $run($empty));
        self::assertSame(0, $empty->changes);

        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO tenants (id) VALUES (1)");
        $pdo->exec("INSERT INTO personnel_job_roles (id, tenant_id, name) VALUES (1, 1, 'Médecin'), (2, 1, 'JTAC')");
        $before = $pdo->query('SELECT * FROM personnel_job_roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $pdo->changes = 0;
        $first = $run($pdo);
        self::assertStringNotContainsString('[ERREUR]', $first);
        $schema = new SchemaInspector($pdo);
        self::assertTrue($schema->tableExists('personnel_job_role_requirements'));
        self::assertTrue($schema->tableExists('personnel_job_role_waivers'));
        $pdo->changes = 0;
        $pdo->ddl = [];
        self::assertStringContainsString('déjà présentes', $run($pdo));
        self::assertSame(0, $pdo->changes, implode(' | ', $pdo->ddl));
        self::assertSame($before, $pdo->query('SELECT * FROM personnel_job_roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        $pdo->exec("INSERT INTO personnel_job_role_requirements (tenant_id, job_role_id, criteria_json) VALUES (1, 1, '[]')");
        $this->expectException(\PDOException::class);
        $pdo->exec("INSERT INTO personnel_job_role_requirements (tenant_id, job_role_id, criteria_json) VALUES (1, 1, '[]')");
    }

    public function testConstraintNamesAreUniqueAcrossTheSchema(): void
    {
        $root = dirname(__DIR__, 2);
        $sql = (string) file_get_contents($root . '/migrations/2026_11_02_020_personnel_job_role_prerequisites.sql');
        preg_match_all('/(?:CONSTRAINT|KEY)\s+`([a-z0-9_]+)`/i', $sql, $m);
        $mine = $m[1];
        self::assertSame(count($mine), count(array_unique($mine)));
        $others = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/migrations', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = (string) $file;
            if (str_ends_with($path, '.sql') && !str_ends_with(str_replace('\\', '/', $path), '2026_11_02_020_personnel_job_role_prerequisites.sql')) {
                $others .= (string) file_get_contents($path);
            }
        }
        foreach ($mine as $name) {
            self::assertStringNotContainsString('`' . $name . '`', $others, $name . ' déjà utilisé ailleurs.');
        }
    }
}
