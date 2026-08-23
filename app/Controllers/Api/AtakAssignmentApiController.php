<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakUnitAssignmentRepository;
use App\Services\Tactical\AtakUnitMotionService;
use App\Support\ComspecApiKeyAuth;

/**
 * Affectations unité → destination (isolées par tenant).
 */
final class AtakAssignmentApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?AtakUnitAssignmentRepository $assignments = null,
        private ?AtakUnitMotionService $motion = null,
        private ?AtakDataRepository $units = null
    ) {
        $this->assignments ??= new AtakUnitAssignmentRepository();
        $this->motion ??= new AtakUnitMotionService();
        $this->units ??= new AtakDataRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $rows = $this->assignments->listActive($tenantId, $mapId);
        $units = [];
        $air = [];
        try {
            $units = $this->motion->attachToUnits($tenantId, $mapId, $this->units->getUnits($tenantId, $mapId));
        } catch (\Throwable) {
        }
        try {
            $air = $this->motion->attachToAir($tenantId, $mapId, $this->units->getActiveAirAssets($tenantId, $mapId));
        } catch (\Throwable) {
        }
        $decorated = [];
        $unitIndex = [];
        foreach ($units as $u) {
            $cs = trim((string) ($u['call_sign'] ?? ''));
            if ($cs !== '') {
                $unitIndex['ground:' . $cs] = $u;
            }
        }
        foreach ($air as $a) {
            $cs = trim((string) ($a['callsign'] ?? $a['call_sign'] ?? ''));
            if ($cs !== '') {
                $unitIndex['air:' . $cs] = $a;
            }
        }
        foreach ($rows as $row) {
            $key = ((string) ($row['unit_kind'] ?? 'ground')) . ':' . trim((string) ($row['unit_ref'] ?? ''));
            $live = $unitIndex[$key] ?? null;
            $row['navigation'] = is_array($live) ? ($live['navigation'] ?? $live['assignment'] ?? null) : null;
            $decorated[] = $row;
        }

        return Response::json(['ok' => true, 'assignments' => $decorated]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }
        $body = $this->body($request);
        $mapId = $this->mapId($request);
        $unitRef = trim((string) ($body['unit_ref'] ?? $body['call_sign'] ?? $body['callsign'] ?? ''));
        if ($unitRef === '') {
            return Response::json(['ok' => false, 'error' => 'Unité requise.'], 422);
        }
        $unitKind = strtolower(trim((string) ($body['unit_kind'] ?? 'ground'))) === 'air' ? 'air' : 'ground';
        $unitId = (int) ($body['unit_id'] ?? 0);
        if ($unitId < 1 && $unitKind === 'ground') {
            try {
                $found = $this->units->getUnitByCallSign($tenantId, $mapId, $unitRef);
                if (is_array($found)) {
                    $unitId = (int) ($found['id'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }
        $resolved = $this->resolveDestinationCoords($tenantId, $mapId, $body);
        if ($resolved['error'] !== null) {
            return Response::json(['ok' => false, 'error' => $resolved['error']], 422);
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        $label = trim((string) ($body['assigned_by_label'] ?? Session::get('callsign') ?? Session::get('display_name') ?? ''));
        $row = $this->assignments->create($tenantId, $mapId, [
            'unit_kind' => $unitKind,
            'unit_id' => $unitId > 0 ? $unitId : null,
            'unit_ref' => $unitRef,
            'destination_type' => $resolved['type'],
            'destination_id' => $resolved['id'],
            'destination_label' => $resolved['label'],
            'destination_x' => $resolved['x'],
            'destination_y' => $resolved['y'],
            'assignment_mode' => $body['assignment_mode'] ?? 'DIRECT',
            'assigned_by' => $userId > 0 ? $userId : null,
            'assigned_by_label' => $label !== '' ? $label : null,
        ]);
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'Affectation impossible.'], 500);
        }

        return Response::json(['ok' => true, 'assignment' => $row], 201);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }
        $id = (int) ($params['id'] ?? 0);
        $body = $this->body($request);
        $patch = $body;
        if (isset($body['destination_type']) || isset($body['destination_id']) || isset($body['destination_x'])) {
            $resolved = $this->resolveDestinationCoords($tenantId, $this->mapId($request), $body);
            if ($resolved['error'] !== null) {
                return Response::json(['ok' => false, 'error' => $resolved['error']], 422);
            }
            $patch['destination_type'] = $resolved['type'];
            $patch['destination_id'] = $resolved['id'];
            $patch['destination_label'] = $resolved['label'];
            $patch['destination_x'] = $resolved['x'];
            $patch['destination_y'] = $resolved['y'];
        }
        $row = $this->assignments->update($tenantId, $id, $patch);
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'Affectation introuvable.'], 404);
        }

        return Response::json(['ok' => true, 'assignment' => $row]);
    }

    public function detach(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->assignments->detach($tenantId, $id);
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'Affectation introuvable.'], 404);
        }

        return Response::json(['ok' => true, 'assignment' => $row]);
    }

    public function arrivals(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $mapId = $this->mapId($request);
        $type = trim((string) ($request->query('destination_type') ?? $request->query('type') ?? 'marker'));
        $destId = trim((string) ($request->query('destination_id') ?? $request->query('id') ?? ''));
        if ($destId === '') {
            return Response::json(['ok' => false, 'error' => 'Destination requise.'], 422);
        }
        $units = [];
        $air = [];
        try {
            $units = $this->motion->attachToUnits($tenantId, $mapId, $this->units->getUnits($tenantId, $mapId));
        } catch (\Throwable) {
        }
        try {
            $air = $this->motion->attachToAir($tenantId, $mapId, $this->units->getActiveAirAssets($tenantId, $mapId));
        } catch (\Throwable) {
        }
        $seq = $this->motion->arrivalSequence($tenantId, $mapId, $type, $destId, $units, $air);

        return Response::json(['ok' => true, 'arrivals' => $seq]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{type:string,id:?string,label:?string,x:?float,y:?float,error:?string}
     */
    private function resolveDestinationCoords(int $tenantId, int $mapId, array $body): array
    {
        $type = strtolower(trim((string) ($body['destination_type'] ?? 'custom')));
        $id = trim((string) ($body['destination_id'] ?? ''));
        $label = trim((string) ($body['destination_label'] ?? $body['label'] ?? ''));
        $x = $this->num($body['destination_x'] ?? $body['pos_x'] ?? $body['x'] ?? null);
        $y = $this->num($body['destination_y'] ?? $body['pos_y'] ?? $body['y'] ?? null);

        if ($type === 'unit' && $id !== '') {
            $found = $this->units->getUnitByCallSign($tenantId, $mapId, $id);
            if (!is_array($found)) {
                try {
                    $air = $this->units->getActiveAirAssets($tenantId, $mapId);
                    foreach ($air as $a) {
                        if (strcasecmp(trim((string) ($a['callsign'] ?? '')), $id) === 0) {
                            $found = $a;
                            $found['pos_x'] = $a['pos_x'] ?? null;
                            $found['pos_y'] = $a['pos_y'] ?? null;
                            $found['call_sign'] = $a['callsign'] ?? $id;
                            break;
                        }
                    }
                } catch (\Throwable) {
                }
            }
            if (is_array($found)) {
                $x = $this->num($found['pos_x'] ?? null) ?? $x;
                $y = $this->num($found['pos_y'] ?? null) ?? $y;
                if ($label === '') {
                    $label = (string) ($found['call_sign'] ?? $found['callsign'] ?? $id);
                }
            }
        } elseif (in_array($type, ['marker', 'arma_marker'], true) && $id !== '' && ctype_digit($id)) {
            $marker = $this->units->getMarkerById($tenantId, (int) $id);
            if (is_array($marker)) {
                $md = $marker['markerData'] ?? null;
                $parsed = is_string($md) ? json_decode($md, true) : (is_array($md) ? $md : []);
                if (is_array($parsed)) {
                    $x = $this->num($parsed['x'] ?? $parsed['pos_x'] ?? $parsed['lng'] ?? null) ?? $x;
                    $y = $this->num($parsed['y'] ?? $parsed['pos_y'] ?? $parsed['lat'] ?? null) ?? $y;
                    if ($label === '') {
                        $label = (string) ($parsed['label'] ?? $parsed['text'] ?? $parsed['name'] ?? '');
                    }
                }
            }
        }

        if ($x === null || $y === null) {
            return ['type' => $type, 'id' => $id !== '' ? $id : null, 'label' => $label !== '' ? $label : null, 'x' => $x, 'y' => $y, 'error' => 'Position de destination manquante.'];
        }

        return [
            'type' => $type !== '' ? $type : 'custom',
            'id' => $id !== '' ? $id : null,
            'label' => $label !== '' ? $label : null,
            'x' => $x,
            'y' => $y,
            'error' => null,
        ];
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

    private function writeRefused(): Response
    {
        return Response::json(['ok' => false, 'error' => 'Session expirée ou clé d’accès absente.'], 419);
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
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }
}
