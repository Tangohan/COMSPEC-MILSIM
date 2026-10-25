<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Intel\IntelFusionService;
use App\Support\TacticalMissionScope;

class IntelController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private IntelFusionService $intelFusion
    ) {
    }

    /**
     * Bac de mission de la communauté appelante (voir TacticalMissionScope) : un missionId
     * étranger, vide ou numérique n’est jamais retenu tel quel ; non scalaire → 422.
     */
    private function missionId(Request $request, array $body = []): string|Response
    {
        $tenantId = TacticalMissionScope::tenantId();
        if ($tenantId === null) {
            return TacticalMissionScope::tenantRequiredResponse();
        }
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id');
        if (!TacticalMissionScope::isAcceptableRaw($missionId)) {
            return TacticalMissionScope::invalidMissionIdResponse();
        }
        $mapId = $body['mapId'] ?? $request->query('mapId');

        return TacticalMissionScope::resolve($missionId, $mapId, $tenantId, self::DEFAULT_MAP_ID);
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
        if (is_scalar($missionIdRaw) && (string) $missionIdRaw === '') {
            return Response::json(['status' => 'error', 'message' => 'Identifiant de mission manquant.'], 400);
        }
        $missionId = $this->missionId($request, $body);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $report = $this->intelFusion->ingestReport($missionId, $body);
        if (empty($report)) {
            return Response::json([
                'status' => 'error',
                'message' => 'Type de cible non reconnu. Choisissez une option de la liste.',
            ], 400);
        }
        $report['status'] = ((int) ($report['merged_count'] ?? 1)) > 1 ? 'merged' : 'ok';
        $report['reportId'] = (string) ($report['id'] ?? '');
        return Response::json($report, 201);
    }

    public function fused(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $status = $request->query('status');
        $list = $this->intelFusion->listFused($missionId, $status !== '' ? $status : null);
        return Response::json($list);
    }

    /**
     * Supprime un signalement fusionné du tableau de situation.
     * DELETE /api/intel/report/{id}
     * POST  /api/intel/report/{id}/delete
     */
    public function delete(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $request->query('id') ?? 0);
        if ($id <= 0) {
            return Response::json([
                'status' => 'error',
                'message' => 'Signalement introuvable.',
            ], 400);
        }
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $ok = $this->intelFusion->deleteReport($missionId, $id);
        if (!$ok) {
            return Response::json([
                'status' => 'error',
                'message' => 'Ce signalement n’existe plus ou ne fait pas partie de cette mission.',
            ], 404);
        }
        return Response::json([
            'status' => 'ok',
            'message' => 'Signalement retiré du tableau de situation.',
        ]);
    }
}
