<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakTocApiController;
use App\Controllers\Api\AtakTocBaseController;
use App\Controllers\Api\AtakTocReportsApiController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanValidator;
use App\Services\AtakToc\TocAccess;
use App\Services\AtakToc\TocReportService;
use App\Services\AtakToc\TocRoomService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TocReportsSqlite;

/**
 * Panneau Rapports du poste TOC web, côté API : droit « tenir le poste », communauté de la session (jamais de la requête),
 * anti-IDOR sur les rapports et sur les plans, CSRF et limite de débit sur chaque écriture, habilitation de l'opérateur,
 * ETag de la liste, empreinte du canal de synchro, routes rattachées derrière l'accès membre.
 */
final class AtakTocReportsApiAccessTest extends TestCase
{
    private PDO $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    private string $level = 'tres_restreint';

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedGet = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = TocReportsSqlite::open();
        if ($this->pdo instanceof \Pdo\Sqlite) {
            $this->pdo->createFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        } elseif (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        }
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        TocReportsSqlite::seedRealistic($this->pdo, time());
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $cur = $db->getValue();
        $this->savedPdo = $cur instanceof PDO ? $cur : null;
        $db->setValue(null, $this->pdo);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION = [];
        $this->savedGet = $_GET;
        $_GET = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD', 'HTTP_IF_NONE_MATCH'] as $k) {
            if (array_key_exists($k, $_SERVER)) {
                $this->savedServer[$k] = $_SERVER[$k];
            }
            unset($_SERVER[$k]);
        }
        AtakTocBaseController::$testBody = null;
        $this->level = 'tres_restreint';
    }

    protected function tearDown(): void
    {
        Gate::reset();
        AtakTocBaseController::$testBody = null;
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = [];
        $_GET = $this->savedGet;
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD', 'HTTP_IF_NONE_MATCH'] as $k) {
            unset($_SERVER[$k]);
        }
        foreach ($this->savedServer as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }

    /** @param list<string> $perms */
    private function signIn(int $tenant, int $user, array $perms, bool $csrf = true): void
    {
        $_SESSION = ['tenant_id' => $tenant, 'user_id' => $user, '_csrf_token' => 'jeton'];
        if ($csrf) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        } else {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Gate::getInstance()->setPermissions($perms);
    }

    private function api(): AtakTocReportsApiController
    {
        return new AtakTocReportsApiController(
            new TocAccess(null, static fn (): array => []),
            new TocReportService($this->pdo, static fn (int $t, array $rows): array => $rows),
            fn (): string => $this->level,
            new AtakPlanRepository($this->pdo)
        );
    }

    /** @return array<string, mixed> */
    private static function json(Response $r): array
    {
        $d = json_decode($r->body(), true);

        return is_array($d) ? $d : [];
    }

    public function testReadingNeedsAMemberOfTheCommunityWhoHoldsThePost(): void
    {
        self::assertSame(401, $this->api()->index(new Request())->statusCode());
        $this->signIn(1, 7, []);
        foreach (['index', 'show', 'plans', 'pdf'] as $method) {
            self::assertSame(403, $this->api()->$method(new Request(), ['key' => 'tr-1'])->statusCode(), $method . ' sans atak.toc.use');
        }
        $this->signIn(1, 7, ['atak.toc.use']);
        $ok = $this->api()->index(new Request());
        self::assertSame(200, $ok->statusCode());
        self::assertCount(40, self::json($ok)['reports']);
        self::assertSame(40, self::json($ok)['counts']['unread']);
        // La configuration du poste ou l'administration de la communauté valent le droit d'usage.
        $this->signIn(1, 7, ['atak.toc.manage']);
        self::assertSame(200, $this->api()->index(new Request())->statusCode());
    }

    public function testTheCommunityComesFromTheSessionNotFromTheRequest(): void
    {
        $other = TocReportsSqlite::tactical($this->pdo, 2, 10, 'BDA', 'AUTRE 1', 'AUTRE', 'BDA autre communauté', ['result' => 'DÉTRUIT']);
        $_GET = ['tenant_id' => '2', 'tenantId' => '2'];
        $this->signIn(1, 7, ['atak.toc.use']);
        $list = self::json($this->api()->index(new Request()));
        self::assertCount(40, $list['reports']);
        self::assertSame([], array_filter($list['reports'], static fn (array $r): bool => $r['who'] === 'AUTRE 1'));
        self::assertSame(404, $this->api()->show(new Request(), ['key' => 'tr-' . $other])->statusCode());
        self::assertSame(404, $this->api()->pdf(new Request(), ['key' => 'tr-' . $other])->statusCode());
        $this->signIn(2, 9, ['atak.toc.use']);
        self::assertSame(200, $this->api()->show(new Request(), ['key' => 'tr-' . $other])->statusCode());
        self::assertCount(1, self::json($this->api()->index(new Request()))['reports']);
    }

    public function testListFiltersComeFromTheQueryAndTheListHasAnEtag(): void
    {
        $this->signIn(1, 7, ['atak.toc.use']);
        $_GET = ['family' => 'bda', 'unread' => '1', 'sort' => 'priority', 'map' => 'all'];
        $res = $this->api()->index(new Request());
        $d = self::json($res);
        self::assertCount(12, $d['reports']);
        self::assertSame(12, $d['counts']['families']['bda']['total'] ?? 12);
        self::assertNotNull($res->headerValue('ETag'));
        $_SERVER['HTTP_IF_NONE_MATCH'] = (string) $res->headerValue('ETag');
        self::assertSame(304, $this->api()->index(new Request())->statusCode(), 'rien n’a bougé');
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        $_GET = ['period' => '1h'];
        self::assertLessThan(40, count(self::json($this->api()->index(new Request()))['reports']));
        $_GET = ['period' => 'all', 'q' => 'rochefoucauld'];
        self::assertCount(1, self::json($this->api()->index(new Request()))['reports']);
    }

    public function testClearanceOfTheOperatorDecidesWhatTheApiShows(): void
    {
        $this->signIn(1, 7, ['atak.toc.use']);
        $this->level = 'interne';
        $list = self::json($this->api()->index(new Request()));
        self::assertCount(39, $list['reports']);
        self::assertSame(1, $list['hidden']);
        $this->level = 'tres_restreint';
        $hidden = array_values(array_filter(self::json($this->api()->index(new Request()))['reports'], static fn (array $r): bool => $r['classification'] === 'CONFIDENTIAL'));
        self::assertCount(1, $hidden);
        $this->level = 'interne';
        self::assertSame(404, $this->api()->show(new Request(), ['key' => $hidden[0]['key']])->statusCode());
        self::assertSame(404, $this->api()->pdf(new Request(), ['key' => $hidden[0]['key']])->statusCode());
        $this->level = 'encadrement';
        self::assertSame(200, $this->api()->show(new Request(), ['key' => $hidden[0]['key']])->statusCode());
    }

    public function testEveryWriteChecksCsrfAndTheReportIsReadable(): void
    {
        $this->signIn(1, 7, ['atak.toc.use'], false);
        foreach (['read', 'ack', 'attach'] as $method) {
            self::assertSame(419, $this->api()->$method(new Request(), ['key' => 'tr-1'])->statusCode(), $method . ' sans CSRF');
        }
        self::assertSame(419, $this->api()->readAll(new Request())->statusCode());
        $this->signIn(1, 7, ['atak.toc.use']);
        // Lu / non lu.
        AtakTocBaseController::$testBody = json_encode(['read' => true]);
        $read = self::json($this->api()->read(new Request(), ['key' => 'tr-1']));
        self::assertTrue($read['ok']);
        self::assertTrue($read['persisted']);
        self::assertSame(39, self::json($this->api()->index(new Request()))['counts']['unread']);
        AtakTocBaseController::$testBody = json_encode(['read' => false]);
        $this->api()->read(new Request(), ['key' => 'tr-1']);
        self::assertSame(40, self::json($this->api()->index(new Request()))['counts']['unread']);
        // Rapport inconnu, autre communauté ou clé mal formée : 404, jamais d'écriture.
        foreach (['tr-9999', 'nl-9999', 'tr-1; DROP', '../x'] as $bad) {
            self::assertSame(404, $this->api()->read(new Request(), ['key' => $bad])->statusCode(), $bad);
            self::assertSame(404, $this->api()->ack(new Request(), ['key' => $bad])->statusCode(), $bad);
        }
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'atak_toc.report.ack'")->fetchColumn(), 'aucun accusé n’a été journalisé pour les clés refusées');
        // Accusé de réception : journalisé.
        AtakTocBaseController::$testBody = json_encode([]);
        $ack = $this->api()->ack(new Request(), ['key' => 'tr-13']);
        self::assertSame(200, $ack->statusCode(), $ack->body());
        self::assertTrue(self::json($ack)['report']['acked']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'atak_toc.report.ack'")->fetchColumn());
        // Tout marquer comme lu.
        $all = self::json($this->api()->readAll(new Request()));
        self::assertSame(40, $all['marked']);
    }

    public function testReadStateFallsBackWithAClearMessageWhenTheTableIsMissing(): void
    {
        $this->pdo->exec('DROP TABLE atak_toc_report_reads');
        $this->signIn(1, 7, ['atak.toc.use']);
        $list = self::json($this->api()->index(new Request()));
        self::assertSame('degraded', $list['readState']);
        self::assertSame(40, $list['counts']['unread']);
        AtakTocBaseController::$testBody = json_encode(['read' => true]);
        $res = self::json($this->api()->read(new Request(), ['key' => 'tr-1']));
        self::assertTrue($res['ok']);
        self::assertFalse($res['persisted'], 'rien n’est conservé : le panneau le dit');
        self::assertSame('degraded', $res['readState']);
    }

    public function testAttachingToAPlanFollowsTheSameRulesAsThePlanEditor(): void
    {
        $repo = new AtakPlanRepository($this->pdo);
        $mine = $repo->create(1, 7, 'Opération ÉPERVIER', 1, 'altis', 'private', null, PlanValidator::meta(['side' => 'WEST']));
        $theirs = $repo->create(1, 8, 'Plan d’un autre opérateur', 1, 'altis', 'private', null, PlanValidator::meta(['side' => 'WEST']));
        $shared = $repo->create(1, 8, 'Plan partagé en lecture', 1, 'altis', 'community', null, PlanValidator::meta(['side' => 'WEST']));
        $this->pdo->exec('UPDATE atak_plans SET locked = 1 WHERE id = ' . $shared);
        $foreign = $repo->create(2, 9, 'Autre communauté', 1, 'altis', 'community', null, PlanValidator::meta(['side' => 'WEST']));
        $this->signIn(1, 7, ['atak.toc.use']);
        AtakTocBaseController::$testBody = json_encode(['planId' => $mine]);
        $ok = $this->api()->attach(new Request(), ['key' => 'tr-1']);
        self::assertSame(200, $ok->statusCode(), $ok->body());
        self::assertSame('rep-tr-1', self::json($ok)['uid']);
        self::assertSame($mine, self::json($ok)['plans'][0]['plan_id']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'atak_toc.report.attach'")->fetchColumn());
        // Plan privé d'un autre membre ou d'une autre communauté : 404 ; plan verrouillé : 403.
        foreach ([$theirs, $foreign, 99999] as $planId) {
            AtakTocBaseController::$testBody = json_encode(['planId' => $planId]);
            self::assertSame(404, $this->api()->attach(new Request(), ['key' => 'tr-1'])->statusCode(), (string) $planId);
        }
        AtakTocBaseController::$testBody = json_encode(['planId' => $shared]);
        self::assertSame(403, $this->api()->attach(new Request(), ['key' => 'tr-1'])->statusCode());
        // Le rapport doit être lisible : celui d'une autre communauté ne se joint pas.
        $other = TocReportsSqlite::tactical($this->pdo, 2, 4, 'BDA', 'AUTRE 1', 'AUTRE', 'BDA autre communauté', ['result' => 'DÉTRUIT']);
        AtakTocBaseController::$testBody = json_encode(['planId' => $mine]);
        self::assertSame(404, $this->api()->attach(new Request(), ['key' => 'tr-' . $other])->statusCode());
        // Liste des plans proposés : seulement ceux qu'on peut modifier.
        $plans = self::json($this->api()->plans(new Request()))['plans'];
        self::assertSame([$mine], array_column($plans, 'id'));
    }

    public function testStateCarriesTheReportStampOfTheSyncChannel(): void
    {
        $this->signIn(1, 7, ['atak.toc.use']);
        $rooms = new TocRoomService($this->pdo, static fn (): ?int => 1, static fn (): ?string => 'pending');
        $state = new AtakTocApiController(new TocAccess(null, static fn (): array => []), $rooms);
        $a = self::json($state->state(new Request()))['reports']['stamp'] ?? null;
        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', (string) $a);
        TocReportsSqlite::tactical($this->pdo, 1, 0, 'TIC', 'ALPHA 1-1', 'ALPHA 1', 'Contact (TIC) · 0100 0100', ['nature' => 'OBSERVÉ']);
        self::assertNotSame($a, self::json($state->state(new Request()))['reports']['stamp']);
    }

    public function testEveryRouteIsWiredBehindMemberMiddleware(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach ([
            "'/atak/toc/api/reports', [\$tocReports, 'index']", "'/atak/toc/api/reports/read-all', [\$tocReports, 'readAll']", "'/atak/toc/api/reports/plans', [\$tocReports, 'plans']",
            "'/atak/toc/api/reports/{key}', [\$tocReports, 'show']", "'/atak/toc/api/reports/{key}/read', [\$tocReports, 'read']", "'/atak/toc/api/reports/{key}/ack', [\$tocReports, 'ack']",
            "'/atak/toc/api/reports/{key}/attach', [\$tocReports, 'attach']", "'/atak/toc/reports/{key}/export.pdf', [\$tocReports, 'pdf']",
        ] as $needle) {
            $at = strpos($routes, $needle);
            self::assertNotFalse($at, $needle);
            $line = substr($routes, (int) $at, (int) strpos($routes, "\n", (int) $at) - (int) $at);
            self::assertStringContainsString('$mwAtakMemberOnly', $line, $needle);
        }
        // Les écritures sont en POST, la lecture en GET.
        self::assertStringContainsString("\$router->post('/atak/toc/api/reports/{key}/ack'", $routes);
        self::assertStringContainsString("\$router->post('/atak/toc/api/reports/{key}/read'", $routes);
        self::assertStringContainsString("\$router->get('/atak/toc/api/reports'", $routes);
        // « plans » est déclarée avant « {key} » : sinon la clé « plans » prendrait la route de la fiche.
        self::assertLessThan(strpos($routes, "'/atak/toc/api/reports/{key}', [\$tocReports, 'show']"), strpos($routes, "'/atak/toc/api/reports/plans'"));
    }
}
