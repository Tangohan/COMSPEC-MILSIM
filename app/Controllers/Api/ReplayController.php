<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Replay\ReplayAarPdfService;
use App\Services\Replay\ReplayService;
use App\Support\AtakMissionKey;
use App\Support\TacticalMissionScope;

class ReplayController
{
    public function __construct(
        private ReplayService $replayService
    ) {
    }

    public function mission(Request $request, array $params = []): Response
    {
        $missionId = $this->scopedMissionId($request, $params);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $data = $this->replayService->getTimeline($missionId, $from ?: null, $to ?: null, $this->missionKey($request));
        return Response::json($data);
    }

    /**
     * Sessions jouées (clé de mission de l'ATAK natif) : GET /api/replay/sessions/{missionId}.
     */
    public function sessions(Request $request, array $params = []): Response
    {
        $missionId = $this->scopedMissionId($request, $params);
        if ($missionId instanceof Response) {
            return $missionId;
        }

        return Response::json($this->replayService->getSessions($missionId));
    }

    private function missionKey(Request $request): string
    {
        return AtakMissionKey::normalize($request->query('mission_key'));
    }

    /**
     * missionId de l’URL cloisonné par communauté (voir TacticalMissionScope) : absent → 400,
     * communauté non identifiée → 403, valeur invalide → 422, mission d’une autre communauté → 403
     * au lieu d’être relue (positions, pertes, ordres, intel, véhicules de cette communauté).
     */
    private function scopedMissionId(Request $request, array $params): string|Response
    {
        $raw = $params['missionId'] ?? $request->query('missionId') ?? '';
        if (is_scalar($raw) && trim((string) $raw) === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $tenantId = TacticalMissionScope::tenantId();
        if ($tenantId === null) {
            return TacticalMissionScope::tenantRequiredResponse();
        }
        if (!TacticalMissionScope::isAcceptableRaw($raw)) {
            return TacticalMissionScope::invalidMissionIdResponse();
        }
        $missionId = trim((string) $raw);
        if (!TacticalMissionScope::belongsTo($missionId, $tenantId)) {
            return TacticalMissionScope::missionForbiddenResponse();
        }

        return $missionId;
    }

    public function events(Request $request, array $params = []): Response
    {
        $missionId = $this->scopedMissionId($request, $params);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $from = $request->query('from');
        $to = $request->query('to');
        return Response::json($this->replayService->getEvents($missionId, $from ?: null, $to ?: null, $this->missionKey($request)));
    }

    public function aar(Request $request, array $params = []): Response
    {
        $missionId = $this->scopedMissionId($request, $params);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $from = $request->query('from');
        $to = $request->query('to');
        return Response::json($this->replayService->buildAfterActionReview($missionId, $from ?: null, $to ?: null, $this->missionKey($request)));
    }

    public function aarExportPdf(Request $request, array $params = []): Response
    {
        $missionId = $this->scopedMissionId($request, $params);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $aar = $this->replayService->buildAfterActionReview($missionId, $from ?: null, $to ?: null, $this->missionKey($request));
        $title = trim($request->queryString('title'));

        return ReplayAarPdfService::response($aar, $title);
    }
}
