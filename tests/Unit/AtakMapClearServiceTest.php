<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDataRepository;
use App\Repositories\MapShapeRepository;
use App\Services\Atak\AtakMapClearService;
use PHPUnit\Framework\TestCase;

/** Effacer la carte : portée, droits, isolation communauté + carte, corbeille et journal. */
final class AtakMapClearServiceTest extends TestCase
{
    private string $dir;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    /** @var list<int> */
    private array $deletedMarkers = [];

    /** @var list<int> */
    private array $deletedShapes = [];

    /** @var list<int> */
    private array $restoredMarkers = [];

    /** @var list<int> */
    private array $restoredShapes = [];

    /** @var list<int> communautés vues par les dépôts */
    private array $tenants = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/atak-clear-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function service(): AtakMapClearService
    {
        $markers = [
            ['id' => 1, 'layer_id' => 1, 'marker_data' => json_encode(['owner_user_id' => 7, 'type' => 'mil_dot']), 'arma_name' => null],
            ['id' => 2, 'layer_id' => 1, 'marker_data' => json_encode(['author' => 'Raven', 'type' => 'mil_dot']), 'arma_name' => null],
            ['id' => 3, 'layer_id' => 1, 'marker_data' => json_encode(['author' => 'Autre', 'type' => 'mil_dot']), 'arma_name' => 'm3'],
            ['id' => 4, 'layer_id' => 1, 'marker_data' => json_encode(['mobile_user' => 9]), 'arma_name' => null],
        ];
        $shapes = [
            ['id' => 10, 'shape_uid' => 'a', 'type' => 'ROUTE', 'created_by' => 'Raven', 'geometry' => ['type' => 'LineString', 'coordinates' => []], 'meta' => []],
            ['id' => 11, 'shape_uid' => 'b', 'type' => 'AO', 'created_by' => 'Autre', 'geometry' => ['type' => 'Polygon', 'coordinates' => []], 'meta' => ['owner_user_id' => 7]],
            ['id' => 12, 'shape_uid' => 'c', 'type' => 'POINT', 'created_by' => 'Autre', 'geometry' => ['type' => 'Point', 'coordinates' => []], 'meta' => []],
        ];
        $atak = $this->createMock(AtakDataRepository::class);
        $atak->method('listLiveMarkerRows')->willReturnCallback(function (int $t, int $m) use ($markers): array {
            $this->tenants[] = $t;

            return $markers;
        });
        $atak->method('deleteMarker')->willReturnCallback(function (int $t, int $id): bool {
            $this->tenants[] = $t;
            $this->deletedMarkers[] = $id;

            return true;
        });
        $atak->method('restoreMarkerRow')->willReturnCallback(function (int $t, int $m, array $row): bool {
            $this->tenants[] = $t;
            $this->restoredMarkers[] = (int) $row['id'];

            return true;
        });
        $repo = $this->createMock(MapShapeRepository::class);
        $repo->method('list')->willReturnCallback(function (int $t, int $m) use ($shapes): array {
            $this->tenants[] = $t;

            return $shapes;
        });
        $repo->method('delete')->willReturnCallback(function (int $t, int $id): bool {
            $this->tenants[] = $t;
            $this->deletedShapes[] = $id;

            return true;
        });
        $repo->method('restoreRow')->willReturnCallback(function (int $t, int $m, array $row): bool {
            $this->restoredShapes[] = (int) $row['id'];

            return true;
        });

        return new AtakMapClearService($atak, $repo, null, $this->dir, function (string $action, int $t, ?int $u, int $m, array $d): void {
            $this->audit[] = ['action' => $action, 'tenant' => $t, 'user' => $u, 'map' => $m] + $d;
        });
    }

    /** @return array{userId:int, aliases:list<string>, canManage:bool} */
    private static function player(bool $manage = false): array
    {
        return ['userId' => 7, 'aliases' => ['Raven'], 'canManage' => $manage];
    }

    public function testShapesAreClassifiedAndPreviewCountsTotalAndMine(): void
    {
        self::assertSame('route', AtakMapClearService::shapeKind(['type' => 'LINE', 'geometry' => ['type' => 'LineString']]));
        self::assertSame('zone', AtakMapClearService::shapeKind(['type' => 'DANGER', 'geometry' => ['type' => 'Polygon']]));
        self::assertSame('form', AtakMapClearService::shapeKind(['type' => 'POINT', 'geometry' => ['type' => 'Point']]));
        $p = $this->service()->preview(3, 2, self::player());
        self::assertSame(['marker' => 4, 'form' => 1, 'route' => 1, 'zone' => 1], $p['counts']);
        self::assertSame(['marker' => 2, 'form' => 0, 'route' => 1, 'zone' => 1], $p['mine']);
        self::assertSame(7, $p['total']);
        self::assertFalse($p['canAll']);
    }

    public function testPlayerClearsOnlyOwnItems(): void
    {
        $r = $this->service()->clear(3, 2, self::player(), 'mine', [], 'supprimer');
        self::assertTrue($r['ok']);
        self::assertSame([1, 2], $this->deletedMarkers);
        self::assertSame([10, 11], $this->deletedShapes);
        self::assertSame(4, $r['total']);
    }

    public function testPlayerCannotClearEveryoneButManagerCan(): void
    {
        $r = $this->service()->clear(3, 2, self::player(), 'all', [], 'SUPPRIMER');
        self::assertFalse($r['ok']);
        self::assertSame('forbidden', $r['error']);
        self::assertSame([], $this->deletedMarkers);
        $r = $this->service()->clear(3, 2, self::player(true), 'all', [], 'SUPPRIMER');
        self::assertTrue($r['ok']);
        self::assertSame([1, 2, 3, 4], $this->deletedMarkers);
        self::assertSame([10, 11, 12], $this->deletedShapes);
    }

    public function testTypeFilterAndConfirmationPhrase(): void
    {
        $bad = $this->service()->clear(3, 2, self::player(true), 'all', [], 'oui');
        self::assertSame('confirm_required', $bad['error']);
        self::assertSame([], $this->deletedMarkers);
        $r = $this->service()->clear(3, 2, self::player(true), 'all', ['route'], 'SUPPRIMER');
        self::assertSame([], $this->deletedMarkers);
        self::assertSame([10], $this->deletedShapes);
        self::assertSame(1, $r['total']);
        self::assertSame('scope', $this->service()->clear(3, 2, self::player(true), 'x', [], 'SUPPRIMER')['error']);
    }

    public function testEverythingStaysInTheCommunityAndMapAndIsAudited(): void
    {
        $r = $this->service()->clear(3, 2, self::player(), 'mine', ['marker'], 'SUPPRIMER');
        foreach ($this->tenants as $t) {
            self::assertSame(3, $t);
        }
        self::assertCount(1, $this->audit);
        self::assertSame('atak.map_clear', $this->audit[0]['action']);
        self::assertSame(3, $this->audit[0]['tenant']);
        self::assertSame(7, $this->audit[0]['user']);
        self::assertSame(2, $this->audit[0]['map']);
        self::assertSame('mine', $this->audit[0]['scope']);
        self::assertSame(2, $this->audit[0]['total']);
        self::assertSame($r['batch'], $this->audit[0]['batch']);
    }

    public function testUndoRestoresOnlyForTheAuthorOrManagerAndOnlyOnce(): void
    {
        $svc = $this->service();
        $batch = $svc->clear(3, 2, self::player(), 'mine', [], 'SUPPRIMER')['batch'];
        $other = ['userId' => 8, 'aliases' => ['Autre'], 'canManage' => false];
        self::assertSame('forbidden', $svc->undo(3, 2, $other, $batch)['error']);
        self::assertSame('expired', $svc->undo(3, 2, self::player(), 'not-a-batch')['error']);
        // Autre communauté ou autre carte : le lot n'existe pas.
        self::assertSame('expired', $svc->undo(4, 2, self::player(), $batch)['error']);
        self::assertSame('expired', $svc->undo(3, 5, self::player(), $batch)['error']);
        $r = $svc->undo(3, 2, self::player(), $batch);
        self::assertTrue($r['ok']);
        self::assertSame(4, $r['restored']);
        self::assertSame([1, 2], $this->restoredMarkers);
        self::assertSame([10, 11], $this->restoredShapes);
        self::assertSame('expired', $svc->undo(3, 2, self::player(), $batch)['error']);
        self::assertSame('atak.map_clear_undo', $this->audit[1]['action']);
    }

    public function testExpiredTrashCannotBeRestored(): void
    {
        $svc = $this->service();
        $batch = $svc->clear(3, 2, self::player(), 'mine', [], 'SUPPRIMER')['batch'];
        $file = glob($this->dir . '/*.json')[0];
        $raw = json_decode((string) file_get_contents($file), true);
        $raw['at'] = time() - AtakMapClearService::TRASH_TTL - 5;
        file_put_contents($file, json_encode($raw));
        self::assertSame('expired', $svc->undo(3, 2, self::player(), $batch)['error']);
    }

    public function testRoutesCsrfAndNothingOutsideTheMap(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("/api/atak/map/clear', [AtakApiController::class, 'mapClear']", $routes);
        self::assertStringContainsString("'/m/api/carte/effacer'", $routes);
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $guard = substr($api, (int) strpos($api, 'private function mapClearGuard'), 1800);
        self::assertStringContainsString('Csrf::verify($request)', $guard);
        self::assertStringContainsString('requireReconOperatorSession', $guard);
        $mobile = (string) file_get_contents($root . '/app/Controllers/Web/AtakMobileAppsController.php');
        $fn = substr($mobile, (int) strpos($mobile, 'public function mapClear('), 1200);
        self::assertStringContainsString('guardPost', $fn);
        self::assertStringContainsString('SCOPE_MINE', $fn);
        self::assertStringNotContainsString('SCOPE_ALL', $fn);
        $svc = (string) file_get_contents($root . '/app/Services/Atak/AtakMapClearService.php');
        foreach (['DELETE FROM', 'atak_units', 'personnel_', 'FROM documents'] as $needle) {
            self::assertStringNotContainsString($needle, $svc);
        }
    }

    public function testManagerRightsComeFromTheRoleNotTheEmptyApiGate(): void
    {
        self::assertTrue(AtakMapClearService::managerFromRole('platform', false));
        self::assertTrue(AtakMapClearService::managerFromRole('manager', false));
        self::assertTrue(AtakMapClearService::managerFromRole(null, true));
        self::assertFalse(AtakMapClearService::managerFromRole(null, false));
        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        $guard = substr($api, (int) strpos($api, 'private function mapClearGuard'), 2600);
        self::assertStringNotContainsString("can('admin", $guard);
        self::assertStringContainsString('AtakGameAdminService', $guard);
    }

    public function testGameMarkersCountForManagersAndPreviewMatchesClear(): void
    {
        $p = $this->service()->preview(3, 2, self::player(true));
        self::assertTrue($p['canAll']);
        self::assertSame([], $p['needs']);
        self::assertSame(7, $p['total']);
        self::assertGreaterThan(0, $p['counts']['marker']);
        $r = $this->service()->clear(3, 2, self::player(true), 'all', [], 'SUPPRIMER');
        self::assertSame($p['total'], $r['total']);
        self::assertSame($p['counts'], $r['counts']);
        $q = $this->service()->preview(3, 2, self::player());
        self::assertFalse($q['canAll']);
        self::assertContains('admin.system', $q['needs']);
        self::assertContains('atak.mission_cycle.manage', $q['needs']);
        $this->deletedMarkers = [];
        $mine = $this->service()->clear(3, 2, self::player(), 'mine', [], 'SUPPRIMER');
        self::assertSame($q['totalMine'], $mine['total']);
    }
}
