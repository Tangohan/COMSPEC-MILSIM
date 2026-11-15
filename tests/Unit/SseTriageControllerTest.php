<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseTriageController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseTriageEventRepository;
use App\Repositories\SseTriageRuleRepository;
use App\Repositories\SseTriageStateRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseTriageActions;
use App\Services\Sse\SseTriageInbox;
use App\Services\Sse\SseTriageSubjects;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseTriageSqlite;

/**
 * Écritures de « À trier » : POST seulement, jeton CSRF, compte identifié, droits (traiter = écrire dans « Analyse », règles = gérer),
 * communauté de la session, anti-IDOR (une clé qui n'est pas dans la liste de la session est refusée, rien n'est écrit), assignation
 * limitée aux comptes de la communauté qui peuvent traiter, journal d'audit.
 */
final class SseTriageControllerTest extends TestCase
{
    private PDO $pdo;
    private int $now;

    /** @var array<string, bool> niveaux accordés à la session : « section:niveau » */
    private array $grants = [];

    /** @var array<int, array<string, bool>> droits des autres comptes */
    private array $otherGrants = [];

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    private array $savedPost = [];
    private array $savedServer = [];

    protected function setUp(): void
    {
        ShortCache::setEnabled(false);
        $this->pdo = SseTriageSqlite::open(true);
        $this->now = time();
        $this->grants = ['analyse:write' => true, 'analyse:manage' => false, 'analyse:read' => true];
        $this->audit = [];
        $this->savedPost = $_POST;
        $this->savedServer = $_SERVER;
        Session::set('tenant_id', 1);
        Session::set('user_id', 7);
        Session::set(SseAccessCodeService::SESSION_GUEST, false);
        Session::set('display_name', 'Alice');
        SseTriageSqlite::addCase($this->pdo, 1, 'DOS-0001', 'Réseau du port');
        SseTriageSqlite::addCase($this->pdo, 2, 'DOS-0002', 'Autre communauté', 2);
        foreach ([[1, 1, 'Photographie manquante'], [2, 1, 'Biométrie manquante'], [3, 2, 'Photographie manquante']] as [$id, $tenant, $title]) {
            $this->pdo->prepare("INSERT INTO sse_engine_signals (id, tenant_id, case_id, signal_type, severity, title, detail, status, rule_key, run_id, created_at, updated_at) VALUES (?, ?, ?, 'gap_auto', 'medium', ?, 'BESOIN', 'open', 'auto_gap', 'r', ?, ?)")
                ->execute([$id, $tenant, $tenant === 1 ? 1 : 2, 'Lacune / besoin proposé : ' . $title, gmdate('Y-m-d H:i:s', $this->now - $id * 86400), gmdate('Y-m-d H:i:s', $this->now)]);
        }
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, 's7', 'Alice'), (8, 1, 's8', 'Bob'), (9, 2, 's9', 'Zoé'), (10, 1, 's10', 'Sans droit')");
        $this->otherGrants = [8 => ['analyse:write' => true], 10 => []];
    }

    protected function tearDown(): void
    {
        $_POST = $this->savedPost;
        $_SERVER = $this->savedServer;
        ShortCache::setEnabled(null);
        SseTriageInbox::useForSession(null);
        SseTriageSqlite::close();
    }

    private function controller(): SseTriageController
    {
        $clock = fn (): string => gmdate('Y-m-d H:i:s', $this->now);
        $defs = array_values(array_filter(SseTriageInbox::defaultDefinitions(7), static fn (array $d): bool => $d['key'] === 'anomalies'));
        $inbox = new SseTriageInbox($defs, fn (): bool => true, fn (): int => $this->now, new SseTriageStateRepository(null, $clock), new SseTriageRuleRepository(null, $clock), new SseTriageEventRepository(null, $clock), new SseTriageSubjects(null, fn (): bool => true, fn (): bool => false, fn (): array => []));
        $allows = fn (string $section, string $level, int $user): bool => $user === 7 ? ($this->grants[$section . ':' . $level] ?? false) : ($this->otherGrants[$user][$section . ':' . $level] ?? false);
        $events = new SseTriageEventRepository(null, $clock);
        $rules = new SseTriageRuleRepository(null, $clock);
        $actions = new SseTriageActions(new SseTriageStateRepository(null, $clock), $events, $rules, function (array $row): void {
            $this->audit[] = $row;
        }, $clock);

        return new SseTriageController(null, null, $inbox, $actions, $rules, $events, $allows, fn (): int => (int) Session::get('user_id'), function (array $row): void {
            $this->audit[] = $row;
        });
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, bool $csrf = true): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $post + ($csrf ? ['_csrf_token' => Csrf::token()] : []);

        return new Request();
    }

    /** @return array<string, mixed> */
    private function json(\App\Core\Response $r): array
    {
        return json_decode($r->body(), true);
    }

    private function stateCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_triage_state')->fetchColumn();
    }

    public function testAnActionNeedsTheCsrfToken(): void
    {
        $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1']], false));

        self::assertSame(419, $r->statusCode());
        self::assertSame(0, $this->stateCount());
        $_POST = ['_csrf_token' => 'faux', 'action' => 'acknowledge', 'keys' => ['signal:1']];
        self::assertSame(419, $this->controller()->act(new Request())->statusCode(), 'un mauvais jeton est refusé aussi');
    }

    public function testOnlyPostWrites(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = ['_csrf_token' => Csrf::token(), 'action' => 'acknowledge', 'keys' => ['signal:1']];

        self::assertSame(405, $this->controller()->act(new Request())->statusCode());
        self::assertSame(0, $this->stateCount());
    }

    public function testAGuestSessionCannotTreatAnything(): void
    {
        Session::set(SseAccessCodeService::SESSION_GUEST, true);
        $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1']]));

        self::assertSame(403, $r->statusCode());
        self::assertSame(0, $this->stateCount());
    }

    public function testWithoutTheWriteRightOnAnalysisNothingIsWritten(): void
    {
        $this->grants['analyse:write'] = false;
        $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1']]));

        self::assertSame(403, $r->statusCode());
        self::assertFalse($this->json($r)['ok']);
        self::assertSame(0, $this->stateCount());
    }

    public function testAcknowledgeWritesTheStateTheEventAndTheAudit(): void
    {
        $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1', 'signal:2'], 'note' => 'connu']));
        $body = $this->json($r);

        self::assertSame(200, $r->statusCode());
        self::assertTrue($body['ok']);
        self::assertSame(2, $body['changed']);
        self::assertNotSame('', $body['batch']);
        self::assertSame(2, $this->stateCount());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_triage_events WHERE action = 'acknowledge' AND actor_user_id = 7")->fetchColumn());
        self::assertCount(1, $this->audit);
        self::assertSame('sse.triage.acknowledge', $this->audit[0]['action']);
        self::assertSame(1, $this->audit[0]['tenant_id']);
        self::assertSame('private, no-store', $r->headerValue('Cache-Control'));
    }

    public function testAKeyOutsideTheSessionsListIsRefusedAndNothingIsWritten(): void
    {
        // signal:3 est un signal de la communauté 2 ; signal:99 n'existe pas.
        foreach (['signal:3', 'signal:99', 'fiche:1'] as $foreign) {
            $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1', $foreign]]));
            self::assertSame(404, $r->statusCode(), $foreign);
        }
        self::assertSame(0, $this->stateCount(), 'même la clé valide du lot n’est pas écrite');
    }

    public function testTheCommunityComesFromTheSessionNotFromTheRequest(): void
    {
        $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1'], 'tenant_id' => '2']));

        self::assertSame(200, $r->statusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT tenant_id FROM sse_triage_state')->fetchColumn());
    }

    public function testSnoozeNeedsAValidDurationAndAnUnknownActionIsRefused(): void
    {
        self::assertSame(422, $this->controller()->act($this->post(['action' => 'snooze', 'keys' => ['signal:1'], 'days' => '0']))->statusCode());
        self::assertSame(422, $this->controller()->act($this->post(['action' => 'snooze', 'keys' => ['signal:1'], 'days' => '9999']))->statusCode());
        self::assertSame(422, $this->controller()->act($this->post(['action' => 'detruire', 'keys' => ['signal:1']]))->statusCode());
        self::assertSame(422, $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => []]))->statusCode());
        self::assertSame(0, $this->stateCount());
        self::assertSame(200, $this->controller()->act($this->post(['action' => 'snooze', 'keys' => ['signal:1'], 'days' => '7']))->statusCode());
    }

    public function testAssignOnlyToAnAccountOfTheCommunityWhoCanTreat(): void
    {
        self::assertSame(200, $this->controller()->act($this->post(['action' => 'assign', 'keys' => ['signal:1'], 'assignee' => 'me']))->statusCode());
        self::assertSame(7, (int) $this->pdo->query('SELECT assigned_to FROM sse_triage_state')->fetchColumn());
        self::assertSame(200, $this->controller()->act($this->post(['action' => 'assign', 'keys' => ['signal:1'], 'assignee' => '8']))->statusCode(), 'Bob peut traiter');
        self::assertSame(422, $this->controller()->act($this->post(['action' => 'assign', 'keys' => ['signal:1'], 'assignee' => '9']))->statusCode(), 'Zoé est d’une autre communauté');
        self::assertSame(422, $this->controller()->act($this->post(['action' => 'assign', 'keys' => ['signal:1'], 'assignee' => '10']))->statusCode(), 'pas le droit de traiter');
        self::assertSame(8, (int) $this->pdo->query('SELECT assigned_to FROM sse_triage_state')->fetchColumn());
        self::assertSame(200, $this->controller()->act($this->post(['action' => 'assign', 'keys' => ['signal:1'], 'assignee' => '0']))->statusCode(), 'retirer l’assignation');
        self::assertNull($this->pdo->query('SELECT assigned_to FROM sse_triage_state')->fetchColumn() ?: null);
    }

    public function testUndoWithinTheWindowRestoresAndAStrangerCannotUndo(): void
    {
        $batch = $this->json($this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1', 'signal:2']])))['batch'];
        Session::set('user_id', 8);
        $this->otherGrants[8]['analyse:write'] = true;
        self::assertSame(422, $this->controller()->undo($this->post(['batch' => $batch]))->statusCode(), 'Bob n’annule pas le lot d’Alice');
        self::assertSame(2, $this->stateCount());

        Session::set('user_id', 7);
        $ok = $this->controller()->undo($this->post(['batch' => $batch]));
        self::assertSame(200, $ok->statusCode());
        self::assertSame(2, $this->json($ok)['restored']);
        self::assertSame(0, $this->stateCount());
        self::assertSame(422, $this->controller()->undo($this->post(['batch' => $batch]))->statusCode(), 'une seule fois');
        self::assertSame(419, $this->controller()->undo($this->post(['batch' => $batch], false))->statusCode());
    }

    public function testRulesAreManagedOnlyByThoseWhoManageAnalysis(): void
    {
        $r = $this->controller()->rulesSave($this->post(['op' => 'preset', 'preset' => 'old_completeness', 'enabled' => '1']));
        self::assertSame(403, $r->statusCode(), 'traiter ne suffit pas pour gérer les règles');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_triage_rules')->fetchColumn());

        $this->grants['analyse:manage'] = true;
        self::assertSame(419, $this->controller()->rulesSave($this->post(['op' => 'preset', 'preset' => 'old_completeness', 'enabled' => '1'], false))->statusCode());
        $ok = $this->controller()->rulesSave($this->post(['op' => 'preset', 'preset' => 'old_completeness', 'enabled' => '1']));
        $body = $this->json($ok);

        self::assertSame(200, $ok->statusCode());
        self::assertTrue($body['enabled']);
        $row = $this->pdo->query('SELECT * FROM sse_triage_rules')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('old_completeness', $row['preset_key']);
        self::assertSame('acknowledge', $row['action']);
        self::assertSame('signal:completeness', $row['type_key']);
        self::assertSame(30, (int) $row['min_age_days']);
        self::assertSame('sse.triage.rule.preset', $this->audit[count($this->audit) - 1]['action']);
    }

    public function testToggleAndCustomRulesAreValidated(): void
    {
        $this->grants['analyse:manage'] = true;
        $c = $this->controller();
        self::assertSame(422, $c->rulesSave($this->post(['op' => 'preset', 'preset' => 'inconnu']))->statusCode());
        self::assertSame(422, $c->rulesSave($this->post(['op' => 'custom', 'name' => '', 'rule_action' => 'acknowledge']))->statusCode());
        self::assertSame(422, $c->rulesSave($this->post(['op' => 'custom', 'name' => 'X', 'rule_action' => 'detruire']))->statusCode());
        self::assertSame(422, $c->rulesSave($this->post(['op' => 'custom', 'name' => 'X', 'rule_action' => 'snooze', 'action_value' => '0']))->statusCode());
        self::assertSame(422, $c->rulesSave($this->post(['op' => 'custom', 'name' => 'X', 'rule_action' => 'assign', 'action_value' => '10']))->statusCode(), 'assigner à quelqu’un qui ne peut pas traiter');
        self::assertSame(404, $c->rulesSave($this->post(['op' => 'toggle', 'id' => '999', 'enabled' => '1']))->statusCode());
        self::assertSame(422, $c->rulesSave($this->post(['op' => 'autre']))->statusCode());

        $made = $this->json($c->rulesSave($this->post(['op' => 'custom', 'name' => 'Veille des biométries', 'keyword' => 'Biométrie', 'rule_action' => 'snooze', 'action_value' => '14', 'scope' => 'cover', 'enabled' => '0'])));
        self::assertTrue($made['ok']);
        $row = $this->pdo->query('SELECT * FROM sse_triage_rules WHERE id = ' . (int) $made['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('cover', $row['scope']);
        self::assertSame(0, (int) $row['enabled'], 'une règle créée est désactivée tant qu’on ne l’active pas');
        $on = $this->json($c->rulesSave($this->post(['op' => 'toggle', 'id' => (string) $made['id'], 'enabled' => '1'])));
        self::assertTrue($on['enabled']);
    }

    public function testAnotherCommunitysRuleCannotBeToggled(): void
    {
        $this->grants['analyse:manage'] = true;
        $foreign = (new SseTriageRuleRepository(null, fn (): string => gmdate('Y-m-d H:i:s')))->save(2, null, ['name' => 'Autre', 'enabled' => 0, 'action' => 'acknowledge'], 9);

        self::assertSame(404, $this->controller()->rulesSave($this->post(['op' => 'toggle', 'id' => (string) $foreign, 'enabled' => '1']))->statusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT enabled FROM sse_triage_rules WHERE id = ' . $foreign)->fetchColumn());
    }

    public function testThePreviewCountsWhatTheRuleWouldTreatBeforeItIsEnabled(): void
    {
        $this->grants['analyse:manage'] = true;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['keyword' => 'Photographie', 'source_key' => 'anomalies'];
        $r = $this->controller()->rulesPreview(new Request());
        $_GET = [];

        self::assertSame(200, $r->statusCode());
        self::assertSame(1, $this->json($r)['count'], 'un seul signal de photographie dans la communauté 1');
        $this->grants['analyse:manage'] = false;
        self::assertSame(403, $this->controller()->rulesPreview(new Request())->statusCode());
    }

    public function testWithoutTheTablesWritesAnswerClearlyAndWriteNothing(): void
    {
        SseTriageSqlite::close();
        $this->pdo = SseTriageSqlite::open(false);
        SseTriageSqlite::addCase($this->pdo, 1, 'DOS-0001', 'x');
        $this->pdo->exec("INSERT INTO sse_engine_signals (id, tenant_id, case_id, signal_type, severity, title, status, rule_key, created_at, updated_at) VALUES (1, 1, 1, 'gap_auto', 'medium', 'Lacune / besoin proposé : Photographie manquante', 'open', 'auto_gap', '2026-11-01 00:00:00', '2026-11-01 00:00:00')");
        $r = $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1']]));

        self::assertSame(503, $r->statusCode());
        self::assertStringContainsString('mise à jour de la base de données', $this->json($r)['error']);
    }

    public function testCountersFollowTheStateAndTheRoutesAreWired(): void
    {
        $this->controller()->act($this->post(['action' => 'acknowledge', 'keys' => ['signal:1']]));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $data = $this->json($this->controller()->counters(new Request()));

        self::assertSame(1, $data['total'], 'deux signaux en communauté 1, un acquitté');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach (['traiter' => 'act', 'annuler' => 'undo', 'regles' => 'rulesSave'] as $path => $method) {
            self::assertStringContainsString("post('/atak/sse/a-trier/" . $path . "', [\\App\\Controllers\\Web\\SseTriageController::class, '" . $method . "'], \$mwSsePortal)", $routes);
        }
        self::assertStringContainsString("get('/atak/sse/a-trier/regles/apercu', [\\App\\Controllers\\Web\\SseTriageController::class, 'rulesPreview'], \$mwSsePortal)", $routes);
    }

    public function testNoNewRightIsNeededSoNothingIsGrantedAutomatically(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SseTriageController.php');

        self::assertStringContainsString("'analyse', SseAccessCatalog::LEVEL_WRITE", $src);
        self::assertStringContainsString("'analyse', SseAccessCatalog::LEVEL_MANAGE", $src);
        self::assertStringNotContainsString('atak.sse.triage.', $src, 'aucun droit nouveau dans le catalogue de la page Rôles');
        self::assertStringNotContainsString('sse.triage', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Authorization/TenantPermissionCatalog.php'));
    }
}
