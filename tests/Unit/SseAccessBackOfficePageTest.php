<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminSseAccessController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Back-office › Accès renseignement (centre de gestion des accès) : non-régression des onglets de codes repris de la
 * première refonte (bande, onglets, délivrance en fenêtre) : rendu réel de la vue avec et sans données, plafond de niveau,
 * code affiché une seule fois, droits ; puis les POST existants (délivrer, révoquer) contre une base SQLite en mémoire :
 * mêmes routes, même CSRF, même journal. Les onglets du centre (personnels, documents, SSE) : SseAccessHubTest.
 */
final class SseAccessBackOfficePageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TOKEN = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];
    /** @var array<string, mixed> */
    private array $savedServer = [];
    /** @var array<string, mixed> */
    private array $savedPost = [];
    private ?PDO $savedPdo = null;
    private PDO $pdo;

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $this->savedServer = $_SERVER;
        $this->savedPost = $_POST;
        $_SESSION = ['_csrf_token' => self::TOKEN, 'tenant_id' => 1, 'user_id' => 7];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization', 'atak.sse.grant', 'atak.sse.clearance.confidentiel']);

        $this->savedPdo = (new \ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $this->pdo->exec("CREATE TABLE sse_access_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT NOT NULL, code_hash TEXT NOT NULL, code_hint TEXT NOT NULL DEFAULT '',
            label TEXT NOT NULL DEFAULT '', grant_type TEXT NOT NULL DEFAULT 'member', clearance_level TEXT NOT NULL DEFAULT 'interne', case_id INT NULL, created_by INT NULL,
            expires_at TEXT NOT NULL, session_ttl_minutes INT NOT NULL DEFAULT 240, max_uses INT NOT NULL DEFAULT 1, uses_count INT NOT NULL DEFAULT 0, revoked_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE sse_access_grants_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT NOT NULL, access_code_id INT NULL, case_id INT NULL,
            actor_user_id INT NULL, actor_label TEXT NULL, event_type TEXT NOT NULL DEFAULT 'redeem', detail TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        $_SERVER = $this->savedServer;
        $_POST = $this->savedPost;
        Gate::reset();
    }

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
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

        return $render(self::ROOT . '/views/admin/sse_access/index.php', $vars + [
            'cases' => [['id' => 3, 'reference_code' => 'SSE-2026-014', 'title' => 'Réseau nord']],
            'canManage' => true,
            'activeTab' => 'codes',
            'maxLevel' => 'confidentiel',
            'clearanceOrigin' => 'Habilitation accordée à votre compte.',
        ]);
    }

    /** @return array<string, mixed> */
    private function filled(): array
    {
        $future = gmdate('Y-m-d H:i:s', time() + 7200);
        $past = gmdate('Y-m-d H:i:s', time() - 86400);

        return [
            'codes' => [
                ['id' => 9, 'label' => 'Liaison Ranger', 'grant_type_label' => 'Invité', 'clearance_level' => 'encadrement', 'code_hint' => 'K7QM···', 'expires_at' => $future, 'max_uses' => 3, 'uses_count' => 1, 'active' => true, 'status' => 'active', 'status_label' => 'Actif'],
                ['id' => 8, 'label' => 'Ancien briefing', 'grant_type_label' => 'Membre habilité', 'clearance_level' => 'interne', 'code_hint' => 'ZP4D···', 'expires_at' => $past, 'max_uses' => 1, 'uses_count' => 0, 'active' => false, 'status' => 'expired', 'status_label' => 'Expiré'],
                ['id' => 7, 'label' => 'Observateur', 'grant_type_label' => 'Invité', 'clearance_level' => 'tres_restreint', 'code_hint' => 'H3VN···', 'expires_at' => $future, 'max_uses' => 2, 'uses_count' => 0, 'active' => false, 'status' => 'revoked', 'status_label' => 'Révoqué'],
            ],
            'actionLog' => [
                ['event_type' => 'redeem', 'code_id' => 9, 'event_label' => 'Code utilisé', 'detail' => 'session_invite', 'actor' => 'Lt. Marsh', 'created_at' => '2026-11-16 10:24:00'],
                ['event_type' => 'issue', 'code_id' => 9, 'event_label' => 'Code délivré', 'detail' => 'Liaison Ranger', 'actor' => null, 'created_at' => '2026-11-16 09:00:00'],
            ],
        ];
    }

    private static function hits(string $pattern, string $html): int
    {
        return preg_match_all($pattern, $html);
    }

    public function testHeaderBandTabsAndKeyFigures(): void
    {
        $html = $this->render($this->filled());

        self::assertSame(1, self::hits('/<h1\b/', $html), 'un seul titre de page, dans la bande');
        self::assertStringContainsString('bo-band bo-band--hero', $html);
        self::assertMatchesRegularExpression('#<h1 class="bo-band__title" id="ath-page-title">Accès renseignement</h1>#', $html);
        self::assertSame(1, self::hits('/class="bo-band__line"/', $html), 'une seule phrase d’explication');
        foreach (['Codes valides' => 1, 'Votre plafond' => 'S'] as $label => $n) {
            self::assertMatchesRegularExpression('#<dt>' . $label . '</dt><dd><strong>' . $n . '</strong></dd>#u', $html);
        }
        self::assertStringNotContainsString('Membres avec accès', $html, 'sans le droit de gestion SSE, pas de chiffres de membres');
        self::assertMatchesRegularExpression('#bo-band__btn bo-band__btn--solid" href="[^"]*atak/sse">Ouvrir le portail#', $html);
        self::assertMatchesRegularExpression('#class="bo-band__btn" href="[^"]*atak/sse/commandement">Entrer comme commandement#', $html);
        self::assertMatchesRegularExpression('#class="sseacc-hero__link" href="[^"]*atak/sse/droits">Droits d’accès par section#u', $html);

        self::assertSame(1, self::hits('/role="tablist"/', $html));
        self::assertSame(7, self::hits('/role="tab"/', $html), 'sans le droit de gestion SSE, pas d’onglet Personnels');
        self::assertSame(7, self::hits('/role="tabpanel"/', $html));
        self::assertSame(6, self::hits('/role="tabpanel"[^>]*\shidden>/', $html), 'un seul onglet ouvert');
        self::assertMatchesRegularExpression('/id="sseacc-tab-codes"[^>]*aria-selected="true"/', $html);
        foreach (['04' => 'Codes émis', '05' => 'Délivrer un code', '06' => 'Niveaux de diffusion', '07' => 'Journal'] as $i => $title) {
            self::assertMatchesRegularExpression('#<span class="bo-section__index" aria-hidden="true">/ ' . $i . '</span>\s*<h2 class="bo-section__title">' . $title . '</h2>#u', $html);
        }
        // Onglet choisi par l'adresse (repli sans JavaScript).
        $journal = $this->render(['activeTab' => 'journal'] + $this->filled());
        self::assertMatchesRegularExpression('/id="sseacc-tab-journal"[^>]*aria-selected="true"/', $journal);
        self::assertMatchesRegularExpression('/id="sseacc-panel-codes"[^>]*\shidden>/', $journal);

        // Tableau des codes : état, niveau, dernier usage lu dans le journal.
        self::assertSame(3, self::hits('/<tr data-sseacc-row data-status=/', $html));
        self::assertStringContainsString('sseacc-state sseacc-state--revoked">Révoqué', $html);
        self::assertStringContainsString('sseacc-state sseacc-state--expired">Expiré', $html);
        self::assertStringContainsString('Dernier usage <span class="sseacc-num">16/11/2026 10:24</span>', $html);
        self::assertStringContainsString('Jamais utilisé', $html);
        // Journal : libellé du code et détail lisible, pas de valeur brute.
        self::assertStringContainsString('Session invité', $html);
        self::assertStringNotContainsString('session_invite<', $html);

        // Consignes d'interface : pas de surtitre ni de mention de fiction.
        self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline|tracking-widest|uppercase/i', $html);
        self::assertDoesNotMatchRegularExpression('/fiction|simulation|usage RP/i', $html);
    }

    public function testIssueDialogHasFieldsCeilingAndCsrf(): void
    {
        $html = $this->render($this->filled());

        self::assertMatchesRegularExpression('#<dialog class="bo-dialog sseacc-dialog" id="sseacc-issue" aria-labelledby="sseacc-issue-title">#', $html);
        self::assertSame(2, self::hits('/data-bo-dialog-open="sseacc-issue"/', $html), 'ouverture depuis l’onglet Codes et l’onglet Délivrer');
        preg_match('#<dialog[^>]*id="sseacc-issue".*?</dialog>#s', $html, $m);
        $dialog = $m[0] ?? '';
        self::assertMatchesRegularExpression('#<form method="post" action="[^"]*back-office/renseignement/acces" class="bo-dialog__form"#', $dialog);
        self::assertStringContainsString('name="_csrf_token" value="' . self::TOKEN . '"', $dialog);
        foreach (['Qui', 'Niveau de lecture accordé', 'Validité'] as $legend) {
            self::assertStringContainsString('<legend class="sseacc-fs__title">' . $legend . '</legend>', $dialog);
        }
        self::assertStringContainsString('Récapitulatif', $dialog);
        self::assertMatchesRegularExpression('/<input type="text" name="label" required maxlength="120"/', $dialog);
        self::assertMatchesRegularExpression('/name="grant_type" value="member" checked/', $dialog);
        self::assertMatchesRegularExpression('/name="grant_type" value="guest"/', $dialog);
        foreach (['ttl_hours', 'max_uses', 'session_ttl_minutes', 'case_id'] as $field) {
            self::assertMatchesRegularExpression('/<select name="' . $field . '"/', $dialog);
        }
        self::assertStringContainsString('SSE-2026-014', $dialog);

        // Plafond : au-dessus de l'habilitation (ici Secret), le niveau est grisé.
        self::assertSame(4, self::hits('/name="clearance_level"/', $dialog));
        self::assertMatchesRegularExpression('/value="tres_restreint"[^>]*disabled/', $dialog);
        self::assertDoesNotMatchRegularExpression('/value="confidentiel"[^>]*disabled/', $dialog);
        self::assertMatchesRegularExpression('/value="interne" checked/', $dialog);
        self::assertStringContainsString('Au-dessus de votre habilitation.', $dialog);

        $floor = $this->render(['maxLevel' => 'interne'] + $this->filled());
        self::assertSame(3, self::hits('/name="clearance_level" value="[a-z_]+"[^>]*disabled/', $floor));
    }

    public function testIssuedCodeIsShownOnceInALiveDialog(): void
    {
        $with = $this->render(['issuedPlain' => 'K7QM82XD'] + $this->filled());
        preg_match('#<dialog[^>]*id="sseacc-issued".*?</dialog>#s', $with, $m);
        $dialog = $m[0] ?? '';
        self::assertNotSame('', $dialog);
        self::assertStringContainsString('data-sseacc-autoopen', $dialog);
        self::assertMatchesRegularExpression('/role="status" aria-live="polite"[^>]*>.*K7QM82XD/s', $dialog);
        self::assertStringContainsString('data-sseacc-copy="sseacc-code-value"', $dialog);
        self::assertStringContainsString('À communiquer par un canal sécurisé', $dialog);
        self::assertSame(1, substr_count($with, 'K7QM82XD'), 'le code n’apparaît qu’à un endroit');

        $without = $this->render($this->filled());
        self::assertStringNotContainsString('id="sseacc-issued"', $without);
        self::assertStringNotContainsString('K7QM82XD', $without);
    }

    public function testEmptyStates(): void
    {
        $html = $this->render(['codes' => [], 'actionLog' => []]);

        self::assertStringContainsString('Aucun code délivré', $html);
        self::assertStringContainsString('Aucun événement d’accès', $html);
        self::assertStringNotContainsString('<table class="sseacc-table">', $html);
        self::assertStringNotContainsString('data-sseacc-filter', $html);
        self::assertMatchesRegularExpression('#<dt>Codes valides</dt><dd><strong>0</strong></dd>#u', $html);
        // L'état vide porte l'action ; l'en-tête de section ne la répète pas.
        preg_match('#id="sseacc-panel-codes".*?</section>#s', $html, $m);
        self::assertSame(1, substr_count($m[0] ?? '', 'data-bo-dialog-open="sseacc-issue"'));
        // Échelle des niveaux toujours expliquée, avec lien vers la fiche effectifs.
        preg_match('#id="sseacc-panel-niveaux".*?</section>#s', $html, $lv);
        self::assertSame(4, self::hits('/<tr( class="is-cap")?>\s*<th scope="row">/', $lv[0] ?? ''));
        self::assertStringContainsString('Ouvrir les effectifs', $html);
    }

    public function testWithoutRightsNoAction(): void
    {
        Gate::reset();
        Gate::getInstance()->setPermissions([]);
        $html = $this->render(['canManage' => false] + $this->filled());

        self::assertStringNotContainsString('data-bo-dialog-open', $html);
        self::assertStringNotContainsString('id="sseacc-issue"', $html);
        self::assertStringNotContainsString('/revoquer', $html);
        self::assertStringNotContainsString('atak/sse/droits', $html);
        self::assertStringNotContainsString('Journal d’audit complet', $html);
        self::assertStringContainsString('Seul le commandement peut délivrer un code.', $html);
    }

    private function controller(): AdminSseAccessController
    {
        $access = new SseAccessCodeService(new SseAccessCodeRepository(new Database()));

        return new AdminSseAccessController($access, new SseAccessCodeRepository(new Database()), new SseClearanceService($access));
    }

    /** @param array<string, mixed> $post */
    private function post(array $post): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/back-office/renseignement/acces';
        $_POST = $post;

        return new Request();
    }

    private static function location(Response $r): string
    {
        return (string) $r->headerValue('Location');
    }

    public function testIssuePostStoresHashLogsAndFlashesTheCodeOnce(): void
    {
        $r = $this->controller()->issue($this->post([
            '_csrf_token' => self::TOKEN, 'label' => 'Liaison Ranger', 'grant_type' => 'guest', 'ttl_hours' => '24',
            'session_ttl_minutes' => '120', 'max_uses' => '3', 'case_id' => '', 'clearance_level' => 'confidentiel',
        ]));

        self::assertStringEndsWith('back-office/renseignement/acces', self::location($r));
        $plain = Session::getFlash('sse_issued_code');
        self::assertIsString($plain);
        self::assertSame(8, strlen($plain));
        self::assertNull(Session::getFlash('sse_issued_code'), 'affiché une seule fois');
        $row = $this->pdo->query('SELECT * FROM sse_access_codes')->fetch();
        self::assertSame(hash('sha256', $plain), $row['code_hash']);
        self::assertSame(substr($plain, 0, 4) . '···', $row['code_hint']);
        self::assertSame(['Liaison Ranger', 'guest', 'confidentiel', 3, 120], [$row['label'], $row['grant_type'], $row['clearance_level'], (int) $row['max_uses'], (int) $row['session_ttl_minutes']]);
        self::assertStringNotContainsString($plain, json_encode((new SseAccessCodeRepository(new Database()))->listActiveForTenant(1)) ?: '');
        $log = $this->pdo->query('SELECT event_type, access_code_id, detail FROM sse_access_grants_log')->fetchAll();
        self::assertSame([['event_type' => 'issue', 'access_code_id' => (int) $row['id'], 'detail' => 'Liaison Ranger']], array_map(static fn (array $l): array => ['event_type' => $l['event_type'], 'access_code_id' => (int) $l['access_code_id'], 'detail' => $l['detail']], $log));
    }

    public function testIssueAboveCeilingOrWithoutCsrfIsRefused(): void
    {
        $this->controller()->issue($this->post(['_csrf_token' => self::TOKEN, 'label' => 'Trop haut', 'clearance_level' => 'tres_restreint']));
        self::assertSame('Vous ne pouvez pas accorder une habilitation plus haute que la vôtre.', Session::getFlash('error'));

        $r = $this->controller()->issue($this->post(['_csrf_token' => 'faux', 'label' => 'Sans jeton', 'clearance_level' => 'interne']));
        self::assertStringEndsWith('back-office/renseignement/acces', self::location($r));
        self::assertSame('Session expirée. Réessayez.', Session::getFlash('error'));

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_access_codes')->fetchColumn());
        self::assertNull(Session::getFlash('sse_issued_code'));
    }

    public function testRevokePostRevokesAndLogs(): void
    {
        $this->controller()->issue($this->post(['_csrf_token' => self::TOKEN, 'label' => 'À révoquer', 'clearance_level' => 'interne']));
        $id = (int) $this->pdo->query('SELECT id FROM sse_access_codes')->fetchColumn();

        $this->controller()->revoke($this->post(['_csrf_token' => 'faux']), ['id' => (string) $id]);
        self::assertSame('Action non autorisée.', Session::getFlash('error'));
        self::assertNull($this->pdo->query('SELECT revoked_at FROM sse_access_codes')->fetchColumn() ?: null);

        $this->controller()->revoke($this->post(['_csrf_token' => self::TOKEN]), ['id' => (string) $id]);
        self::assertSame('Code révoqué.', Session::getFlash('success'));
        $codes = (new SseAccessCodeRepository(new Database()))->listActiveForTenant(1);
        self::assertSame(['revoked', false], [$codes[0]['status'], $codes[0]['active']]);
        $log = (new SseAccessCodeRepository(new Database()))->listLogForTenant(1);
        self::assertSame(['revoke', $id], [$log[0]['event_type'], $log[0]['code_id']]);
    }

    public function testWithoutRightEveryRouteRedirectsAndNothingIsWritten(): void
    {
        Gate::reset();
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $c = $this->controller();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        foreach ([
            fn (): Response => $c->index(new Request()),
            fn (): Response => $c->issue($this->post(['_csrf_token' => self::TOKEN, 'label' => 'x', 'clearance_level' => 'interne'])),
            fn (): Response => $c->revoke($this->post(['_csrf_token' => self::TOKEN]), ['id' => '1']),
        ] as $call) {
            $r = $call();
            self::assertStringEndsWith('back-office', self::location($r));
            self::assertSame('Seul le commandement peut gérer les accès au renseignement.', Session::getFlash('error'));
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_access_codes')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_access_grants_log')->fetchColumn());
    }

    public function testRoutesAndControllerWiringAreUnchanged(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("\$router->get('/back-office/renseignement/acces', [\\App\\Controllers\\Admin\\AdminSseAccessController::class, 'index']", $routes);
        self::assertStringContainsString("\$router->post('/back-office/renseignement/acces', [\\App\\Controllers\\Admin\\AdminSseAccessController::class, 'issue']", $routes);
        self::assertStringContainsString("\$router->post('/back-office/renseignement/acces/{id}/revoquer', [\\App\\Controllers\\Admin\\AdminSseAccessController::class, 'revoke']", $routes);
        self::assertSame(['ensemble', 'personnels', 'documents', 'sse', 'codes', 'delivrer', 'niveaux', 'journal'], AdminSseAccessController::TABS);
        foreach (["'/back-office/renseignement/acces/personnels/habilitation', [\App\Controllers\Admin\AdminSseAccessController::class, 'peopleClearance']",
            "'/back-office/renseignement/acces/personnels/acces', [\App\Controllers\Admin\AdminSseAccessController::class, 'peopleAccess']",
            "'/back-office/renseignement/acces/personnels/{id}/regles/{ruleId}/retirer', [\App\Controllers\Admin\AdminSseAccessController::class, 'peopleRuleRevoke']"] as $route) {
            self::assertStringContainsString("\$router->post(" . $route, $routes);
        }
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/back-office-sse-access.css');
        self::assertDoesNotMatchRegularExpression('/sse-ds|Archivo|\.sse-[a-z]/', $css, 'pas le design system SSE rejeté');
        self::assertDoesNotMatchRegularExpression('/font-size:\s*(?:[0-9]|1[0-2])px/', $css, 'textes de 13 px au moins');
        self::assertStringContainsString('html[data-bo-theme="dark"]', $css);
    }
}
