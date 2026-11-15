<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SsePersonWorkController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseMemberImageGate;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SsePersonWorkSqlite;

/**
 * Actions de la page Personnes par la route : jeton CSRF, communauté de la session (une fiche d'une autre communauté est
 * introuvable), droits d'écriture, habilitation, retour interne seulement, export sans fiche restreinte ni formule de tableur.
 */
final class SsePersonWorkControllerTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        $this->pdo = SsePersonWorkSqlite::open();
        Csrf::token();
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.access', 'atak.sse.case.manage', 'atak.sse.clearance.encadrement', 'atak.sse.clearance.confidentiel']);
        SseMemberImageGate::useForSession(new SseMemberImageGate(null, null, static fn (): array => [], static fn (): bool => false, static function (array $row): void {
        }, null, null, null, null));
    }

    protected function tearDown(): void
    {
        SsePersonWorkSqlite::close();
        SseMemberImageGate::useForSession(null);
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, bool $json = true, bool $csrf = true): Request
    {
        $_POST = $post + ($csrf ? ['_csrf_token' => 'jeton'] : []);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT'] = $json ? 'application/json' : 'text/html';

        return new Request();
    }

    /** @param array<string, mixed> $extra */
    private function person(string $last, array $extra = [], int $tenant = 1): int
    {
        $row = $extra + ['tenant_id' => $tenant, 'last_name' => $last, 'first_name' => 'Test', 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00'];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function json(\App\Core\Response $r): array
    {
        return json_decode($r->body(), true) ?? [];
    }

    public function testBatchNeedsAValidCsrfToken(): void
    {
        $p = $this->person('Alpha');
        $c = new SsePersonWorkController();
        $r = $c->batch($this->post(['action' => 'close', 'ids' => (string) $p, 'reason_code' => 'resolu'], true, false), []);
        self::assertSame(422, $r->statusCode());
        self::assertSame('csrf', $this->json($r)['code']);
        $bad = $c->batch($this->post(['_csrf_token' => 'faux', 'action' => 'close', 'ids' => (string) $p, 'reason_code' => 'resolu'], true, false), []);
        self::assertSame('csrf', $this->json($bad)['code']);
        self::assertNull((new SsePersonRepository())->findById($p, 1)['closed_at']);

        $page = $c->batch($this->post(['action' => 'close', 'ids' => (string) $p, 'reason_code' => 'resolu'], false, false), []);
        self::assertSame(302, $page->statusCode(), 'Sans JavaScript : retour à la page avec un message');
    }

    public function testBatchClosesLogsAndCanBeUndone(): void
    {
        $a = $this->person('Alpha');
        $b = $this->person('Bravo');
        $c = new SsePersonWorkController();
        $r = $c->batch($this->post(['action' => 'close', 'ids' => $a . ',' . $b . ',' . $a, 'reason_code' => 'resolu', 'note' => 'Lot']), []);
        self::assertSame(200, $r->statusCode());
        $j = $this->json($r);
        self::assertTrue($j['ok']);
        self::assertSame([$a, $b], $j['done']);
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_audit_log WHERE action = 'person.close'")->fetchColumn());

        $undo = $c->batch($this->post(['action' => 'reopen', 'ids' => [$a, $b], 'reason' => '']), []);
        self::assertSame([$a, $b], $this->json($undo)['done']);
        self::assertNull((new SsePersonRepository())->findById($a, 1)['closed_at']);
    }

    public function testAnotherCommunitysFileIsNeverTouched(): void
    {
        $mine = $this->person('Alpha');
        $foreign = $this->person('Etrangere', [], 2);
        $c = new SsePersonWorkController();
        $r = $c->batch($this->post(['action' => 'close', 'ids' => $foreign . ',' . $mine, 'reason_code' => 'resolu']), []);
        $j = $this->json($r);
        self::assertSame([$mine], $j['done']);
        self::assertSame('notfound', $j['results'][0]['code']);
        self::assertNull($this->pdo->query('SELECT closed_at FROM sse_persons WHERE id = ' . $foreign)->fetchColumn() ?: null);

        $single = $c->close($this->post(['reason_code' => 'resolu']), ['id' => $foreign]);
        self::assertSame(422, $single->statusCode());
        self::assertSame('notfound', $this->json($single)['code']);

        self::assertSame(404, $c->drawer(new Request(), ['id' => $foreign])->statusCode());
        self::assertSame(200, $c->drawer(new Request(), ['id' => $mine])->statusCode());
    }

    public function testWithoutTheWriteRightNothingChangesAndTheReasonIsExplained(): void
    {
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.access']);
        $p = $this->person('Alpha');
        $c = new SsePersonWorkController();
        $r = $c->classify($this->post(['level' => 'interne']), ['id' => $p]);
        self::assertSame(422, $r->statusCode());
        self::assertSame('right', $this->json($r)['code']);
        self::assertStringContainsString('Droit manquant', $this->json($r)['error']);
        self::assertNull((new SsePersonRepository())->findById($p, 1)['classification']);
    }

    public function testClearanceCeilingAppliesThroughTheRoute(): void
    {
        $p = $this->person('Alpha');
        $secret = $this->person('Secrete', ['classification' => 'tres_restreint']);
        $c = new SsePersonWorkController();
        self::assertSame('ceiling', $this->json($c->classify($this->post(['level' => 'tres_restreint']), ['id' => $p]))['code']);
        self::assertTrue($this->json($c->classify($this->post(['level' => 'confidentiel']), ['id' => $p]))['ok']);
        self::assertSame('ceiling', $this->json($c->close($this->post(['reason_code' => 'resolu']), ['id' => $secret]))['code']);
        $lock = $c->lock($this->post(['reason' => 'Opération en cours', 'until' => gmdate('Y-m-d\TH:i', time() + 86400)]), ['id' => $p]);
        self::assertTrue($this->json($lock)['ok']);
        self::assertSame('confidentiel', (new SsePersonRepository())->findById($p, 1)['classification']);
        self::assertTrue($this->json($c->unlock($this->post(['reason' => 'Terminé']), ['id' => $p]))['ok']);
    }

    public function testDrawerOfARestrictedFileIsNotFoundAndRecordsNoOpening(): void
    {
        $locked = $this->person('Verrou', ['classification' => 'tres_restreint', 'locked_at' => '2026-11-01 00:00:00']);
        $c = new SsePersonWorkController();
        $r = $c->drawer(new Request(), ['id' => $locked]);
        self::assertSame(404, $r->statusCode());
        self::assertStringNotContainsString('Verrou', $r->body());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_person_views')->fetchColumn());

        $open = $this->person('Visible');
        $ok = $c->drawer(new Request(), ['id' => $open]);
        self::assertSame(200, $ok->statusCode());
        self::assertStringContainsString('Visible', $ok->body());
        self::assertStringContainsString('no-store', (string) $ok->headerValue('Cache-Control'));
        $c->drawer(new Request(), ['id' => $open]);
        self::assertSame(1, (int) $this->pdo->query('SELECT open_count FROM sse_person_views WHERE person_id = ' . $open)->fetchColumn(), 'Un rafraîchissement ne compte pas');
    }

    public function testReturnAddressStaysInsideTheSite(): void
    {
        $p = $this->person('Alpha');
        $c = new SsePersonWorkController();
        $r = $c->classify($this->post(['level' => 'interne', 'return' => 'https://exemple.test/hameçon'], false), ['id' => $p]);
        self::assertSame(302, $r->statusCode());
        self::assertStringEndsWith('atak/sse/identites/' . $p, (string) $r->headerValue('Location'));
        $r2 = $c->classify($this->post(['level' => 'encadrement', 'reason' => 'Relevé', 'return' => '//exemple.test'], false), ['id' => $p]);
        self::assertStringEndsWith('atak/sse/identites/' . $p, (string) $r2->headerValue('Location'));
        $r3 = $c->classify($this->post(['level' => 'confidentiel', 'reason' => 'Relevé', 'return' => '/atak/sse/identites?tab=closes'], false), ['id' => $p]);
        self::assertSame('/atak/sse/identites?tab=closes', $r3->headerValue('Location'));
    }

    public function testExportSkipsRestrictedFilesAndNeutralisesSpreadsheetFormulas(): void
    {
        $ok = $this->person('=HYPERLINK("x")', ['alias' => '+cmd']);
        $locked = $this->person('Cachee', ['classification' => 'tres_restreint', 'locked_at' => '2026-11-01 00:00:00']);
        $foreign = $this->person('Etrangere', [], 2);
        $c = new SsePersonWorkController();
        $r = $c->batch($this->post(['action' => 'export', 'ids' => $ok . ',' . $locked . ',' . $foreign], false), []);
        self::assertSame(200, $r->statusCode());
        self::assertStringContainsString('text/csv', (string) $r->headerValue('Content-Type'));
        $csv = $r->body();
        self::assertStringContainsString("'Test =HYPERLINK", str_replace('"', '', $csv) . "'Test =HYPERLINK");
        self::assertStringNotContainsString('Cachee', $csv);
        self::assertStringNotContainsString('Etrangere', $csv);
        self::assertStringNotContainsString(';+cmd', $csv, 'Une cellule qui commence par + est neutralisée');
        self::assertStringContainsString("'+cmd", $csv);
        self::assertSame("'=x", $c->cell('=x'));
        self::assertSame('x=', $c->cell('x='));
    }

    public function testExportNeedsTheExportRight(): void
    {
        $p = $this->person('Alpha');
        $guest = new SsePersonWorkController();
        $_SESSION['sse_guest'] = true;
        $r = $guest->batch($this->post(['action' => 'export', 'ids' => (string) $p]), []);
        self::assertSame(403, $r->statusCode());
    }

    public function testAutoLockSettingNeedsTheManageRight(): void
    {
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.access', 'atak.sse.files.admin', 'atak.sse.clearance.encadrement', 'atak.sse.clearance.confidentiel']);
        $c = new SsePersonWorkController();
        $ok = $c->settings($this->post(['level' => 'encadrement']), []);
        self::assertSame(200, $ok->statusCode(), $ok->body());
        self::assertSame('encadrement', $this->pdo->query("SELECT setting_value FROM sse_portal_settings WHERE setting_key = 'person_auto_lock_above'")->fetchColumn());
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.access']);
        $denied = $c->settings($this->post(['level' => 'interne']), []);
        self::assertSame(422, $denied->statusCode());
        self::assertSame('encadrement', $this->pdo->query("SELECT setting_value FROM sse_portal_settings WHERE setting_key = 'person_auto_lock_above'")->fetchColumn());
    }

    public function testLinkingToACaseNeedsAReachableCaseAndReadableFiles(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS sse_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, reference_code TEXT, title TEXT, status TEXT, classification TEXT, is_folder INTEGER DEFAULT 0, parent_id INTEGER, created_at TEXT, updated_at TEXT)");
        $this->pdo->exec("INSERT INTO sse_cases (id, tenant_id, reference_code, title, status, classification, is_folder) VALUES (1, 1, 'DOS-1', 'Mon dossier', 'ouvert', 'encadrement', 0), (2, 2, 'DOS-2', 'Autre communauté', 'ouvert', 'encadrement', 0)");
        $a = $this->person('Alpha');
        $locked = $this->person('Cachee', ['classification' => 'tres_restreint', 'locked_at' => '2026-11-01 00:00:00']);
        $c = new SsePersonWorkController(null, null, new SseCaseRepository());
        $_SESSION['sse_scope'] = 'all';
        $foreignCase = $c->batch($this->post(['action' => 'link', 'ids' => (string) $a, 'case_id' => '2']), []);
        self::assertSame(422, $foreignCase->statusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_case_persons')->fetchColumn());
        $ok = $c->batch($this->post(['action' => 'link', 'ids' => $a . ',' . $locked, 'case_id' => '1']), []);
        self::assertTrue($this->json($ok)['ok'], $ok->body());
        self::assertSame([$a], array_map('intval', $this->pdo->query('SELECT person_id FROM sse_case_persons')->fetchAll(PDO::FETCH_COLUMN)));
    }
}
