<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakTerrainRepository;
use App\Services\Tactical\AtakTerrainMath;
use App\Support\ComspecApiKeyAuth;

final class AtakTerrainApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(private ?AtakTerrainRepository $terrain = null)
    {
        $this->terrain ??= new AtakTerrainRepository();
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $metaOnly = $request->query('meta') === '1';
        try {
            $grid = $this->terrain->getGrid($tenantId, $mapId, !$metaOnly);
        } catch (\Throwable) {
            return Response::json([
                'ok' => true,
                'ready' => false,
                'progress' => 0,
                'message' => 'Relief du théâtre non encore relevé.',
            ]);
        }
        if (!is_array($grid)) {
            return Response::json([
                'ok' => true,
                'ready' => false,
                'progress' => 0,
                'message' => 'Relief du théâtre non encore relevé.',
            ]);
        }
        $cols = (int) ($grid['cols'] ?? 0);
        $rows = (int) ($grid['rows'] ?? 0);
        $filled = (int) ($grid['filled_cells'] ?? 0);
        $total = max(1, $cols * $rows);
        $ready = ((int) ($grid['ready'] ?? 0)) === 1;
        $out = [
            'ok' => true,
            'ready' => $ready,
            'progress' => round($filled / $total, 3),
            'world_name' => $grid['world_name'] ?? '',
            'world_size' => (int) ($grid['world_size'] ?? 0),
            'origin_x' => (float) ($grid['origin_x'] ?? 0),
            'origin_y' => (float) ($grid['origin_y'] ?? 0),
            'cell_m' => (int) ($grid['cell_m'] ?? 50),
            'cols' => $cols,
            'rows' => $rows,
            'min_z' => $grid['min_z'] !== null ? (int) $grid['min_z'] : null,
            'max_z' => $grid['max_z'] !== null ? (int) $grid['max_z'] : null,
            'sampled_at' => $grid['sampled_at'] ?? null,
        ];
        if (!$metaOnly && $ready && is_string($grid['heights'] ?? null) && $grid['heights'] !== '') {
            $out['encoding'] = 'int16le_b64';
            $out['heights'] = base64_encode((string) $grid['heights']);
        } elseif (!$ready) {
            $out['message'] = 'Relevé du relief en cours.';
        }

        return Response::json($out);
    }

    public function chunk(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée ou clé d’accès absente.'], 419);
        }
        $body = $this->body($request);
        $mapId = $this->mapId($request);
        $heights = $body['heights'] ?? [];
        if (!is_array($heights) || $heights === []) {
            return Response::json(['ok' => false, 'error' => 'Bloc d’altitudes manquant.'], 422);
        }
        $result = $this->terrain->upsertChunk(
            $tenantId,
            $mapId,
            $body,
            (int) ($body['col0'] ?? 0),
            (int) ($body['row0'] ?? 0),
            (int) ($body['cw'] ?? $body['width'] ?? 0),
            (int) ($body['rh'] ?? $body['height'] ?? 0),
            array_values($heights)
        );
        if (empty($result['ok'])) {
            return Response::json($result, 500);
        }

        return Response::json($result);
    }

    public function sample(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $grid = $this->terrain->getGrid($tenantId, $mapId, true);
        if (!is_array($grid) || (int) ($grid['ready'] ?? 0) !== 1) {
            return Response::json(['ok' => false, 'ready' => false, 'error' => 'Relief du théâtre non encore relevé.'], 404);
        }
        $x = $this->num($request->query('x') ?? $this->body($request)['x'] ?? null);
        $y = $this->num($request->query('y') ?? $this->body($request)['y'] ?? null);
        if ($x === null || $y === null) {
            return Response::json(['ok' => false, 'error' => 'Position manquante.'], 422);
        }
        $z = AtakTerrainMath::heightAt($grid, $x, $y);

        return Response::json(['ok' => true, 'x' => $x, 'y' => $y, 'z' => $z]);
    }

    private function mapId(Request $request): int
    {
        $body = $this->body($request);
        $raw = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId');
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

        return 0;
    }

    private function writeAllowed(Request $request): bool
    {
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return true;
        }
        $body = $this->body($request);
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    private function tenantRequired(): Response
    {
        return Response::json([
            'ok' => false,
            'error' => 'tenant_context_required',
            'message' => 'Communauté non identifiée.',
        ], 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        $decoded = json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }
}
