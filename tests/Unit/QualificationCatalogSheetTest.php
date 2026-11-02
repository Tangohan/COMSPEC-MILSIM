<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\JobCatalogController;
use App\Controllers\Admin\Organization\QualificationReferentielController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\JobCatalogCollectionRepository;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Support\JobCatalog\CommunityJobCatalog;
use App\Support\JobCatalog\JobCatalog;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationCatalogExplorer as QX;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Brevets, qualifications et formations cliquables du catalogue d’emplois, et catalogue de référence du référentiel
 * des qualifications : prérequis reliés, niveau estimé, emplois qui exigent une qualification, cours Academy,
 * fiche détaillée (JSON) avec l’état « déjà importée », import sans doublon depuis la fiche, filtres de la page,
 * qualifications des catalogues de communauté reliées au référentiel (étape 3550, droits, isolation).
 */
final class QualificationCatalogSheetTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;
    private mixed $previousPdo;
    private array $globals;
    private array $permissions;
    private mixed $sessionStarted;

    protected function setUp(): void
    {
        QualificationCatalog::useDirectory(null);
        JobCatalog::useDirectory(null);
        $this->sessionStarted = (new ReflectionProperty(Session::class, 'started'))->getValue();
        (new ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        $this->globals = [$_GET, $_POST, $_SERVER, $_SESSION ?? [], $_FILES];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new MysqlDdlSqlitePdo();
        foreach ([
            "CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, settings TEXT)",
            "INSERT INTO tenants (id, name, settings) VALUES (14, 'Task Force Phantom', '{}'), (15, 'Fondation 07', '{}')",
            'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, created_at TEXT)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, default_validity_months INTEGER, validity_days INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER)',
            "INSERT INTO personnel_qualification_definitions (id, tenant_id, code, name) VALUES (60, 14, 'GUARD-1', 'Garde de la Fondation'), (61, 15, 'AUTRE', 'Autre communauté')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        (new ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        Gate::getInstance()->setPermissions($this->permissions);
        [$_GET, $_POST, $_SERVER, $_SESSION, $_FILES] = $this->globals;
    }

    private static function ids(array $prerequisites): array
    {
        return array_merge(...array_map(static fn (array $p): array => $p['refs'], $prerequisites));
    }

    public function testPrerequisitesAreLinkedOnlyWhenTheCatalogEntryIsUnambiguous(): void
    {
        $sfqc = QX::prerequisites(QualificationCatalog::findRaw('us-sfqc'), 'fr');
        self::assertSame(['us-sfas', 'us-basic-airborne'], self::ids($sfqc), 'SFAS réussie ; Basic Airborne Course');
        self::assertSame('Sélection SFAS réussie', $sfqc[0]['text']);
        self::assertSame('Passed SFAS', QX::prerequisites(QualificationCatalog::findRaw('us-sfqc'), 'en')[0]['text'], 'langue du lecteur');
        // Le pack du pays passe avant l’OTAN : « JTAC qualifié » d’un instructeur américain est le JTAC américain.
        self::assertSame(['us-jtac'], self::ids(QX::prerequisites(QualificationCatalog::findRaw('us-jtac-instructor'), 'fr')));
        // Sigle partagé par deux entrées (« PFV » : KSK et KSM) : rien n’est relié plutôt qu’un lien faux.
        self::assertNotContains('de-ksk-pfv', self::ids(QX::prerequisites(QualificationCatalog::findRaw('de-kampfschwimmer'), 'fr')));
        self::assertNotContains('de-ksk-pfv', self::ids(QX::prerequisites(QualificationCatalog::findRaw('de-ksm-pfv'), 'fr')));
        // Chaque lien désigne une entrée existante, jamais l’entrée elle-même.
        foreach (QualificationCatalog::rawEntries() as $entry) {
            foreach (self::ids(QX::prerequisites($entry, 'fr')) as $ref) {
                self::assertNotNull(QualificationCatalog::findRaw($ref), $entry['id'] . ' → ' . $ref);
                self::assertNotSame($entry['id'], $ref);
            }
        }
    }

    public function testLevelIsEstimatedOnAFiveStepScaleUnlessThePackGivesIt(): void
    {
        $level = static fn (string $id): string => QX::level(QualificationCatalog::findRaw($id))['key'];
        self::assertSame('initial', $level('fr-brevet-parachutiste'));
        self::assertSame('qualified', $level('fr-jedburgh'));
        self::assertSame('confirmed', $level('us-jumpmaster'));
        self::assertSame('instructor', $level('us-jtac-instructor'));
        self::assertSame('instructor', $level('fr-moniteur-parachutiste'));
        self::assertSame('expert', $level('us-eic'), 'instructeur évaluateur');
        self::assertTrue(QX::level(QualificationCatalog::findRaw('us-eic'))['estimated']);
        $counts = array_count_values(array_map(static fn (array $e): string => QX::level($e)['key'], QualificationCatalog::rawEntries()));
        self::assertSame(array_keys(QX::LEVELS), array_values(array_intersect(array_keys(QX::LEVELS), array_keys($counts))), 'chaque niveau est représenté');
        // Niveau sourcé dans un pack : repris tel quel, sans la mention « estimé ».
        self::assertSame(['key' => 'expert', 'rank' => 5, 'estimated' => false], QX::level(['id' => 'xx-test', 'level' => 'expert', 'name' => ['en' => 'Course'], 'prerequisites' => ['fr' => []]]));
        self::assertSame('Advanced', QX::levelLabel('confirmed', 'en'));
    }

    public function testJobsAcademyCoursesAndDetailComeFromTheShippedPacks(): void
    {
        $jobs = QX::jobs('nato-jtac', 'fr');
        self::assertNotEmpty($jobs);
        foreach ($jobs as $job) {
            self::assertContains('nato-jtac', JobCatalog::qualificationRefs(array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['id'] === $job['id']))[0]));
        }
        foreach (QX::ACADEMY_COURSES as $id => $key) {
            self::assertNotNull(QualificationCatalog::findRaw($id), $id);
            self::assertNotNull(AcademyBuiltInCourses::find($key), $key);
        }
        self::assertSame(['key' => AcademyBuiltInCourses::JTAC_KEY, 'code' => 'ACAD-JTAC'], array_intersect_key(QX::academyCourse('us-jtac', 'fr'), ['key' => 1, 'code' => 1]));
        self::assertSame('ACAD-OTAN-RADIO', QX::academyCourse('nato-acp125', 'en')['code']);
        self::assertNull(QX::academyCourse('us-sfqc', 'fr'));

        $detail = QX::detail('us-sfqc', 'en');
        self::assertStringStartsWith('Special Forces Qualification Course (SFQC', $detail['names']['en']);
        self::assertArrayHasKey('fr', $detail['names']);
        self::assertSame('school', $detail['issuer_detail']['kind']);
        self::assertSame('us-sfas', $detail['prerequisite_links'][0]['links'][0]['id']);
        self::assertContains('us-sfqc', array_column(QX::detail('us-sfas', 'fr')['unlocks'], 'id'), 'SFAS ouvre l’accès au SFQC');
        self::assertNotEmpty($detail['jobs']);
        self::assertStringContainsString('no real-world value', $detail['disclaimer']);
        self::assertNull(QX::detail('inconnue', 'fr'));

        // Fiche emploi : le brevet cité en texte désigne sa qualification du catalogue.
        $job = JobCatalog::find('us-army-18a', 'fr');
        self::assertCount(count($job['qualifications']), $job['qualification_links']);
        self::assertContains('us-sfas', $job['qualification_links']);
        $jtacJob = JobCatalog::find(QX::jobIndex()['nato-jtac'][0], 'fr');
        $linked = array_values(array_filter($jtacJob['linked_qualifications'], static fn (array $q): bool => $q['id'] === 'nato-jtac'))[0];
        self::assertSame(AcademyBuiltInCourses::JTAC_KEY, $linked['academy']['key'], 'formation Academy proposée depuis la fiche emploi');
        self::assertNotSame('', $linked['level']);
    }

    public function testCataloguePageFiltersSortsAndSearchesEveryLanguage(): void
    {
        $rows = QX::rows('fr');
        self::assertCount(count(QualificationCatalog::rawEntries()), $rows);
        $f = QX::filters(['pays' => 'de', 'niveau' => 'instructor', 'categorie' => 'nope', 'tri' => 'hack', 'academy' => '1', 'etat' => 'x']);
        self::assertSame(['q' => '', 'pays' => 'DE', 'categorie' => '', 'niveau' => 'instructor', 'emetteur' => '', 'academy' => '1', 'etat' => '', 'tri' => 'pays'], $f);
        $de = QX::filter($rows, QX::filters(['pays' => 'DE']), []);
        self::assertSame(['DE'], array_values(array_unique(array_column($de, 'country'))));
        $academy = QX::filter($rows, QX::filters(['academy' => '1']), []);
        self::assertEqualsCanonicalizing(array_keys(QX::ACADEMY_COURSES), array_column($academy, 'id'));
        // Recherche dans l’autre langue et dans l’intitulé officiel (lecteur francophone).
        self::assertContains('us-sfqc', array_column(QX::filter($rows, QX::filters(['q' => 'Qualification Course']), []), 'id'));
        self::assertContains('de-einzelkaempfer', array_column(QX::filter($rows, QX::filters(['q' => 'einzelkaempfer']), []), 'id'), 'sans tréma');
        // Déjà importée / à importer.
        $imported = ['US-SFQC' => 12];
        self::assertSame(['us-sfqc'], array_column(QX::filter($rows, QX::filters(['etat' => 'importee']), $imported), 'id'));
        self::assertNotContains('us-sfqc', array_column(QX::filter($rows, QX::filters(['etat' => 'a-importer']), $imported), 'id'));
        // Tri par niveau : le plus élevé d’abord ; par emplois liés : le plus demandé d’abord.
        $byLevel = QX::filter($rows, QX::filters(['tri' => 'niveau']), []);
        self::assertSame(5, $byLevel[0]['level']['rank']);
        $byJobs = QX::filter($rows, QX::filters(['tri' => 'emplois']), []);
        self::assertSame(max(array_column($rows, 'jobs_count')), $byJobs[0]['jobs_count']);
    }

    private function referentiel(): QualificationReferentielController
    {
        return (new ReflectionClass(QualificationReferentielController::class))->newInstanceWithoutConstructor();
    }

    public function testSheetEndpointShowsImportStateAndImportsOnceWithItsRealIssuer(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = [];
        $_GET = ['id' => 'us-sfqc'];
        self::assertSame(401, $this->referentiel()->catalogEntry(new Request())->statusCode());
        $_SESSION = ['user_id' => 7, 'tenant_id' => 14, '_csrf_token' => str_repeat('a', 64)];
        $_GET = ['id' => 'pas-une-entree'];
        self::assertSame(404, $this->referentiel()->catalogEntry(new Request())->statusCode());
        $_GET = ['id' => 'us-jtac'];
        $json = json_decode($this->referentiel()->catalogEntry(new Request())->body(), true);
        self::assertTrue($json['ok']);
        self::assertTrue($json['can_import']);
        self::assertNull($json['qualification']['imported']);
        self::assertStringContainsString('academy/catalogue?onglet=formations&fiche=a-' . AcademyBuiltInCourses::JTAC_KEY, $json['qualification']['academy']['url']);
        self::assertStringContainsString('catalogue_emploi=', $json['qualification']['jobs'][0]['url']);
        self::assertStringContainsString('catalogue_qualif=us-jtac', $json['qualification']['jobs_url']);

        // Import depuis la fiche (JSON) : jeton exigé, fiche créée une seule fois avec l’émetteur réel.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_POST = ['ids' => ['us-jtac'], '_csrf_token' => 'faux'];
        self::assertSame(419, $this->referentiel()->importCatalog(new Request())->statusCode());
        $_POST = ['ids' => ['us-jtac'], '_csrf_token' => str_repeat('a', 64)];
        $first = json_decode($this->referentiel()->importCatalog(new Request())->body(), true);
        self::assertTrue($first['ok'], (string) $first['message']);
        self::assertSame(1, $first['created']);
        $definitionId = (int) $first['definitions']['us-jtac']['definition_id'];
        $again = json_decode($this->referentiel()->importCatalog(new Request())->body(), true);
        self::assertSame([0, $definitionId], [$again['created'], (int) $again['definitions']['us-jtac']['definition_id']], 'aucun doublon');
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM personnel_qualification_definitions WHERE tenant_id = 14 AND code = 'US-JTAC'")->fetchColumn());
        $issuer = (string) $this->pdo->query('SELECT i.name FROM qualification_issuers i JOIN personnel_qualification_definitions d ON d.default_issuer_id = i.id WHERE d.id = ' . $definitionId)->fetchColumn();
        self::assertSame(QualificationCatalog::issuerOf(QualificationCatalog::findRaw('us-jtac'), 'fr')['name'], $issuer);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => 'us-jtac'];
        $json = json_decode($this->referentiel()->catalogEntry(new Request())->body(), true);
        self::assertSame($definitionId, $json['qualification']['imported']['definition_id']);
        self::assertStringEndsWith('back-office/referentiels/qualifications/' . $definitionId, $json['qualification']['imported']['url']);
        // Autre communauté : pas importée chez elle.
        $_SESSION = ['user_id' => 9, 'tenant_id' => 15];
        self::assertNull(json_decode($this->referentiel()->catalogEntry(new Request())->body(), true)['qualification']['imported']);
    }

    private function migrate(): void
    {
        $pdo = $this->pdo;
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/3480_job_catalog_collections.php';
            require $root . '/database/steps/3550_job_catalog_qualification_links.php';
        } finally {
            ob_end_clean();
        }
    }

    private function post(string $method, array $post, int $tenant = 14, array $permissions = ['personnel.assignments.manage']): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = ['user_id' => 7, 'tenant_id' => $tenant, '_csrf_token' => str_repeat('a', 64)];
        $_POST = $post + ['_csrf_token' => str_repeat('a', 64)];
        $_FILES = [];
        Gate::getInstance()->setPermissions($permissions);
        $response = (new JobCatalogController())->{$method}(new Request());

        return [$response->statusCode(), json_decode($response->body(), true)];
    }

    public function testCommunityQualificationsCanBeLinkedToTheAuthorsReferentielOnly(): void
    {
        $repo = new JobCatalogCollectionRepository($this->pdo);
        $this->migrate();
        self::assertTrue($repo->linksReady());
        // Étape rejouable.
        $this->pdo->changes = 0;
        $this->migrate();
        self::assertSame(0, $this->pdo->changes);

        $collection = $repo->saveCollection(14, 0, CommunityJobCatalog::normalizeCollection(['name' => 'SCP Foundation']), null, null, 7)['id'];
        $entry = $repo->saveEntry(14, $collection, 0, CommunityJobCatalog::normalizeEntry(['name' => 'Agent MTF', 'qualifications' => "Garde niveau 1\nConfinement"]), null, 7)['id'];
        self::assertSame(403, $this->post('linkQualification', ['entry_id' => (string) $entry, 'text' => 'Garde niveau 1', 'definition_id' => '60'], 14, [])[0]);
        self::assertSame(419, $this->post('linkQualification', ['entry_id' => (string) $entry, 'text' => 'Garde niveau 1', 'definition_id' => '60', '_csrf_token' => 'faux'])[0]);
        self::assertSame(422, $this->post('linkQualification', ['entry_id' => (string) $entry, 'text' => 'Garde niveau 1', 'definition_id' => '61'])[0], 'fiche d’une autre communauté');
        self::assertSame(422, $this->post('linkQualification', ['entry_id' => (string) $entry, 'text' => 'Inconnue', 'definition_id' => '60'])[0], 'texte absent de l’emploi');
        self::assertSame(422, $this->post('linkQualification', ['entry_id' => (string) $entry, 'text' => 'Garde niveau 1', 'definition_id' => '60'], 15)[0], 'emploi d’une autre communauté');
        [$status, $body] = $this->post('linkQualification', ['entry_id' => (string) $entry, 'text' => 'Garde niveau 1', 'definition_id' => '60']);
        self::assertSame(200, $status, (string) ($body['message'] ?? ''));

        // La fenêtre montre le lien à la communauté auteur, avec la liste de son référentiel pour relier.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $json = json_decode((new JobCatalogController())->catalogue(new Request())->body(), true);
        $mine = array_values(array_filter($json['entries'], static fn (array $e): bool => $e['name'] === 'Agent MTF'))[0];
        self::assertSame(['definition_id' => 60, 'name' => 'Garde de la Fondation', 'code' => 'GUARD-1'], $mine['qualification_definitions'][0]);
        self::assertNull($mine['qualification_definitions'][1]);
        self::assertSame([60], array_column($json['referentiel'], 'id'));

        // Modifier l’emploi garde le lien des qualifications encore citées seulement.
        $repo->saveEntry(14, $collection, $entry, CommunityJobCatalog::normalizeEntry(['name' => 'Agent MTF', 'qualifications' => "Garde niveau 1\nAutre"]), null, 7);
        self::assertSame(['Garde niveau 1' => 60], CommunityJobCatalog::links($this->pdo->query('SELECT qualification_links_json FROM job_catalog_collection_entries WHERE id = ' . $entry)->fetchColumn()));
        $repo->saveEntry(14, $collection, $entry, CommunityJobCatalog::normalizeEntry(['name' => 'Agent MTF', 'qualifications' => 'Autre']), null, 7);
        self::assertSame([], CommunityJobCatalog::links($this->pdo->query('SELECT qualification_links_json FROM job_catalog_collection_entries WHERE id = ' . $entry)->fetchColumn()));
        self::assertGreaterThan(0, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'personnel.job_catalog_entry.updated'")->fetchColumn());
    }

    public function testWindowAndPageOfferClickableQualificationsWithKeyboardAndTranslations(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/back-office/referentiels/qualifications/catalogue/fiche', [QualificationReferentielController::class, 'catalogEntry']", $routes);
        self::assertStringContainsString("fonctions/catalogue/emplois/qualification', [\\App\\Controllers\\Admin\\JobCatalogController::class, 'linkQualification']", $routes);
        self::assertLessThan(strpos($routes, "qualifications/{id}', [QualificationRulesController::class, 'show']"), strpos($routes, 'qualifications/catalogue/fiche'));
        $modal = (string) file_get_contents($root . '/views/admin/effectifs_workspace/partials/job_catalog_modal.php');
        foreach (['data-job-catalog-qsheet', 'data-qualification-url=', 'data-academy-url=', '← Retour à l’emploi', 'Chercher ce brevet dans les qualifications', 'Relier à une qualification du référentiel'] as $needle) {
            self::assertStringContainsString($needle, $modal);
        }
        $js = (string) file_get_contents($root . '/public/assets/js/job-catalog.js');
        foreach (["closeQualification();", "aria-haspopup', 'dialog'", 'catalogue_qualif', 'catalogue_emploi', "document.addEventListener('cancel'"] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        $sheet = (string) file_get_contents($root . '/public/assets/js/qualification-sheet.js');
        foreach (['Importer dans mon référentiel', 'Déjà dans votre référentiel', 'Suivre la formation', 'Catalogue Academy', "setAttribute('translate', 'no')"] as $needle) {
            self::assertStringContainsString($needle, $sheet);
        }
        $page = (string) file_get_contents($root . '/views/admin/organization/qualifications/catalog.php');
        foreach (['data-rqc-open', 'name="niveau"', 'name="categorie"', 'name="emetteur"', 'name="academy"', 'name="etat"', 'name="tri"', 'return_query', 'class="jobcat__flag" data-flag='] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
        self::assertStringContainsString("require __DIR__ . '/_tabs.php'", (string) file_get_contents($root . '/views/admin/organization/qualifications/index.php'));
        $dict = require $root . '/lang/en/site_auto.php';
        foreach (['Importer dans mon référentiel', 'Catalogue de référence', '← Retour à l’emploi', 'Chercher ce brevet dans les qualifications'] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
        }
    }
}
