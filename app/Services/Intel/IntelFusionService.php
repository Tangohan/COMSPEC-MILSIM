<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Repositories\IntelReportRepository;

class IntelFusionService
{
    private const FUSION_RADIUS_METERS = 100;
    private const FUSION_WINDOW_SECONDS = 300;

    /** Liste blanche des target_type acceptés */
    private const TARGET_TYPE_WHITELIST = ['INFANTRY', 'VEHICLE', 'ARMOR', 'AIR_DEFENSE', 'UNKNOWN'];

    public function __construct(
        private IntelReportRepository $repository
    ) {
    }

    public function ingestReport(string $missionId, array $payload): array
    {
        $targetType = $payload['target_type'] ?? $payload['targetType'] ?? 'UNKNOWN';
        $targetType = strtoupper(trim((string) $targetType));
        if (!in_array($targetType, self::TARGET_TYPE_WHITELIST, true)) {
            return [];
        }
        $posX = (float) ($payload['pos_x'] ?? $payload['posX'] ?? 0);
        $posY = (float) ($payload['pos_y'] ?? $payload['posY'] ?? 0);
        $posZ = (float) ($payload['pos_z'] ?? $payload['posZ'] ?? 0);
        $sourceCallsign = $payload['source_callsign'] ?? $payload['sourceCallsign'] ?? null;
        $reportType = $payload['report_type'] ?? $payload['reportType'] ?? null;

        $existing = $this->repository->findCompatible(
            $missionId,
            $targetType,
            $posX,
            $posY,
            self::FUSION_RADIUS_METERS,
            self::FUSION_WINDOW_SECONDS
        );
        if ($existing) {
            $this->repository->mergeInto((int) $existing['id'], $sourceCallsign, $payload);
            return $this->repository->getById((int) $existing['id']) ?? [];
        }
        return $this->repository->create($missionId, [
            'source_callsign' => $sourceCallsign,
            'report_type' => $reportType,
            'target_type' => $targetType,
            'pos_x' => $posX,
            'pos_y' => $posY,
            'pos_z' => $posZ,
            'raw_payload_json' => $payload,
        ]);
    }

    public function listFused(string $missionId, ?string $status = null): array
    {
        return $this->repository->listByMission($missionId, $status);
    }
}
