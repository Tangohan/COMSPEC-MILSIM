<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Intel\IntelFusionService;

class IntelController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private IntelFusionService $intelFusion
    ) {
    }

    private function missionId(Request $request, array $body = []): string
    {
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id');
        if ($missionId !== null && $missionId !== '') {
            return (string) $missionId;
        }
        $tenantId = Session::get('tenant_id');
        $tid = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : 1;
        $mapId = $body['mapId'] ?? $request->query('mapId');
        $mid = $mapId !== null && $mapId !== '' ? (int) $mapId : self::DEFAULT_MAP_ID;
        return 'mission_' . $tid . '_map_' . $mid;
    }

    private function jsonBody(Request $request): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function report(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionIdRaw = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id');
        if ($missionIdRaw !== null && (string) $missionIdRaw === '') {
            return Response::json(['status' => 'error', 'message' => 'missionId vide'], 400);
        }
        $missionId = $this->missionId($request, $body);
        $report = $this->intelFusion->ingestReport($missionId, $body);
        if (empty($report)) {
            return Response::json(['status' => 'error', 'message' => 'Rejet (target_type invalide ou erreur)'], 400);
        }
        $report['status'] = ((int) ($report['merged_count'] ?? 1)) > 1 ? 'merged' : 'ok';
        $report['reportId'] = (string) ($report['id'] ?? '');
        return Response::json($report, 201);
    }

    public function fused(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $status = $request->query('status');
        $list = $this->intelFusion->listFused($missionId, $status !== '' ? $status : null);
        return Response::json($list);
    }
}
