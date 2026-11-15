<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakMarkerCycleApiController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Atak\MarkerCycle\AtakMarkerArchiveService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;

/**
 * Routes JSON du cycle des marqueurs : communauté lue dans la session seulement, jeton CSRF sur toute écriture, droits du
 * poste TOC (épingler, restaurer) et de la gestion (réglages, purge avec phrase de confirmation), archives en lecture seule,
 * aucune fuite entre communautés, vues et scripts branchés sur les trois postes web.
 */
final class AtakMarkerCycleApiTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private InMemoryMarkerCycleStore $store;

    private bool $savedStarted = false;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var list<array{string, int, int, array<string, mixed>}> */
    private array $audits = [];

    /** @var array{operate: bool, manage: bool} */
    private array $rights = ['operate' => false, 'manage' => false];

    protected function setUp(): void
    {
        $this->store = new InMemoryMarkerCycleStore();
        $this->audits = [];
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD'] as $k) {
            if (array_key_exists($k, $_SERVER)) {
                $this->savedServer[$k] = $_SERVER[$k];
            }
            unset($_SERVER[$k]);
        }
        $_GET = [];
        AtakMarkerCycleApiController::$testBody = null;
        // Un cycle fini à archiver : la communauté 7 (marqueur 1) et la communauté 8 (marqueur 2).
        $now = (new \DateTimeImmutable('2026-11-15 13:00:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $this->store->addMarker(7, 1, 1, '2026-11-14 14:00:00');
        $this->store->addShape(7, 1, 3, '2026-11-14 15:00:00');
        $this->store->addMarker(8, 1, 2, '2026-11-14 14:00:00');
        (new AtakMarkerArchiveService($this->store, static fn (): int => $now, static function (): void {
        }))->runScheduled();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = [];
        $_GET = [];
        AtakMarkerCycleApiController::$testBody = null;
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD'] as $k) {
            unset($_SERVER[$k]);
        }
        foreach ($this->savedServer as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }

    private function signIn(int $tenant, int $user, bool $operate, bool $manage, bool $csrf = true): void
    {
        $_SESSION = ['tenant_id' => $tenant, 'user_id' => $user, '_csrf_token' => 'jeton'];
        if ($csrf) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        } else {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->rights = ['operate' => $operate || $manage, 'manage' => $manage];
    }

    private function api(): AtakMarkerCycleApiController
    {
        $now = (new \DateTimeImmutable('2026-11-15 13:00:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $rights = $this->rights;

        return new AtakMarkerCycleApiController(
            new AtakMarkerArchiveService($this->store, static fn (): int => $now),
            static fn (int $t, int $u): array => $rights,
            function (string $action, int $tenantId, int $userId, array $details): void {
                $this->audits[] = [$action, $tenantId, $userId, $details];
            }
        );
    }

    /** @return array<string, mixed> */
    private static function json(Response $r): array
    {
        $d = json_decode($r->body(), true);

        return is_array($d) ? $d : [];
    }

    private function archiveId(int $tenant): int
    {
        foreach ($this->store->archives as $a) {
            if ($a['tenant_id'] === $tenant) {
                return (int) $a['id'];
            }
        }

        return 0;
    }

    public function testEveryRouteRequiresAMemberSession(): void
    {
        $api = $this->api();
        self::assertSame(401, $api->status(new Request())->statusCode());
        self::assertSame(401, $api->archives(new Request())->statusCode());
        self::assertSame(401, $api->show(new Request(), ['id' => 1])->statusCode());
        self::assertSame(401, $api->export(new Request(), ['id' => 1])->statusCode());
        self::assertSame(401, $api->pin(new Request())->statusCode());
        self::assertSame(401, $api->settings(new Request())->statusCode());
    }

    public function testStatusShowsTheCurrentCycleAndRights(): void
    {
        $this->signIn(7, 5, false, false);
        $r = $this->api()->status(new Request());
        self::assertSame(200, $r->statusCode());
        $j = self::json($r);
        self::assertTrue($j['cycle']['enabled']);
        self::assertSame('12:00', $j['cycle']['endTime']);
        self::assertSame(23 * 3600, $j['cycle']['remaining']);
        self::assertFalse($j['canOperate']);
        self::assertFalse($j['canManage']);
    }

    public function testWritesRequireCsrfAndTheRightRole(): void
    {
        AtakMarkerCycleApiController::$testBody = json_encode(['kind' => 'marker', 'id' => 1, 'pinned' => true]);
        $this->signIn(7, 5, true, false, false);
        self::assertSame(419, $this->api()->pin(new Request())->statusCode(), 'jeton CSRF');
        self::assertSame(419, $this->api()->settings(new Request())->statusCode());
        self::assertSame(419, $this->api()->restore(new Request(), ['id' => 1])->statusCode());

        $this->signIn(7, 5, false, false);
        self::assertSame(403, $this->api()->pin(new Request())->statusCode(), 'sans le droit du poste');
        self::assertSame(403, $this->api()->restore(new Request(), ['id' => $this->archiveId(7)])->statusCode());
        self::assertSame(403, $this->api()->settings(new Request())->statusCode());
        self::assertSame(403, $this->api()->delete(new Request(), ['id' => $this->archiveId(7)])->statusCode());

        $this->signIn(7, 5, true, false);
        self::assertSame(403, $this->api()->settings(new Request())->statusCode(), 'le poste TOC ne règle pas le cycle');
        self::assertSame(403, $this->api()->delete(new Request(), ['id' => $this->archiveId(7)])->statusCode(), 'la purge est réservée à la gestion');
        self::assertCount(1, $this->store->listArchives(7, [], 10, 0));
    }

    public function testPinAndUnpinThroughTheApi(): void
    {
        $this->store->addMarker(7, 1, 40, '2026-11-15 12:30:00');
        $this->signIn(7, 5, true, false);
        AtakMarkerCycleApiController::$testBody = json_encode(['kind' => 'marker', 'id' => 40, 'pinned' => true]);
        $r = $this->api()->pin(new Request());
        self::assertSame(200, $r->statusCode());
        $d = json_decode((string) $this->store->live[count($this->store->live) - 1]['payload']['marker_data'], true);
        self::assertTrue($d['web_permanent']);
        self::assertSame('atak_marker_cycle.pin', $this->audits[0][0]);
        AtakMarkerCycleApiController::$testBody = json_encode(['kind' => 'marker', 'id' => 40, 'pinned' => false]);
        self::assertSame(200, $this->api()->pin(new Request())->statusCode());
        // Élément d'une autre communauté ou inconnu : introuvable.
        AtakMarkerCycleApiController::$testBody = json_encode(['kind' => 'marker', 'id' => 999, 'pinned' => true]);
        self::assertSame(404, $this->api()->pin(new Request())->statusCode());
    }

    public function testCommunityComesFromTheSessionNeverFromTheRequest(): void
    {
        $this->signIn(7, 5, true, true);
        $_GET = ['tenantId' => '8', 'tenant_id' => '8'];
        $mine = self::json($this->api()->archives(new Request()));
        self::assertCount(1, $mine['archives']);
        self::assertSame($this->archiveId(7), $mine['archives'][0]['id']);
        self::assertSame('Mission du 14/11 12:00 → 15/11 12:00', $mine['archives'][0]['label']);
        self::assertSame(1, $mine['archives'][0]['markers']);
        self::assertSame(1, $mine['archives'][0]['shapes']);

        $foreign = $this->archiveId(8);
        self::assertSame(404, $this->api()->show(new Request(), ['id' => $foreign])->statusCode());
        self::assertSame(404, $this->api()->export(new Request(), ['id' => $foreign])->statusCode());
        AtakMarkerCycleApiController::$testBody = json_encode(['tenantId' => 8, 'confirm' => 'SUPPRIMER']);
        self::assertSame(404, $this->api()->delete(new Request(), ['id' => $foreign])->statusCode());
        self::assertSame(404, $this->api()->restore(new Request(), ['id' => $foreign])->statusCode());
        self::assertSame(1, count($this->store->listArchives(8, [], 10, 0)), 'rien supprimé chez l’autre communauté');
    }

    public function testArchiveViewIsReadOnlyAndExportIsAFile(): void
    {
        $this->signIn(7, 5, false, false);
        $id = $this->archiveId(7);
        $before = json_encode([$this->store->archives, $this->store->items, $this->store->live]);
        $r = $this->api()->show(new Request(), ['id' => $id]);
        $j = self::json($r);
        self::assertTrue($j['readOnly']);
        self::assertCount(2, $j['items']);
        self::assertSame('marker', $j['items'][0]['kind']);
        self::assertSame([101.0, 200.0], array_map('floatval', $j['items'][0]['pos']));
        self::assertSame('LineString', $j['items'][1]['geometry']['type']);
        self::assertSame($before, json_encode([$this->store->archives, $this->store->items, $this->store->live]), 'consultation sans effet');

        $exp = $this->api()->export(new Request(), ['id' => $id]);
        self::assertSame(200, $exp->statusCode());
        self::assertStringContainsString('attachment', (string) $exp->headerValue('Content-Disposition'));
        self::assertSame('comspec-atak-mission-archive', self::json($exp)['format']);
        self::assertSame('atak_marker_cycle.export', $this->audits[0][0]);
    }

    public function testRestoreAndGuardedPurge(): void
    {
        $id = $this->archiveId(7);
        $this->signIn(7, 5, true, false);
        $r = self::json($this->api()->restore(new Request(), ['id' => $id]));
        self::assertTrue($r['ok']);
        self::assertSame(2, $r['restored']);
        self::assertSame('atak_marker_cycle.restore', $this->audits[0][0]);

        $this->signIn(7, 5, true, true);
        AtakMarkerCycleApiController::$testBody = json_encode(['confirm' => 'oui']);
        self::assertSame(422, $this->api()->delete(new Request(), ['id' => $id])->statusCode(), 'phrase de confirmation exigée');
        self::assertCount(1, $this->store->listArchives(7, [], 10, 0));
        AtakMarkerCycleApiController::$testBody = json_encode(['confirm' => 'supprimer']);
        self::assertSame(200, $this->api()->delete(new Request(), ['id' => $id])->statusCode());
        self::assertSame([], $this->store->listArchives(7, [], 10, 0));
    }

    public function testSettingsAreValidatedAndSaved(): void
    {
        $this->signIn(7, 5, true, true);
        AtakMarkerCycleApiController::$testBody = json_encode(['enabled' => true, 'switch_time' => '06:00', 'duration_days' => 2, 'retention_days' => 730, 'timezone' => 'Asia/Tokyo']);
        $j = self::json($this->api()->settings(new Request()));
        self::assertTrue($j['ok']);
        self::assertSame('06:00', $j['settings']['switch_time']);
        self::assertSame(2, $j['settings']['duration_days']);
        self::assertSame('06:00', $this->store->settingsByTenant[7]['switch_time']);
        self::assertFalse(isset($this->store->settingsByTenant[8]), 'les réglages d’une autre communauté ne bougent pas');
        AtakMarkerCycleApiController::$testBody = json_encode(['enabled' => false, 'switch_time' => 'pas une heure', 'duration_days' => 400, 'retention_days' => 3]);
        $bad = self::json($this->api()->settings(new Request()));
        self::assertSame('12:00', $bad['settings']['switch_time']);
        self::assertSame(7, $bad['settings']['duration_days']);
        self::assertFalse($bad['settings']['enabled']);
        self::assertFalse($bad['cycle']['enabled'], 'désactivé : le poste n’affiche plus de cycle');
        self::assertSame('atak_marker_cycle.settings', $this->audits[count($this->audits) - 1][0]);
    }

    public function testPagesRoutesAndScriptsAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (['/api/atak/cycle/status', '/api/atak/cycle/archives', '/api/atak/cycle/archives/{id}', '/api/atak/cycle/archives/{id}/export.json', '/api/atak/cycle/archives/{id}/restore', '/api/atak/cycle/archives/{id}/delete', '/api/atak/cycle/settings', '/api/atak/cycle/pin'] as $path) {
            self::assertStringContainsString("'" . $path . "'", $routes, $path);
        }
        foreach (['views/atak/toc/console.php', 'views/atak-overwatch-beta.php', 'views/tacmap.php'] as $view) {
            self::assertStringContainsString("views/partials/atak_marker_cycle.php", (string) file_get_contents(self::ROOT . '/' . $view), $view);
        }
        $partial = (string) file_get_contents(self::ROOT . '/views/partials/atak_marker_cycle.php');
        self::assertStringContainsString('\App\Core\Csrf::token()', $partial);
        self::assertStringNotContainsString('data-tenant', $partial, 'la communauté n’est jamais envoyée par la page');
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/atak-marker-cycle.js');
        self::assertStringContainsString("'X-CSRF-Token'", $js);
        self::assertStringNotContainsString('tenantId', $js);
        self::assertStringNotContainsString('innerHTML', $js, 'données lues en texte, jamais injectées en HTML');
        $toc = (string) file_get_contents(self::ROOT . '/public/assets/js/toc/toc-console.js');
        self::assertStringContainsString('AtakMarkerCycle.pin', $toc);
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Api/AtakMarkerCycleApiController.php');
        self::assertStringNotContainsString("query('tenant", $ctrl);
        self::assertStringNotContainsString("['tenant", $ctrl);
        self::assertSame(4, substr_count($ctrl, '$this->deny($request, true'), 'les quatre écritures vérifient le jeton CSRF');
        self::assertSame(4, substr_count($ctrl, '$this->deny($request, false)'), 'les quatre lectures exigent une session de membre');
        self::assertStringContainsString('Csrf::verify($request)', $ctrl);
    }
}
