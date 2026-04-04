<?php

declare(strict_types=1);

namespace App\Services\Replay;

use App\Repositories\ReplayRepository;

class ReplayService
{
    public function __construct(
        private ReplayRepository $repository
    ) {
    }

    /**
     * Build timeline grouped by timestamp for GET /api/replay/mission/{missionId}.
     */
    public function getTimeline(string $missionId, ?string $from, ?string $to): array
    {
        $rows = $this->repository->getTimeline($missionId, $from, $to);
        $byTime = [];
        foreach ($rows as $r) {
            $ts = $r['logged_at'];
            if (!isset($byTime[$ts])) {
                $byTime[$ts] = ['timestamp' => $ts, 'units' => []];
            }
            $byTime[$ts]['units'][] = [
                'unitId' => $r['unit_id'],
                'callsign' => $r['callsign'],
                'x' => (float) $r['pos_x'],
                'y' => (float) $r['pos_y'],
                'z' => (float) ($r['pos_z'] ?? 0),
                'heading' => $r['heading'] !== null ? (float) $r['heading'] : null,
            ];
        }
        return [
            'missionId' => $missionId,
            'timeline' => array_values($byTime),
        ];
    }

    public function logPosition(string $missionId, string $unitId, string $callsign, float $posX, float $posY, ?float $posZ = null, ?float $heading = null, ?string $unitType = null, ?string $side = null, ?float $speed = null, ?array $state = null): void
    {
        $this->repository->insertLog(
            $missionId,
            $unitId,
            $callsign,
            $unitType,
            $side,
            $posX,
            $posY,
            $posZ,
            $heading,
            $speed,
            $state !== null ? json_encode($state) : null
        );
    }
}
