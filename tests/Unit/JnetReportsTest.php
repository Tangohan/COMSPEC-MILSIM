<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAarReportsController;
use App\Controllers\Web\JnetReportsController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AarReportRepository;
use App\Repositories\AtakDebriefRepository;
use App\Repositories\UnitRepository;
use App\Services\Aar\AarReportAdminService;
use App\Services\Atak\AtakMobileHistoryService;
use App\Services\Community\TenantTypeConfig;
use App\Services\Jnet\JnetMemberHub;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Support\JnetMailSqlite as Db;

/**
 * JNET : les comptes rendus du back-office (/back-office/atak/comptes-rendus) dans l'extranet (/jnet/comptes-rendus).
 * Le vrai contrôleur, les vrais dépôts et la vraie coque JNET tournent sur une base sqlite : une communauté (1) avec un
 * gestionnaire (1, admin.organization), un chef de section (35, lecture ATAK sans droit d'administration) et un membre
 * (37) ; une autre communauté (2) dont rien ne doit sortir.
 *
 * Processus séparés : les dépôts gardent en mémoire statique la présence des tables de la base ouverte.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JnetReportsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private const MANAGER = 1;

    private const CHIEF = Db::KELLER;

    private const MEMBER = 37;

    /** Textes qui ne doivent apparaître qu'aux lecteurs du back-office. */
    private const POST_OP = ['Faucon pèlerin', 'Synthèse confidentielle du Faucon', 'Annexe secrète Gerfaut', 'Revoir le plan de feux'];

    /** Textes d'une autre communauté ou d'un autre joueur : jamais montrés. */
    private const NEVER = ['Opération Minuit étrangère', 'CR d’un autre joueur', 'CR de la communauté 2', 'Fin d’opération étrangère'];

    private PDO $pdo;

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/jnet/comptes-rendus';
        $this->pdo = Db::open('small');
        $this->schema();
        $this->seed();
    }

    protected function tearDown(): void
    {
        Gate::reset();
        Db::close();
        Db::signOut();
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // —— Droits : membre, chef, gestionnaire ——

    public function testMemberSeesOnlyWhatHeSentAndTheEndOfOpReportsAskedOfHim(): void
    {
        $this->signIn(self::MEMBER);
        $page = $this->get(['vue' => 'rapports']);

        self::assertSame(200, $page->statusCode());
        $html = $page->body();
        self::assertStringContainsString('Deux véhicules au carrefour', $html);
        self::assertStringContainsString('Lu par le poste de commandement', $html, 'état de réception de son envoi');
        self::assertStringContainsString('Fin d’opération Aigle noir', $html);
        self::assertStringContainsString('>Ouvert', $html, 'son état dans le CR imposé');
        // Une seule vue, même quand l'adresse en demande une autre ; aucun compte rendu post-op, d'aucune sorte.
        self::assertSame(['mes'], self::viewTabs($html));
        foreach (array_merge(self::POST_OP, self::NEVER, ['Tempête de sable', 'AAR-0001', 'Ouvrir dans le back-office']) as $hidden) {
            self::assertStringNotContainsString($hidden, $html, $hidden);
        }
    }

    public function testChiefWithAtakReadingButNoBackOfficeRightIsTreatedAsAMember(): void
    {
        $this->signIn(self::CHIEF, ['atak.terminals.view', 'operations.missions.view', 'operations.aar.view']);
        $html = $this->get(['vue' => 'actions'])->body();

        self::assertSame(['mes'], self::viewTabs($html));
        foreach (array_merge(self::POST_OP, self::NEVER) as $hidden) {
            self::assertStringNotContainsString($hidden, $html, $hidden);
        }
        self::assertSame(404, $this->show(1)->statusCode(), 'le back-office ne lui montre pas ce compte rendu');
    }

    public function testManagerSeesEveryReportOfHisCommunityAndNothingElse(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);
        $html = $this->get(['vue' => 'rapports'])->body();

        self::assertSame(['mes', 'rapports', 'actions', 'terrain'], self::viewTabs($html));
        self::assertStringContainsString('Faucon pèlerin', $html);
        self::assertStringContainsString('Tempête de sable', $html);
        self::assertStringContainsString('href="' . url('back-office/atak/comptes-rendus') . '"', $html, 'lien vers la page du back-office');
        foreach (self::NEVER as $hidden) {
            self::assertStringNotContainsString($hidden, $html, $hidden);
        }
        // Mêmes nombres que le back-office : mêmes services sur les mêmes lignes.
        $all = (new AarReportRepository($this->pdo))->listForTenant(Db::TENANT);
        self::assertCount(2, $all);
        self::assertSame(1, AarReportAdminService::kpis($all)['to_review']);
        self::assertMatchesRegularExpression('~<b>1</b>\s*<span>à relire</span>~', $html);
    }

    // —— Liste : filtres, recherche, tri ——

    public function testFiltersSearchAndSortUseTheBackOfficeService(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);
        $titles = fn (array $q): array => self::rowTitles($this->get(['vue' => 'rapports'] + $q)->body());

        self::assertSame(['Tempête de sable', 'Faucon pèlerin'], $titles([]), 'plus récents d’abord');
        self::assertSame(['Faucon pèlerin', 'Tempête de sable'], $titles(['tri' => 'ancien']));
        self::assertSame(['Faucon pèlerin', 'Tempête de sable'], $titles(['tri' => 'statut']), 'à traiter d’abord');
        self::assertSame(['Tempête de sable'], $titles(['statut' => 'validated']));
        self::assertSame(['Faucon pèlerin'], $titles(['unite' => 'Section Alpha']), 'unité de l’auteur');
        self::assertSame(['Tempête de sable'], $titles(['auteur' => 'Martin Dupont']));
        self::assertSame(['Faucon pèlerin'], $titles(['type' => 'questionnaire']));
        self::assertSame(['Tempête de sable'], $titles(['q' => 'tempête']));
        self::assertSame(['Faucon pèlerin'], $titles(['actions_ouvertes' => '1']));
        self::assertSame([], $titles(['periode' => '30', 'q' => 'faucon']), 'déposé il y a plus de 30 jours');
        // Valeurs inconnues : ignorées, pas d'erreur.
        self::assertSame(['Tempête de sable', 'Faucon pèlerin'], $titles(['statut' => 'x', 'type' => 'y', 'tri' => 'z', 'periode' => '7']));
    }

    // —— Lecture ——

    public function testReadingPageShowsContentAttachmentsHistoryAndBothCrossLinks(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);
        $page = $this->show(1);

        self::assertSame(200, $page->statusCode());
        $html = $page->body();
        foreach (['Faucon pèlerin', 'AAR-0001', 'Liam Keller', 'Section Alpha', 'Synthèse confidentielle du Faucon', 'Liaison radio tenue',
            'Retard au point de rendez-vous', 'Revoir le plan de feux', 'Annexe secrète Gerfaut', 'Historique', 'Déposé par Liam Keller',
            'Quelle météo ?', 'Brouillard'] as $text) {
            self::assertStringContainsString($text, $html, $text);
        }
        self::assertStringContainsString('href="' . url('back-office/atak/comptes-rendus/1') . '"', $html, 'Ouvrir dans le back-office');
        self::assertStringContainsString(url('jnet/courrier/nouveau') . '?membre=35&amp;cr=1', $html, 'Répondre par message à l’auteur');
        // Pièce jointe du terrain : servie par la route contrôlée des remontées, jamais par un chemin de fichier.
        self::assertStringContainsString('href="' . url('atak/sse/remontees/cr/10') . '"', $html);
        self::assertDoesNotMatchRegularExpression('~(storage|uploads)/~', $html);
        // Traitement dans une fenêtre, avec les transitions du back-office.
        self::assertMatchesRegularExpression('~<dialog class="jr-dlg" id="jr-status"~', $html);
        self::assertStringContainsString('Passer en relecture', $html);
        self::assertStringContainsString('Marquer manquant', $html);

        // La fiche du back-office renvoie vers le JNET.
        $_SERVER['REQUEST_URI'] = '/back-office/atak/comptes-rendus/1';
        // La coque du back-office journalise les pastilles de son menu absentes de cette base : hors de la sortie du test.
        $log = tempnam(sys_get_temp_dir(), 'jr');
        $previousLog = ini_set('error_log', (string) $log);
        try {
            $bo = (new AdminAarReportsController(new AarReportRepository($this->pdo)))->show(new Request(), ['id' => '1'])->body();
        } finally {
            ini_set('error_log', (string) $previousLog);
            @unlink((string) $log);
        }
        self::assertStringContainsString('href="' . url('jnet/comptes-rendus/1') . '"', $bo);
        self::assertStringContainsString('Ouvrir dans le JNET', $bo);
    }

    public function testNotFoundIsTheSameForAbsentForeignAndForbiddenReports(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);
        $absent = $this->show(999);
        $foreign = $this->show(3);
        self::assertSame(404, $absent->statusCode());
        self::assertSame(404, $foreign->statusCode());
        self::assertStringNotContainsString('Minuit', $foreign->body());

        $this->signIn(self::MEMBER);
        $forbidden = $this->show(1);
        $missing = $this->show(999);
        self::assertSame(404, $forbidden->statusCode());
        self::assertSame($missing->body(), $forbidden->body(), 'on ne révèle pas qu’il existe');
        foreach (self::POST_OP as $hidden) {
            self::assertStringNotContainsString($hidden, $forbidden->body(), $hidden);
        }
    }

    // —— Actions ——

    public function testManagerActionsGoThroughTheBackOfficeAndComeBackToJnet(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);

        $moved = $this->post('status', 1, ['status' => 'in_review']);
        self::assertSame(302, $moved->statusCode());
        self::assertSame(url('jnet/comptes-rendus/1'), $moved->headerValue('Location'));
        self::assertSame('in_review', $this->column(1, 'status'));
        self::assertSame('Compte rendu passé en relecture.', $_SESSION['_flash']['success'] ?? null);

        // Transition refusée par la règle du back-office : rien ne change.
        $_SESSION['_flash'] = [];
        $this->post('status', 1, ['status' => 'missing']);
        self::assertSame('in_review', $this->column(1, 'status'));
        self::assertNotEmpty($_SESSION['_flash']['error'] ?? null);

        $closed = $this->post('toggleAction', 1, ['state' => 'open', 'index' => '0', 'retour' => 'suivi']);
        self::assertSame(url('jnet/comptes-rendus') . '?vue=actions', $closed->headerValue('Location'));
        self::assertSame('[]', $this->column(1, 'open_actions_json'));
        self::assertStringContainsString('Revoir le plan de feux', (string) $this->column(1, 'closed_actions_json'));

        // Jeton absent : rien n'est modifié.
        $_SESSION['_flash'] = [];
        $stale = $this->post('status', 1, ['status' => 'validated'], 'perime');
        self::assertSame(302, $stale->statusCode());
        self::assertSame('in_review', $this->column(1, 'status'));
        self::assertNotEmpty($_SESSION['_flash']['error'] ?? null);

        self::assertSame(404, $this->post('status', 3, ['status' => 'in_review'])->statusCode(), 'compte rendu d’une autre communauté');
        self::assertSame('pending', $this->column(3, 'status'));
    }

    public function testMembersAndChiefsCannotActWhetherTheReportExistsOrNot(): void
    {
        foreach ([[self::MEMBER, []], [self::CHIEF, ['atak.terminals.view', 'operations.aar.view']]] as [$user, $perms]) {
            $this->signIn($user, $perms);
            self::assertSame(403, $this->post('status', 1, ['status' => 'validated'])->statusCode());
            self::assertSame(403, $this->post('status', 999, ['status' => 'validated'])->statusCode());
            self::assertSame(403, $this->post('toggleAction', 1, ['state' => 'open', 'index' => '0'])->statusCode());
        }
        self::assertSame('pending', $this->column(1, 'status'));
        self::assertStringContainsString('Revoir le plan de feux', (string) $this->column(1, 'open_actions_json'));
    }

    public function testReplyByMessageIsPreparedOnlyForReaders(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);
        $_GET = ['membre' => '35', 'cr' => '1'];
        $html = Db::controller($this->pdo)->mailCompose(new Request())->body();
        self::assertStringContainsString('Compte rendu AAR-0001 — Faucon pèlerin', $html);
        self::assertStringContainsString(url('jnet/comptes-rendus/1'), $html);

        $this->signIn(self::MEMBER);
        $_GET = ['cr' => '1'];
        $member = Db::controller($this->pdo)->mailCompose(new Request())->body();
        self::assertStringNotContainsString('Faucon', $member);
    }

    // —— Menu ——

    public function testMenuEntryIsActiveCountsWhatWaitsAndHasNoOrphanLink(): void
    {
        $this->signIn(self::MANAGER, ['admin.organization']);
        $list = $this->get([])->body();
        $show = $this->show(1)->body();
        $routes = self::getRoutes();

        foreach (['liste' => $list, 'fiche' => $show] as $label => $html) {
            $tabs = self::headerTabs($html);
            self::assertArrayHasKey('Comptes rendus', $tabs, $label);
            self::assertTrue($tabs['Comptes rendus']['current'], $label . ' : rubrique allumée');
            self::assertSame(1, count(array_filter($tabs, static fn (array $t): bool => $t['current'])), $label);
            foreach ($tabs as $name => $tab) {
                self::assertContains($tab['path'], $routes, $label . ' : lien orphelin « ' . $name . ' » → ' . $tab['path']);
            }
        }
        self::assertSame('1', self::headerTabs($list)['Comptes rendus']['count'], 'un compte rendu à relire');

        // Membre : la pastille compte le CR de fin d'opération qu'il doit remettre.
        $this->signIn(self::MEMBER);
        self::assertSame('1', self::headerTabs($this->get([])->body())['Comptes rendus']['count']);

        // Menu latéral et « Mon extranet » : même adresse ; rubrique du module ATAK pour les profils de communauté.
        self::assertStringContainsString("url('jnet/comptes-rendus')", (string) file_get_contents(self::ROOT . 'views/partials/ath_sidebar_nav.php'));
        $hub = [];
        foreach (JnetMemberHub::sections(['tenantType' => 'full', 'badges' => ['reports_pending' => 2]]) as $section) {
            foreach ($section['items'] as $item) {
                $hub[$item['path']] = $item['badge'];
            }
        }
        self::assertSame(2, $hub['jnet/comptes-rendus'] ?? null);
        self::assertSame('atak', TenantTypeConfig::moduleForUri('jnet/comptes-rendus/12'));
        self::assertSame(TenantTypeConfig::moduleForUri('back-office/atak/comptes-rendus'), TenantTypeConfig::moduleForUri('jnet/comptes-rendus'));
    }

    // —— Interface ——

    public function testInterfaceFollowsTheJnetRulesAndIsTranslated(): void
    {
        $views = (string) file_get_contents(self::ROOT . 'views/jnet/reports.php') . (string) file_get_contents(self::ROOT . 'views/jnet/report_show.php');
        $css = (string) file_get_contents(self::ROOT . 'public/assets/css/jnet_reports.css');
        foreach (['sse-ds', 'Archivo', 'class="sse-', 'kicker', 'eyebrow', 'fiction', 'simulation', ' RP '] as $banned) {
            self::assertStringNotContainsStringIgnoringCase($banned, $views . $css, $banned);
        }
        self::assertStringContainsString('<dialog', $views);
        self::assertStringContainsString('aria-labelledby', $views);
        // Aucun texte sous 13 px, aucune cible sous 44 px.
        preg_match_all('~font-size:\s*(\d+(?:\.\d+)?)px~', $css, $sizes);
        foreach ($sizes[1] as $size) {
            self::assertGreaterThanOrEqual(13, (float) $size, 'font-size ' . $size . 'px');
        }
        preg_match_all('~min-height:\s*(\d+)px~', $css, $heights);
        self::assertContains('44', $heights[1]);
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        // Les couleurs suivent le thème de l'extranet (jetons), clair comme sombre.
        self::assertStringNotContainsString('#050505', $css);
        self::assertStringContainsString('var(--ja-c1)', $css);

        $dictionary = require self::ROOT . 'lang/en/site_auto.php';
        foreach (['Mes comptes rendus', 'Envoyés depuis mon ATAK', 'CR de fin d’opération', 'Suivi des actions', 'Remontées du terrain',
            'Ouvrir dans le back-office', 'Ouvrir dans le JNET', 'Répondre par message', 'Pièces jointes', 'Historique', 'Traiter',
            'Plus récents', 'À traiter d’abord', 'Formulaire standard'] as $text) {
            self::assertArrayHasKey($text, $dictionary, $text);
            self::assertNotSame($text, $dictionary[$text], $text);
        }
    }

    // —— Outils ——

    /** @param list<string> $permissions */
    private function signIn(int $userId, array $permissions = []): void
    {
        Db::signIn($userId);
        Gate::reset();
        Gate::getInstance()->setPermissions($permissions);
    }

    private function controller(): JnetReportsController
    {
        return new JnetReportsController(
            Db::controller($this->pdo),
            new AarReportRepository($this->pdo),
            new AtakMobileHistoryService($this->pdo),
            new AtakDebriefRepository($this->pdo),
            new UnitRepository($this->pdo),
        );
    }

    /** @param array<string, string> $query */
    private function get(array $query): Response
    {
        $_GET = $query;
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        return $this->controller()->index(new Request());
    }

    private function show(int $id): Response
    {
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        return $this->controller()->show(new Request(), ['id' => (string) $id]);
    }

    /** @param array<string, string> $input */
    private function post(string $method, int $id, array $input, string $token = 'jeton'): Response
    {
        $_GET = [];
        $_POST = $input + ['_csrf_token' => $token];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = $this->controller()->{$method}(new Request(), ['id' => (string) $id]);
        $_POST = [];

        return $response;
    }

    private function column(int $id, string $column): mixed
    {
        return $this->pdo->query('SELECT ' . $column . ' FROM aar_reports WHERE id = ' . $id)->fetchColumn();
    }

    /** @return list<string> vues proposées par la page (onglets ?vue=) */
    private static function viewTabs(string $html): array
    {
        preg_match('~<nav class="ja-tabs jr-tabs"[^>]*>(.*?)</nav>~s', $html, $nav);
        preg_match_all('~\?vue=([a-z]+)~', $nav[1] ?? '', $m);

        return $m[1];
    }

    /** @return list<string> titres des lignes de la liste des comptes rendus, dans l'ordre */
    private static function rowTitles(string $html): array
    {
        preg_match_all('~<a class="ja-row jr-row" href="[^"]*/jnet/comptes-rendus/\d+">.*?<span class="t"><b>(.*?)</b>~s', $html, $m);

        return array_map(static fn (string $t): string => html_entity_decode($t, ENT_QUOTES, 'UTF-8'), $m[1]);
    }

    /** @return array<string, array{path: string, current: bool, count: string}> rubriques de l'en-tête JNET */
    private static function headerTabs(string $html): array
    {
        preg_match('~<nav class="ja-tabs" aria-label="Rubriques de l’extranet">(.*?)</nav>~s', $html, $nav);
        preg_match_all('~<a class="ja-tab[^"]*" href="([^"]+)"([^>]*)><span class="ja-mono">\d+</span>(.*?)(?:<em>(\d+)</em>)?</a>~s', $nav[1] ?? '', $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $tab) {
            $path = trim((string) parse_url(html_entity_decode($tab[1]), PHP_URL_PATH), '/');
            $base = trim((string) parse_url(url(''), PHP_URL_PATH), '/');
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = trim(substr($path, strlen($base)), '/');
            }
            $out[html_entity_decode(trim(strip_tags($tab[3])), ENT_QUOTES, 'UTF-8')] = [
                'path' => $path,
                'current' => str_contains($tab[2], 'aria-current="page"'),
                'count' => $tab[4] ?? '',
            ];
        }

        return $out;
    }

    /** @return list<string> chemins des routes GET déclarées (sans slash initial) */
    private static function getRoutes(): array
    {
        preg_match_all("~\\\$router->get\('/([^'{]*)'~", (string) file_get_contents(self::ROOT . 'routes/web.php'), $m);

        return array_values(array_unique(array_map(static fn (string $p): string => trim($p, '/'), $m[1])));
    }

    private function schema(): void
    {
        foreach ([
            'CREATE TABLE aar_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, mission_cycle_id INTEGER, mission_plan_id INTEGER,
                template_id INTEGER, author_user_id INTEGER, validated_by_user_id INTEGER, title TEXT NOT NULL, operation_label TEXT,
                status TEXT NOT NULL DEFAULT \'pending\', reported_at TEXT, validated_at TEXT, mission_started_at TEXT, mission_ended_at TEXT,
                summary_text TEXT, strengths_json TEXT, weaknesses_json TEXT, open_actions_json TEXT, closed_actions_json TEXT, scores_json TEXT,
                metrics_json TEXT, custom_answers_json TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE aar_report_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, created_by_user_id INTEGER, title TEXT,
                description TEXT, status TEXT, fields_json TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE aar_report_annexes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, report_id INTEGER, kind TEXT, source_id INTEGER,
                title TEXT, body TEXT, sort_order INTEGER DEFAULT 0)',
            'CREATE TABLE theatre_mission_cycles (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1, title TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'preparation\', started_at TEXT, ended_at TEXT, aar_summary TEXT, replay_mission_id TEXT NOT NULL DEFAULT \'\',
                created_by_user_id INTEGER, closed_by_user_id INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE atak_tactical_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL,
                report_type TEXT, report_number TEXT, priority TEXT, submitter_user_id INTEGER, submitter_callsign TEXT, submitter_steam_id TEXT,
                grid_reference TEXT, summary TEXT, details TEXT, remarks TEXT, structured_data TEXT, status TEXT, report_timestamp TEXT,
                created_at TEXT, deleted_at TEXT)',
            'CREATE TABLE atak_admin_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, delivered_at TEXT, acked_at TEXT,
                cancelled_at TEXT, expires_at TEXT)',
            'CREATE TABLE atak_debriefs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, mission_key TEXT NOT NULL DEFAULT \'\',
                label TEXT NOT NULL, mode TEXT NOT NULL DEFAULT \'fill\', template_id INTEGER, aar_report_id INTEGER, intro TEXT, read_text TEXT,
                questions_json TEXT, source TEXT DEFAULT \'site\', game_ref TEXT, created_by_user_id INTEGER, created_by_label TEXT, created_at TEXT,
                expires_at TEXT, closed_at TEXT)',
            'CREATE TABLE atak_debrief_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, debrief_id INTEGER NOT NULL, tenant_id INTEGER NOT NULL,
                who_key TEXT NOT NULL, user_id INTEGER, steam_uid TEXT, callsign TEXT, message_id INTEGER, sent_at TEXT, delivered_at TEXT,
                opened_at TEXT, submitted_at TEXT, read_at TEXT, answers_json TEXT, report_id INTEGER)',
            'CREATE TABLE atak_debrief_settings (tenant_id INTEGER PRIMARY KEY, default_mode TEXT, template_id INTEGER, intro TEXT, zeus_allowed INTEGER,
                authorized_uids TEXT, updated_by_user_id INTEGER, updated_at TEXT)',
            'CREATE TABLE atak_debrief_ops (tenant_id INTEGER, mission_key TEXT, label TEXT, hidden_at TEXT, updated_by_user_id INTEGER, updated_at TEXT,
                PRIMARY KEY (tenant_id, mission_key))',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        // Les dépôts lisent la présence des tables et colonnes dans information_schema (servi par la base réelle).
        $insT = $this->pdo->prepare("INSERT INTO information_schema.TABLES VALUES ('jnet', ?)");
        $insC = $this->pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('jnet', ?, ?)");
        foreach (['aar_reports', 'aar_report_templates', 'aar_report_annexes', 'theatre_mission_cycles', 'atak_tactical_reports', 'atak_admin_messages',
            'atak_debriefs', 'atak_debrief_recipients', 'atak_debrief_settings', 'atak_debrief_ops'] as $table) {
            $insT->execute([$table]);
            foreach ($this->pdo->query('PRAGMA main.table_info("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
                $insC->execute([$table, (string) $col['name']]);
            }
        }
    }

    private function seed(): void
    {
        $old = gmdate('Y-m-d H:i:s', time() - 60 * 86400);
        $recent = gmdate('Y-m-d H:i:s', time() - 2 * 86400);
        $aar = $this->pdo->prepare('INSERT INTO aar_reports (id, tenant_id, author_user_id, title, operation_label, status, reported_at, summary_text,
            strengths_json, weaknesses_json, open_actions_json, closed_actions_json, custom_answers_json, template_id, created_at, updated_at, validated_at, validated_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $custom = json_encode(['fields' => [['id' => 'meteo', 'type' => 'text', 'label' => 'Quelle météo ?']], 'answers' => ['meteo' => 'Brouillard']], JSON_UNESCAPED_UNICODE);
        $aar->execute([1, Db::TENANT, self::CHIEF, 'Compte rendu Faucon', 'Faucon pèlerin', 'pending', $old, 'Synthèse confidentielle du Faucon',
            json_encode(['Liaison radio tenue']), json_encode(['Retard au point de rendez-vous']),
            json_encode([['label' => 'Revoir le plan de feux', 'owner' => 'KELLER', 'due_at' => '2026-01-10']]), '[]', $custom, 4, $old, $old, null, null]);
        $aar->execute([2, Db::TENANT, self::MANAGER, 'Compte rendu Tempête', 'Tempête de sable', 'validated', $recent, 'Bonne coordination.',
            '[]', '[]', '[]', '[]', null, null, $recent, $recent, $recent, self::MANAGER]);
        $aar->execute([3, 2, Db::FOREIGNER, 'CR étranger', 'Opération Minuit étrangère', 'pending', $recent, 'Secret de la communauté 2',
            '[]', '[]', '[]', '[]', null, null, $recent, $recent, null, null]);
        $this->pdo->exec("INSERT INTO aar_report_annexes (tenant_id, report_id, kind, source_id, title, body, sort_order) VALUES
            (1, 1, 'note', NULL, 'Annexe secrète Gerfaut', 'Croquis du dispositif.', 0),
            (1, 1, 'cr', 10, '', NULL, 1),
            (2, 3, 'note', NULL, 'Annexe de la communauté 2', 'Ne pas lire.', 0)");

        $cr = $this->pdo->prepare('INSERT INTO atak_tactical_reports (id, tenant_id, context_id, report_type, priority, submitter_user_id, submitter_callsign,
            grid_reference, summary, status, report_timestamp, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $cr->execute([10, Db::TENANT, 3, 'SPOTREP', 'ROUTINE', self::MEMBER, 'NOMAD', '123456', 'Deux véhicules au carrefour', 'ACKNOWLEDGED', $recent, $recent]);
        $cr->execute([11, Db::TENANT, 3, 'CONTACT', 'FLASH', 36, 'ÉCHO', '654321', 'CR d’un autre joueur', 'SUBMITTED', $recent, $recent]);
        $cr->execute([12, 2, 4, 'SITREP', 'ROUTINE', self::MEMBER, 'NOMAD', '', 'CR de la communauté 2', 'SUBMITTED', $recent, $recent]);

        $this->pdo->exec("INSERT INTO atak_debriefs (id, tenant_id, label, mode, created_at) VALUES (5, 1, 'Fin d’opération Aigle noir', 'fill', '{$recent}'),
            (6, 2, 'Fin d’opération étrangère', 'fill', '{$recent}')");
        $this->pdo->exec("INSERT INTO atak_debrief_recipients (debrief_id, tenant_id, who_key, user_id, callsign, sent_at, opened_at, submitted_at) VALUES
            (5, 1, 'u:37', 37, 'NOMAD', '{$recent}', '{$recent}', NULL),
            (5, 1, 'u:36', 36, 'ÉCHO', '{$recent}', '{$recent}', '{$recent}'),
            (6, 2, 'u:37', 37, 'NOMAD', '{$recent}', NULL, NULL)");
    }
}
