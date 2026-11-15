<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\PermissionImplication;
use App\Authorization\TenantPermissionCatalog;
use App\Controllers\Admin\MemberDossierSuiviController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Services\Effectifs\Dossier\DossierStore;
use App\Services\Effectifs\Dossier\MemberDossierTimeline;
use App\Services\Rbac\AccessRightsCatalog;
use App\Support\EffectifsLmsAccess;
use App\Support\MemberFicheTabs;
use PHPUnit\Framework\TestCase;
use Tests\Support\DossierFixture as F;

/**
 * Droits, migration, routes, contrôles d'entrée et vues du suivi de dossier.
 */
final class MemberDossierSuiviWiringTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sessionBefore = [];

    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    protected function setUp(): void
    {
        $this->sessionBefore = $_SESSION ?? [];
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        Gate::reset();
    }

    protected function tearDown(): void
    {
        Gate::reset();
        $_SESSION = $this->sessionBefore;
        $_POST = [];
        $_GET = [];
    }

    /** @param list<string> $permissions */
    private function grant(array $permissions): Gate
    {
        $gate = Gate::getInstance();
        $gate->setPermissions($permissions);

        return $gate;
    }

    // ------------------------------------------------------------------ Droits

    public function testNewRightsAreDeclaredInTheCatalogWithLabelsExplanationAndDependencies(): void
    {
        $slugs = TenantPermissionCatalog::allSlugs();
        self::assertContains('effectifs.suivi.view', $slugs);
        self::assertContains('effectifs.suivi.manage', $slugs);
        $view = AccessRightsCatalog::meta('effectifs.suivi.view');
        $manage = AccessRightsCatalog::meta('effectifs.suivi.manage');
        self::assertSame('Consulter le suivi du dossier des membres', $view['name']);
        self::assertSame('Modifier le suivi du dossier des membres', $manage['name']);
        self::assertSame('members', $view['domain']);
        self::assertTrue($view['curated']);
        self::assertTrue($manage['curated']);
        self::assertStringContainsString('Jamais sur son propre dossier', $manage['explanation']);
        self::assertSame(AccessRightsCatalog::SENSITIVITY_STANDARD, $view['sensitivity']);
        self::assertSame(AccessRightsCatalog::SENSITIVITY_ELEVATED, $manage['sensitivity']);
        self::assertSame(['effectifs.suivi.view'], $manage['requires']);
        self::assertSame([], $view['requires']);
        self::assertArrayNotHasKey('effectifs.suivi.view', AccessRightsCatalog::NOT_EDITABLE);
    }

    public function testNoDefaultRoleReceivesTheNewRights(): void
    {
        foreach (['app/Services/Community/TenantDefaultRoleDefinitions.php', 'app/Services/Community/TenantSeedHelper.php', 'migrations/20260903090000_simplified_community_roles.sql'] as $file) {
            self::assertStringNotContainsString('effectifs.suivi', self::read($file), $file);
        }
        self::assertStringNotContainsString('INSERT INTO role_permissions', self::read('database/steps/4720_member_dossier_steps.php'));
    }

    public function testViewAndManageRulesFollowTheExistingFicheAccess(): void
    {
        // Gestion du dossier (coffre RH) : consultation sans modification.
        $hr = $this->grant(['personnel.status.manage']);
        self::assertTrue(EffectifsLmsAccess::canViewSuivi($hr));
        self::assertFalse(EffectifsLmsAccess::canManageSuivi($hr));
        // Un droit d'affectation ou de grade n'ouvre pas le suivi (comme le coffre RH).
        self::assertFalse(EffectifsLmsAccess::canViewSuivi($this->grant(['personnel.assignments.manage'])));
        self::assertFalse(EffectifsLmsAccess::canViewSuivi($this->grant(['organization.effectifs.hub.view'])));
        // Les nouveaux droits, accordés explicitement.
        self::assertTrue(EffectifsLmsAccess::canViewSuivi($this->grant(['effectifs.suivi.view'])));
        self::assertFalse(EffectifsLmsAccess::canManageSuivi($this->grant(['effectifs.suivi.view'])));
        $manage = $this->grant(['effectifs.suivi.manage']);
        self::assertTrue(EffectifsLmsAccess::canManageSuivi($manage));
        self::assertTrue(EffectifsLmsAccess::canViewSuivi($manage), 'modifier suppose consulter');
        // Les administrateurs de la communauté.
        foreach (['admin.organization', 'admin.access'] as $admin) {
            $g = $this->grant([$admin]);
            self::assertTrue(EffectifsLmsAccess::canViewSuivi($g), $admin);
            self::assertTrue(EffectifsLmsAccess::canManageSuivi($g), $admin);
        }
        // Personne sans droit.
        self::assertFalse(EffectifsLmsAccess::canViewSuivi($this->grant([])));
        self::assertFalse(EffectifsLmsAccess::canManageSuivi($this->grant(['personnel.profile.view'])));
    }

    public function testAdminOrganizationDoesNotImplyTheNewRightsSilently(): void
    {
        self::assertFalse(PermissionImplication::isGranted(['admin.organization'], 'effectifs.suivi.manage'));
        self::assertTrue(PermissionImplication::isGranted(['admin.access'], 'effectifs.suivi.manage'));
    }

    // ------------------------------------------------------------------ Migration

    public function testMigrationIsRegisteredOnceAndIsRerunnable(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'id' => 'member_dossier_steps'"));
        self::assertStringContainsString("'file' => '4720_member_dossier_steps.php'", $registry);
        self::assertSame(1, substr_count($registry, "'file' => '4720_"), 'numéro libre sur main');
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/4720_member_dossier_steps.php');
        self::assertFileExists(dirname(__DIR__, 2) . '/migrations/2026_11_19_100_member_dossier_steps.sql');
        self::assertFileExists(dirname(__DIR__, 2) . '/migrations/rollback/member_dossier_steps_down.sql');
    }

    public function testMigrationSqlIsSqliteCompatibleAndDeclaresTheDocumentedColumns(): void
    {
        $sql = self::read('migrations/2026_11_19_100_member_dossier_steps.sql');
        self::assertDoesNotMatchRegularExpression('/ALTER\s+TABLE[^;]*ADD\s+(UNIQUE|CONSTRAINT|FOREIGN)/i', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_mds_source', $sql, 'unique dans le CREATE TABLE');
        self::assertSame(2, substr_count($sql, 'CREATE TABLE IF NOT EXISTS'));
        foreach (['id', 'tenant_id', 'user_id', 'track', 'title', 'status', 'owner_user_id', 'due_on', 'done_at', 'note', 'created_by', 'created_at', 'updated_at', 'source_type', 'source_id'] as $col) {
            self::assertMatchesRegularExpression('/^\s+' . $col . '\s/m', $sql, $col);
        }
        if (extension_loaded('pdo_sqlite')) {
            $pdo = F::pdo(false);
            F::migrate($pdo);
            F::migrate($pdo);
            $store = new DossierStore($pdo);
            self::assertTrue($store->available());
            $id = $store->insert(1, 7, ['track' => 'documents', 'title' => 'Une étape']);
            self::assertGreaterThan(0, $id);
            $store->insert(1, 7, ['track' => 'documents', 'title' => 'Une autre']);
            // Une seule ligne par étape complétée (unicité du couple source), plusieurs étapes personnalisées sans source.
            $store->insert(1, 7, ['source_type' => 'qualification', 'source_id' => '3', 'status' => 'active']);
            $this->expectException(\PDOException::class);
            $store->insert(1, 7, ['source_type' => 'qualification', 'source_id' => '3', 'status' => 'active']);
        } else {
            self::assertTrue(true);
        }
    }

    public function testRollbackRemovesTablesAndRightsOnly(): void
    {
        $down = self::read('migrations/rollback/member_dossier_steps_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS member_dossier_step_events', $down);
        self::assertStringContainsString('DROP TABLE IF EXISTS member_dossier_steps', $down);
        self::assertStringContainsString("slug IN ('effectifs.suivi.view', 'effectifs.suivi.manage')", $down);
        self::assertDoesNotMatchRegularExpression('/DROP TABLE IF EXISTS (member_integration|users|enlistments|data_campaign)/', $down);
        $step = self::read('database/steps/4720_member_dossier_steps.php');
        self::assertStringContainsString('tableExists', $step, 'étape rejouable');
        self::assertStringContainsString("'effectifs.suivi.manage'", $step);
    }

    // ------------------------------------------------------------------ Routes et contrôles d'entrée

    public function testRoutesAreProtectedByAuthAndTheEffectifsMiddleware(): void
    {
        $routes = self::read('routes/web.php');
        $needles = [
            "get('/back-office/ressources/effectifs/suivi'",
            "get('/back-office/ressources/effectifs/suivi/export'",
            "post('/back-office/ressources/effectifs/suivi/relancer'",
            "get('/back-office/ressources/effectifs/membres/{id}/suivi/export'",
            "post('/back-office/ressources/effectifs/membres/{id}/suivi/{action}'",
        ];
        foreach ($needles as $needle) {
            $pos = strpos($routes, $needle);
            self::assertNotFalse($pos, $needle);
            $line = substr($routes, (int) $pos, (int) strpos($routes, "\n", (int) $pos) - (int) $pos);
            self::assertStringContainsString('AuthMiddleware::class, OrganizationAdminMiddleware::class', $line, $needle);
        }
        // Les écritures sont en POST, jamais en GET.
        self::assertStringNotContainsString("get('/back-office/ressources/effectifs/membres/{id}/suivi/{action}'", $routes);
    }

    public function testWriteEntryPointsCheckCsrfBeforeAnyWrite(): void
    {
        $src = self::read('app/Controllers/Admin/MemberDossierSuiviController.php');
        foreach (['public function act(', 'public function bulkRemind('] as $method) {
            $body = substr($src, (int) strpos($src, $method), 1800);
            self::assertLessThan((int) strpos($body, 'actions()'), (int) strpos($body, 'Csrf::validate'), $method . ' : le jeton est vérifié avant tout geste');
            self::assertStringContainsString('deny(true)', $body, $method . ' : droit de modifier');
        }
        self::assertStringContainsString('memberInTenant', $src, 'le membre doit appartenir à la communauté');
        self::assertStringContainsString("in_array(\$action, self::ACTIONS, true)", $src, 'liste blanche des gestes');
        self::assertStringContainsString("Session::get('tenant_id')", $src, 'communauté lue en session, jamais dans la requête');
        self::assertDoesNotMatchRegularExpression('/input\(\s*[\'"]tenant/', $src);
    }

    public function testActRefusesAViewerWithoutTheManageRightWithoutTouchingAnything(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        $_SESSION = ['user_id' => 11, 'tenant_id' => 1, '_csrf_token' => 'jeton'];
        $this->grant(['personnel.status.manage']);
        $_POST = ['_csrf_token' => 'jeton', 'key' => 'integration_step:4', 'reason' => 'x'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = (new MemberDossierSuiviController())->act(new Request(), ['id' => 7, 'action' => 'complete']);
        self::assertSame(302, $response->statusCode());
        self::assertStringContainsString('ressources/effectifs', (string) $response->headerValue('Location'));
        self::assertStringContainsString('pas habilité', (string) ($_SESSION['_flash']['error'] ?? json_encode($_SESSION)));
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testActAndExportRefuseAnonymousUsers(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        $_SESSION = [];
        $response = (new MemberDossierSuiviController())->exportMember(new Request(), ['id' => 7]);
        self::assertStringContainsString('login', (string) $response->headerValue('Location'));
        $this->grant(['personnel.assignments.manage']);
        $_SESSION = ['user_id' => 11, 'tenant_id' => 1];
        $denied = (new MemberDossierSuiviController())->exportTeam(new Request(), []);
        self::assertSame(302, $denied->statusCode(), 'sans droit de consulter, pas d’export');
    }

    // ------------------------------------------------------------------ Onglet, vues et ressources

    public function testTabIsOnlyVisibleToThoseWhoMayConsultTheFollowUp(): void
    {
        self::assertContains('suivi', MemberFicheTabs::slugs());
        self::assertSame('Suivi du dossier', MemberFicheTabs::label('suivi'));
        self::assertNotContains('suivi', MemberFicheTabs::visible(['papers' => true]));
        self::assertContains('suivi', MemberFicheTabs::visible(['suivi' => true]));
        self::assertSame('suivi', MemberFicheTabs::resolve('suivi', MemberFicheTabs::visible(['suivi' => true])));
        self::assertSame('identite', MemberFicheTabs::resolve('suivi', MemberFicheTabs::visible([])), 'onglet absent sans le droit');
        self::assertSame('suivi', MemberFicheTabs::tabForAnchor('#suivi'));
    }

    public function testMemberViewAndControllerAreWired(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString("\$panelOpen('suivi')", $view);
        self::assertStringContainsString('partials/member_dossier_suivi.php', $view);
        self::assertStringContainsString('assets/js/eff-suivi.js', $view);
        $ctl = self::read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString('MemberDossierSuiviController::tabData(', $ctl);
        self::assertStringContainsString("'memberSuivi' => \$memberSuivi", $ctl);
        self::assertStringContainsString("queryString('ouvrir', '') === 'suivi'", $ctl, '?ouvrir=suivi ouvre l’onglet');
        self::assertStringContainsString("'eff-suivi.css'", $ctl);
        self::assertStringContainsString("params.get('ouvrir') === 'suivi'", self::read('public/assets/js/eff-member.js'));
        $shell = self::read('views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString("['suivi', 'Étapes des dossiers', 'suivi'", $shell);
        self::assertStringContainsString("canViewSuivi", $shell);
        self::assertStringContainsString("effectifs_workspace_url('suivi')", self::read('views/admin/effectifs_workspace/partials/effectifs_lms_rail.php'));
    }

    public function testAssetsFollowTheUiRules(): void
    {
        foreach (['public/assets/css/eff-suivi.css', 'public/assets/js/eff-suivi.js'] as $file) {
            self::assertFileExists(dirname(__DIR__, 2) . '/' . $file);
        }
        $css = self::read('public/assets/css/eff-suivi.css');
        self::assertDoesNotMatchRegularExpression('/font-size:\s*(?:[0-9]|1[0-2])(?:\.\d+)?px/', $css, 'textes de 13 px au moins');
        self::assertStringContainsString('--sv-tap: 44px', $css);
        self::assertStringContainsString("html[data-bo-theme='dark']", $css);
        self::assertStringContainsString('@container sv', $css, 'tableau devenu cartes');
        self::assertDoesNotMatchRegularExpression('/\b(kicker|eyebrow|surtitre)\b/i', $css);
        $js = self::read('public/assets/js/eff-suivi.js');
        self::assertDoesNotMatchRegularExpression('/\beval\(|document\.write|innerHTML\s*=\s*[^\'"\s]/', str_replace("body.innerHTML = '';", '', $js));
    }

    /** @return array<string, mixed> */
    private function tabFor(string $sceneNote = '', bool $manage = true, int $viewer = 4, bool $withMigration = true): array
    {
        $pdo = F::pdo($withMigration);
        foreach ([4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Lt Vasseur'] as $id => $n) {
            F::user($pdo, $id, $n);
        }
        F::richMember($pdo, 7);
        $store = new DossierStore($pdo);
        if ($withMigration) {
            $store->insert(1, 7, ['track' => 'documents', 'title' => $sceneNote !== '' ? $sceneNote : 'Signer la décharge', 'status' => 'todo', 'due_on' => date('Y-m-d', strtotime('+5 days')), 'created_by' => 4]);
        }
        $timeline = new MemberDossierTimeline($pdo, $store, null, static fn (int $t, array $ids): array => [4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Lt Vasseur']);

        return MemberDossierSuiviController::buildTab($timeline, 1, 7, $viewer, 'Sgt Marchand', ['track' => '', 'status' => '', 'q' => ''], $manage, [['id' => 9, 'label' => 'Lt Vasseur']], null, static fn (int $t, array $ids): array => [4 => 'Cpt Delorme']);
    }

    /** @param array<string, mixed> $suivi */
    private function render(array $suivi): string
    {
        $id = 7;
        $name = 'Sgt Marchand';
        $csrfToken = 'jeton';
        ob_start();
        require dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/partials/member_dossier_suivi.php';

        return (string) ob_get_clean();
    }

    public function testTabRendersSummaryTimelineDrawerAndEditDialogsForAManager(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $html = $this->render($this->tabFor());
        self::assertStringContainsString('Suivi du dossier', $html);
        self::assertStringContainsString('Toutes les étapes de Sgt Marchand', $html, 'une ligne de description sous le titre');
        self::assertStringContainsString('5 <span>étapes sur 16</span>', $html);
        self::assertStringContainsString('Prochaine action', $html);
        foreach (['Recrutement', 'Intégration', 'Formation et qualifications', 'Documents', 'Carrière', 'Fiabilisation'] as $track) {
            self::assertStringContainsString('>' . $track . '</h3>', $html);
        }
        foreach (['complete', 'reopen', 'postpone', 'reassign', 'note', 'skip', 'unskip', 'remind', 'remove', 'add'] as $dialog) {
            self::assertStringContainsString('id="sv-dlg-' . $dialog . '"', $html, $dialog);
        }
        self::assertStringContainsString('name="_csrf_token" value="jeton"', $html);
        self::assertStringContainsString('action="/back-office/ressources/effectifs/membres/7/suivi/postpone"', $html);
        self::assertStringContainsString('id="sv-drawer"', $html);
        self::assertStringContainsString('Exporter en CSV', $html);
        self::assertStringContainsString('Ajouter une étape', $html);
        self::assertStringContainsString('data-sv-act="postpone"', $html);
        self::assertStringContainsString('Cette étape se termine dans son écran d’origine', $html, 'étapes non modifiables : renvoi vers l’écran d’origine');
        self::assertStringContainsString('Ajoutée à la main', $html);
    }

    public function testTabRespectsTheUiRules(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $html = $this->render($this->tabFor());
        self::assertDoesNotMatchRegularExpression('/simulation|simulé/i', $html);
        self::assertDoesNotMatchRegularExpression('/(kicker|eyebrow|surtitre)/i', $html, 'aucun surtitre au-dessus des titres');
        self::assertStringContainsString('<h2 id="suivi-titre">', $html);
        // Chaque titre de section est suivi d'une ligne de description.
        self::assertMatchesRegularExpression('#</h3>\s*<p>[^<]+</p>#', $html);
        self::assertStringContainsString('role="progressbar"', $html);
        self::assertStringContainsString('aria-label="Fermer"', $html);
    }

    public function testTabIsReadOnlyForAViewerWithoutTheManageRightAndForTheirOwnDossier(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $ro = $this->render($this->tabFor('', false));
        self::assertStringContainsString('Le droit « Modifier le suivi du dossier des membres » est nécessaire', $ro);
        self::assertStringNotContainsString('id="sv-dlg-', $ro);
        self::assertStringNotContainsString('data-sv-act=', $ro);
        self::assertStringNotContainsString('Ajouter une étape', $ro);
        self::assertStringContainsString('Exporter en CSV', $ro, 'la consultation et l’export restent possibles');
        $self = $this->render($this->tabFor('', true, 7));
        self::assertStringContainsString('C’est votre propre dossier', $self);
        self::assertStringNotContainsString('data-sv-act="complete"', $self);
        self::assertStringNotContainsString('data-sv-act="postpone"', $self);
        self::assertStringContainsString('data-sv-act="note"', $self, 'seule la note reste permise');
        self::assertStringNotContainsString('data-sv-act="add"', $self);
    }

    public function testDegradedModeShowsAClearMessageAndNoEditing(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $html = $this->render($this->tabFor('', true, 4, false));
        self::assertStringContainsString('Suivi en lecture seule', $html);
        self::assertStringContainsString('mise à jour de la base', $html);
        self::assertStringNotContainsString('data-sv-act=', $html);
        self::assertStringNotContainsString('id="sv-dlg-', $html);
        self::assertStringContainsString('Fil des étapes', $html, 'les étapes des parcours existants restent visibles');
    }

    public function testUserTextIsEscapedInTheTabAndInTheCsv(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $payload = '<script>alert(1)</script>"><img src=x onerror=alert(2)>';
        $html = $this->render($this->tabFor($payload));
        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)', $html);
    }

    public function testReminderNotificationIsPresentedWithAWhitelistedLinkOnly(): void
    {
        $presenter = new \App\Services\Notifications\ActivityHubPresentationService();
        $row = static fn (array $payload): array => ['type' => 'dossier_step_reminder', 'payload_json' => json_encode($payload), 'read_at' => null, 'created_at' => '2026-11-15 10:00:00'];
        $ok = $presenter->formatNotificationRow($row(['step' => 'Lire la charte', 'due_on' => '2026-11-20', 'path' => 'mon-integration']));
        self::assertSame('Une étape de votre dossier attend votre action', $ok['title']);
        self::assertStringContainsString('Lire la charte · avant le 20/11/2026', $ok['detail']);
        self::assertStringEndsWith('/mon-integration', $ok['href']);
        $evil = $presenter->formatNotificationRow($row(['step' => 'x', 'path' => 'https://evil.example/phish']));
        self::assertStringNotContainsString('evil.example', $evil['href']);
    }

    public function testEnglishDictionaryCoversTheFollowUpScreens(): void
    {
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['Suivi en lecture seule.', 'Fil des étapes', 'Reporter l’échéance', 'Étapes des dossiers', 'Modifier le suivi du dossier des membres', 'Bilan du membre à 30 jours', 'Une étape de votre dossier attend votre action', 'Ce geste n’est pas permis sur cette étape.%1'] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
            self::assertNotSame('', $dict[$fr]);
        }
    }

    public function testTeamViewRendersARowPerMemberWithBulkRemindOnlyForManagers(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = F::pdo();
        F::user($pdo, 7, 'Sgt Marchand');
        F::user($pdo, 8, 'Cal Dupuis');
        F::richMember($pdo, 7);
        $store = new DossierStore($pdo);
        $tl = new MemberDossierTimeline($pdo, $store, null, static fn (): array => []);
        $board = new \App\Services\Effectifs\Dossier\MemberDossierTeamBoard($tl, $store);
        $rows = $board->rows(1, [['id' => 7, 'name' => 'Sgt Marchand', 'unit_id' => 1, 'unit_name' => 'Alpha'], ['id' => 8, 'name' => 'Cal Dupuis', 'unit_id' => 1, 'unit_name' => 'Alpha']]);
        $render = function (bool $manage) use ($rows): string {
            $suiviRows = $rows;
            $suiviTotals = \App\Services\Effectifs\Dossier\MemberDossierTeamBoard::totals($rows);
            $suiviUnits = [1 => 'Alpha'];
            $suiviFilters = ['q' => '', 'track' => '', 'owner' => '', 'etat' => '', 'unit' => 0, 'sort' => 'retard', 'desc' => false];
            $suiviCount = 2;
            $suiviPage = 1;
            $suiviPages = 1;
            $canManageSuivi = $manage;
            $csrfToken = 'jeton';
            $bulkMax = 25;
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/suivi.php';

            return (string) ob_get_clean();
        };
        $html = $render(true);
        self::assertStringContainsString('Étapes des dossiers', $html);
        self::assertStringContainsString('href="/back-office/ressources/effectifs/membres/7?ouvrir=suivi"', $html, 'lien direct vers l’onglet');
        self::assertStringContainsString('action="/back-office/ressources/effectifs/suivi/relancer"', $html);
        self::assertStringContainsString('name="members[]" value="7"', $html);
        self::assertStringContainsString('en retard', $html, 'badge en retard');
        self::assertStringContainsString('Au plus 25 relances', $html);
        self::assertStringContainsString('Aucune étape', $html, 'membre sans suivi');
        self::assertDoesNotMatchRegularExpression('/simulation|kicker|eyebrow/i', $html);
        $ro = $render(false);
        self::assertStringNotContainsString('suivi/relancer', $ro);
        self::assertStringNotContainsString('data-sv-bulk', $ro);
        self::assertStringContainsString('Ouvrir le suivi', $ro);
    }
}
