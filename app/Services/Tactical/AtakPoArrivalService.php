<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Support\AtakPoMarker;
use JsonException;

/**
 * Confirme un point d’objectif PO lorsqu’un opérateur ATAK entre dans le rayon de 20 m.
 */
final class AtakPoArrivalService
{
    public function __construct(
        private AtakDataRepository $atak,
        private AtakActivityLogService $activityLog,
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @return list<array{id:int, label:string, distance_m:float, reached_by:string}>
     */
    public function confirmFromOperatorPosition(
        int $tenantId,
        int $mapId,
        string $callSign,
        float $posX,
        float $posY,
        array $extra = [],
    ): array {
        $callSign = trim($callSign);
        if ($callSign === '' || $tenantId < 1 || $mapId < 1) {
            return [];
        }
        if (!is_finite($posX) || !is_finite($posY) || (abs($posX) < 0.5 && abs($posY) < 0.5)) {
            return [];
        }
        if (AtakDataRepository::isProxyContactExtra($extra)
            || AtakDataRepository::callSignLooksLikeProxy($callSign)
            || AtakDataRepository::shouldHideEnemyAiContact($extra, $callSign)) {
            return [];
        }

        try {
            $rows = $this->atak->getMarkers($tenantId, $mapId);
        } catch (\Throwable) {
            return [];
        }

        $hits = [];
        foreach ($rows as $row) {
            $confirmed = $this->confirmRow($tenantId, $mapId, $row, $callSign, $posX, $posY);
            if ($confirmed !== null) {
                $hits[] = $confirmed;
            }
        }

        return $hits;
    }

    /**
     * Confirmation manuelle depuis le poste (opérateur déjà dans le rayon).
     *
     * @return array{id:int, label:string, distance_m:float, reached_by:string}|null
     */
    public function confirmMarker(
        int $tenantId,
        int $mapId,
        int $markerId,
        string $callSign,
        float $posX,
        float $posY,
    ): ?array {
        $row = $this->atak->getMarkerById($tenantId, $markerId);
        if ($row === null) {
            return null;
        }
        $data = AtakPoMarker::decodeData($row['markerData'] ?? $row['marker_data'] ?? null);
        $world = AtakPoMarker::worldPosition($data);
        if ($world !== null && AtakPoMarker::isPoMarker($data) && !empty($data['reached'])) {
            $distance = AtakPoMarker::distanceM($posX, $posY, $world['x'], $world['y']);
            $label = AtakPoMarker::labelOf($data);

            return [
                'id' => (int) ($row['id'] ?? $markerId),
                'label' => $label !== '' ? $label : 'PO',
                'distance_m' => round($distance, 1),
                'reached_by' => (string) ($data['reached_by'] ?? $callSign),
            ];
        }

        return $this->confirmRow($tenantId, $mapId, $row, $callSign, $posX, $posY);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int, label:string, distance_m:float, reached_by:string}|null
     */
    private function confirmRow(
        int $tenantId,
        int $mapId,
        array $row,
        string $callSign,
        float $posX,
        float $posY,
    ): ?array {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $data = AtakPoMarker::decodeData($row['markerData'] ?? $row['marker_data'] ?? null);
        if (!AtakPoMarker::isPoMarker($data)) {
            return null;
        }
        if (!empty($data['reached'])) {
            return null;
        }
        $world = AtakPoMarker::worldPosition($data);
        if ($world === null) {
            return null;
        }
        $distance = AtakPoMarker::distanceM($posX, $posY, $world['x'], $world['y']);
        $radius = AtakPoMarker::radiusM($data);
        if ($distance > $radius) {
            return null;
        }

        $label = AtakPoMarker::labelOf($data);
        $updated = AtakPoMarker::withReached($data, $callSign, $distance);
        try {
            $encoded = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        $saved = $this->atak->updateMarker($tenantId, $id, $encoded);
        if ($saved === null) {
            return null;
        }
        if (empty($data['reached'])) {
            try {
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_MARKER,
                    'Point d’objectif atteint — ' . ($label !== '' ? $label : 'PO') . ' · ' . $callSign,
                    $callSign,
                    [
                        'po' => true,
                        'label' => $label,
                        'distance_m' => round($distance, 1),
                    ]
                );
            } catch (\Throwable) {
            }
        }

        return [
            'id' => $id,
            'label' => $label !== '' ? $label : 'PO',
            'distance_m' => round($distance, 1),
            'reached_by' => $callSign,
        ];
    }
}
