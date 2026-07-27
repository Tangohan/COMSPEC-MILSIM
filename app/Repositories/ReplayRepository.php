<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\C2PillarsSchema;
use PDO;

class ReplayRepository
{
    private PDO $pdo;

    public function __construct()
    {
        C2PillarsSchema::ensure();
        $this->pdo = Database::getPdo();
    }

    public function insertLog(string $missionId, string $unitId, string $callsign, ?string $unitType, ?string $side, float $posX, float $posY, ?float $posZ, ?float $heading, ?float $speed, ?string $stateJson): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO logs_positions (mission_id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $missionId,
            $unitId,
            $callsign,
            $unitType,
            $side,
            $posX,
            $posY,
            $posZ ?? 0,
            $heading,
            $speed,
            $stateJson,
        ]);
    }

    /**
     * Get timeline of positions for mission between from and to (datetime strings or null for unbounded).
     */
    public function getTimeline(string $missionId, ?string $from, ?string $to, int $limit = 5000): array
    {
        $sql = 'SELECT id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, logged_at FROM logs_positions WHERE mission_id = ?';
        $params = [$missionId];
        if ($from !== null && $from !== '') {
            $sql .= ' AND logged_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND logged_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY logged_at ASC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIntelEvents(string $missionId, ?string $from, ?string $to, int $limit = 1000): array
    {
        $sql = 'SELECT id, source_callsign, report_type, target_type, pos_x, pos_y, pos_z, confidence_score, status, first_seen_at, last_seen_at, merged_count
                FROM intel_reports
                WHERE mission_id = ?';
        $params = [$missionId];
        if ($from !== null && $from !== '') {
            $sql .= ' AND last_seen_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND first_seen_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY last_seen_at ASC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Parse mission_id "mission_{tenant}_map_{map}" → [tenantId, mapId].
     *
     * @return array{0: int, 1: int}|null
     */
    public function parseMissionScope(string $missionId): ?array
    {
        if (!preg_match('/^mission_(\d+)_map_(\d+)$/', trim($missionId), $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            $cache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    /**
     * Événements opérationnels (contacts intel + MEDEVAC + ordres + marqueurs) pour la timeline AAR.
     *
     * @return list<array<string, mixed>>
     */
    public function getOperationalEvents(string $missionId, ?string $from, ?string $to, int $limit = 1500): array
    {
        $events = [];
        foreach ($this->getIntelEvents($missionId, $from, $to, min(800, $limit)) as $row) {
            $events[] = [
                'type' => 'contact',
                'kind' => 'intel',
                'id' => (int) $row['id'],
                'timestamp' => (string) ($row['last_seen_at'] ?? $row['first_seen_at'] ?? ''),
                'label' => $this->intelEventLabel($row),
                'source' => (string) ($row['source_callsign'] ?? ''),
                'reportType' => $row['report_type'] ?? null,
                'targetType' => $row['target_type'] ?? null,
                'x' => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
                'y' => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
                'confidence' => (int) ($row['confidence_score'] ?? 0),
                'status' => $row['status'] ?? null,
            ];
        }

        $scope = $this->parseMissionScope($missionId);
        if ($scope === null) {
            return $this->sortTrimEvents($events, $limit);
        }
        [$tenantId, $mapId] = $scope;

        if ($this->tableExists('atak_medevac_requests')) {
            try {
                $sql = 'SELECT id, requested_by_callsign, radio_callsign, status, pickup_pos_x, pickup_pos_y, created_at, requested_at
                        FROM atak_medevac_requests
                        WHERE tenant_id = ? AND map_id = ?';
                $params = [$tenantId, $mapId];
                if ($from !== null && $from !== '') {
                    $sql .= ' AND COALESCE(requested_at, created_at) >= ?';
                    $params[] = $from;
                }
                if ($to !== null && $to !== '') {
                    $sql .= ' AND COALESCE(requested_at, created_at) <= ?';
                    $params[] = $to;
                }
                $sql .= ' ORDER BY COALESCE(requested_at, created_at) ASC LIMIT 400';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cs = trim((string) ($row['requested_by_callsign'] ?? $row['radio_callsign'] ?? ''));
                    $events[] = [
                        'type' => 'medevac',
                        'kind' => 'medevac',
                        'id' => (int) $row['id'],
                        'timestamp' => (string) ($row['requested_at'] ?? $row['created_at'] ?? ''),
                        'label' => 'MEDEVAC' . ($cs !== '' ? ' — ' . $cs : ''),
                        'source' => $cs,
                        'status' => $row['status'] ?? null,
                        'x' => isset($row['pickup_pos_x']) ? (float) $row['pickup_pos_x'] : null,
                        'y' => isset($row['pickup_pos_y']) ? (float) $row['pickup_pos_y'] : null,
                    ];
                }
            } catch (\Throwable) {
                // Table partielle / schéma divergent — ignorer.
            }
        }

        if ($this->tableExists('atak_orders')) {
            try {
                $sql = 'SELECT id, order_type, target, issuer, status, priority, created_at
                        FROM atak_orders
                        WHERE tenant_id = ? AND map_id = ?';
                $params = [$tenantId, $mapId];
                if ($from !== null && $from !== '') {
                    $sql .= ' AND created_at >= ?';
                    $params[] = $from;
                }
                if ($to !== null && $to !== '') {
                    $sql .= ' AND created_at <= ?';
                    $params[] = $to;
                }
                $sql .= ' ORDER BY created_at ASC LIMIT 400';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $otype = strtoupper(trim((string) ($row['order_type'] ?? 'ORDRE')));
                    $target = trim((string) ($row['target'] ?? ''));
                    $issuer = trim((string) ($row['issuer'] ?? ''));
                    $label = 'Ordre ' . $otype;
                    if ($target !== '') {
                        $label .= ' → ' . $target;
                    }
                    $events[] = [
                        'type' => 'order',
                        'kind' => 'order',
                        'id' => (int) $row['id'],
                        'timestamp' => (string) ($row['created_at'] ?? ''),
                        'label' => $label,
                        'source' => $issuer,
                        'status' => $row['status'] ?? null,
                        'orderType' => $otype,
                        'x' => null,
                        'y' => null,
                    ];
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($this->tableExists('atak_markers')) {
            try {
                $sql = 'SELECT id, marker_data, arma_name, created_at, updated_at
                        FROM atak_markers
                        WHERE tenant_id = ? AND map_id = ?';
                $params = [$tenantId, $mapId];
                if ($from !== null && $from !== '') {
                    $sql .= ' AND COALESCE(updated_at, created_at) >= ?';
                    $params[] = $from;
                }
                if ($to !== null && $to !== '') {
                    $sql .= ' AND created_at <= ?';
                    $params[] = $to;
                }
                $sql .= ' ORDER BY created_at ASC LIMIT 400';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $md = [];
                    $raw = $row['marker_data'] ?? '';
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $md = $decoded;
                        }
                    }
                    $text = trim((string) ($md['text'] ?? $md['label'] ?? $row['arma_name'] ?? 'Repère'));
                    if (str_starts_with($text, 'comspec_')) {
                        $text = 'Repère';
                    }
                    $x = null;
                    $y = null;
                    if (isset($md['pos']) && is_array($md['pos'])) {
                        $x = isset($md['pos'][0]) ? (float) $md['pos'][0] : null;
                        $y = isset($md['pos'][1]) ? (float) $md['pos'][1] : null;
                    }
                    $events[] = [
                        'type' => 'marker',
                        'kind' => 'marker',
                        'id' => (int) $row['id'],
                        'timestamp' => (string) ($row['created_at'] ?? ''),
                        'label' => 'Repère — ' . mb_substr($text, 0, 40),
                        'source' => (string) ($md['source'] ?? 'arma'),
                        'x' => $x,
                        'y' => $y,
                    ];
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return $this->sortTrimEvents($events, $limit);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function intelEventLabel(array $row): string
    {
        $target = strtoupper((string) ($row['target_type'] ?? 'UNKNOWN'));
        $labels = [
            'INFANTRY' => 'Contact — Infanterie',
            'VEHICLE' => 'Contact — Véhicule',
            'ARMOR' => 'Contact — Blindé',
            'AIR_DEFENSE' => 'Contact — Défense antiaérienne',
            'UNKNOWN' => 'Contact — Non identifié',
        ];
        $base = $labels[$target] ?? ('Contact — ' . $target);
        $src = trim((string) ($row['source_callsign'] ?? ''));
        if ($src !== '') {
            $base .= ' (' . $src . ')';
        }

        return $base;
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    private function sortTrimEvents(array $events, int $limit): array
    {
        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($a['timestamp'] ?? ''), (string) ($b['timestamp'] ?? ''));
        });
        if (count($events) > $limit) {
            $events = array_slice($events, 0, $limit);
        }

        return array_values($events);
    }
}
