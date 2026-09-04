<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakSceneObjectRepository;
use App\Repositories\AtakTerrainRepository;
use App\Repositories\MapShapeRepository;
use App\Repositories\AtakDataRepository;
use App\Services\Tactical\AtakEventEnvelopeIngest;
use App\Support\ComspecApiKeyAuth;

final class AtakSceneApiController
{
    public function __construct(
        private ?AtakSceneObjectRepository $objects = null,
        private ?AtakTerrainRepository $terrain = null,
        private ?AtakEventEnvelopeIngest $events = null,
    ) {
        $this->objects ??= new AtakSceneObjectRepository();
        $this->terrain ??= new AtakTerrainRepository();
        $this->events ??= new AtakEventEnvelopeIngest(
            $this->objects,
            new MapShapeRepository(),
            new AtakDataRepository(),
        );
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        $bbox = array_map('floatval', explode(',', (string) $request->query('bbox')));
        if (count($bbox) !== 4) return Response::json(['ok' => false, 'error' => 'bbox_required'], 422);
        [$minX, $minY, $maxX, $maxY] = $bbox;
        try {
            $items = $this->objects->visible($tenantId, max(1, (int) ($request->query('mapId') ?: 1)), min($minX, $maxX), min($minY, $maxY), max($minX, $maxX), max($minY, $maxY));
        } catch (\Throwable) {
            $items = [];
        }
        return Response::json(['ok' => true, 'objects' => $items]);
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
        ];
        if (is_array($counts)) {
            $payload['buildings'] = (int) ($counts['building'] ?? 0);
            $payload['forests'] = (int) ($counts['forest'] ?? 0);
        }

        return Response::json($payload);
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

    private function tenantId(): int
    {
        return ComspecApiKeyAuth::matchedTenantId() ?? max(0, (int) (Session::get('tenant_id') ?? 0));
    }
}
