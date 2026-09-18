<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakGeoPlaceRepository;
use App\Repositories\AtakGeoRoadRepository;
use App\Repositories\AtakSceneObjectRepository;
use App\Repositories\AtakTerrainRepository;
use App\Repositories\MapShapeRepository;
use App\Repositories\AtakDataRepository;
use App\Services\Tactical\AtakEventEnvelopeIngest;
use App\Services\Tactical\AtakSceneBounds;
use App\Services\Tactical\AtakSceneKind;
use App\Services\Tactical\AtakSceneMeshBake;
use App\Support\ComspecApiKeyAuth;

final class AtakSceneApiController
{
    public function __construct(
        private ?AtakSceneObjectRepository $objects = null,
        private ?AtakTerrainRepository $terrain = null,
        private ?AtakEventEnvelopeIngest $events = null,
        private ?AtakSceneMeshBake $mesh = null,
    ) {
        $this->objects ??= new AtakSceneObjectRepository();
        $this->terrain ??= new AtakTerrainRepository();
        $this->events ??= new AtakEventEnvelopeIngest(
            $this->objects,
            new MapShapeRepository(),
            new AtakDataRepository(),
        );
        $this->mesh ??= new AtakSceneMeshBake($this->objects, $this->terrain);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        $bbox = array_map('floatval', explode(',', (string) $request->query('bbox')));
        if (count($bbox) !== 4) return Response::json(['ok' => false, 'error' => 'bbox_required'], 422);
        [$minX, $minY, $maxX, $maxY] = $bbox;
        $kind = $this->normalizeKind($request->query('kind'));
        $limit = (int) ($request->query('limit') ?: 5000);
        if ($limit < 1) {
            $limit = 5000;
        }
        $limit = min(8000, $limit);
        try {
            $items = $this->objects->visible(
                $tenantId,
                max(1, (int) ($request->query('mapId') ?: 1)),
                min($minX, $maxX),
                min($minY, $maxY),
                max($minX, $maxX),
                max($minY, $maxY),
                $limit,
                $kind
            );
        } catch (\Throwable) {
            $items = [];
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rowKind = $this->normalizeKind($item['kind'] ?? '') ?? 'building';
            $box = AtakSceneBounds::sanitize($item);
            $row = [
                'id' => (string) ($item['id'] ?? ''),
                'kind' => $rowKind,
                'x' => (float) ($item['x'] ?? 0),
                'y' => (float) ($item['y'] ?? 0),
                'bearing' => (float) ($item['bearing'] ?? 0),
                'width' => $box['width'],
                'depth' => $box['depth'],
                'height' => $box['height'],
            ];
            if ($rowKind === 'forest' && isset($item['density']) && is_numeric($item['density'])) {
                $row['density'] = (float) $item['density'];
            }
            $out[] = $row;
        }

        return Response::json(['ok' => true, 'objects' => $out]);
    }

    public function ingest(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        $raw = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($raw) ? $raw : [];
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));
        if (ComspecApiKeyAuth::extractPresentedKey() === '' && !Csrf::validate($token)) {
            return Response::json(['ok' => false, 'error' => 'access_denied'], 419);
        }
        $items = $body['objects'] ?? [];
        $schema = (string) ($body['schema'] ?? '');
        if ($schema !== 'athena.event.v1' && (!is_array($items) || $items === [])) {
            return Response::json(['ok' => false, 'error' => 'objects_required'], 422);
        }
        $result = $this->events->ingest($tenantId, $body);
        if (empty($result['ok'])) {
            return Response::json(['ok' => false, 'error' => (string) ($result['error'] ?? 'objects_required')], 422);
        }
        try {
            $this->mesh->invalidate(max(1, (int) ($body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?: 1)));
        } catch (\Throwable) {
        }
        return Response::json(['ok' => true, 'upserted' => (int) ($result['upserted'] ?? 0)] + array_filter([
            'ignored' => $result['ignored'] ?? null,
            'deleted' => $result['deleted'] ?? null,
        ]));
    }

    public function coverage(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        }
        $mapId = max(1, (int) ($request->query('mapId') ?: 1));
        $counts = null;
        try {
            $counts = $this->objects->countByKind($tenantId, $mapId);
        } catch (\Throwable) {
            $counts = null;
        }
        try {
            $terrain = $this->terrain->coverageSummary($tenantId, $mapId);
        } catch (\Throwable) {
            $terrain = [
                'terrain_filled' => 0,
                'terrain_total' => 0,
                'terrain_chunks' => 0,
                'terrain_coverage_pct' => 0,
                'sampled_at' => null,
            ];
        }
        try {
            $sceneAt = $this->objects->lastUpdatedAt($tenantId, $mapId);
        } catch (\Throwable) {
            $sceneAt = null;
        }
        $lastSurvey = self::laterStamp(
            isset($terrain['sampled_at']) && is_string($terrain['sampled_at']) ? $terrain['sampled_at'] : null,
            $sceneAt
        );

        $payload = [
            'ok' => true,
            'terrain_filled' => (int) ($terrain['terrain_filled'] ?? 0),
            'terrain_total' => (int) ($terrain['terrain_total'] ?? 0),
            'terrain_chunks' => (int) ($terrain['terrain_chunks'] ?? 0),
            'terrain_coverage_pct' => (int) ($terrain['terrain_coverage_pct'] ?? 0),
            'last_survey_at' => $lastSurvey,
            'places' => 0,
            'roads' => 0,
        ];
        if (is_array($counts)) {
            $payload['buildings'] = (int) ($counts['building'] ?? 0);
            $payload['forests'] = (int) ($counts['forest'] ?? 0);
            $payload['obstacles'] = (int) ($counts['obstacle'] ?? 0);
        }
        try {
            $placeSummary = (new AtakGeoPlaceRepository())->summary($tenantId, $mapId);
            $payload['places'] = (int) ($placeSummary['places'] ?? 0);
        } catch (\Throwable) {
            $payload['places'] = 0;
        }
        try {
            $roadSummary = (new AtakGeoRoadRepository())->summary($tenantId, $mapId);
            $payload['roads'] = (int) ($roadSummary['roads'] ?? 0);
        } catch (\Throwable) {
            $payload['roads'] = 0;
        }
        if ($request->query('include') === 'gaps') {
            $payload['terrain_gaps'] = [];
            try {
                $grid = $this->terrain->getGrid($tenantId, $mapId, true);
                if (is_array($grid)) {
                    $payload['terrain_gaps'] = \App\Services\Tactical\AtakTerrainSight::coverageGaps($grid);
                }
            } catch (\Throwable) {
                $payload['terrain_gaps'] = [];
            }
        }

        return Response::json($payload);
    }

    public function anchor(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        }
        if (ComspecApiKeyAuth::extractPresentedKey() === '') {
            $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? '');
            if (!Csrf::validate($token)) {
                return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
            }
        }
        $body = [];
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }
        $mapId = max(1, (int) ($body['mapId'] ?? $request->query('mapId') ?: 1));
        $id = trim((string) ($body['id'] ?? $request->query('id') ?? ''));
        if ($id === '') {
            return Response::json(['ok' => false, 'error' => 'Objet manquant.'], 422);
        }
        $row = $this->objects->findBySourceId($mapId, $id);
        if (!is_array($row)) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $extras = [];
        if (!empty($row['extras'])) {
            $decodedExtras = json_decode((string) $row['extras'], true);
            $extras = is_array($decodedExtras) ? $decodedExtras : [];
        }
        $anchors = is_array($extras['anchors'] ?? null) ? $extras['anchors'] : [];
        $floor = isset($body['floor']) && is_numeric($body['floor']) ? max(0, min(20, (int) $body['floor'])) : null;
        $face = strtolower(trim((string) ($body['face'] ?? '')));
        if (!in_array($face, ['n', 's', 'e', 'w', 'roof', 'interior', ''], true)) {
            $face = '';
        }
        $kind = strtolower(trim((string) ($body['kind'] ?? $body['payload_kind'] ?? 'note')));
        if (!in_array($kind, ['photo', 'task', 'note', 'event', 'sse', 'marker'], true)) {
            $kind = 'note';
        }
        $label = substr(trim((string) ($body['label'] ?? '')), 0, 160);
        $payloadId = substr(trim((string) ($body['payload_id'] ?? '')), 0, 64);
        $anchors[] = [
            'kind' => $kind,
            'label' => $label,
            'payload_id' => $payloadId,
            'face' => $face !== '' ? $face : null,
            'floor' => $floor,
        ];
        $anchors = array_slice($anchors, -40);
        $patch = ['anchors' => $anchors];
        if ($floor !== null && !empty($body['set_floor'])) {
            $patch['floor_focus'] = $floor;
        }
        if (!$this->objects->mergeExtras($mapId, $id, $patch)) {
            return Response::json(['ok' => false, 'error' => 'Enregistrement impossible.'], 500);
        }

        return Response::json(['ok' => true, 'anchors' => $anchors, 'floor_focus' => $patch['floor_focus'] ?? ($extras['floor_focus'] ?? null)]);
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

    public function mesh(Request $request): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        }
        $bbox = array_map('floatval', explode(',', (string) $request->query('bbox')));
        if (count($bbox) !== 4) {
            return Response::json(['ok' => false, 'error' => 'bbox_required'], 422);
        }
        [$minX, $minY, $maxX, $maxY] = $bbox;
        $lod = (string) $request->query('lod', '2');
        try {
            return Response::json($this->mesh->mesh(
                $tenantId,
                max(1, (int) ($request->query('mapId') ?: 1)),
                min($minX, $maxX),
                min($minY, $maxY),
                max($minX, $maxX),
                max($minY, $maxY),
                $lod
            ));
        } catch (\Throwable) {
            return Response::json(['ok' => true, 'lod' => AtakSceneKind::normalizeLod($lod), 'objects' => [], 'obstacles' => []]);
        }
    }

    public function show(Request $request): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        }
        $id = trim((string) ($request->query('id') ?: ''));
        if ($id === '') {
            return Response::json(['ok' => false, 'error' => 'object_required'], 422);
        }
        $mapId = max(1, (int) ($request->query('mapId') ?: 1));
        try {
            $row = $this->objects->findBySourceId($mapId, $id);
        } catch (\Throwable) {
            $row = null;
        }
        if (!is_array($row)) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $kind = AtakSceneKind::normalize((string) ($row['kind'] ?? 'building'));
        $box = AtakSceneKind::isObstacle($kind)
            ? AtakSceneBounds::sanitizeObstacle($row)
            : AtakSceneBounds::sanitize($row);
        $extras = [];
        if (!empty($row['extras'])) {
            $decoded = json_decode((string) $row['extras'], true);
            $extras = is_array($decoded) ? $decoded : [];
        }
        $doors = (int) ($extras['doors'] ?? 0);
        $exits = is_array($extras['exits'] ?? null) ? $extras['exits'] : [];
        $anchors = is_array($extras['anchors'] ?? null) ? $extras['anchors'] : [];
        $model = (string) ($row['model'] ?? '');
        $x = (float) ($row['x'] ?? 0);
        $y = (float) ($row['y'] ?? 0);
        $floors = AtakSceneKind::floors($box['height']);
        $photos = [];
        try {
            $all = (new AtakDataRepository())->getIntelPhotos($tenantId, $mapId);
            foreach ($all as $photo) {
                $px = isset($photo['pos_x']) && is_numeric($photo['pos_x']) ? (float) $photo['pos_x'] : null;
                $py = isset($photo['pos_y']) && is_numeric($photo['pos_y']) ? (float) $photo['pos_y'] : null;
                if ($px === null || $py === null) {
                    continue;
                }
                if (hypot($px - $x, $py - $y) > 40) {
                    continue;
                }
                $photos[] = [
                    'id' => (string) ($photo['id'] ?? ''),
                    'author' => (string) ($photo['author'] ?? ''),
                    'created_at' => (string) ($photo['created_at'] ?? ''),
                ];
                if (count($photos) >= 8) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return Response::json([
            'ok' => true,
            'object' => [
                'id' => (string) ($row['id'] ?? $id),
                'kind' => $kind,
                'kind_label' => AtakSceneKind::label($kind),
                'name' => AtakSceneKind::humanName($model, $kind, (string) ($row['id'] ?? $id)),
                'model' => $model,
                'x' => $x,
                'y' => $y,
                'z' => isset($row['z']) && is_numeric($row['z']) ? (float) $row['z'] : null,
                'bearing' => (float) ($row['bearing'] ?? 0),
                'width' => $box['width'],
                'depth' => $box['depth'],
                'height' => $box['height'],
                'floors' => $floors,
                'floor_labels' => AtakSceneKind::floorLabels($floors),
                'floor_focus' => isset($extras['floor_focus']) && is_numeric($extras['floor_focus']) ? (int) $extras['floor_focus'] : null,
                'doors' => $doors,
                'exits' => $exits,
                'quality' => AtakSceneKind::quality($row, $box['clipped']),
                'photos' => $photos,
                'anchors' => $anchors,
                'roof' => [
                    'height' => round($box['height'], 1),
                    'area_m2' => (int) round($box['width'] * $box['depth']),
                    'slope_pct' => null,
                ],
                'chunk' => ((int) floor($x / 400.0)) . ':' . ((int) floor($y / 400.0)),
                'source' => 'relevé jeu',
                'lod' => AtakSceneKind::isObstacle($kind) ? '2' : '3',
                'confidence' => AtakSceneKind::quality($row, $box['clipped']),
            ],
        ]);
    }

    private function tenantId(): int
    {
        return ComspecApiKeyAuth::matchedTenantId() ?? max(0, (int) (Session::get('tenant_id') ?? 0));
    }

    private function normalizeKind(mixed $raw): ?string
    {
        $kind = strtolower(trim((string) $raw));
        if ($kind === 'building' || $kind === 'buildings') {
            return 'building';
        }
        if ($kind === 'forest' || $kind === 'forests' || $kind === 'tree' || $kind === 'trees') {
            return 'forest';
        }

        return $kind === '' ? null : $kind;
    }
}
