<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakTerrainRepository;
use App\Services\Tactical\AtakTerrainCartography;
use App\Services\Tactical\AtakTerrainMath;
use App\Services\Tactical\AtakTerrainSight;
use App\Support\ComspecApiKeyAuth;

final class AtakTerrainApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?AtakTerrainRepository $terrain = null,
        private ?AtakTerrainCartography $cartography = null,
    ) {
        $this->terrain ??= new AtakTerrainRepository();
        $this->cartography ??= new AtakTerrainCartography($this->terrain);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $wantHeights = $request->query('include') === 'heights';
        try {
            $grid = $this->terrain->getGrid($tenantId, $mapId, $wantHeights);
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
        $ready = $filled >= 9;
        $out = [
            'ok' => true,
            'ready' => $ready,
            'progress' => round($filled / $total, 3),
            'coverage_pct' => (int) round(100 * $filled / $total),
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
            'filled_cells' => $filled,
            'total_cells' => $total,
        ];
        if ($request->query('include') === 'heights' && $ready && is_string($grid['heights'] ?? null) && $grid['heights'] !== '') {
            $out['encoding'] = 'int16le_b64';
            $out['heights'] = base64_encode((string) $grid['heights']);
        } elseif (!$ready) {
            $out['message'] = $filled > 0 ? 'Relevé du relief en cours.' : 'Relief du théâtre non encore relevé.';
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

    public function samples(Request $request, array $params = []): Response
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
        $points = $body['points'] ?? [];
        if (!is_array($points) || $points === []) {
            return Response::json(['ok' => false, 'error' => 'Aucun échantillon de relief.'], 422);
        }
        if (count($points) > 20000) {
            $points = array_slice($points, 0, 20000);
        }
        $result = $this->terrain->upsertSamples($tenantId, $mapId, $body, array_values($points));
        if (empty($result['ok'])) {
            return Response::json($result, 500);
        }

        return Response::json($result);
    }

    public function hillshade(Request $request, array $params = []): Response
    {
        return $this->pngOverlay($request, 'hillshade');
    }

    public function slope(Request $request, array $params = []): Response
    {
        return $this->pngOverlay($request, 'slope');
    }

    public function contours(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $geo = $this->cartography->contours($tenantId, $mapId);

        return Response::json($geo);
    }

    public function sample(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $grid = $this->terrain->getGrid($tenantId, $mapId, true);
        if (!is_array($grid) || (int) ($grid['filled_cells'] ?? 0) < 9) {
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

    public function profile(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $grid = $this->loadGrid($request);
        if ($grid === null) {
            return $this->reliefNotReady();
        }
        $body = $this->body($request);
        $points = $body['points'] ?? [];
        if (!is_array($points) || $points === []) {
            return Response::json([
                'ok' => false,
                'error' => 'Tracez au moins deux points sur la carte.',
            ], 422);
        }

        return Response::json(AtakTerrainSight::profile($grid, array_values($points)));
    }

    public function los(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $grid = $this->loadGrid($request);
        if ($grid === null) {
            return $this->reliefNotReady();
        }
        $body = $this->body($request);
        $obs = is_array($body['observer'] ?? null) ? $body['observer'] : [];
        $tgt = is_array($body['target'] ?? null) ? $body['target'] : [];
        $x0 = $this->num($obs['x'] ?? $body['x0'] ?? $body['ox'] ?? null);
        $y0 = $this->num($obs['y'] ?? $body['y0'] ?? $body['oy'] ?? null);
        $x1 = $this->num($tgt['x'] ?? $body['x1'] ?? $body['tx'] ?? null);
        $y1 = $this->num($tgt['y'] ?? $body['y1'] ?? $body['ty'] ?? null);
        if ($x0 === null || $y0 === null || $x1 === null || $y1 === null) {
            return Response::json([
                'ok' => false,
                'error' => 'Cliquez l’observateur, puis la cible.',
            ], 422);
        }
        $obsEye = $this->num($body['observer_eye_m'] ?? $obs['eye_m'] ?? 1.6) ?? 1.6;
        $tgtEye = $this->num($body['target_eye_m'] ?? $tgt['eye_m'] ?? 0) ?? 0.0;

        return Response::json(AtakTerrainSight::lineOfSight($grid, $x0, $y0, $x1, $y1, $obsEye, $tgtEye));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadGrid(Request $request): ?array
    {
        $mapId = $this->mapId($request);
        try {
            $grid = $this->terrain->getGrid($this->resolveTenantId($request), $mapId, true);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($grid) || !AtakTerrainSight::gridReady($grid)) {
            return null;
        }

        return $grid;
    }

    private function reliefNotReady(): Response
    {
        return Response::json([
            'ok' => true,
            'ready' => false,
            'gaps' => true,
            'gap_message' => AtakTerrainSight::GAP_MESSAGE,
            'message' => 'Relief du théâtre non encore relevé.',
            'samples' => [],
        ]);
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
        $peeked = ComspecApiKeyAuth::peekJsonObject();
        if ($peeked !== []) {
            $this->jsonBodyCache = $peeked;

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

    private function pngOverlay(Request $request, string $kind): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $path = $kind === 'slope'
            ? $this->cartography->slopePath($tenantId, $mapId)
            : $this->cartography->hillshadePath($tenantId, $mapId);
        if ($path === null || !is_file($path)) {
            return Response::json(['ok' => false, 'error' => 'Relief du théâtre non encore relevé.'], 404);
        }
        $mtime = (string) filemtime($path);
        $etag = '"' . $kind . '-' . $tenantId . '-' . $mapId . '-' . $mtime . '"';
        $ifNone = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        if ($ifNone !== '' && trim($ifNone) === $etag) {
            $r = new Response();
            $r->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'private, max-age=60');

            return $r;
        }
        $bin = (string) file_get_contents($path);
        $r = new Response();
        $r->setStatusCode(200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'private, max-age=60')
            ->header('ETag', $etag)
            ->setBody($bin);

        return $r;
    }
}
