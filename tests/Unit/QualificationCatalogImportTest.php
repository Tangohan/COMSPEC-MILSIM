<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\JobCatalogQualificationLinker;
use App\Services\Personnel\JobPrerequisiteService;
use App\Services\Personnel\QualificationCatalogImporter;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\JobCatalog\JobCatalog;
use App\Support\QualificationCatalog\QualificationCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Catalogue des qualifications de référence (un pack par pays et l’OTAN) : packs livrés valides et sourcés, liens
 * depuis les emplois du catalogue d’emplois, import dans le référentiel d’une communauté au choix et sans doublon
 * avec l’émetteur réel (créé si absent) et la mention « qualification de jeu », prérequis ajoutés à un emploi.
 */
final class QualificationCatalogImportTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Pack => [pays, préfixe d’id, préfixe de code, langue officielle]. */
    private const PACKS = [
        'us' => ['US', 'us-', 'US-', 'en'],
        'fr' => ['FR', 'fr-', 'FR-', 'fr'],
        'de' => ['DE', 'de-', 'DE-', 'de'],
        'cn' => ['CN', 'cn-', 'CN-', 'zh'],
        'ru' => ['RU', 'ru-', 'RU-', 'ru'],
        'my' => ['MY', 'my-', 'MY-', 'ms'],
        'nato' => ['NATO', 'nato-', 'OTAN-', 'en'],
    ];

    protected function tearDown(): void
    {
        QualificationCatalog::useDirectory(null);
        JobCatalog::useDirectory(null);
    }

    public function testShippedCatalogsAreValidUniqueSourcedAndBilingualPerCountry(): void
    {
        self::assertSame(QualificationCatalog::PACKS, array_keys(QualificationCatalog::packs()), 'chaque pack inscrit est livré et lisible');
        self::assertSame(['US', 'FR', 'DE', 'CN', 'RU', 'MY', 'NATO'], array_keys(QualificationCatalog::COUNTRIES));
        $allIds = [];
        $allCodes = [];
        foreach (self::PACKS as $name => [$country, $idPrefix, $codePrefix, $official]) {
            $pack = QualificationCatalog::pack($name);
            self::assertIsArray($pack, $name);
            self::assertSame([$country, $official], [$pack['country'], $pack['official_lang']], $name);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $pack['compiled_at']);
            self::assertNotSame('', trim((string) $pack['issuer']['name']));
            foreach (['fr', 'en'] as $l) {
                self::assertNotSame('', trim((string) $pack['sources_note'][$l]));
                self::assertNotSame('', trim((string) $pack['label'][$l]));
            }
            self::assertStringContainsString('sans valeur réelle', (string) $pack['disclaimer']['fr'], $name);
            self::assertStringContainsString('no real-world value', (string) $pack['disclaimer']['en'], $name);
            $entries = QualificationCatalog::rawEntries($name);
            self::assertGreaterThanOrEqual(15, count($entries), $name);
            self::assertLessThanOrEqual(400, count($entries), $name);
            $problems = [];
            foreach ($entries as $e) {
                foreach (QualificationCatalog::problems($e) as $p) {
                    $problems[] = $e['id'] . ' : ' . $p;
                }
                self::assertStringStartsWith($idPrefix, (string) $e['id']);
                self::assertStringStartsWith($codePrefix, (string) $e['code']);
                if ($name !== 'nato') {
                    self::assertNotSame('', trim((string) ($e['official_name'] ?? '')), $e['id'] . ' : intitulé officiel dans la langue du pays');
                }
                $allIds[] = $e['id'];
                $allCodes[] = $e['code'];
            }
            self::assertSame([], $problems, $name);
        }
        self::assertSame(count($allIds), count(array_unique($allIds)), 'identifiant en double');
        self::assertSame(count($allCodes), count(array_unique($allCodes)), 'code en double');
        self::assertStringContainsString('STANAG 3797', (string) json_encode(QualificationCatalog::rawEntries('nato'), JSON_UNESCAPED_UNICODE), 'JTAC selon le STANAG 3797');
        // Servi dans la langue du lecteur.
        $first = QualificationCatalog::rawEntries('nato')[0];
        self::assertSame(trim($first['name']['en']), QualificationCatalog::entries('en', 'nato')[0]['name']);
        self::assertSame(trim($first['name']['fr']), QualificationCatalog::entries('fr-FR', 'nato')[0]['name']);
        self::assertSame('OTAN', QualificationCatalog::entries('fr', 'nato')[0]['country_label']);
    }

    public function testJobPacksLinkTheirCitedBadgesToExistingCatalogEntries(): void
    {
        $linkedJobs = [];
        foreach (JobCatalog::rawEntries() as $job) {
            $refs = JobCatalog::qualificationRefs($job);
            foreach ($refs as $ref) {
                self::assertNotNull(QualificationCatalog::findRaw($ref), $job['id'] . ' : « ' . $ref . ' » absent du catalogue de qualifications');
            }
            if ($refs !== []) {
                $linkedJobs[$job['country']] = ($linkedJobs[$job['country']] ?? 0) + 1;
            }
        }
        foreach (['US', 'FR', 'DE', 'CN', 'RU', 'MY', 'NATO'] as $country) {
            self::assertGreaterThan(0, $linkedJobs[$country] ?? 0, 'emplois ' . $country . ' reliés à leurs brevets');
        }
        // La fiche d’un emploi relié montre ses qualifications dans la langue du lecteur.
        foreach (JobCatalog::entries('en') as $entry) {
            if ($entry['qualification_refs'] !== []) {
                self::assertCount(count($entry['qualification_refs']), $entry['linked_qualifications']);
                self::assertNotSame('', $entry['linked_qualifications'][0]['name']);
                self::assertNotSame('', $entry['linked_qualifications'][0]['issuer']);
                break;
            }
        }
        self::assertSame(['qualification_refs invalides'], array_values(array_filter(JobCatalog::problems(['qualification_refs' => ['Pas un id']]), static fn (string $p): bool => str_contains($p, 'qualification_refs'))));
    }

    private function useFixture(): void
    {
        $dir = sys_get_temp_dir() . '/athena-qualcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $code, string $cat, string $fr, string $en, array $extra = []): array => $extra + [
            'id' => $id, 'code' => $code, 'category' => $cat, 'name' => ['fr' => $fr, 'en' => $en], 'short_name' => 'X',
            'description' => ['fr' => 'Description française.', 'en' => 'English description.'],
            'body' => ['fr' => 'JAPCC', 'en' => 'JAPCC'], 'reference' => ['fr' => 'STANAG 3797', 'en' => 'STANAG 3797'],
            'prerequisites' => ['fr' => ['Pré-requis'], 'en' => ['Prerequisite']], 'validity' => ['fr' => '', 'en' => ''],
            'validity_months' => null, 'sources' => [['url' => 'https://www.japcc.org/', 'title' => 'JAPCC']],
        ];
        $pack = static fn (string $name, string $country, string $issuer, string $disclaimerFr, array $entries): array => [
            'pack' => $name, 'country' => $country, 'official_lang' => $name === 'fr' ? 'fr' : 'en', 'label' => ['fr' => $name, 'en' => $name], 'compiled_at' => '2026-11-02',
            'disclaimer' => ['fr' => $disclaimerFr, 'en' => 'In-game qualification, with no real-world value.'],
            'issuer' => ['name' => $issuer, 'short_name' => $country, 'description' => ['fr' => 'Émetteur de référence.', 'en' => 'Reference issuer.']],
            'sources_note' => ['fr' => 'Sources publiques.', 'en' => 'Public sources.'],
            'entries' => $entries,
        ];
        file_put_contents($dir . '/nato.json', json_encode($pack('nato', 'NATO', 'OTAN (référence)', 'Qualification de jeu inspirée du standard OTAN, sans valeur réelle.', [
            $entry('nato-jtac', 'OTAN-JTAC', 'air_support', 'JTAC qualifié', 'Qualified JTAC'),
            $entry('nato-jfo', 'OTAN-JFO', 'fires', 'Observateur JFO', 'Joint Fires Observer'),
            $entry('nato-slp', 'OTAN-SLP', 'c2_staff', 'Niveau de langue', 'Language proficiency'),
        ]), JSON_UNESCAPED_UNICODE));
        file_put_contents($dir . '/fr.json', json_encode($pack('fr', 'FR', 'Armées françaises (référence)', 'Qualification de jeu inspirée d’un brevet militaire français réel, sans valeur réelle.', [
            $entry('fr-bmp', 'FR-BMP', 'airborne', 'Brevet militaire de parachutiste', 'Military parachutist badge', [
                'official_name' => 'Brevet militaire de parachutiste', 'issuer' => ['name' => 'École des troupes aéroportées', 'short_name' => 'ETAP'],
            ]),
        ]), JSON_UNESCAPED_UNICODE));
        QualificationCatalog::useDirectory($dir);
    }

    private static function base(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, default_validity_months INTEGER, validity_days INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER)',
            "INSERT INTO personnel_qualification_definitions (id, tenant_id, code, name) VALUES (40, 7, 'otan-jfo', 'JFO déjà saisi'), (41, 8, 'OTAN-JTAC', 'Autre communauté')",
        ] as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;
    }

    public function testImportCreatesChosenQualificationsOnceWithTheirRealIssuerAndTheGameMention(): void
    {
        $this->useFixture();
        $pdo = self::base();
        $importer = new QualificationCatalogImporter(new QualificationReferenceWriter($pdo));

        $first = $importer->import(7, ['nato-jtac', 'nato-jfo', 'inconnu'], 'fr', 3);
        self::assertSame(['JTAC qualifié'], $first['created']);
        self::assertSame(['Observateur JFO'], $first['skipped'], 'code déjà présent (casse ignorée) : pas de doublon');
        self::assertSame(40, $first['definitions']['nato-jfo'], 'fiche existante retrouvée');
        self::assertSame(1, $first['unknown']);
        $def = $pdo->query("SELECT * FROM personnel_qualification_definitions WHERE tenant_id = 7 AND code = 'OTAN-JTAC'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame((int) $def['id'], $first['definitions']['nato-jtac']);
        self::assertSame('JTAC qualifié', $def['name']);
        self::assertStringContainsString('Qualification de jeu inspirée du standard OTAN, sans valeur réelle.', (string) $def['description']);
        self::assertStringContainsString('Référence publique : STANAG 3797', (string) $def['description']);
        self::assertStringContainsString('https://www.japcc.org/', (string) $def['description']);
        self::assertSame(1, (int) $def['is_permanent']);
        $issuer = $pdo->query('SELECT * FROM qualification_issuers WHERE id = ' . (int) $def['default_issuer_id'])->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(['OTAN (référence)', 'NATO', 'external', 7], [$issuer['name'], $issuer['short_name'], $issuer['issuer_kind'], (int) $issuer['tenant_id']]);
        self::assertSame([(int) $issuer['id']], $first['issuer_ids']);
        self::assertSame('OTAN — Appui aérien', $pdo->query('SELECT name FROM qualification_categories WHERE id = ' . (int) $def['category_id'])->fetchColumn());

        // Brevet national : émetteur réel (l’école), intitulé officiel et mention du pays.
        $fr = $importer->import(7, ['fr-bmp', 'nato-slp'], 'fr', 3);
        self::assertSame(['Brevet militaire de parachutiste', 'Niveau de langue'], $fr['created']);
        $bmp = $pdo->query("SELECT * FROM personnel_qualification_definitions WHERE tenant_id = 7 AND code = 'FR-BMP'")->fetch(\PDO::FETCH_ASSOC);
        self::assertStringContainsString('Qualification de jeu inspirée d’un brevet militaire français réel, sans valeur réelle.', (string) $bmp['description']);
        self::assertSame(['École des troupes aéroportées', 'ETAP', 'school'], array_values($pdo->query('SELECT name, short_name, issuer_kind FROM qualification_issuers WHERE id = ' . (int) $bmp['default_issuer_id'])->fetch(\PDO::FETCH_ASSOC)));
        self::assertSame('France — Parachutisme et mise à terre', $pdo->query('SELECT name FROM qualification_categories WHERE id = ' . (int) $bmp['category_id'])->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM qualification_issuers WHERE tenant_id = 7 AND name = 'OTAN (référence)'")->fetchColumn(), 'émetteur réutilisé');

        // Relance : rien en double.
        $again = $importer->import(7, ['nato-jtac', 'nato-jfo', 'nato-slp', 'fr-bmp'], 'fr', 3);
        self::assertSame([], $again['created']);
        self::assertCount(4, $again['skipped']);
        $codes = $importer->importedCodes(7);
        ksort($codes);
        self::assertSame(['FR-BMP' => true, 'OTAN-JFO' => true, 'OTAN-JTAC' => true, 'OTAN-SLP' => true], $codes);

        // Autre communauté, anglophone : sa propre fiche et son propre émetteur, en anglais.
        $en = $importer->import(9, ['nato-jfo'], 'en', null);
        self::assertSame(['Joint Fires Observer'], $en['created']);
        $desc = (string) $pdo->query('SELECT description FROM personnel_qualification_definitions WHERE tenant_id = 9')->fetchColumn();
        self::assertStringContainsString('In-game qualification, with no real-world value.', $desc);
        self::assertSame('NATO — Fire support', $pdo->query('SELECT name FROM qualification_categories WHERE tenant_id = 9')->fetchColumn());
        self::assertSame('Autre communauté', $pdo->query('SELECT name FROM personnel_qualification_definitions WHERE id = 41')->fetchColumn(), 'fiche existante jamais modifiée');
    }

    public function testCopyAllWithLinkedQualificationsAddsThemAsJobPrerequisitesWithoutDuplicates(): void
    {
        $this->useFixture();
        $pdo = self::base();
        $pdo->exec('CREATE TABLE personnel_job_role_requirements (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, job_role_id INTEGER, criteria_json TEXT, updated_by INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE personnel_job_role_waivers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER)');
        $pdo->exec("INSERT INTO personnel_job_role_requirements (tenant_id, job_role_id, criteria_json) VALUES (7, 5, '[{\"type\":\"metric\",\"metric\":\"playtime_hours\",\"scope\":\"\",\"min\":10}]')");
        $prerequisites = new JobPrerequisiteService($pdo);
        $linker = new JobCatalogQualificationLinker(new QualificationCatalogImporter(new QualificationReferenceWriter($pdo)), $prerequisites);

        $result = $linker->apply(7, 5, ['nato-jtac', 'nato-jfo', 'Pas un id'], 'fr', 3);
        self::assertSame(['imported' => 1, 'existing' => 1, 'added' => 2], $result);
        $items = $prerequisites->requirements(7, 5);
        self::assertSame('metric', $items[0]['type'], 'prérequis existant conservé');
        $jtac = (int) $pdo->query("SELECT id FROM personnel_qualification_definitions WHERE tenant_id = 7 AND code = 'OTAN-JTAC'")->fetchColumn();
        self::assertSame([['type' => 'qualification', 'definition_id' => $jtac], ['type' => 'qualification', 'definition_id' => 40]], array_slice($items, 1));
        self::assertSame(['imported' => 0, 'existing' => 2, 'added' => 0], $linker->apply(7, 5, ['nato-jtac', 'nato-jfo'], 'fr', 3), 'relance : rien en double');
        self::assertCount(3, $prerequisites->requirements(7, 5));
    }

    public function testCatalogPageAndImportAreRoutedAndTheQualificationsPageLinksToThem(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("'/back-office/referentiels/qualifications/catalogue', [QualificationReferentielController::class, 'catalog']", $routes);
        self::assertStringContainsString("'/back-office/referentiels/qualifications/catalogue/importer', [QualificationReferentielController::class, 'importCatalog']", $routes);
        self::assertLessThan(strpos($routes, "qualifications/{id}', [QualificationRulesController::class, 'show']"), strpos($routes, 'qualifications/catalogue'), 'avant la fiche {id}');
        $index = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/index.php');
        self::assertStringContainsString('Importer depuis le catalogue', $index);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/catalog.php');
        self::assertStringContainsString('class="jobcat__flag" data-flag=', $view, 'onglets pays avec drapeau ou emblème');
        $modal = (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/partials/job_catalog_modal.php');
        self::assertStringContainsString('data-job-catalog-link-quals', $modal);
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/job-catalog.js');
        self::assertStringContainsString("catalog_qualification_refs[]", $js);
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString('catalog_qualifications_prereq', $controller);
    }
}
