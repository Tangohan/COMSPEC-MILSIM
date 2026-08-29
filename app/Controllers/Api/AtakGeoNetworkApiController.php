<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakGeoPlaceRepository;
use App\Repositories\AtakGeoRoadRepository;
use App\Services\Tactical\AtakRoutePlanningService;
use App\Support\ComspecApiKeyAuth;

/**
 * Réseau géographique (villes, routes) et planification d’itinéraires road-aware.
 *
 * Ingest depuis le mod via COMSPECExtension « Geo.Ingest » ; lecture / planification depuis la carte web.
 */
final class AtakGeoNetworkApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?AtakGeoPlaceRepository $places = null,
        private ?AtakGeoRoadRepository $roads = null,
        private ?AtakRoutePlanningService $planner = null,
    ) {
        $this->places ??= new AtakGeoPlaceRepository();
        $this->roads ??= new AtakGeoRoadRepository();
        $this->planner ??= new AtakRoutePlanningService($this->roads);
    }

    /** GET /api/atak/geo/places */
    public function placesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $items = $this->places->searchByName($tenantId, $mapId, $q);

            return Response::json(['ok' => true, 'places' => $items, 'count' => count($items)]);
        }

        $bbox = $this->parseBbox($request);
        if ($bbox === null) {
            return Response::json(['ok' => false, 'error' => 'bbox_or_q_required'], 422);
        }
        [$minX, $minY, $maxX, $maxY] = $bbox;
        $items = $this->places->inBbox($tenantId, $mapId, $minX, $minY, $maxX, $maxY, (int) $request->query('limit', 500));

        return Response::json([
            'ok' => true,
            'places' => $items,
            'count' => count($items),
            'place_types' => AtakGeoPlaceRepository::placeTypes(),
        ]);
    }

    /** GET /api/atak/geo/roads */
    public function roadsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $bbox = $this->parseBbox($request);
        if ($bbox === null) {
            return Response::json(['ok' => false, 'error' => 'bbox_required'], 422);
        }
        [$minX, $minY, $maxX, $maxY] = $bbox;
        $mapId = $this->mapId($request);
        $items = $this->roads->inBbox(
            $tenantId,
            $mapId,
            $minX,
            $minY,
            $maxX,
            $maxY,
            (int) $request->query('limit', 2000)
        );

        return Response::json([
            'ok' => true,
            'roads' => $items,
            'count' => count($items),
            'road_classes' => AtakGeoRoadRepository::roadClasses(),
        ]);
    }

    /** GET /api/atak/geo/coverage */
    public function coverage(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $placeSummary = $this->places->summary($tenantId, $mapId);
        $roadSummary = $this->roads->summary($tenantId, $mapId);
        $last = self::laterStamp($placeSummary['last_updated_at'] ?? null, $roadSummary['last_updated_at'] ?? null);

        return Response::json([
            'ok' => true,
            'map_id' => $mapId,
            'places' => (int) ($placeSummary['places'] ?? 0),
            'roads' => (int) ($roadSummary['roads'] ?? 0),
            'geo_ready' => ((int) ($roadSummary['roads'] ?? 0)) >= 10,
            'last_ingest_at' => $last,
        ]);
    }

    /** POST /api/atak/geo/ingest */
    public function ingest(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $body = $this->body($request);
        $mapId = max(1, (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID));
        $places = is_array($body['places'] ?? null) ? array_values($body['places']) : [];
        $roads = is_array($body['roads'] ?? null) ? array_values($body['roads']) : [];
        if ($places === [] && $roads === []) {
            return Response::json(['ok' => false, 'error' => 'places_or_roads_required'], 422);
        }

        $placeCount = $places !== [] ? $this->places->upsertBatch($tenantId, $mapId, $places) : 0;
        $roadCount = $roads !== [] ? $this->roads->upsertBatch($tenantId, $mapId, $roads) : 0;

        return Response::json([
            'ok' => true,
            'map_id' => $mapId,
            'places_upserted' => $placeCount,
            'roads_upserted' => $roadCount,
        ]);
    }

    /** POST /api/atak/route/plan */
    public function planRoute(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }

        $body = $this->body($request);
        $mapId = max(1, (int) ($body['mapId'] ?? $body['map_id'] ?? self::DEFAULT_MAP_ID));
        $start = is_array($body['start'] ?? null) ? $body['start'] : null;
        $end = is_array($body['end'] ?? null) ? $body['end'] : null;
        if ($start === null || $end === null) {
            return Response::json(['ok' => false, 'error' => 'start_and_end_required'], 422);
        }

        $via = [];
        if (is_array($body['via'] ?? null)) {
            foreach ($body['via'] as $point) {
                if (is_array($point)) {
                    $via[] = $point;
                }
            }
        }

        $mode = strtolower(trim((string) ($body['mode'] ?? 'foot')));
        $snapM = isset($body['snap_m']) && is_numeric($body['snap_m'])
            ? max(20.0, min(500.0, (float) $body['snap_m']))
            : 150.0;

        $result = $this->planner->plan($tenantId, $mapId, $start, $end, $via, $mode, $snapM);
        if (empty($result['ok'])) {
            return Response::json($result, 422);
        }

        return Response::json($result);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    private function parseBbox(Request $request): ?array
    {
        $raw = (string) $request->query('bbox', '');
        if ($raw === '') {
            return null;
        }
        $parts = array_map('floatval', explode(',', $raw));
        if (count($parts) !== 4) {
            return null;
        }

        return [$parts[0], $parts[1], $parts[2], $parts[3]];
    }

    private static function laterStamp(?string $a, ?string $b): ?string
    {
        $a = ($a !== null && $a !== '') ? $a : null;
        $b = ($b !== null && $b !== '') ? $b : null;
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }
        $ta = strtotime($a);
        $tb = strtotime($b);
        if ($ta === false) {
            return $b;
        }
        if ($tb === false) {
            return $a;
        }

        return $ta >= $tb ? $a : $b;
    }

    private function tenantRequired(): Response
    {
        return Response::json([
            'ok' => false,
            'error' => 'tenant_context_required',
            'message' => 'Communauté non identifiée.',
        ], 403);
    }

    private function writeRefused(): Response
    {
        return Response::json(['ok' => false, 'error' => 'Session expirée ou clé d’accès absente.'], 419);
    }

    private function mapId(Request $request): int
    {
        $body = $this->body($request);
        $raw = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? $request->query('map_id');
        $mapId = ($raw !== null && $raw !== '') ? (int) $raw : self::DEFAULT_MAP_ID;

        return $mapId < 1 ? self::DEFAULT_MAP_ID : $mapId;
    }

    private function resolveTenantId(Request $request): int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '') {
            $n = (int) $sid;

            return $n > 0 ? $n : 0;
        }
        $body = $this->body($request);
        if (!empty($body['tenant_id'])) {
            $n = (int) $body['tenant_id'];

            return $n > 0 ? $n : 0;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : 0;
        }

        return 0;
    }

    private function writeAllowed(Request $request): bool
    {
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return true;
        }

        return $this->csrfOk($request);
    }

    private function csrfOk(Request $request): bool
    {
        $body = $this->body($request);
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->jsonBodyCache = $decoded;

                return $this->jsonBodyCache;
            }
        }
        $this->jsonBodyCache = $request->all();

        return $this->jsonBodyCache;
    }
}
