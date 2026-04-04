<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Replay\ReplayService;

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
        $data = $this->replayService->getTimeline($missionId, $from ?: null, $to ?: null);
        return Response::json($data);
    }

    public function events(Request $request, array $params = []): Response
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? '';
        if ($missionId === '') {
            return Response::json(['error' => 'missionId required'], 400);
        }
        return Response::json(['events' => [], 'missionId' => $missionId]);
    }
}
