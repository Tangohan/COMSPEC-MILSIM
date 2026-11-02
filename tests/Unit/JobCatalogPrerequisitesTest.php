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
 * Catalogue d’emplois de référence (forces spéciales américaines, françaises et allemandes) et prérequis d’un emploi : doctrines, heures de
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
            self::assertGreaterThanOrEqual(80, count($entries), $name);
            self::assertLessThanOrEqual(150, count($entries), $name);
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
        self::assertSame(['US', 'FR', 'DE'], array_column($body['countries'], 'code'));
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

    public function testAssignmentGateWarnsBlocksAndRecordsARevocableAuditedWaiver(): void
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
        // Bloquant sans motif, ou motif sans droit de déroger : refus.
        self::assertFalse($service->gate(14, 5, [1], '', true, true, 7)['allowed']);
        self::assertFalse($service->gate(14, 5, [1], 'Ancien pilote confirmé', false, true, 7)['allowed']);
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
        self::assertFalse($service->gate(14, 5, [1], '', true, true, 7)['allowed']);
        self::assertSame('Heures non confirmées', $service->waiversForRole(14, 1)[0]['revoke_reason']);

        $actions = $this->pdo->query("SELECT action FROM audit_logs WHERE entity_type = 'personnel_job_role' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['personnel.job_role.prerequisites', 'personnel.job_role.waiver_granted', 'personnel.job_role.waiver_revoked'], $actions);
        self::assertStringContainsString('Viper (#5)', (string) $this->pdo->query("SELECT new_value FROM audit_logs WHERE action LIKE '%waiver_granted'")->fetchColumn());

        $settings = PersonnelJobRoleAssignmentsSettings::resolve([]);
        self::assertFalse($settings['prerequisites_blocking'], 'Indicatifs par défaut.');
        self::assertTrue(PersonnelJobRoleAssignmentsSettings::resolve(PersonnelJobRoleAssignmentsSettings::mergePatch([], ['prerequisites_blocking' => true]))['prerequisites_blocking']);
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
