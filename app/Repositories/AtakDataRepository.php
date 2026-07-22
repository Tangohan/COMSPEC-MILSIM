<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AtakDataRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getMarkers(int $tenantId, int $mapId, ?string $since = null): array
    {
        $sql = 'SELECT id, layer_id, marker_data, updated_at FROM atak_markers WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if ($since !== null && $since !== '') {
            $sql .= ' AND (updated_at >= ? OR created_at >= ?)';
            $params[] = $since;
            $params[] = $since;
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'layerId' => (int) $r['layer_id'],
                'markerData' => $r['marker_data'],
                'updated_at' => $r['updated_at'],
            ];
        }
        return $out;
    }

    public function addMarker(int $tenantId, int $mapId, int $layerId, string $markerData, ?string $armaName = null): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO atak_markers (tenant_id, map_id, layer_id, marker_data, arma_name) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $mapId, $layerId, $markerData, $armaName]);
        $id = (int) $this->pdo->lastInsertId();
        return $this->getMarkerById($tenantId, $id);
    }

    public function getMarkerById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, layer_id, marker_data, updated_at FROM atak_markers WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'layerId' => (int) $r['layer_id'],
            'markerData' => $r['marker_data'],
            'updated_at' => $r['updated_at'],
        ];
    }

    public function upsertMarkerByArmaName(int $tenantId, int $mapId, int $layerId, string $armaName, string $markerData): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name = ?');
        $stmt->execute([$tenantId, $mapId, $armaName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->pdo->prepare('UPDATE atak_markers SET layer_id = ?, marker_data = ? WHERE id = ?')->execute([$layerId, $markerData, $existing['id']]);
            return $this->getMarkerById($tenantId, (int) $existing['id']);
        }
        return $this->addMarker($tenantId, $mapId, $layerId, $markerData, $armaName);
    }

    public function updateMarker(int $tenantId, int $id, string $markerData, ?int $layerId = null): ?array
    {
        $existing = $this->getMarkerById($tenantId, $id);
        if ($existing === null) {
            return null;
        }
        if ($layerId !== null) {
            $stmt = $this->pdo->prepare('UPDATE atak_markers SET marker_data = ?, layer_id = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$markerData, $layerId, $tenantId, $id]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE atak_markers SET marker_data = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$markerData, $tenantId, $id]);
        }
        return $this->getMarkerById($tenantId, $id);
    }

    public function deleteMarkerByArmaName(int $tenantId, int $mapId, string $armaName): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name = ?');
        $stmt->execute([$tenantId, $mapId, $armaName]);

        return $stmt->rowCount() > 0;
    }

    public function deleteMarker(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM atak_markers WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        return $stmt->rowCount() > 0;
    }

    public function getUnits(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_units WHERE tenant_id = ? AND map_id = ? ORDER BY call_sign');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{created: bool}
     */
    public function upsertUnitPosition(int $tenantId, int $mapId, string $callSign, float $posX, float $posY, ?float $heading, string $role, string $extraJson): array
    {
        $gridRef = (string) round($posX) . ' ' . round($posY);
        $stmt = $this->pdo->prepare('SELECT id FROM atak_units WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $stmt->execute([$tenantId, $mapId, $callSign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $created = false;
        if ($existing) {
            $this->pdo->prepare('UPDATE atak_units SET grid_ref = ?, heading = ?, role = ?, extra = ? WHERE id = ?')->execute([$gridRef, $heading, $role, $extraJson, $existing['id']]);
        } else {
            $this->pdo->prepare('INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, heading, extra) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $callSign, $role, 'linked', $gridRef, $heading, $extraJson]);
            $created = true;
        }
        $this->setLastActivity($tenantId, $mapId);

        return ['created' => $created];
    }

    public function addUnit(int $tenantId, int $mapId, array $data): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, heading, extra) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $tenantId,
            $mapId,
            $data['call_sign'] ?? 'Unknown',
            $data['role'] ?? '',
            $data['status'] ?? 'linked',
            $data['grid_ref'] ?? '',
            $data['heading'] ?? null,
            isset($data['extra']) ? (is_string($data['extra']) ? $data['extra'] : json_encode($data['extra'])) : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->pdo->prepare('SELECT * FROM atak_units WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUnit(int $tenantId, int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_units WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $fields = ['call_sign', 'role', 'status', 'grid_ref', 'heading', 'extra'];
        $updates = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $updates[] = "{$f} = ?";
                $params[] = is_array($data[$f] ?? null) ? json_encode($data[$f]) : ($data[$f] ?? $row[$f]);
            }
        }
        if ($updates === []) {
            return $row;
        }
        $params[] = $id;
        $this->pdo->prepare('UPDATE atak_units SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
        $stmt = $this->pdo->prepare('SELECT * FROM atak_units WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getChatMessages(int $tenantId, int $mapId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_chat_messages WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$tenantId, $mapId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addChatMessage(int $tenantId, int $mapId, string $author, string $body): array
    {
        $this->pdo->prepare('INSERT INTO atak_chat_messages (tenant_id, map_id, author, body) VALUES (?, ?, ?, ?)')->execute([$tenantId, $mapId, $author, $body]);
        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM atak_chat_messages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Alertes / bilans médicaux dérivés du tchat ATAK (préfixe ALERTE MÉDICALE ou WIA).
     *
     * @return list<array<string, mixed>>
     */
    public function getMedicalAlertsFromChat(int $tenantId, int $mapId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        // On lit un volume plus large puis on filtre côté PHP (préfixe accentué / WIA).
        $scan = min(500, max(100, $limit * 5));
        $rows = $this->getChatMessages($tenantId, $mapId, $scan);
        $out = [];
        foreach ($rows as $row) {
            $enriched = \App\Support\MedicalAlertParser::enrichChatRow(is_array($row) ? $row : []);
            if ($enriched === null) {
                continue;
            }
            $out[] = $enriched;
        }
        if (count($out) > $limit) {
            $out = array_slice($out, -$limit);
        }
        return $out;
    }

    /**
     * Unités dont l’état santé (extra.health) est critique / blessé.
     *
     * @return list<array<string, mixed>>
     */
    public function getUnitsWithCriticalHealth(int $tenantId, int $mapId): array
    {
        $units = $this->getUnits($tenantId, $mapId);
        $out = [];
        foreach ($units as $unit) {
            $extra = [];
            $raw = $unit['extra'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            } elseif (is_array($raw)) {
                $extra = $raw;
            }
            $health = (string) ($extra['health'] ?? $unit['health'] ?? '');
            if (!\App\Support\MedicalAlertParser::isCriticalHealth($health)) {
                continue;
            }
            $out[] = [
                'id' => $unit['id'] ?? null,
                'call_sign' => (string) ($unit['call_sign'] ?? ''),
                'role' => (string) ($unit['role'] ?? ''),
                'grid_ref' => (string) ($unit['grid_ref'] ?? ''),
                'pos_x' => $unit['pos_x'] ?? null,
                'pos_y' => $unit['pos_y'] ?? null,
                'health' => $health,
                'health_label' => \App\Support\MedicalAlertParser::healthLabelFr($health),
                'severity' => \App\Support\MedicalAlertParser::isEmergencyHealth($health) ? 'critical' : 'attention',
                'status' => (string) ($unit['status'] ?? ''),
                'updated_at' => (string) ($unit['updated_at'] ?? ''),
            ];
        }
        return $out;
    }

    public function getPings(int $tenantId, int $mapId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_pings WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$tenantId, $mapId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addPing(int $tenantId, int $mapId, string $author, float $posX, float $posY, string $message): array
    {
        $this->pdo->prepare('INSERT INTO atak_pings (tenant_id, map_id, author, pos_x, pos_y, message) VALUES (?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $author, $posX, $posY, $message]);
        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM atak_pings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNineLines(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND map_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addNineLine(int $tenantId, int $mapId, string $author, array $lines): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO atak_nine_line (tenant_id, map_id, author, line1, line2, line3, line4, line5, line6, line7, line8, line9, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $tenantId,
            $mapId,
            $author,
            $lines['line1'] ?? '',
            $lines['line2'] ?? '',
            $lines['line3'] ?? '',
            $lines['line4'] ?? '',
            $lines['line5'] ?? '',
            $lines['line6'] ?? '',
            $lines['line7'] ?? '',
            $lines['line8'] ?? '',
            $lines['line9'] ?? '',
            'active',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->pdo->prepare('SELECT * FROM atak_nine_line WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function updateNineLineStatus(int $tenantId, int $id, string $status): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        if (!$stmt->fetch()) {
            return null;
        }
        $this->pdo->prepare('UPDATE atak_nine_line SET status = ? WHERE id = ?')->execute([$status, $id]);
        $row = $this->pdo->prepare('SELECT * FROM atak_nine_line WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function getDesignators(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_designator_targets WHERE tenant_id = ? AND map_id = ?');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertDesignator(int $tenantId, int $mapId, string $callSign, float $posX, float $posY): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM atak_designator_targets WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $stmt->execute([$tenantId, $mapId, $callSign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->pdo->prepare('UPDATE atak_designator_targets SET pos_x = ?, pos_y = ? WHERE id = ?')->execute([$posX, $posY, $existing['id']]);
        } else {
            $this->pdo->prepare('INSERT INTO atak_designator_targets (tenant_id, map_id, call_sign, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $callSign, $posX, $posY]);
        }
        $row = $this->pdo->prepare('SELECT * FROM atak_designator_targets WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $row->execute([$tenantId, $mapId, $callSign]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function addSigint(int $tenantId, int $mapId, string $callSign, float $posX, float $posY, ?float $bearing = null): array
    {
        $this->pdo->prepare('INSERT INTO atak_sigint_reports (tenant_id, map_id, call_sign, pos_x, pos_y, bearing) VALUES (?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $callSign, $posX, $posY, $bearing]);
        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM atak_sigint_reports WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSigintZones(int $tenantId, int $mapId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_sigint_reports WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$tenantId, $mapId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $zones = [];
        if (count($rows) >= 2) {
            $cx = array_sum(array_column($rows, 'pos_x')) / count($rows);
            $cy = array_sum(array_column($rows, 'pos_y')) / count($rows);
            $radius = 100;
            foreach ($rows as $r) {
                $radius = max($radius, hypot((float) $r['pos_x'] - $cx, (float) $r['pos_y'] - $cy));
            }
            $radius = max(100, $radius * 1.5);
            $zones[] = ['pos_x' => $cx, 'pos_y' => $cy, 'radius' => $radius, 'reports' => count($rows)];
        }
        return $zones;
    }

    public function getIntelPhotos(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_intel_photos WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addIntelPhoto(int $tenantId, int $mapId, string $filename, string $path, string $author, ?float $posX = null, ?float $posY = null): array
    {
        $this->pdo->prepare('INSERT INTO atak_intel_photos (tenant_id, map_id, filename, path, author, pos_x, pos_y) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $filename, $path, $author, $posX, $posY]);
        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM atak_intel_photos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function setLastActivity(int $tenantId, int $mapId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO atak_last_activity (tenant_id, map_id, last_activity_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_activity_at = NOW()');
        $stmt->execute([$tenantId, $mapId]);
    }

    public function getLastActivity(int $tenantId, int $mapId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT last_activity_at FROM atak_last_activity WHERE tenant_id = ? AND map_id = ?');
        $stmt->execute([$tenantId, $mapId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['last_activity_at'] : null;
    }

    /** @return list<array{call_sign: string, updated_at: string}> */
    public function getActiveUnitsSummary(int $tenantId, int $mapId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT call_sign, updated_at FROM atak_units WHERE tenant_id = ? AND map_id = ? ORDER BY updated_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$tenantId, $mapId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn ($r) => ['call_sign' => $r['call_sign'] ?? '', 'updated_at' => $r['updated_at'] ?? ''], $rows);
    }

    private const AIR_ASSET_TTL_SECONDS = 30;

    public function upsertAirAsset(
        int $tenantId,
        int $mapId,
        string $callsign,
        array $data
    ): array {
        $stmt = $this->pdo->prepare('SELECT id FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND callsign = ?');
        $stmt->execute([$tenantId, $mapId, $callsign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');
        $pos = $data['pos'] ?? null;
        $fields = [
            'mission_id' => $data['mission_id'] ?? $data['missionId'] ?? null,
            'model' => $data['model'] ?? null,
            'aircraft_type' => $data['aircraft_type'] ?? $data['aircraftType'] ?? null,
            'freq' => $data['freq'] ?? $data['radioMain'] ?? $data['radio_main'] ?? null,
            'radio_main' => $data['radio_main'] ?? $data['radioMain'] ?? null,
            'radio_aux' => $data['radio_aux'] ?? $data['radioAux'] ?? null,
            'laser' => $data['laser'] ?? '1688',
            'auth' => $data['auth'] ?? null,
            'auth_code' => $data['auth_code'] ?? $data['authCode'] ?? null,
            'pilot' => $data['pilot'] ?? null,
            'crew' => isset($data['crew']) ? (is_string($data['crew']) ? $data['crew'] : json_encode($data['crew'])) : null,
            'fuel_pct' => isset($data['fuelPct']) ? (int) $data['fuelPct'] : (isset($data['fuel_pct']) ? (int) $data['fuel_pct'] : null),
            'ordnance' => isset($data['ordnance']) ? (is_string($data['ordnance']) ? $data['ordnance'] : json_encode($data['ordnance'])) : null,
            'station' => $data['station'] ?? null,
            'eta_minutes' => isset($data['etaMinutes']) ? (int) $data['etaMinutes'] : (isset($data['eta_minutes']) ? (int) $data['eta_minutes'] : null),
            'bingo_fuel' => $data['bingoFuel'] ?? $data['bingo_fuel'] ?? null,
            'checklist' => isset($data['checklist']) ? (is_string($data['checklist']) ? $data['checklist'] : json_encode($data['checklist'])) : null,
            'pos_x' => $data['pos_x'] ?? (is_array($pos) && isset($pos[0]) ? (float) $pos[0] : null),
            'pos_y' => $data['pos_y'] ?? (is_array($pos) && isset($pos[1]) ? (float) $pos[1] : null),
            'pos_z' => $data['pos_z'] ?? (is_array($pos) && isset($pos[2]) ? (float) $pos[2] : null),
            'alt' => $data['alt'] ?? $data['altitude'] ?? null,
            'heading' => $data['heading'] ?? null,
            'side' => $data['side'] ?? 'WEST',
            'status' => $data['status'] ?? 'AVAILABLE',
            'aircraft_count' => (int) ($data['aircraft_count'] ?? $data['count'] ?? 1),
            'last_update' => isset($data['lastUpdate']) ? (int) $data['lastUpdate'] : time(),
            'updated_at' => $now,
        ];
        if ($existing) {
            $set = implode(', ', array_map(fn ($k) => "`$k` = ?", array_keys($fields)));
            $params = array_values($fields);
            $params[] = $existing['id'];
            $this->pdo->prepare("UPDATE atak_air_assets SET $set WHERE id = ?")->execute($params);
            $id = (int) $existing['id'];
        } else {
            $cols = array_keys($fields);
            $placeholders = implode(', ', array_merge(['?', '?', '?'], array_fill(0, count($cols), '?')));
            $this->pdo->prepare(
                'INSERT INTO atak_air_assets (tenant_id, map_id, callsign, ' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')'
            )->execute(array_merge([$tenantId, $mapId, $callsign], array_values($fields)));
            $id = (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare('SELECT * FROM atak_air_assets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getActiveAirAssets(int $tenantId, int $mapId): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::AIR_ASSET_TTL_SECONDS);
        $stmt = $this->pdo->prepare('SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND updated_at >= ? ORDER BY callsign');
        $stmt->execute([$tenantId, $mapId, $cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['status'] = $r['status'] ?? 'IN-FLIGHT';
        }
        return $rows;
    }

    public function updateAirAssetPilotStatus(int $tenantId, int $mapId, string $callsign, string $pilotStatus): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE atak_air_assets SET pilot_status = ?, updated_at = NOW() WHERE tenant_id = ? AND map_id = ? AND callsign = ?');
        $stmt->execute([$pilotStatus, $tenantId, $mapId, $callsign]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND callsign = ?');
        $stmt->execute([$tenantId, $mapId, $callsign]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLayers(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_layers WHERE tenant_id = ? AND map_id = ? ORDER BY `order`');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
