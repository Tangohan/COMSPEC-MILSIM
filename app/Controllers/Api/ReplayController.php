<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Replay\ReplayAarPdfService;
use App\Services\Replay\ReplayService;
use App\Support\AtakMissionKey;

class ReplayController
{
    public function __construct(
        private ReplayService $replayService
    ) {
    }

    public function mission(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
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
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }

        return Response::json($this->replayService->getSessions($missionId));
    }

    private function missionKey(Request $request): string
    {
        return AtakMissionKey::normalize($request->query('mission_key'));
    }

    public function events(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        return Response::json($this->replayService->getEvents($missionId, $from ?: null, $to ?: null, $this->missionKey($request)));
    }

    public function aar(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        return Response::json($this->replayService->buildAfterActionReview($missionId, $from ?: null, $to ?: null, $this->missionKey($request)));
    }

    public function aarExportPdf(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $aar = $this->replayService->buildAfterActionReview($missionId, $from ?: null, $to ?: null, $this->missionKey($request));
        $title = trim($request->queryString('title'));

        return ReplayAarPdfService::response($aar, $title);
    }
}
