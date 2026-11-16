<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\BackOfficeHomeController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\BackOfficeNavPrefsRepository;
use App\Repositories\BoSheetRepository;
use App\Repositories\PlayerSessionRepository;
use App\Services\BackOffice\Home\HomeLayout;
use App\Support\PilotageDashboardPresenter;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Tableau de bord du back-office en widgets : catalogue filtré par les droits, compositions par défaut sans trou,
 * placement sans trou quelles que soient les tailles, validation stricte de la disposition envoyée, persistance par
 * personne et par communauté, réinitialisation, widget vide compact, mode dégradé sans la table, lectures en lot.
 */
final class BackOfficeDashboardWidgetsTest extends TestCase
{
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->saved = [$_SESSION ?? [], $_POST, $_SERVER];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 0, 'user_id' => 0];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization', 'admin.access']);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        [$_SESSION, $_POST, $_SERVER] = $this->saved;
        Gate::reset();
    }

    // ——— Catalogue et droits ———

    public function testCatalogIsFilteredByRights(): void
    {
        $noRecruitNoStaff = static fn (string $path): bool => !in_array($path, [HomeLayout::PATH_RECRUITMENTS, HomeLayout::PATH_EFFECTIFS], true);
        $ids = array_keys(HomeLayout::catalogFor($noRecruitNoStaff));
        foreach (['pipeline', 'availability', 'sessions'] as $id) {
            self::assertNotContains($id, $ids, $id . ' : la donnée n’est pas visible pour cette personne.');
        }
        self::assertContains('agenda', $ids);
        self::assertSame(array_keys(HomeLayout::catalog()), array_keys(HomeLayout::catalogFor(static fn (): bool => true)));

        foreach (HomeLayout::catalog() as $id => $def) {
            self::assertNotSame('', $def['label'], $id);
            self::assertNotSame('', $def['hint'], $id);
            self::assertLessThanOrEqual($def['def'][0], $def['min'][0], $id);
            self::assertLessThanOrEqual($def['max'][0], $def['def'][0], $id);
            self::assertLessThanOrEqual($def['def'][1], $def['min'][1], $id);
            self::assertLessThanOrEqual($def['max'][1], $def['def'][1], $id);
            self::assertLessThanOrEqual(HomeLayout::COLS, $def['max'][0], $id);
        }

        // Composition par défaut sans le droit : le widget n'y est pas, même masqué.
        $default = HomeLayout::defaults(HomeLayout::ROLE_MANAGER, $ids);
        self::assertNotContains('pipeline', array_column($default, 'id'));
        // Rôle déduit des droits.
        self::assertSame(HomeLayout::ROLE_MANAGER, HomeLayout::roleFor(true, $noRecruitNoStaff));
        self::assertSame(HomeLayout::ROLE_HR, HomeLayout::roleFor(false, static fn (string $p): bool => $p === HomeLayout::PATH_EFFECTIFS));
        self::assertSame(HomeLayout::ROLE_RECRUITER, HomeLayout::roleFor(false, static fn (string $p): bool => $p === HomeLayout::PATH_RECRUITMENTS));
        self::assertSame(HomeLayout::ROLE_SUPPORT, HomeLayout::roleFor(false, static fn (): bool => false));
    }

    public function testUnauthorizedWidgetIsNeverRendered(): void
    {
        $allowed = array_values(array_diff(array_keys(HomeLayout::catalog()), ['pipeline', 'availability']));
        $html = $this->render(true, ['allowed' => $allowed, 'items' => HomeLayout::defaults(HomeLayout::ROLE_MANAGER), 'availability' => ['ready' => true, 'days' => 30, 'total' => 3, 'rows' => [], 'href' => '/x']]);
        self::assertStringNotContainsString('data-widget="pipeline"', $html);
        self::assertStringNotContainsString('data-widget="availability"', $html);
        self::assertStringContainsString('data-widget="agenda"', $html);
    }

    // ——— Compositions par défaut et placement sans trou ———

    public function testDefaultCompositionsFillWholeRowsForEveryRole(): void
    {
        foreach ([HomeLayout::ROLE_MANAGER, HomeLayout::ROLE_HR, HomeLayout::ROLE_RECRUITER, HomeLayout::ROLE_SUPPORT] as $role) {
            $shown = array_values(array_filter(HomeLayout::defaults($role), static fn (array $i): bool => !$i['hidden']));
            $area = array_sum(array_map(static fn (array $i): int => $i['w'] * $i['h'], $shown));
            self::assertSame(0, $area % HomeLayout::COLS, $role . ' : la somme des surfaces remplit des rangées entières.');
            $packed = HomeLayout::pack($shown, HomeLayout::COLS);
            self::assertSame(0, HomeLayout::holes($packed, HomeLayout::COLS), $role . ' : aucun trou sans même combler.');
            $rows = max(array_map(static fn (array $p): int => $p['y'] + $p['h'] - 1, $packed));
            self::assertSame($area, $rows * HomeLayout::COLS, $role);
            foreach ($shown as $i) {
                $b = HomeLayout::bounds($i['id']);
                self::assertTrue($i['w'] >= $b['min'][0] && $i['w'] <= $b['max'][0] && $i['h'] >= $b['min'][1] && $i['h'] <= $b['max'][1], $role . ' ' . $i['id']);
            }
            $this->assertPlanHasNoHole(HomeLayout::plan($shown), $role);
        }
    }

    public function testAnySizesAndEmptyWidgetsGiveAGridWithoutHoles(): void
    {
        $catalog = HomeLayout::catalog();
        $ids = array_keys($catalog);
        mt_srand(1811);
        for ($run = 0; $run < 400; $run++) {
            $pick = $ids;
            shuffle($pick);
            $items = [];
            foreach (array_slice($pick, 0, mt_rand(1, count($pick))) as $id) {
                $d = $catalog[$id];
                $items[] = ['id' => $id, 'w' => mt_rand($d['min'][0], $d['max'][0]), 'h' => mt_rand($d['min'][1], $d['max'][1]), 'compact' => mt_rand(0, 3) === 0];
            }
            $plan = HomeLayout::plan($items, mt_rand(0, 1) === 1);
            self::assertCount(count($items), $plan);
            $this->assertPlanHasNoHole($plan, 'tirage ' . $run);
        }
    }

    public function testEmptyWidgetsBecomeOneRowAndGoAfterTheOthers(): void
    {
        $plan = HomeLayout::plan([
            ['id' => 'participation', 'w' => 8, 'h' => 3, 'compact' => true],
            ['id' => 'pipeline', 'w' => 4, 'h' => 3],
            ['id' => 'agenda', 'w' => 4, 'h' => 3, 'compact' => true],
            ['id' => 'feed', 'w' => 8, 'h' => 3],
        ]);
        $byId = array_column($plan, null, 'id');
        self::assertSame(1, $byId['participation']['h'], 'Un graphique sans données n’occupe plus une rangée entière.');
        self::assertSame(1, $byId['agenda']['h']);
        self::assertGreaterThan($byId['feed']['y'], $byId['participation']['y'], 'Les widgets vides passent après ceux qui ont des données.');
        self::assertSame(['pipeline', 'feed', 'participation', 'agenda'], array_column($plan, 'id'), 'Ordre de lecture de la grille.');
        $this->assertPlanHasNoHole($plan, 'compacts');
    }

    // ——— Validation de la disposition envoyée ———

    public function testServerValidationRejectsUnknownOversizedAndUnauthorized(): void
    {
        $allowed = array_values(array_diff(array_keys(HomeLayout::catalog()), ['pipeline']));
        $ok = ['v' => 2, 'items' => [['id' => 'agenda', 'w' => 6, 'h' => 4, 'hidden' => false], ['id' => 'sheet:5', 'w' => 12, 'h' => 3, 'hidden' => true]]];
        [$stored, $errors] = HomeLayout::validate($ok, $allowed, [5]);
        self::assertSame([], $errors);
        self::assertSame(2, $stored['v']);
        self::assertSame(['id' => 'agenda', 'w' => 6, 'h' => 4, 'hidden' => false], $stored['items'][0]);
        self::assertSame(array_keys(HomeLayout::catalog()), $stored['known']);

        $cases = [
            'id inconnu' => [['id' => 'piratage', 'w' => 4, 'h' => 2]],
            'largeur hors bornes' => [['id' => 'agenda', 'w' => 7, 'h' => 3]],
            'hauteur hors bornes' => [['id' => 'kpi_members', 'w' => 3, 'h' => 0]],
            'taille non entière' => [['id' => 'agenda', 'w' => '4', 'h' => 3]],
            'widget non autorisé' => [['id' => 'pipeline', 'w' => 4, 'h' => 3]],
            'tableau illisible' => [['id' => 'sheet:9', 'w' => 12, 'h' => 4]],
            'doublon' => [['id' => 'agenda', 'w' => 4, 'h' => 3], ['id' => 'agenda', 'w' => 4, 'h' => 3]],
        ];
        foreach ($cases as $label => $items) {
            [$stored, $errors] = HomeLayout::validate(['v' => 2, 'items' => $items], $allowed, [5]);
            self::assertNull($stored, $label);
            self::assertNotSame([], $errors, $label);
        }
        self::assertNull(HomeLayout::validate(['v' => 1, 'items' => []], $allowed, [])[0], 'Version inconnue.');
        self::assertNull(HomeLayout::validate('{"v":2}', $allowed, [])[0]);
        self::assertNull(HomeLayout::validate(['v' => 2, 'items' => array_fill(0, HomeLayout::MAX_ITEMS + 1, ['id' => 'agenda'])], $allowed, [])[0]);
    }

    public function testVersionOneLayoutsAreMigratedSoftly(): void
    {
        $v1 = ['v' => 1, 'items' => [
            ['id' => 'feed', 'hidden' => false, 'size' => 'wide'],
            ['id' => 'kpis', 'hidden' => true, 'size' => 'full'],
            ['id' => 'decisions', 'hidden' => false, 'size' => 'full'],
        ], 'known' => ['operation', 'decisions', 'tasks', 'shortcuts', 'kpis', 'participation', 'units', 'pipeline', 'agenda', 'feed', 'usage', 'messages', 'gaps', 'custom']];
        $out = HomeLayout::normalize($v1, HomeLayout::ROLE_MANAGER, []);
        $byId = array_column($out, null, 'id');
        self::assertSame(['feed', 'kpi_members', 'kpi_presence', 'kpi_month', 'kpi_files', 'decisions'], array_slice(array_column($out, 'id'), 0, 6));
        self::assertSame(8, $byId['feed']['w'], '« wide » : 8 colonnes.');
        self::assertSame(6, $byId['decisions']['w'], '« full » ramené au maximum du widget.');
        self::assertTrue($byId['kpi_presence']['hidden'], 'Le bloc « Chiffres clés » masqué masque ses quatre tuiles.');
        self::assertTrue($byId['operation']['hidden'], 'Bloc connu en v1 et absent : reste masqué.');
        self::assertFalse($byId['availability']['hidden'], 'Nouveau widget : visible.');
    }

    // ——— Persistance par personne et par communauté, réinitialisation, mode dégradé ———

    public function testLayoutIsStoredPerUserAndPerCommunity(): void
    {
        $pdo = $this->prefsDb();
        $repo = new BackOfficeNavPrefsRepository($pdo);
        self::assertTrue($repo->ready());
        $a = HomeLayout::stored([['id' => 'agenda', 'w' => 6, 'h' => 4, 'hidden' => false]]);
        $b = HomeLayout::stored([['id' => 'feed', 'w' => 8, 'h' => 2, 'hidden' => false]]);
        self::assertTrue($repo->put(1, 10, BackOfficeNavPrefsRepository::COL_HOME, $a));
        self::assertTrue($repo->put(1, 11, BackOfficeNavPrefsRepository::COL_HOME, $b));
        self::assertTrue($repo->put(2, 10, BackOfficeNavPrefsRepository::COL_HOME, $b));
        self::assertTrue($repo->put(1, 10, BackOfficeNavPrefsRepository::COL_FAVORITES, ['back-office/events']));

        $fresh = new BackOfficeNavPrefsRepository($pdo);
        self::assertSame('agenda', $fresh->get(1, 10)[BackOfficeNavPrefsRepository::COL_HOME]['items'][0]['id']);
        self::assertSame('feed', $fresh->get(1, 11)[BackOfficeNavPrefsRepository::COL_HOME]['items'][0]['id'], 'Une autre personne garde sa disposition.');
        self::assertSame('feed', $fresh->get(2, 10)[BackOfficeNavPrefsRepository::COL_HOME]['items'][0]['id'], 'La même personne a une disposition par communauté.');
        self::assertSame(['back-office/events'], $fresh->get(1, 10)[BackOfficeNavPrefsRepository::COL_FAVORITES], 'Les favoris du menu ne sont pas écrasés.');

        // Réinitialisation : la disposition du rôle revient, celle des autres ne bouge pas.
        self::assertTrue($fresh->put(1, 10, BackOfficeNavPrefsRepository::COL_HOME, null));
        $after = new BackOfficeNavPrefsRepository($pdo);
        self::assertNull($after->get(1, 10)[BackOfficeNavPrefsRepository::COL_HOME]);
        self::assertSame(HomeLayout::defaults(HomeLayout::ROLE_MANAGER), HomeLayout::normalize($after->get(1, 10)[BackOfficeNavPrefsRepository::COL_HOME], HomeLayout::ROLE_MANAGER, []));
        self::assertSame('feed', $after->get(1, 11)[BackOfficeNavPrefsRepository::COL_HOME]['items'][0]['id']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM bo_user_nav_prefs WHERE tenant_id = 1 AND user_id = 10')->fetchColumn(), 'Une ligne par personne et par communauté.');
    }

    public function testControllerSavesValidatesAndResets(): void
    {
        $pdo = $this->prefsDb();
        $prefs = new BackOfficeNavPrefsRepository($pdo);
        $controller = new BackOfficeHomeController(new BoSheetRepository($pdo), $prefs);
        Session::set('tenant_id', 3);
        Session::set('user_id', 7);

        $layout = json_encode(['v' => 2, 'items' => [['id' => 'agenda', 'w' => 6, 'h' => 3, 'hidden' => false], ['id' => 'feed', 'w' => 6, 'h' => 3, 'hidden' => true]]]);
        $res = $controller->saveLayout($this->post(['layout' => $layout]));
        self::assertSame(200, $res->statusCode());
        $stored = (new BackOfficeNavPrefsRepository($pdo))->get(3, 7)[BackOfficeNavPrefsRepository::COL_HOME];
        self::assertSame(2, $stored['v']);
        self::assertSame([['id' => 'agenda', 'w' => 6, 'h' => 3, 'hidden' => false], ['id' => 'feed', 'w' => 6, 'h' => 3, 'hidden' => true]], $stored['items']);

        // Refus : rien n'est écrit.
        foreach ([
            ['id' => 'piratage', 'w' => 4, 'h' => 2],
            ['id' => 'agenda', 'w' => 12, 'h' => 3],
        ] as $bad) {
            $res = $controller->saveLayout($this->post(['layout' => json_encode(['v' => 2, 'items' => [$bad]])]));
            self::assertSame(422, $res->statusCode());
        }
        self::assertSame('agenda', (new BackOfficeNavPrefsRepository($pdo))->get(3, 7)[BackOfficeNavPrefsRepository::COL_HOME]['items'][0]['id']);

        // Jeton CSRF absent : 419, rien n'est écrit.
        self::assertSame(419, $controller->saveLayout($this->post(['layout' => $layout], false))->statusCode());

        // Réinitialisation.
        self::assertSame(200, $controller->saveLayout($this->post(['reset' => '1']))->statusCode());
        self::assertNull((new BackOfficeNavPrefsRepository($pdo))->get(3, 7)[BackOfficeNavPrefsRepository::COL_HOME]);
    }

    public function testWithoutTheTableTheDefaultLayoutStaysAndCannotBeEdited(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $prefs = new BackOfficeNavPrefsRepository($pdo);
        self::assertFalse($prefs->ready());
        self::assertFalse($prefs->put(1, 2, BackOfficeNavPrefsRepository::COL_HOME, ['v' => 2, 'items' => []]));
        self::assertNull($prefs->get(1, 2)[BackOfficeNavPrefsRepository::COL_HOME]);

        Session::set('tenant_id', 1);
        Session::set('user_id', 2);
        $controller = new BackOfficeHomeController(new BoSheetRepository($pdo), $prefs);
        self::assertSame(503, $controller->saveLayout($this->post(['layout' => '{"v":2,"items":[]}']))->statusCode());

        $html = $this->render(true, ['customize' => true, 'can_save' => false]);
        self::assertStringContainsString('Personnalisation indisponible pour le moment', $html);
        self::assertStringNotContainsString('data-dw-edit', $html, 'Pas de poignées sans enregistrement possible.');
        self::assertStringNotContainsString('data-dw-save', $html);
        self::assertStringContainsString('data-widget="agenda"', $html, 'La disposition par défaut reste affichée.');
    }

    // ——— Rendu ———

    public function testEmptyWidgetIsCompactAndKeepsAUsefulAction(): void
    {
        $html = $this->render(false);
        self::assertMatchesRegularExpression('#<div class="dw dw--chart is-compact is-empty" data-widget="participation"[^>]*--h:1;#', $html);
        self::assertStringContainsString('<section class="dw-card dw-card--compact" aria-labelledby="pil-part-title">', $html);
        self::assertMatchesRegularExpression('#data-widget="agenda".*?Rien n’est prévu pour le moment\.</p><a class="dw-btn dw-btn--sm" href="[^"]*back-office/events">Calendrier</a>#s', $html);
        self::assertMatchesRegularExpression('#class="dw dw--band is-compact is-empty" data-widget="operation"#', $html);
        self::assertStringContainsString('Programmer une activité', $html);
        self::assertStringNotContainsString('class="pil-chart"', $html, 'Pas de graphique vide.');

        // Mode « Personnaliser » : la taille choisie est montrée, pas la version compacte.
        $edit = $this->render(false, ['customize' => true, 'can_save' => true]);
        self::assertMatchesRegularExpression('#<div class="dw dw--chart is-empty" data-widget="participation"[^>]*--w:8;--h:3;#', $edit);
    }

    public function testFilledDashboardRendersEveryWidgetInsideTheGrid(): void
    {
        $html = $this->render(true, [
            'availability' => ['ready' => true, 'days' => 30, 'total' => 2, 'rows' => [['id' => 41, 'name' => 'Tom Durand', 'linked' => true, 'last_at' => time() - 40 * 86400], ['id' => 42, 'name' => 'Léa Martin', 'linked' => false, 'last_at' => null]], 'href' => '/back-office/ressources/effectifs?sessions=verifier30'],
            'sessions' => ['ready' => true, 'min_minutes' => 60, 'href' => '/e', 'rows' => [['user_id' => 41, 'name' => 'Tom Durand', 'started_at' => time() - 7200, 'last_seen_at' => time() - 3600, 'duration_s' => 5400, 'aberrant' => false], ['user_id' => 0, 'name' => '', 'started_at' => time() - 9000, 'last_seen_at' => time() - 8000, 'duration_s' => 1200, 'aberrant' => false]]],
        ]);
        foreach (['kpi_members', 'kpi_presence', 'kpi_month', 'kpi_files', 'operation', 'decisions', 'tasks', 'agenda', 'participation', 'pipeline', 'units', 'feed', 'availability', 'usage', 'messages', 'sessions'] as $id) {
            self::assertMatchesRegularExpression('#<div class="dw dw--\w+(?: is-row)?" data-widget="' . $id . '" id="[^"]+" style="--x:\d+;--y:\d+;--w:\d+;--h:\d+;--x6:\d+;--y6:\d+;--w6:\d+;--h6:\d+;--x2:\d+;--y2:\d+;--w2:\d+">#', $html, $id);
        }
        self::assertStringContainsString('<b>2</b><span>membres à vérifier</span>', $html);
        self::assertStringContainsString('Steam non relié', $html);
        self::assertStringContainsString('back-office/ressources/effectifs/membres/41', $html);
        self::assertStringContainsString('1 h 30', $html);
        self::assertStringContainsString('Joueur non relié', $html);
        self::assertStringContainsString('Trop courte', $html);
        self::assertStringContainsString('class="pil-chart"', $html);
        // Positions de la grande grille : aucune case vide.
        preg_match_all('#--x:(\d+);--y:(\d+);--w:(\d+);--h:(\d+)#', $html, $m, PREG_SET_ORDER);
        $placed = array_map(static fn (array $r): array => ['x' => (int) $r[1], 'y' => (int) $r[2], 'w' => (int) $r[3], 'h' => (int) $r[4]], $m);
        self::assertSame(0, HomeLayout::holes($placed, HomeLayout::COLS));
        // Pas de surtitre, index « / 01 » seulement à côté d'un titre de bande.
        self::assertDoesNotMatchRegularExpression('#class="[^"]*\b(?:eyebrow|kicker|overline)\b#', $html);
    }

    public function testCustomizeModeHasHandlesSizesDrawerAndLiveRegion(): void
    {
        $html = $this->render(true, ['customize' => true, 'can_save' => true]);
        self::assertStringContainsString('data-dw-edit data-dw-url="', $html);
        self::assertStringContainsString('data-dw-csrf="' . Csrf::token() . '"', $html);
        self::assertStringContainsString('aria-live="polite" data-dw-live', $html);
        self::assertStringContainsString('aria-label="Déplacer « Agenda à venir »" aria-describedby="dw-grip-help"', $html);
        self::assertStringContainsString('data-dw-act="w+"', $html);
        self::assertStringContainsString('data-dw-act="h-"', $html);
        self::assertStringContainsString('data-dw-act="up"', $html);
        self::assertStringContainsString('data-dw-resize', $html);
        self::assertStringContainsString('<dialog class="bo-dialog dw-drawer" id="dw-add"', $html);
        self::assertStringContainsString('data-dw-search', $html);
        self::assertStringContainsString('Réinitialiser la disposition', $html);
        self::assertStringContainsString('>Annuler</a>', $html);
        self::assertStringContainsString('data-dw-save>Enregistrer</button>', $html);
        // Widgets masqués présents (pour les rajouter sans recharger) et proposés dans le tiroir.
        $hidden = HomeLayout::defaults(HomeLayout::ROLE_MANAGER);
        foreach ($hidden as &$i) {
            if ($i['id'] === 'feed') {
                $i['hidden'] = true;
            }
        }
        unset($i);
        $html = $this->render(true, ['customize' => true, 'can_save' => true, 'items' => $hidden]);
        self::assertMatchesRegularExpression('#data-widget="feed" id="dw-feed" hidden data-w=#', $html);
        self::assertStringContainsString('data-dw-add="feed">Ajouter</button>', $html);
        self::assertStringContainsString('data-dw-add="agenda" disabled>Affiché</button>', $html);
    }

    public function testStylesAndScriptFollowTheProjectRules(): void
    {
        $root = dirname(__DIR__, 2);
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-widgets.css');
        foreach (['container: dwg / inline-size', '@container dwg (max-width: 979.98px)', '@container dwg (max-width: 599.98px)', 'grid-auto-flow: row dense', 'repeat(12, minmax(0, 1fr))', 'repeat(6, minmax(0, 1fr))', 'prefers-reduced-motion: reduce', 'min-height: 44px', 'overflow-y: auto', ':focus-visible'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        preg_match_all('#font-size:\s*(\d+(?:\.\d+)?)px#', $css, $sizes);
        foreach ($sizes[1] as $size) {
            self::assertGreaterThanOrEqual(13, (float) $size, 'Texte de 13 px au moins.');
        }
        foreach (preg_split('/\R/', $css) ?: [] as $line) {
            if (preg_match('/^\s*(?:background|color|border(?:-color)?)\s*:[^;]*(?:#[0-9a-f]{3,8}\b|rgba?\()/i', $line) === 1) {
                self::fail('Couleur littérale hors jeton : ' . trim($line));
            }
        }
        self::assertDoesNotMatchRegularExpression('#Archivo|\.sse-|sse-ds#', $css);

        $js = (string) file_get_contents($root . '/public/assets/js/back-office-home.js');
        foreach (['function pack(', 'function fill(', 'function strips(', "'X-CSRF-Token'", 'ArrowUp', 'Escape', 'setPointerCapture', 'prefers-reduced-motion', 'inert'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        self::assertStringContainsString("'back-office-widgets.css'", $ctrl);
        self::assertFileDoesNotExist($root . '/views/admin/organization/_home_customize.php');
    }

    // ——— Lectures en lot : disponibilité et sessions de jeu ———

    public function testAvailabilityAndRecentSessionsUseAFixedNumberOfQueries(): void
    {
        $now = (int) strtotime('2026-11-16 12:00:00');
        foreach ([5, 60] as $members) {
            $pdo = new CountingSqlitePdo();
            $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT, steam_id TEXT, display_name TEXT)');
            $pdo->exec('CREATE TABLE player_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_uid TEXT, user_id INTEGER, started_at TEXT, last_seen_at TEXT, ended_at TEXT, duration_s INTEGER, aberrant INTEGER, source TEXT, server_id INTEGER)');
            $pdo->exec('CREATE TABLE member_availability_checks (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, created_at TEXT)');
            for ($i = 1; $i <= $members; $i++) {
                $steam = '765611980000' . sprintf('%05d', $i);
                $pdo->exec("INSERT INTO users VALUES ({$i}, 1, 'active', '{$steam}', 'Membre {$i}')");
                // Un sur trois a joué récemment une session qui compte ; un sur cinq a été vérifié.
                $at = $i % 3 === 0 ? $now - 2 * 86400 : $now - 50 * 86400;
                $pdo->exec("INSERT INTO player_sessions (tenant_id, steam_uid, started_at, last_seen_at, duration_s, aberrant) VALUES (1, '{$steam}', '" . date('Y-m-d H:i:s', $at - 5400) . "', '" . date('Y-m-d H:i:s', $at) . "', 5400, 0)");
                if ($i % 5 === 0) {
                    $pdo->exec("INSERT INTO member_availability_checks (tenant_id, user_id, created_at) VALUES (1, {$i}, '" . date('Y-m-d H:i:s', $now - 86400) . "')");
                }
            }
            $pdo->exec("INSERT INTO users VALUES (900, 2, 'active', NULL, 'Autre communauté')");
            $pdo->exec("INSERT INTO users VALUES (901, 1, 'active', NULL, 'Sans Steam')");
            $repo = new PlayerSessionRepository($pdo);
            $pdo->queries = 0;
            $res = $repo->membersToVerify(1, $now - 30 * 86400, 3600, 5);
            self::assertLessThanOrEqual(5, $pdo->queries, $members . ' membres : nombre de requêtes fixe.');
            $expected = 1 + count(array_filter(range(1, $members), static fn (int $i): bool => $i % 3 !== 0 && $i % 5 !== 0));
            self::assertSame($expected, $res['total']);
            self::assertSame(901, $res['rows'][0]['id'], 'Jamais vu en jeu d’abord.');
            self::assertFalse($res['rows'][0]['linked']);
            self::assertNotContains(900, array_column($res['rows'], 'id'), 'Isolation par communauté.');

            $pdo->queries = 0;
            $recent = $repo->recentForTenant(1, $now - 14 * 86400, 8);
            self::assertLessThanOrEqual(2, $pdo->queries);
            self::assertNotEmpty($recent);
            self::assertSame(5400, $recent[0]['duration_s']);
        }

        $none = new PlayerSessionRepository(new CountingSqlitePdo());
        self::assertNull($none->membersToVerify(1, $now, 3600), 'Sans la table des sessions : suivi indisponible.');
        self::assertNull($none->recentForTenant(1, $now));
    }

    // ——— Outils ———

    /** @param list<array<string, mixed>> $plan */
    private function assertPlanHasNoHole(array $plan, string $label): void
    {
        foreach ([[HomeLayout::COLS, 'x', 'y', 'w', 'h'], [HomeLayout::COLS_TABLET, 'x6', 'y6', 'w6', 'h6']] as [$cols, $x, $y, $w, $h]) {
            $placed = array_map(static fn (array $p): array => ['x' => $p[$x], 'y' => $p[$y], 'w' => $p[$w], 'h' => $p[$h]], $plan);
            self::assertSame(0, HomeLayout::holes($placed, $cols), $label . ' : grille de ' . $cols);
            $this->assertNoOverlap($placed, $label . ' / ' . $cols);
        }
        $mobile = array_map(static fn (array $p): array => ['x' => $p['x2'], 'y' => $p['y2'], 'w' => $p['w2'], 'h' => 1], $plan);
        self::assertSame(0, HomeLayout::holes($mobile, HomeLayout::COLS_MOBILE), $label . ' : téléphone');
        $this->assertNoOverlap($mobile, $label . ' / téléphone');
    }

    /** @param list<array{x: int, y: int, w: int, h: int}> $placed */
    private function assertNoOverlap(array $placed, string $label): void
    {
        $seen = [];
        foreach ($placed as $p) {
            for ($r = $p['y']; $r < $p['y'] + $p['h']; $r++) {
                for ($c = $p['x']; $c < $p['x'] + $p['w']; $c++) {
                    self::assertArrayNotHasKey($r . ':' . $c, $seen, $label . ' : chevauchement');
                    $seen[$r . ':' . $c] = true;
                }
            }
        }
    }

    private function prefsDb(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $migrationRunner = null;
        $migrationEnsurePdo = static function (): void {
        };
        ob_start();
        require dirname(__DIR__, 2) . '/database/steps/4170_bo_user_nav_prefs.php';
        ob_end_clean();

        return $pdo;
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, bool $withToken = true): Request
    {
        $_POST = $post + ($withToken ? ['_csrf_token' => Csrf::token()] : []);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/back-office/accueil/disposition';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        return new Request();
    }

    /** @param array<string, mixed> $home */
    private function render(bool $filled, array $home = []): string
    {
        $now = time();
        $in = ['now' => $now, 'tenant_name' => 'SOAR', 'viewer_name' => 'Jake', 'link_allowed' => null];
        $tasks = [];
        $extra = [];
        if ($filled) {
            $participation = [];
            foreach ([['Basalte', 36, 32], ['Sirocco', 34, 30], ['Granit', 31, 29]] as $i => [$name, $reg, $present]) {
                $participation[] = ['id' => 100 + $i, 'title' => 'Op. ' . $name, 'starts_at' => date('Y-m-d 20:30:00', $now - ($i + 1) * 4 * 86400), 'event_type' => 'operation', 'registered' => $reg, 'present' => $present];
            }
            $in += [
                'kpis' => [['id' => 'members_active', 'value' => '36'], ['id' => 'members_inactive', 'value' => '4'], ['id' => 'active_30d', 'value' => '31']],
                'profile_gaps_total' => 2,
                'upcoming' => [['id' => 12, 'title' => 'Op. Aube Rouge', 'starts_at' => date('Y-m-d 20:30:00', $now + 2 * 86400), 'location' => 'Altis', 'event_type' => 'operation']],
                'rsvp' => [12 => ['yes' => array_fill(0, 28, []), 'maybe' => [], 'no' => []]],
                'elevations' => [['target_display_name' => 'James Alexander', 'kind' => 'role', 'status' => 'pending']],
                'elevation_count' => 1,
                'elevation_kind_labels' => ['role' => 'droits Gestionnaire'],
                'enlistment_counts' => ['submitted' => 1],
                'enlistments' => [['id' => 5, 'first_name' => 'Jules', 'last_name' => 'Petit', 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s', $now - 86400)]],
                'participation' => $participation,
                'units' => [['name' => 'Alpha', 'authorized' => 8, 'filled' => 8, 'score' => 88]],
                'activity' => [['action' => 'event.created', 'actor_name' => 'Marie Lefèvre', 'action_label' => 'A programmé une activité', 'target' => 'Op. Aube Rouge', 'created_at' => date('Y-m-d H:i:s', $now - 3600)]],
            ];
            $tasks = [['title' => 'Corrections', 'path' => 'back-office/corrections', 'count' => 3, 'important' => false, 'key' => 'corr', 'detail' => null]];
            $extra = [
                'orgActivityChart' => ['days' => ['15', '16'], 'portal' => [3, 5], 'atak' => [1, 2], 'max' => 5, 'period_days' => 14],
                'orgMessagesRecent' => [['id' => 3, 'subject' => 'Briefing', 'last_preview' => 'Relire', 'updated_at' => date('Y-m-d H:i:s', $now - 3600), 'thread_unread' => true]],
                'orgProfileGaps' => ['rows' => [['display_name' => 'Tom Durand', 'missing_rank' => true, 'fiche_url' => '/f/1']], 'total' => 2, 'error' => null],
            ];
        }
        $vars = array_merge([
            'pilotage' => PilotageDashboardPresenter::build($in),
            'initialSetupBanner' => null, 'configurationUpdateBadge' => 0, 'discordInviteMissing' => false, 'missingMediaCount' => 0,
            'orgActivityChart' => ['days' => [], 'portal' => [], 'atak' => [], 'max' => 1, 'period_days' => 14],
            'orgMessagesRecent' => [],
            'orgProfileGaps' => ['rows' => [], 'total' => 0, 'error' => null],
            'home' => $home + ['items' => HomeLayout::defaults(HomeLayout::ROLE_MANAGER), 'sheets' => [], 'tasks' => $tasks, 'customize' => false],
        ], $extra);
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(base_path('views/admin/organization/dashboard.php'), $vars);
    }
}

/** SQLite en mémoire qui compte les requêtes (prepare + query) : vérifie l'absence de requête par membre. */
final class CountingSqlitePdo extends PDO
{
    public int $queries = 0;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->queries++;

        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $this->queries++;

        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
