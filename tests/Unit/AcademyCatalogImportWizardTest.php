<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\QualificationCatalogQuota;
use App\Services\Personnel\QualificationCatalogImportWizard as Wizard;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationCatalogExplorer;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Assistant d'importation du catalogue de référence (Academy › Catalogue) : portée combinable avec compteurs, aperçu
 * (déjà présents, qualifications préalables manquantes, émetteurs), « pays entier » réservé à la formule, import par
 * lots sans doublon et dans la limite de la formule ; câblage des routes, des droits, de la vue et du script.
 */
final class AcademyCatalogImportWizardTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/athena-aiw-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $code, string $cat, string $fr, string $en, array $extra = []): array => $extra + [
            'id' => $id, 'code' => $code, 'category' => $cat, 'name' => ['fr' => $fr, 'en' => $en], 'short_name' => 'X',
            'description' => ['fr' => 'Description française.', 'en' => 'English description.'],
            'body' => ['fr' => 'JAPCC', 'en' => 'JAPCC'], 'reference' => ['fr' => 'STANAG 3797', 'en' => 'STANAG 3797'],
            'prerequisites' => ['fr' => ['Aucun'], 'en' => ['None']], 'validity' => ['fr' => '', 'en' => ''],
            'validity_months' => null, 'sources' => [['url' => 'https://www.japcc.org/', 'title' => 'JAPCC']],
        ];
        $pack = static fn (string $name, string $country, string $issuer, array $entries): array => [
            'pack' => $name, 'country' => $country, 'official_lang' => $name === 'fr' ? 'fr' : 'en', 'label' => ['fr' => $name, 'en' => $name], 'compiled_at' => '2026-11-02',
            'disclaimer' => ['fr' => 'Qualification de jeu, sans valeur réelle.', 'en' => 'In-game qualification, with no real-world value.'],
            'issuer' => ['name' => $issuer, 'short_name' => $country, 'description' => ['fr' => 'Émetteur de référence.', 'en' => 'Reference issuer.']],
            'sources_note' => ['fr' => 'Sources publiques.', 'en' => 'Public sources.'],
            'entries' => $entries,
        ];
        file_put_contents($dir . '/nato.json', json_encode($pack('nato', 'NATO', 'OTAN (référence)', [
            // Le JTAC demande d'être observateur JFO, qui demande lui-même le niveau de langue : deux préalables en chaîne.
            $entry('nato-jtac', 'OTAN-JTAC', 'air_support', 'JTAC qualifié', 'Qualified JTAC', ['prerequisites' => ['fr' => ['Être Observateur des feux interarmées qualifié'], 'en' => ['Qualified Joint Fires Observer']]]),
            $entry('nato-jfo', 'OTAN-JFO', 'fires', 'Observateur des feux interarmées', 'Joint Fires Observer', ['prerequisites' => ['fr' => ['Niveau de langue anglaise reconnu'], 'en' => ['Recognised English language proficiency']]]),
            $entry('nato-slp', 'OTAN-SLP', 'c2_staff', 'Niveau de langue anglaise reconnu', 'Recognised English language proficiency'),
        ]), JSON_UNESCAPED_UNICODE));
        file_put_contents($dir . '/fr.json', json_encode($pack('fr', 'FR', 'Armées françaises (référence)', [
            $entry('fr-bmp', 'FR-BMP', 'airborne', 'Brevet militaire de parachutiste', 'Military parachutist badge', ['official_name' => 'Brevet militaire de parachutiste', 'issuer' => ['name' => 'École des troupes aéroportées', 'short_name' => 'ETAP']]),
            $entry('fr-moniteur', 'FR-MONITEUR', 'airborne', 'Moniteur parachutiste', 'Parachute instructor', ['level' => 'instructor']),
            $entry('fr-cdt', 'FR-CDT', 'c2_staff', 'Chef de groupe', 'Section commander'),
        ]), JSON_UNESCAPED_UNICODE));
        QualificationCatalog::useDirectory($dir);
        QualificationCatalogExplorer::reset();
    }

    protected function tearDown(): void
    {
        QualificationCatalog::useDirectory(null);
        QualificationCatalogExplorer::reset();
    }

    private static function base(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, default_validity_months INTEGER, validity_days INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER)',
            // Déjà dans le référentiel de la communauté 7 : le brevet parachutiste et son émetteur.
            "INSERT INTO personnel_qualification_definitions (id, tenant_id, code, name) VALUES (40, 7, 'fr-bmp', 'BMP déjà saisi'), (41, 8, 'OTAN-JTAC', 'Autre communauté')",
            "INSERT INTO qualification_issuers (id, tenant_id, name, short_name, issuer_kind) VALUES (5, 7, 'Armées françaises (référence)', 'FR', 'external')",
        ] as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;
    }

    /** @param list<array<string, mixed>> $rows @return list<string> */
    private static function ids(array $rows): array
    {
        $ids = array_map(static fn (array $r): string => (string) $r['id'], $rows);
        sort($ids);

        return $ids;
    }

    public function testScopeFiltersCombineWithLiveCountersAndManualSelection(): void
    {
        $wizard = new Wizard(new QualificationReferenceWriter(self::base()));
        $rows = Wizard::rows('fr', $wizard->importer()->importedCodes(7));
        self::assertCount(6, $rows);

        // Tout le catalogue.
        $all = Wizard::summary($rows, Wizard::scope([]));
        self::assertSame(['total' => 6, 'already' => 1, 'to_import' => 5], array_slice($all, 0, 3));
        self::assertEquals(['NATO' => 3, 'FR' => 3], $all['facets']['pays']);

        // Un pays.
        $fr = Wizard::scope(['pays' => ['fr', 'XX']]);
        self::assertSame(['FR'], $fr['pays'], 'pays inconnu ignoré');
        self::assertSame(['fr-bmp', 'fr-cdt', 'fr-moniteur'], self::ids(Wizard::select($rows, $fr)));

        // Catégorie (nature) : le brevet ; domaine (type) : parachutisme ; combinables, ou dans une dimension.
        self::assertSame(['fr-bmp'], self::ids(Wizard::select($rows, Wizard::scope(['nature' => ['brevet']]))));
        self::assertSame(['fr-bmp', 'fr-moniteur'], self::ids(Wizard::select($rows, Wizard::scope(['categorie' => ['airborne']]))));
        self::assertSame(['fr-cdt', 'fr-moniteur', 'nato-slp'], self::ids(Wizard::select($rows, Wizard::scope(['categorie' => ['airborne', 'c2_staff'], 'nature' => ['qualification']]))));
        $mixed = Wizard::summary($rows, Wizard::scope(['pays' => ['FR'], 'categorie' => ['airborne']]));
        self::assertSame(2, $mixed['total']);
        self::assertSame(1, $mixed['already']);
        self::assertSame(['airborne' => 2, 'c2_staff' => 1], $mixed['facets']['categorie'], 'compteur d’une dimension : avec les autres choix, sans le sien');

        // Niveau, émetteur, recherche (sans accents), sélection à la main.
        self::assertSame(['fr-moniteur'], self::ids(Wizard::select($rows, Wizard::scope(['niveau' => ['instructor']]))));
        self::assertSame(['fr-bmp'], self::ids(Wizard::select($rows, Wizard::scope(['emetteur' => ['École des troupes aéroportées']]))));
        self::assertSame(['nato-jfo'], self::ids(Wizard::select($rows, Wizard::scope(['q' => 'interarmees observateur']))));
        self::assertSame(['nato-jtac'], self::ids(Wizard::select($rows, Wizard::scope(['pays' => ['NATO'], 'ids' => ['nato-jtac', 'fr-cdt', '../x']]))));
    }

    public function testPreviewPlansMissingPrerequisitesFirstWithIssuersAndAlreadyPresentIgnored(): void
    {
        $wizard = new Wizard(new QualificationReferenceWriter(self::base()));

        $plan = $wizard->plan(7, ['nato-jtac', 'fr-bmp', 'inconnu-x'], true, 'fr');
        self::assertSame(['nato-slp', 'nato-jfo', 'nato-jtac', 'fr-bmp'], $plan['order'], 'préalables avant ce qui les demande');
        self::assertTrue($plan['items']['fr-bmp']['already']);
        self::assertTrue($plan['items']['nato-jfo']['dependency']);
        self::assertSame(['nato-jtac'], $plan['items']['nato-jfo']['required_by']);
        self::assertFalse($plan['items']['nato-jtac']['dependency']);
        self::assertSame([3, 1, 2, 1], [$plan['to_create'], $plan['already'], $plan['dependencies'], $plan['unknown']]);
        self::assertSame([['name' => 'OTAN (référence)', 'short_name' => 'NATO', 'real' => false, 'country' => 'NATO', 'new' => true, 'count' => 3]], $plan['issuers']);

        // Sans les dépendances : signalées comme manquantes, pas importées.
        $alone = $wizard->plan(7, ['nato-jtac'], false, 'fr');
        self::assertSame(['nato-jtac'], $alone['order']);
        self::assertSame(['nato-jfo'], array_column($alone['missing'], 'id'));

        // Un préalable choisi lui aussi n'est pas une dépendance.
        $both = $wizard->plan(7, ['nato-jtac', 'nato-jfo'], true, 'fr');
        self::assertFalse($both['items']['nato-jfo']['dependency']);
        self::assertSame(['nato-slp', 'nato-jfo', 'nato-jtac'], $both['order']);

        // Émetteur de référence déjà présent dans le référentiel.
        $cdt = $wizard->plan(7, ['fr-cdt'], true, 'fr');
        self::assertFalse($cdt['issuers'][0]['new']);
    }

    public function testWholeCountryIsDetectedForThePlanOption(): void
    {
        self::assertSame(['fr'], Wizard::wholePacks(['fr-bmp', 'fr-moniteur', 'fr-cdt', 'nato-jtac']));
        self::assertSame([], Wizard::wholePacks(['fr-bmp', 'fr-moniteur']));
        $quota = new QualificationCatalogQuota([QualificationCatalogQuota::IMPORTS => 10], [QualificationCatalogQuota::IMPORTS => 0], false);
        self::assertFalse($quota->bulk(), 'formule sans « pays entier en un clic »');
    }

    public function testBatchesImportOnceUpToThePlanLimitAndAreIdempotent(): void
    {
        $pdo = self::base();
        $wizard = new Wizard(new QualificationReferenceWriter($pdo));
        $order = $wizard->plan(7, ['nato-jtac', 'fr-bmp', 'fr-cdt', 'fr-moniteur'], true, 'fr')['order'];
        self::assertCount(6, $order);

        // Lot 1 (taille 2) : deux créations.
        $lot = $wizard->batch(7, $order, 0, 'fr', 3, null, 2);
        self::assertSame(2, $lot['next']);
        self::assertSame(['nato-slp', 'nato-jfo'], array_keys($lot['created']));

        // Lot 2 avec 1 seule place restante dans la formule : import partiel, le reste bloqué.
        $lot2 = $wizard->batch(7, $order, $lot['next'], 'fr', 3, 1, 2);
        self::assertSame(['nato-jtac'], array_keys($lot2['created']));
        self::assertSame(40, $lot2['skipped']['fr-bmp'], 'déjà présent : fiche existante, rien créé');
        self::assertSame(4, $lot2['next']);

        // Lot 3 : plafond atteint (0 place) : rien créé.
        $lot3 = $wizard->batch(7, $order, $lot2['next'], 'fr', 3, 0, 2);
        self::assertSame([], $lot3['created']);
        self::assertSame(['fr-cdt', 'fr-moniteur'], $lot3['blocked']);
        self::assertSame(6, $lot3['next']);
        self::assertSame([], $wizard->batch(7, $order, 6, 'fr', 3, null, 2)['ids'], 'fin du plan');

        // Relance complète sans limite : seuls les bloqués sont créés, aucun doublon.
        $again = [];
        for ($from = 0; $from < count($order);) {
            $r = $wizard->batch(7, $order, $from, 'fr', 3, null, 4);
            $again += $r['created'];
            $from = $r['next'];
        }
        self::assertSame(['fr-cdt', 'fr-moniteur'], array_keys($again));
        $codes = $pdo->query('SELECT code FROM personnel_qualification_definitions WHERE tenant_id = 7 ORDER BY code')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['FR-CDT', 'FR-MONITEUR', 'OTAN-JFO', 'OTAN-JTAC', 'OTAN-SLP', 'fr-bmp'], $codes);
        self::assertSame('Autre communauté', $pdo->query('SELECT name FROM personnel_qualification_definitions WHERE id = 41')->fetchColumn(), 'autre communauté jamais touchée');
    }

    public function testPreviewCountsSurePrerequisitesWhenTheCitedQualificationIsImportedTooAndAppliesNoneWithoutFiches(): void
    {
        $rules = $this->createMock(\App\Services\Personnel\QualificationAwardRuleService::class);
        $rules->method('names')->willReturn([]);
        $ruleRows = $this->createMock(\App\Repositories\QualificationAwardRuleRepository::class);
        $ruleRows->method('prerequisitesByDefinition')->willReturn([]);
        $definitions = $this->createMock(\App\Repositories\QualificationDefinitionRepository::class);
        $definitions->method('listForTenant')->willReturn([]);
        $suggester = new \App\Services\Personnel\QualificationPrerequisiteSuggester($rules, $ruleRows, $definitions, $this->createMock(\App\Repositories\QualificationReferentielRepository::class));

        $alone = $suggester->forCatalogEntries(7, ['nato-jtac'], [], 'fr');
        $with = $suggester->forCatalogEntries(7, ['nato-jtac'], ['nato-jfo' => true], 'fr');
        self::assertSame([], $alone['nato-jtac']['sure'] ?? [], 'préalable absent du référentiel et non importé : pas sûr');
        self::assertNotSame([], $with['nato-jtac']['sure'] ?? [], 'préalable importé dans le même passage : prérequis sûr');
        self::assertSame(['added' => 0, 'definitions' => [], 'errors' => 0], $suggester->applySure(7, [], 3));
    }

    public function testRoutesRightsViewAndScriptAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (["get('/academy/catalogue/assistant', [\\App\\Controllers\\Web\\AcademyCatalogImportController::class, 'data'], \$academyMw)",
            "post('/academy/catalogue/assistant/apercu', [\\App\\Controllers\\Web\\AcademyCatalogImportController::class, 'preview'], \$academyMw)",
            "post('/academy/catalogue/assistant/demarrer', [\\App\\Controllers\\Web\\AcademyCatalogImportController::class, 'start'], \$academyMw)",
            "post('/academy/catalogue/assistant/lot', [\\App\\Controllers\\Web\\AcademyCatalogImportController::class, 'batch'], \$academyMw)"] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/AcademyCatalogImportController.php');
        foreach (["allows('admin.organization')", "allows('admin.access')", "allows('site.support')", 'Csrf::verify($request)', 'TenantContext::id()',
            'QualificationCatalogQuota::IMPORTS', '->bulk()', "'academy.catalog_import_batch'", "'academy.catalog_import_done'"] as $needle) {
            self::assertStringContainsString($needle, $ctrl);
        }
        self::assertStringContainsString("if (\$oxCanImport) { require __DIR__ . '/import_wizard.php'; }", (string) file_get_contents(self::ROOT . '/views/academy/offer_catalogue.php'));
        $view = (string) file_get_contents(self::ROOT . '/views/academy/import_wizard.php');
        self::assertStringContainsString("views/partials/ui/stepper.php", $view);
        self::assertStringContainsString('Csrf::token()', $view);
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/academy-import-wizard.js');
        foreach (["'/apercu'", "'/demarrer'", "'/lot'", '_csrf_token', "f.append('from'"] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
    }
}
