<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakPlanApiController;
use App\Controllers\Api\AtakTocBaseController;
use App\Controllers\Web\AtakTocController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanInterop;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\TocAccess;
use App\Services\Tactical\AtakTerrainMath;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Éditeur de plans v2, côté routes : import d'un fichier en aperçu (droits, CSRF, rien d'écrit), exports GeoJSON et
 * KML, ORBAT de la communauté (cloisonné, sans données personnelles), relief en binaire (en-têtes, ETag, ordre des
 * rangées), et rattachement des routes.
 */
final class AtakTocPlanV2ApiTest extends TestCase
{
    private PDO $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var list<array{0: int, 1: int}> appels reçus par les sources injectées (tenant, membre ou carte) */
    private array $calls = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = AtakTocPlanServiceTest::open();
        if ($this->pdo instanceof \Pdo\Sqlite) {
            $this->pdo->createFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        } elseif (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        }
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $cur = $db->getValue();
        $this->savedPdo = $cur instanceof PDO ? $cur : null;
        $db->setValue(null, $this->pdo);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD', 'HTTP_IF_NONE_MATCH'] as $k) {
            if (array_key_exists($k, $_SERVER)) {
                $this->savedServer[$k] = $_SERVER[$k];
            }
            unset($_SERVER[$k]);
        }
        AtakTocBaseController::$testBody = null;
        $this->calls = [];
    }

    protected function tearDown(): void
    {
        Gate::reset();
        AtakTocBaseController::$testBody = null;
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = [];
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

    private function svc(): PlanService
    {
        return new PlanService(new AtakPlanRepository($this->pdo), static fn (): ?int => 1);
    }

    /**
     * @param array<string, mixed>|null|false $orbat arbre rendu par la source de l'ORBAT (false : source du site)
     * @param array<string, mixed>|null $grid grille rendue par la source du relief
     */
    private function api(array|null|false $orbat = false, ?array $grid = null): AtakPlanApiController
    {
        $orbatSrc = $orbat === false ? null : function (int $tenantId, int $userId) use ($orbat): ?array {
            $this->calls[] = [$tenantId, $userId];

            return $tenantId === 1 ? $orbat : null;
        };
        $terrain = function (int $tenantId, int $mapId) use ($grid): ?array {
            $this->calls[] = [$tenantId, $mapId];

            return $mapId === 3 ? $grid : null;
        };

        return new AtakPlanApiController(new TocAccess(null, static fn (): array => []), $this->svc(), static fn (): array => [], $orbatSrc, $terrain);
    }

    /** @return array<string, mixed> */
    private static function json(Response $r): array
    {
        $d = json_decode($r->body(), true);

        return is_array($d) ? $d : [];
    }

    /** Altitudes int16 little-endian signées d'un corps binaire. @return list<int> */
    private static function int16(string $body): array
    {
        return array_map(static fn (int $u): int => $u >= 32768 ? $u - 65536 : $u, array_values(unpack('v*', $body) ?: []));
    }

    /** Plan de la communauté 1 (auteur 7), partagé à la communauté, avec une phase et deux éléments. */
    private function plan(): int
    {
        $this->signIn(1, 7, ['atak.toc.use']);
        AtakTocBaseController::$testBody = json_encode(['title' => 'Opération RAVEN', 'world' => 'altis', 'mapId' => 1]);
        $id = (int) self::json($this->api()->store(new Request()))['id'];
        AtakTocBaseController::$testBody = json_encode(['sharing' => 'community']);
        self::assertSame(200, $this->api()->update(new Request(), ['id' => (string) $id])->statusCode());
        AtakTocBaseController::$testBody = json_encode(['phases' => [['uid' => 'p_1', 'name' => 'Assaut']]]);
        self::assertSame(200, $this->api()->phases(new Request(), ['id' => (string) $id])->statusCode());
        AtakTocBaseController::$testBody = json_encode(['upserts' => [
            ['uid' => 'e_route', 'kind' => 'route', 'geom' => 'line', 'pts' => [[100, 100], [900, 100]], 'phases' => ['p_1']],
            ['uid' => 'e_unit', 'kind' => 'unit', 'geom' => 'point', 'pts' => [[100, 100]], 'props' => ['callsign' => 'ALPHA', 'route' => 'e_route']],
            ['uid' => 'e_hide', 'kind' => 'op', 'geom' => 'point', 'pts' => [[5, 5]], 'props' => ['hidden' => true]],
        ]]);
        self::assertCount(3, self::json($this->api()->elements(new Request(), ['id' => (string) $id]))['saved']);

        return $id;
    }

    public function testImportDeFichierDroitsCsrfEtRienDEcrit(): void
    {
        $id = $this->plan();
        $params = ['id' => (string) $id];
        $repo = new AtakPlanRepository($this->pdo);
        $geo = json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['name' => 'PC'], 'geometry' => ['type' => 'Point', 'coordinates' => [10.0, 60.0]]],
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [10.02, 60.01]]],
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'LineString', 'coordinates' => [[10.0, 60.0]]]],
        ]]);
        $body = ['format' => 'geojson', 'content' => $geo, 'fit' => [0, 0, 2000, 2000]];

        // Sans session, sans droit du poste, sans jeton CSRF.
        AtakTocBaseController::$testBody = json_encode($body);
        $_SESSION = [];
        self::assertSame(401, $this->api()->importFile(new Request(), $params)->statusCode());
        $this->signIn(1, 8, []);
        self::assertSame(403, $this->api()->importFile(new Request(), $params)->statusCode());
        $this->signIn(1, 8, ['atak.toc.use'], false);
        self::assertSame(419, $this->api()->importFile(new Request(), $params)->statusCode(), 'CSRF');
        // Autre communauté : le plan n'existe pas pour elle.
        $this->signIn(2, 8, ['atak.toc.manage']);
        self::assertSame(404, $this->api()->importFile(new Request(), $params)->statusCode());
        // Plan verrouillé : lecture seule pour qui n'en est pas l'auteur.
        $repo->updatePlan(1, $id, ['locked' => true]);
        $this->signIn(1, 8, ['atak.toc.use']);
        $ro = $this->api()->importFile(new Request(), $params);
        self::assertSame(403, $ro->statusCode());
        self::assertSame('read_only', self::json($ro)['error']);
        $repo->updatePlan(1, $id, ['locked' => false]);

        // Membre qui peut modifier : éléments validés rendus, rien d'enregistré.
        $ok = $this->api()->importFile(new Request(), $params);
        self::assertSame(200, $ok->statusCode(), $ok->body());
        $res = self::json($ok);
        self::assertTrue($res['ok']);
        self::assertSame(1, $res['rejected']);
        self::assertSame(['icon', 'icon'], array_column($res['elements'], 'kind'));
        self::assertSame('PC', $res['elements'][0]['props']['label']);
        self::assertEqualsWithDelta([[0.0, 0.0]], $res['elements'][0]['pts'], 1.0);
        self::assertEqualsWithDelta([[2000.0, 2000.0]], $res['elements'][1]['pts'], 1.0);
        self::assertSame(3, $repo->countElements($id), 'aperçu : rien n’est écrit');
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'atak_toc.plan.import_file'")->fetchColumn());

        // Format inconnu, contenu absent, fichier illisible ou dangereux : 422 avec la raison.
        foreach ([
            [['format' => 'gpx', 'content' => 'x'], 'import_format'],
            [['format' => 'geojson'], 'import_format'],
            [['format' => 'geojson', 'content' => 'pas du json'], 'import_format'],
            [['format' => 'kml', 'content' => '<!DOCTYPE kml [<!ENTITY x SYSTEM "file:///etc/passwd">]><kml>&x;</kml>'], 'import_unsafe'],
            [['format' => 'athena', 'content' => '{"format":"autre"}'], 'import_format'],
        ] as [$bad, $why]) {
            AtakTocBaseController::$testBody = json_encode($bad);
            $r = $this->api()->importFile(new Request(), $params);
            self::assertSame(422, $r->statusCode());
            self::assertSame($why, self::json($r)['error']);
        }
    }

    public function testExportsPuisReimportKmlEtAthena(): void
    {
        $id = $this->plan();
        $params = ['id' => (string) $id];
        $web = new AtakTocController(new TocAccess(null, static fn (): array => []), $this->svc());

        $geo = $web->exportGeoJson(new Request(), $params);
        self::assertSame(200, $geo->statusCode());
        self::assertStringStartsWith('application/geo+json', (string) $geo->headerValue('Content-Type'));
        self::assertSame('attachment; filename="plan-operation-raven.geojson"', $geo->headerValue('Content-Disposition'));
        self::assertSame('private, no-store', $geo->headerValue('Cache-Control'));
        $doc = self::json($geo);
        self::assertSame(PlanInterop::GEOJSON_FORMAT, $doc['athena']['format']);
        self::assertCount(2, $doc['features'], 'élément masqué non exporté');

        $kml = $web->exportKml(new Request(), $params);
        self::assertSame(200, $kml->statusCode());
        self::assertStringStartsWith('application/vnd.google-earth.kml+xml', (string) $kml->headerValue('Content-Type'));
        self::assertStringContainsString('plan-operation-raven.kml', (string) $kml->headerValue('Content-Disposition'));
        self::assertSame(2, substr_count($kml->body(), '<Placemark>'));

        // Réimport de notre KML : nouveaux identifiants, renvoi suivi, phase du plan gardée.
        AtakTocBaseController::$testBody = json_encode(['format' => 'kml', 'content' => $kml->body()]);
        $res = self::json($this->api()->importFile(new Request(), $params));
        self::assertSame(['route', 'unit'], array_column($res['elements'], 'kind'));
        self::assertSame(['p_1'], $res['elements'][0]['phases']);
        self::assertNotSame('e_route', $res['elements'][0]['uid']);
        self::assertSame($res['elements'][0]['uid'], $res['elements'][1]['props']['route']);
        self::assertEquals([[100.0, 100.0]], $res['elements'][1]['pts']);

        // Export JSON d'Athena : tous les éléments, phases inconnues du plan retirées.
        $json = $web->exportJson(new Request(), $params)->body();
        $other = json_decode($json, true);
        $other['phases'][] = ['uid' => 'p_x', 'name' => 'Ailleurs'];
        $other['elements'][1]['phases'] = ['p_x'];
        AtakTocBaseController::$testBody = json_encode(['format' => 'athena', 'content' => json_encode($other)]);
        $res = self::json($this->api()->importFile(new Request(), $params));
        self::assertCount(3, $res['elements']);
        self::assertSame([], array_intersect(array_column($res['elements'], 'uid'), ['e_route', 'e_unit', 'e_hide']));
        $phases = array_column($res['elements'], 'phases', 'kind');
        self::assertSame(['p_1'], $phases['route']);
        self::assertSame([], $phases['unit']);

        // Membre sans partage (plan remis en privé), autre communauté, membre sans le droit du poste.
        (new AtakPlanRepository($this->pdo))->updatePlan(1, $id, ['sharing' => 'private']);
        $this->signIn(1, 8, ['atak.toc.use']);
        self::assertSame(404, $web->exportGeoJson(new Request(), $params)->statusCode());
        self::assertSame(404, $web->exportKml(new Request(), $params)->statusCode());
        $this->signIn(2, 7, ['atak.toc.manage']);
        self::assertSame(404, $web->exportKml(new Request(), $params)->statusCode());
        $this->signIn(1, 7, []);
        self::assertSame(403, $web->exportGeoJson(new Request(), $params)->statusCode());
    }

    public function testOrbatDeLaCommunauteAPlatEtCloisonne(): void
    {
        $tree = [
            'id' => 'command', 'unitId' => 0, 'label' => 'Command', 'members' => [['user_id' => 5, 'label' => 'Adj Mercier']], 'children' => [
                ['unitId' => 10, 'label' => '1re compagnie', 'unitCode' => 'CIE-1', 'structType' => 'group', 'echelon' => 'compagnie', 'symbolFunction' => 'infantry_mechanized',
                    'symbolSidc' => 'SFGPUCIZ---E---', 'leader' => 'Cne Durand', 'commanderUserId' => 5, 'members' => [['user_id' => 5, 'label' => 'Cne Durand']], 'children' => [
                        ['unitId' => 11, 'label' => 'Section drones', 'unitCode' => '', 'structType' => 'team', 'echelon' => 'section', 'symbolFunction' => 'uav', 'children' => []],
                        ['unitId' => 0, 'label' => 'Unité masquée', 'isOrbatPlaceholder' => true, 'children' => []],
                        ['unitId' => 12, 'label' => 'Division', 'echelon' => 'division', 'symbolFunction' => 'cbrn', 'symbolSidc' => 'pas un code', 'children' => []],
                    ]],
                ['unitId' => 20, 'label' => 'Soutien', 'children' => 'cassé'],
            ],
        ];
        $_SESSION = [];
        self::assertSame(401, $this->api($tree)->orbat(new Request())->statusCode());
        $this->signIn(1, 7, []);
        self::assertSame(403, $this->api($tree)->orbat(new Request())->statusCode(), 'sans atak.toc.use');
        self::assertSame([], $this->calls, 'source jamais lue sans le droit');

        $this->signIn(1, 7, ['atak.toc.use']);
        $res = self::json($this->api($tree)->orbat(new Request()));
        self::assertSame([[1, 7]], $this->calls, 'communauté et membre de la session');
        self::assertTrue($res['ok']);
        self::assertTrue($res['available']);
        self::assertSame([
            ['id' => 10, 'parent_id' => null, 'name' => '1re compagnie', 'short' => 'CIE-1', 'type' => 'group', 'sidc' => 'SFGPUCIZ---E---', 'echelon' => 'company', 'unitType' => 'mech'],
            ['id' => 11, 'parent_id' => 10, 'name' => 'Section drones', 'short' => '', 'type' => 'team', 'echelon' => 'platoon', 'unitType' => 'uav'],
            ['id' => 12, 'parent_id' => 10, 'name' => 'Division', 'short' => '', 'type' => ''],
            ['id' => 20, 'parent_id' => null, 'name' => 'Soutien', 'short' => '', 'type' => ''],
        ], $res['units'], 'racine synthétique et branche masquée absentes, aucune donnée personnelle');
        self::assertStringNotContainsString('Durand', json_encode($res) ?: '');

        // Autre communauté : sa propre source, rien de la communauté 1.
        $this->signIn(2, 9, ['atak.toc.use']);
        $other = self::json($this->api($tree)->orbat(new Request()));
        self::assertSame([[1, 7], [2, 9]], $this->calls);
        self::assertSame(['ok' => true, 'available' => false, 'units' => []], $other);

        // Source du site : sans le droit de voir l'organigramme (/orbat), rien n'est lu ni rendu.
        $this->signIn(1, 7, ['atak.toc.use']);
        self::assertSame(['ok' => true, 'available' => false, 'units' => []], self::json($this->api()->orbat(new Request())));

        // Plafond.
        $big = ['unitId' => 0, 'children' => []];
        for ($i = 1; $i <= AtakPlanApiController::ORBAT_MAX_UNITS + 50; $i++) {
            $big['children'][] = ['unitId' => $i, 'label' => 'U' . $i, 'children' => []];
        }
        self::assertCount(AtakPlanApiController::ORBAT_MAX_UNITS, self::json($this->api($big)->orbat(new Request()))['units']);
    }

    public function testReliefBinaireEnTetesEtagEtOrdreDesRangees(): void
    {
        // Grille 4 × 3, cellule 50 m, origine (100, 200) : z = 10 × rangée + colonne ; une cellule sans relevé.
        $cols = 4;
        $rows = 3;
        $values = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $values[] = 10 * $r + $c;
            }
        }
        $blob = AtakTerrainMath::packInt16Le($values);
        $blob = substr($blob, 0, 2 * 5) . AtakTerrainMath::emptyBlob(1) . substr($blob, 2 * 6);
        $grid = ['id' => 4, 'cols' => $cols, 'rows' => $rows, 'grid_rows' => $rows, 'cell_m' => 50, 'origin_x' => 100, 'origin_y' => 200, 'world_size' => 8192,
            'min_z' => 0, 'max_z' => 23, 'filled_cells' => 11, 'updated_at' => '2026-11-11 10:00:00', 'heights' => $blob];

        $_SESSION = [];
        self::assertSame(401, $this->api(null, $grid)->dem(new Request(), ['mapId' => '3'])->statusCode());
        $this->signIn(1, 7, []);
        self::assertSame(403, $this->api(null, $grid)->dem(new Request(), ['mapId' => '3'])->statusCode());
        $this->signIn(1, 7, ['atak.toc.use']);
        foreach (['0', '-3', 'abc', '3.5', '3;DROP', ''] as $bad) {
            self::assertSame(404, $this->api(null, $grid)->dem(new Request(), ['mapId' => $bad])->statusCode(), $bad);
        }
        self::assertSame([], $this->calls, 'carte invalide : source jamais lue');

        // Aucune grille pour cette carte, puis grille presque vide.
        $none = $this->api(null, $grid)->dem(new Request(), ['mapId' => '9']);
        self::assertSame(404, $none->statusCode());
        self::assertSame(['ok' => false, 'error' => 'relief_unavailable'], self::json($none));
        self::assertSame([[1, 9]], $this->calls, 'communauté de la session');
        self::assertSame(404, $this->api(null, ['filled_cells' => 8] + $grid)->dem(new Request(), ['mapId' => '3'])->statusCode());

        $ok = $this->api(null, $grid)->dem(new Request(), ['mapId' => '3']);
        self::assertSame(200, $ok->statusCode());
        self::assertSame('application/octet-stream', $ok->headerValue('Content-Type'));
        self::assertSame('private, max-age=300', $ok->headerValue('Cache-Control'));
        foreach (['X-Dem-Cols' => '4', 'X-Dem-Rows' => '3', 'X-Dem-Cell' => '50', 'X-Dem-Origin' => '100,200', 'X-Dem-Min' => '0', 'X-Dem-Max' => '23', 'X-Dem-World' => '8192', 'X-Dem-Coverage' => '92'] as $h => $v) {
            self::assertSame($v, $ok->headerValue($h), $h);
        }
        $body = $ok->body();
        self::assertSame($cols * $rows * 2, strlen($body));
        $heights = self::int16($body);
        // Rangée 0 au sud : index = r × cols + c, x = ox + c × cell, y = oy + r × cell (comme AtakTerrainMath::heightAt).
        self::assertSame(0, $heights[0]);
        self::assertSame(3, $heights[3]);
        self::assertSame(23, $heights[2 * $cols + 3], 'dernière rangée = nord');
        self::assertSame(AtakTerrainMath::MISSING, $heights[1 * $cols + 1], 'cellule sans relevé');
        self::assertSame(22.0, AtakTerrainMath::heightAt($grid, 100 + 2 * 50, 200 + 2 * 50));
        self::assertSame($heights[2 * $cols + 2], (int) AtakTerrainMath::heightAt($grid, 100 + 2 * 50, 200 + 2 * 50));

        // ETag : 304 sans corps si le client a déjà cette grille ; une autre grille change l'ETag.
        $etag = (string) $ok->headerValue('ETag');
        self::assertMatchesRegularExpression('/^"dem-[0-9a-f]{20}"$/', $etag);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
        $cached = $this->api(null, $grid)->dem(new Request(), ['mapId' => '3']);
        self::assertSame(304, $cached->statusCode());
        self::assertSame('', $cached->body());
        self::assertSame($etag, $cached->headerValue('ETag'));
        $changed = $this->api(null, ['filled_cells' => 12, 'heights' => AtakTerrainMath::packInt16Le($values)] + $grid)->dem(new Request(), ['mapId' => '3']);
        self::assertSame(200, $changed->statusCode());
        self::assertNotSame($etag, $changed->headerValue('ETag'));
        unset($_SERVER['HTTP_IF_NONE_MATCH']);

        // Grille tronquée en base : complétée par « pas de relevé », jamais plus longue que cols × rows.
        $short = $this->api(null, ['heights' => substr($blob, 0, 10)] + $grid)->dem(new Request(), ['mapId' => '3']);
        $h = self::int16($short->body());
        self::assertCount($cols * $rows, $h);
        self::assertSame(AtakTerrainMath::MISSING, $h[11]);
    }

    public function testNouvellesRoutesRattachees(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach ([
            "\$router->get('/atak/toc/plans/{id}/export.geojson', [\$tocWeb, 'exportGeoJson'], \$mwAtakMemberOnly);",
            "\$router->get('/atak/toc/plans/{id}/export.kml', [\$tocWeb, 'exportKml'], \$mwAtakMemberOnly);",
            "\$router->post('/atak/toc/api/plans/{id}/import', [\$planApi, 'importFile'], \$mwAtakMemberOnly);",
            "\$router->get('/atak/toc/api/orbat', [\$planApi, 'orbat'], \$mwAtakMemberOnly);",
            "\$router->get('/atak/toc/api/terrain/{mapId}/dem', [\$planApi, 'dem'], \$mwAtakMemberOnly);",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        // Les exports précis passent avant la page {id} (le premier qui correspond gagne).
        self::assertLessThan(strpos($routes, "'/atak/toc/plans/{id}', [\$tocWeb"), strpos($routes, "'/atak/toc/plans/{id}/export.kml'"));
        foreach (['exportGeoJson', 'exportKml'] as $m) {
            self::assertTrue(method_exists(AtakTocController::class, $m));
        }
        foreach (['importFile', 'orbat', 'dem'] as $m) {
            self::assertTrue(method_exists(AtakPlanApiController::class, $m));
        }
    }
}
