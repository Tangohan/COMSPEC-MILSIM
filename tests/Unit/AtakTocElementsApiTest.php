<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakTocBaseController;
use App\Controllers\Api\AtakTocElementsApiController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AtakToc\Purge\TocElementStore;
use App\Services\AtakToc\Purge\TocSoftDelete;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\TocPurgeFixture as F;

/**
 * Routes JSON de suppression, corbeille et purge du poste TOC : membre connecté, communauté lue dans la session seulement,
 * jeton CSRF sur toute écriture, droit du poste pour supprimer, atak.toc.manage pour les éléments des autres,
 * atak.toc.purge (jamais atak.toc.manage seul) pour purger, limite de débit, aucun identifiant d'une autre communauté.
 */
final class AtakTocElementsApiTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    private InMemoryMarkerCycleStore $archives;

    private int $user;

    /** @var list<array{string, int, int, int, array<string, mixed>}> */
    private array $audits = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        TocSoftDelete::force(null);
        $this->pdo = F::open();
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $cur = $db->getValue();
        $this->savedPdo = $cur instanceof PDO ? $cur : null;
        $db->setValue(null, $this->pdo);
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
        AtakTocBaseController::$testBody = null;
        $this->archives = new InMemoryMarkerCycleStore();
        $this->audits = [];
        // Un membre neuf à chaque test : la limite de débit (fichier) ne passe jamais d'un test à l'autre.
        $this->user = random_int(1000000, 9000000);
    }

    protected function tearDown(): void
    {
        Gate::reset();
        TocSoftDelete::force(null);
        AtakTocBaseController::$testBody = null;
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = [];
        $_GET = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD'] as $k) {
            unset($_SERVER[$k]);
        }
        foreach ($this->savedServer as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }

    /** @param list<string> $perms */
    private function signIn(int $tenant, array $perms, bool $csrf = true): void
    {
        $_SESSION = ['tenant_id' => $tenant, 'user_id' => $this->user, '_csrf_token' => 'jeton', 'callsign' => 'Chef'];
        if ($csrf) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        } else {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Gate::getInstance()->setPermissions($perms);
    }

    private function api(): AtakTocElementsApiController
    {
        $now = F::now();

        return new AtakTocElementsApiController(
            null,
            new TocElementStore($this->pdo),
            $this->archives,
            static fn (): int => $now,
            function (string $action, int $tenant, int $user, int $map, array $details): void {
                $this->audits[] = [$action, $tenant, $user, $map, $details];
            }
        );
    }

    /** @return array<string, mixed> */
    private static function json(Response $r): array
    {
        $d = json_decode($r->body(), true);

        return is_array($d) ? $d : [];
    }

    /** @param array<string, mixed> $body */
    private static function body(array $body): void
    {
        AtakTocBaseController::$testBody = json_encode($body);
    }

    private function live(int $tenant = 1): int
    {
        return count((new TocElementStore($this->pdo, true))->elements($tenant, 1));
    }

    public function testEveryRouteRequiresAMemberWithTheTocRight(): void
    {
        $api = $this->api();
        foreach (['trash', 'delete', 'undo', 'pin', 'archive', 'purgeStatus', 'purgeAuthors', 'purgePreview', 'purgeStart', 'purgeStep', 'purgeCancel'] as $route) {
            self::assertSame(401, $api->$route(new Request())->statusCode(), $route . ' sans session');
        }
        $this->signIn(1, []);
        foreach (['trash', 'delete', 'undo', 'pin', 'archive', 'purgeStatus', 'purgeAuthors', 'purgePreview', 'purgeStart', 'purgeStep', 'purgeCancel'] as $route) {
            self::assertSame(403, $this->api()->$route(new Request())->statusCode(), $route . ' sans atak.toc.use');
        }
    }

    public function testWritesRequireTheCsrfToken(): void
    {
        $m = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Chef']);
        $this->signIn(1, ['atak.toc.use', 'atak.toc.manage', 'atak.toc.purge'], false);
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $m]], 'batch' => 'abcdef0123456789', 'scope' => ['types' => ['marker']], 'job' => 1, 'confirm' => 'PURGER', 'expected' => 1]);
        foreach (['delete', 'undo', 'pin', 'archive', 'purgePreview', 'purgeStart', 'purgeStep', 'purgeCancel'] as $route) {
            self::assertSame(419, $this->api()->$route(new Request())->statusCode(), $route . ' sans jeton CSRF');
        }
        self::assertSame(1, $this->live(), 'rien n\'a bougé');
        self::assertSame([], $this->audits);
    }

    public function testDeleteTrashAndUndoThroughTheApi(): void
    {
        $own = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'mien', 'author' => 'Chef']);
        $theirs = F::marker($this->pdo, 1, 1, ['pos' => [2, 2], 'text' => 'autre', 'author' => 'Bravo']);
        $this->signIn(1, ['atak.toc.use']);
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $own], ['table' => 'marker', 'id' => $theirs]]]);
        $r = $this->api()->delete(new Request());
        self::assertSame(200, $r->statusCode());
        $j = self::json($r);
        self::assertSame(1, $j['count']);
        self::assertSame(1, $j['forbidden'], 'le membre du poste supprime les siens seulement');
        self::assertSame(1, $this->live());
        $trash = self::json($this->api()->trash(new Request()));
        self::assertSame($j['batch'], $trash['batches'][0]['batch']);
        self::assertTrue($trash['batches'][0]['canUndo']);
        self::assertSame(900, $trash['ttl']);
        self::body(['mapId' => 1, 'batch' => $j['batch']]);
        $u = $this->api()->undo(new Request());
        self::assertSame(200, $u->statusCode());
        self::assertSame(2, $this->live());
        self::assertSame(['atak.toc.delete', 'atak.toc.delete_undo'], array_column($this->audits, 0));
        // Le même membre ne supprime pas ceux des autres : 403 quand rien n'est permis.
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $theirs]]]);
        self::assertSame(403, $this->api()->delete(new Request())->statusCode());
        $this->signIn(1, ['atak.toc.use', 'atak.toc.manage']);
        self::assertSame(200, $this->api()->delete(new Request())->statusCode(), 'atak.toc.manage supprime ceux des autres');
    }

    public function testPinnedDeletionAsksForTheStrongConfirmationWith422(): void
    {
        $p = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'P', 'author' => 'Chef', 'web_permanent' => true]);
        $this->signIn(1, ['atak.toc.use']);
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $p]]]);
        $r = $this->api()->delete(new Request());
        self::assertSame(422, $r->statusCode());
        self::assertSame('pinned_confirm', self::json($r)['error']);
        self::assertSame('PERMANENT', self::json($r)['phrase']);
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $p]], 'confirmPinned' => 'PERMANENT']);
        self::assertSame(200, $this->api()->delete(new Request())->statusCode());
        self::assertSame(0, $this->live());
    }

    public function testTenantComesFromTheSessionAndForeignIdentifiersAreIgnored(): void
    {
        $mine = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Chef']);
        $foreign = F::marker($this->pdo, 2, 1, ['pos' => [1, 1], 'text' => 'B', 'author' => 'Chef']);
        $this->signIn(1, ['atak.toc.use', 'atak.toc.manage', 'atak.toc.purge']);
        $_GET = ['tenant_id' => '2', 'tenantId' => '2'];
        self::body(['tenant_id' => 2, 'tenantId' => 2, 'mapId' => 1, 'items' => [['table' => 'marker', 'id' => $foreign]]]);
        $r = $this->api()->delete(new Request());
        self::assertSame(422, $r->statusCode());
        self::assertSame('nothing', self::json($r)['error']);
        self::assertSame(1, $this->live(2), 'la communauté 2 n\'a rien perdu');
        // Purge : même cloisonnement.
        self::body(['tenant_id' => 2, 'mapId' => 1, 'scope' => ['types' => ['marker']]]);
        $pre = self::json($this->api()->purgePreview(new Request()));
        self::assertSame(1, $pre['total']);
        self::assertSame(1, $this->live(2));
        self::assertGreaterThan(0, $mine);
    }

    public function testPurgeNeedsItsOwnRightAndManageIsNotEnough(): void
    {
        F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Chef']);
        $this->signIn(1, ['atak.toc.use', 'atak.toc.manage']);
        self::body(['mapId' => 1, 'scope' => ['types' => ['marker']], 'confirm' => 'PURGER', 'expected' => 1]);
        foreach (['purgePreview', 'purgeStart', 'purgeStep', 'purgeCancel', 'purgeAuthors'] as $route) {
            $r = $this->api()->$route(new Request());
            self::assertSame(403, $r->statusCode(), $route);
            self::assertSame('atak.toc.purge', self::json($r)['need'] ?? 'atak.toc.purge');
        }
        // Le statut reste lisible : il dit si le membre peut purger (le bouton « Purger » est grisé sinon).
        $st = self::json($this->api()->purgeStatus(new Request()));
        self::assertFalse($st['canPurge']);
        self::assertTrue($st['canManage']);
        self::assertSame(1, $this->live());
        $this->signIn(1, ['atak.toc.use', 'atak.toc.manage', 'atak.toc.purge']);
        self::assertSame(200, $this->api()->purgePreview(new Request())->statusCode());
        // L'administration de la communauté purge sans le droit dédié.
        $this->signIn(1, ['atak.toc.use', 'admin.organization']);
        self::assertSame(200, $this->api()->purgePreview(new Request())->statusCode());
    }

    public function testFullPurgeThroughTheApiWithPreviewPhraseAndSteps(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            F::marker($this->pdo, 1, 1, ['pos' => [$i, $i], 'text' => 'm' . $i, 'author' => $i % 2 === 0 ? 'Alpha' : 'Bravo']);
        }
        $this->signIn(1, ['atak.toc.use', 'atak.toc.purge']);
        $scope = ['types' => ['marker'], 'authors' => ['Alpha'], 'archiveFirst' => true, 'keepPinned' => true];
        self::body(['mapId' => 1, 'scope' => $scope]);
        $pre = self::json($this->api()->purgePreview(new Request()));
        self::assertSame(6, $pre['total']);
        self::assertSame(6, $pre['byKind']['marker']);
        self::assertFalse($pre['wholeMap']);
        // Mauvaise phrase.
        self::body(['mapId' => 1, 'scope' => $scope, 'confirm' => 'SUPPRIMER', 'expected' => 6]);
        $r = $this->api()->purgeStart(new Request());
        self::assertSame(422, $r->statusCode());
        self::assertSame('confirm_required', self::json($r)['error']);
        // Aperçu périmé.
        self::body(['mapId' => 1, 'scope' => $scope, 'confirm' => 'PURGER', 'expected' => 5]);
        self::assertSame(409, $this->api()->purgeStart(new Request())->statusCode());
        self::body(['mapId' => 1, 'scope' => $scope, 'confirm' => 'PURGER', 'expected' => 6]);
        $start = self::json($this->api()->purgeStart(new Request()));
        self::assertTrue($start['ok']);
        $job = $start['job'];
        // Seconde purge pendant la première : 409.
        self::assertSame(409, $this->api()->purgeStart(new Request())->statusCode());
        $status = self::json($this->api()->purgeStatus(new Request()));
        self::assertSame($job['id'], $status['running']['id']);
        for ($i = 0; $i < 5 && $job['status'] !== 'done'; $i++) {
            self::body(['mapId' => 1, 'job' => $job['id']]);
            $job = self::json($this->api()->purgeStep(new Request()))['job'];
        }
        self::assertSame('done', $job['status']);
        self::assertSame(6, $job['deleted']);
        self::assertSame(6, $this->live());
        self::assertSame(['atak.toc.purge_start', 'atak.toc.purge'], array_column($this->audits, 0));
        $authors = self::json($this->api()->purgeAuthors(new Request()));
        self::assertSame([['name' => 'Bravo', 'count' => 6]], $authors['authors']);
    }

    public function testRateLimitStopsARunawayClient(): void
    {
        $this->signIn(1, ['atak.toc.use', 'atak.toc.purge']);
        self::body(['mapId' => 1, 'scope' => ['types' => ['marker']], 'confirm' => 'PURGER', 'expected' => 0]);
        $codes = [];
        for ($i = 0; $i < 9; $i++) {
            $codes[] = $this->api()->purgeStart(new Request())->statusCode();
        }
        self::assertContains(429, $codes, 'au plus 6 lancements de purge par minute');
        self::assertSame(422, $codes[0]);
    }

    public function testArchiveAndPinThroughTheApi(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Chef']);
        $s = F::shape($this->pdo, 1, 1, 'LINE', ['coordinates' => [[0, 0], [5, 5]]], 'Chef');
        $this->signIn(1, ['atak.toc.use']);
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $a], ['table' => 'shape', 'id' => $s]], 'pinned' => true]);
        $p = self::json($this->api()->pin(new Request()));
        self::assertSame(2, $p['count']);
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $a], ['table' => 'shape', 'id' => $s]]]);
        $r = $this->api()->archive(new Request());
        self::assertSame(422, $r->statusCode());
        self::assertSame('pinned_confirm', self::json($r)['error'], 'archiver un épinglé demande aussi la confirmation renforcée');
        self::body(['mapId' => 1, 'items' => [['table' => 'marker', 'id' => $a], ['table' => 'shape', 'id' => $s]], 'confirmPinned' => 'PERMANENT']);
        $r = self::json($this->api()->archive(new Request()));
        self::assertTrue($r['ok']);
        self::assertSame(2, $r['count']);
        self::assertSame(0, $this->live());
    }

    public function testRoutesAreRegisteredBehindTheMemberMiddlewareAndTheStateCarriesTheMapRevision(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach ([
            ['get', 'elements/trash', 'trash'], ['post', 'elements/delete', 'delete'], ['post', 'elements/undo', 'undo'], ['post', 'elements/pin', 'pin'],
            ['post', 'elements/archive', 'archive'], ['get', 'purge/status', 'purgeStatus'], ['get', 'purge/authors', 'purgeAuthors'],
            ['post', 'purge/preview', 'purgePreview'], ['post', 'purge/start', 'purgeStart'], ['post', 'purge/step', 'purgeStep'], ['post', 'purge/cancel', 'purgeCancel'],
        ] as [$verb, $path, $method]) {
            self::assertStringContainsString("\$router->$verb('/atak/toc/api/$path', [\$tocElements, '$method'], \$mwAtakMemberOnly);", $routes);
        }
        $state = (string) file_get_contents($root . '/app/Controllers/Api/AtakTocApiController.php');
        self::assertStringContainsString("'map' => ['rev' => \$mapRev, 'event' => \$mapEvent]", $state);
        self::assertStringContainsString("ShortCache::gen('toc_map:'", $state);
        self::assertStringContainsString("'toc_evt:'", (string) file_get_contents($root . '/app/Services/AtakToc/Purge/TocDeletionService.php'));
    }
}
