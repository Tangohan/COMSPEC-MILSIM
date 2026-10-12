<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakMissionKey;
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

    public function insertLog(string $missionId, string $unitId, string $callsign, ?string $unitType, ?string $side, float $posX, float $posY, ?float $posZ, ?float $heading, ?float $speed, ?string $stateJson, string $missionKey = ''): void
    {
        $values = [
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
        ];
        if ($missionKey !== '') {
            try {
                $this->pdo->prepare(
                    'INSERT INTO logs_positions (mission_id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, mission_key, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute(array_merge($values, [$missionKey]));

                return;
            } catch (\PDOException $e) {
                if (!AtakMissionKey::isMissingSchema($e)) {
                    throw $e;
                }
                AtakMissionKey::ensureSchema($this->pdo);
            }
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO logs_positions (mission_id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute($values);
    }

    /**
     * Get timeline of positions for mission between from and to (datetime strings or null for unbounded).
     */
    public function getTimeline(string $missionId, ?string $from, ?string $to, int $limit = 5000, string $missionKey = ''): array
    {
        $sql = 'SELECT id, unit_id, callsign, unit_type, side, pos_x, pos_y, pos_z, heading, speed, state_json, logged_at FROM logs_positions WHERE mission_id = ?';
        $params = [$missionId];
        if ($missionKey !== '') {
            $sql .= ' AND mission_key = ?';
            $params[] = $missionKey;
        }
        if ($from !== null && $from !== '') {
            $sql .= ' AND logged_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND logged_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY logged_at ASC LIMIT ' . (int) $limit;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if ($missionKey === '' || !AtakMissionKey::isMissingSchema($e)) {
                throw $e;
            }
            AtakMissionKey::ensureSchema($this->pdo);

            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sessions jouées (clé de mission de l'ATAK natif) de cette carte, les plus récentes d'abord.
     *
     * @return list<array{key: string, label: string, from: string, to: string, samples: int}>
     */
    public function listSessions(string $missionId, int $limit = 30): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT mission_key, MIN(logged_at) AS first_at, MAX(logged_at) AS last_at, COUNT(*) AS samples
                 FROM logs_positions
                 WHERE mission_id = ? AND mission_key IS NOT NULL AND mission_key <> \'\'
                 GROUP BY mission_key ORDER BY last_at DESC LIMIT ' . max(1, min(100, $limit))
            );
            $stmt->execute([$missionId]);
        } catch (\PDOException $e) {
            if (AtakMissionKey::isMissingSchema($e)) {
                AtakMissionKey::ensureSchema($this->pdo);
            }

            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) $row['mission_key'];
            $out[] = [
                'key' => $key,
                'label' => AtakMissionKey::label($key),
                'from' => (string) $row['first_at'],
                'to' => (string) $row['last_at'],
                'samples' => (int) $row['samples'],
            ];
        }

        return $out;
    }

    /**
     * Bornes d'une session (première et dernière position enregistrées sous cette clé).
     *
     * @return array{0: string, 1: string}|null
     */
    public function sessionWindow(string $missionId, string $missionKey): ?array
    {
        if ($missionKey === '') {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT MIN(logged_at), MAX(logged_at) FROM logs_positions WHERE mission_id = ? AND mission_key = ?');
            $stmt->execute([$missionId, $missionKey]);
            $row = $stmt->fetch(PDO::FETCH_NUM);
        } catch (\PDOException) {
            return null;
        }

        return is_array($row) && $row[0] !== null ? [(string) $row[0], (string) $row[1]] : null;
    }

    /**
     * Pertes amies (atak_casualties) de la carte, filtrées par session quand une clé est donnée.
     *
     * @return list<array<string, mixed>>
     */
    public function getCasualties(string $missionId, ?string $from, ?string $to, string $missionKey = '', int $limit = 400): array
    {
        $scope = $this->parseMissionScope($missionId);
        if ($scope === null) {
            return [];
        }
        $sql = 'SELECT call_sign, kind, pos_x, pos_y, killed_at FROM atak_casualties WHERE tenant_id = ? AND map_id = ?';
        $params = [$scope[0], $scope[1]];
        if ($missionKey !== '') {
            $sql .= ' AND mission_key = ?';
            $params[] = $missionKey;
        }
        if ($from !== null && $from !== '') {
            $sql .= ' AND killed_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND killed_at <= ?';
            $params[] = $to;
        }
        try {
            $stmt = $this->pdo->prepare($sql . ' ORDER BY killed_at ASC LIMIT ' . max(1, min(1000, $limit)));
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (AtakMissionKey::isMissingSchema($e)) {
                AtakMissionKey::ensureSchema($this->pdo);
            }

            return [];
        }

        return array_map(static fn (array $r): array => [
            'callsign' => (string) $r['call_sign'],
            'kind' => (string) $r['kind'],
            'x' => (float) $r['pos_x'],
            'y' => (float) $r['pos_y'],
            'timestamp' => (string) $r['killed_at'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Trajets des véhicules et drones (atak_vehicle_position_history, écrit par UpdateVehicleTracking) sur la fenêtre,
     * un point sur N au-delà de 4000 points, et temps de vol des aéronefs par chef de bord (écarts de moins de 2 min).
     *
     * @return array{tracks: list<array<string, mixed>>, flightTime: list<array{callsign: string, seconds: int, vehicles: int}>}
     */
    public function getVehicleTracks(string $missionId, ?string $from, ?string $to): array
    {
        $empty = ['tracks' => [], 'flightTime' => []];
        $scope = $this->parseMissionScope($missionId);
        if ($scope === null || !$this->tableExists('atak_vehicle_position_history')) {
            return $empty;
        }
        $where = 'v.tenant_id = ? AND v.context_id = ?';
        $params = [$scope[0], $scope[1]];
        if ($from !== null && $from !== '') {
            $where .= ' AND h.recorded_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $where .= ' AND h.recorded_at <= ?';
            $params[] = $to;
        }
        try {
            $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM atak_vehicle_position_history h JOIN atak_vehicle_tracking v ON v.id = h.vehicle_tracking_id WHERE {$where}");
            $cnt->execute($params);
            $step = max(1, (int) ceil(((int) $cnt->fetchColumn()) / 4000));
            $stmt = $this->pdo->prepare(
                "SELECT h.vehicle_tracking_id AS vid, h.pos_x, h.pos_y, h.recorded_at,
                        v.vehicle_callsign, v.vehicle_name, v.vehicle_class, v.crew_commander_callsign
                 FROM atak_vehicle_position_history h JOIN atak_vehicle_tracking v ON v.id = h.vehicle_tracking_id
                 WHERE {$where} AND MOD(h.id, {$step}) = 0
                 ORDER BY h.vehicle_tracking_id, h.recorded_at LIMIT 6000"
            );
            $stmt->execute($params);
        } catch (\Throwable) {
            return $empty;
        }
        $tracks = [];
        $flight = [];
        $air = ['HELICOPTER', 'FIXED_WING', 'UAV'];
        $lastTs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $vid = (int) $r['vid'];
            $class = (string) $r['vehicle_class'];
            if (!isset($tracks[$vid])) {
                $tracks[$vid] = [
                    'callsign' => (string) $r['vehicle_callsign'],
                    'name' => (string) ($r['vehicle_name'] ?? ''),
                    'class' => $class,
                    'air' => in_array($class, $air, true),
                    'pilot' => (string) ($r['crew_commander_callsign'] ?? ''),
                    'points' => [],
                ];
            }
            $ts = strtotime((string) $r['recorded_at']) ?: 0;
            $tracks[$vid]['points'][] = [round((float) $r['pos_x']), round((float) $r['pos_y']), (string) $r['recorded_at']];
            if ($tracks[$vid]['air'] && isset($lastTs[$vid]) && ($ts - $lastTs[$vid]) > 0 && ($ts - $lastTs[$vid]) <= 120 * $step) {
                $pilot = $tracks[$vid]['pilot'] !== '' ? $tracks[$vid]['pilot'] : $tracks[$vid]['callsign'];
                $flight[$pilot] ??= ['callsign' => $pilot, 'seconds' => 0, 'vehicles' => []];
                $flight[$pilot]['seconds'] += $ts - $lastTs[$vid];
                $flight[$pilot]['vehicles'][$vid] = true;
            }
            $lastTs[$vid] = $ts;
        }
        $flightTime = array_map(static fn (array $f): array => [
            'callsign' => $f['callsign'], 'seconds' => (int) $f['seconds'], 'vehicles' => count($f['vehicles']),
        ], array_values($flight));
        usort($flightTime, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        return ['tracks' => array_values(array_filter($tracks, static fn (array $t): bool => count($t['points']) >= 2)), 'flightTime' => $flightTime];
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

        // Phase D — observations / pistes (SALUTE, reco, BDA, SIGINT)
        if ($this->tableExists('tactical_observations')) {
            try {
                $sql = 'SELECT id, obs_uid, kind, layer, actor, pos_x, pos_y, confidence, payload_json, created_at
                        FROM tactical_observations
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
                $sql .= ' ORDER BY created_at ASC LIMIT 500';
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $kind = strtolower(trim((string) ($row['kind'] ?? 'obs')));
                    $actor = trim((string) ($row['actor'] ?? ''));
                    $label = match ($kind) {
                        'salute' => 'Observation SALUTE',
                        'bda', 'bda_confirm' => 'Bilan des dégâts',
                        'sigint' => 'Signalement radio',
                        'recon' => 'Note de reconnaissance',
                        default => 'Observation terrain',
                    };
                    $payload = [];
                    $rawPayload = $row['payload_json'] ?? '';
                    if (is_string($rawPayload) && $rawPayload !== '') {
                        $decoded = json_decode($rawPayload, true);
                        if (is_array($decoded)) {
                            $payload = $decoded;
                        }
                    }
                    $detail = trim((string) ($payload['label'] ?? $payload['text'] ?? $payload['tag'] ?? ''));
                    if ($detail !== '') {
                        $label .= ' — ' . mb_substr($detail, 0, 48);
                    } elseif ($actor !== '') {
                        $label .= ' — ' . $actor;
                    }
                    $events[] = [
                        'type' => 'observation',
                        'kind' => $kind,
                        'id' => (int) $row['id'],
                        'timestamp' => (string) ($row['created_at'] ?? ''),
                        'label' => $label,
                        'source' => $actor,
                        'layer' => $row['layer'] ?? 'observation',
                        'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : null,
                        'x' => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
                        'y' => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
                        'obsUid' => $row['obs_uid'] ?? null,
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
