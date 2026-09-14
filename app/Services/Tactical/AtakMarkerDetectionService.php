<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMarkerDetectionRuleRepository;
use App\Repositories\AtakOrderRepository;
use App\Support\AtakMarkerDetection;
use App\Support\AtakPoMarker;
use JsonException;

/**
 * Applique les règles de détection des marqueurs posés en jeu.
 */
final class AtakMarkerDetectionService
{
    public function __construct(
        private AtakDataRepository $atak,
        private AtakMarkerDetectionRuleRepository $rules,
        private AtakActivityLogService $activityLog,
        private ?AtakOrderRepository $orders = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enabledRules(int $tenantId): array
    {
        try {
            return $this->rules->listEnabled($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function applyToJson(int $tenantId, string $json, string $armaName = ''): string
    {
        $data = AtakPoMarker::decodeData($json);
        $rules = $this->enabledRules($tenantId);
        $hit = AtakMarkerDetection::firstMatch($rules, $data, $armaName);
        if ($hit === null) {
            unset($data['detection'], $data['detection_rule_id'], $data['detection_label'], $data['detection_radius_m'], $data['detection_confirm']);
        } else {
            $data = AtakMarkerDetection::annotate($data, $hit);
        }
        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?: $json;
        } catch (JsonException) {
            return $json;
        }
    }

    /**
     * Enrichit une copie du marqueur pour l’affichage (sans écrire en base).
     *
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    public function decorateRow(array $row, array $rules, string $armaName = ''): array
    {
        $raw = $row['markerData'] ?? $row['marker_data'] ?? null;
        $data = AtakPoMarker::decodeData($raw);
        $hit = AtakMarkerDetection::firstMatch($rules, $data, $armaName);
        if ($hit !== null) {
            $data = AtakMarkerDetection::annotate($data, $hit);
        } elseif (!empty($data['detection'])) {
            unset($data['detection'], $data['detection_rule_id'], $data['detection_label'], $data['detection_radius_m'], $data['detection_confirm']);
        } else {
            return $row;
        }
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && $encoded !== '') {
            $row['markerData'] = $encoded;
        }

        return $row;
    }

    /**
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed> $saved
     */
    public function onMarkerSaved(int $tenantId, int $mapId, ?array $previous, array $saved): void
    {
        $data = AtakPoMarker::decodeData($saved['markerData'] ?? $saved['marker_data'] ?? null);
        if (empty($data['detection'])) {
            return;
        }
        $prev = AtakPoMarker::decodeData($previous['marker_data'] ?? $previous['markerData'] ?? null);
        $label = AtakPoMarker::labelOf($data);
        $ruleLabel = trim((string) ($data['detection_label'] ?? 'Marqueur suivi'));
        $wasDetected = !empty($prev['detection']);
        $rules = $this->enabledRules($tenantId);
        $hit = AtakMarkerDetection::firstMatch($rules, $data, '');
        if ($hit === null) {
            return;
        }
        if (!$wasDetected && !empty($hit['notify_web'])) {
            try {
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_MARKER,
                    'Marqueur suivi — ' . $ruleLabel . ($label !== '' ? ' · ' . $label : ''),
                    null,
                    [
                        'detection' => true,
                        'rule_id' => (int) ($hit['id'] ?? 0),
                        'label' => $label,
                    ]
                );
            } catch (\Throwable) {
            }
        }
        if (!empty($hit['notify_atak']) && empty($data['detection_notified'])) {
            $this->notifyOperators($tenantId, $mapId, $ruleLabel, $label);
            $data['detection_notified'] = true;
            try {
                $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $id = (int) ($saved['id'] ?? 0);
                if ($encoded && $id > 0) {
                    $this->atak->updateMarker($tenantId, $id, $encoded);
                }
            } catch (\Throwable) {
            }
        }
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
        $rules = $this->enabledRules($tenantId);
        if ($rules === []) {
            return [];
        }
        try {
            $rows = $this->atak->getMarkers($tenantId, $mapId);
        } catch (\Throwable) {
            return [];
        }
        $hits = [];
        foreach ($rows as $row) {
            $confirmed = $this->confirmRow($tenantId, $mapId, $row, $rules, $callSign, $posX, $posY);
            if ($confirmed !== null) {
                $hits[] = $confirmed;
            }
        }

        return $hits;
    }

    /**
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
        $callSign = trim($callSign);
        if ($callSign === '' || $tenantId < 1 || $markerId < 1) {
            return null;
        }
        $rules = $this->enabledRules($tenantId);
        if ($rules === []) {
            return null;
        }
        try {
            $row = $this->atak->getMarkerById($tenantId, $markerId);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        $data = AtakPoMarker::decodeData($row['markerData'] ?? $row['marker_data'] ?? null);
        $world = AtakPoMarker::worldPosition($data);
        if ($world !== null && !empty($data['detection']) && !empty($data['reached'])) {
            $distance = AtakPoMarker::distanceM($posX, $posY, $world['x'], $world['y']);
            $label = AtakPoMarker::labelOf($data);
            $display = $label !== '' ? $label : (string) ($data['detection_label'] ?? 'Marqueur suivi');

            return [
                'id' => (int) ($row['id'] ?? $markerId),
                'label' => $display,
                'distance_m' => round($distance, 1),
                'reached_by' => (string) ($data['reached_by'] ?? $callSign),
            ];
        }

        return $this->confirmRow($tenantId, $mapId, $row, $rules, $callSign, $posX, $posY);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $rules
     * @return array{id:int, label:string, distance_m:float, reached_by:string}|null
     */
    private function confirmRow(
        int $tenantId,
        int $mapId,
        array $row,
        array $rules,
        string $callSign,
        float $posX,
        float $posY,
    ): ?array {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $data = AtakPoMarker::decodeData($row['markerData'] ?? $row['marker_data'] ?? null);
        if (!empty($data['reached'])) {
            return null;
        }
        if (AtakPoMarker::isPoMarker($data)) {
            return null;
        }
        $hit = AtakMarkerDetection::firstMatch($rules, $data);
        if ($hit === null || empty($hit['confirm_arrival'])) {
            return null;
        }
        $world = AtakPoMarker::worldPosition($data);
        if ($world === null) {
            return null;
        }
        $radius = AtakMarkerDetection::normalizeRadius((int) ($hit['radius_m'] ?? AtakPoMarker::RADIUS_M));
        $distance = AtakPoMarker::distanceM($posX, $posY, $world['x'], $world['y']);
        if ($distance > $radius) {
            return null;
        }
        $label = AtakPoMarker::labelOf($data);
        $display = $label !== '' ? $label : (string) ($hit['label'] ?? 'Marqueur suivi');
        $updated = AtakMarkerDetection::annotate($data, $hit);
        $updated = AtakPoMarker::withReached($updated, $callSign, $distance);
        $updated['po'] = false;
        try {
            $encoded = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if ($this->atak->updateMarker($tenantId, $id, $encoded) === null) {
            return null;
        }
        try {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_MARKER,
                'Point suivi atteint — ' . $display . ' · ' . $callSign,
                $callSign,
                [
                    'detection' => true,
                    'rule_id' => (int) ($hit['id'] ?? 0),
                    'distance_m' => round($distance, 1),
                ]
            );
        } catch (\Throwable) {
        }

        return [
            'id' => $id,
            'label' => $display,
            'distance_m' => round($distance, 1),
            'reached_by' => $callSign,
        ];
    }

    private function notifyOperators(int $tenantId, int $mapId, string $ruleLabel, string $markerLabel): void
    {
        if ($this->orders === null || !$this->orders->tablesReady()) {
            return;
        }
        $text = 'Nouveau point suivi : ' . $ruleLabel;
        if ($markerLabel !== '' && $markerLabel !== $ruleLabel) {
            $text .= ' — ' . $markerLabel;
        }
        try {
            $this->orders->upsertByExternalId($tenantId, $mapId, [
                'external_id' => 'DET-W-' . bin2hex(random_bytes(5)),
                'order_type' => 'NOTIFY',
                'type_label' => 'Marqueur suivi',
                'target' => '',
                'target_type' => 'all',
                'target_ref' => '',
                'target_label' => 'Tous les opérateurs',
                'payload' => $text,
                'priority' => 'IMPORTANT',
                'issuer' => 'Poste',
                'status' => 'PENDING',
                'source' => 'web',
                'radio_sim' => false,
            ]);
        } catch (\Throwable) {
        }
    }
}
