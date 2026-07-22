<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AtakDataRepository
{
    /** Délai sans position au-delà duquel une unité « linked » est considérée hors liaison. */
    public const UNIT_LIVE_TTL_SECONDS = 180;

    /** Origine (0,0) = position non reçue / parse raté — jamais une vraie case jouable. */
    private const POS_ORIGIN_EPS = 0.5;

    private PDO $pdo;

    private ?bool $hasPosColumns = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasPosColumns(): bool
    {
        if ($this->hasPosColumns !== null) {
            return $this->hasPosColumns;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_units' AND COLUMN_NAME = 'pos_x' LIMIT 1"
            );
            $this->hasPosColumns = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $this->hasPosColumns = false;
        }

        return $this->hasPosColumns;
    }

    /** @return array{0: ?float, 1: ?float} */
    public static function parseGridRef(?string $gridRef): array
    {
        $parts = preg_split('/\s+/', trim((string) $gridRef), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return [null, null];
        }
        $x = is_numeric($parts[0]) ? (float) $parts[0] : null;
        $y = is_numeric($parts[1]) ? (float) $parts[1] : null;

        return [$x, $y];
    }

    public static function isValidMapPosition(?float $x, ?float $y): bool
    {
        if ($x === null || $y === null || !is_finite($x) || !is_finite($y)) {
            return false;
        }
        if (abs($x) < self::POS_ORIGIN_EPS && abs($y) < self::POS_ORIGIN_EPS) {
            return false;
        }

        return true;
    }

    /**
     * Statut métier : linked uniquement si pas offline et last update récente.
     */
    public static function resolveLiveStatus(?string $dbStatus, ?string $updatedAt, ?int $now = null): string
    {
        $status = strtolower(trim((string) $dbStatus));
        if ($status === 'offline') {
            return 'offline';
        }
        $now ??= time();
        $ts = $updatedAt !== null && $updatedAt !== '' ? strtotime($updatedAt) : false;
        if ($ts === false) {
            return 'offline';
        }
        $age = $now - $ts;
        if ($age > self::UNIT_LIVE_TTL_SECONDS) {
            return 'offline';
        }
        if ($age > (int) (self::UNIT_LIVE_TTL_SECONDS * 0.6)) {
            return 'delayed';
        }
        if ($status === 'delayed') {
            return 'delayed';
        }

        return 'linked';
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
        $this->markStaleUnitsOffline($tenantId, $mapId);
        $stmt = $this->pdo->prepare('SELECT * FROM atak_units WHERE tenant_id = ? AND map_id = ? ORDER BY call_sign');
        $stmt->execute([$tenantId, $mapId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $now = time();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->normalizeUnitRow($row, $now);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeUnitRow(array $row, ?int $now = null): array
    {
        $now ??= time();
        $dbStatus = isset($row['status']) ? (string) $row['status'] : '';
        $posX = isset($row['pos_x']) && $row['pos_x'] !== null && $row['pos_x'] !== ''
            ? (float) $row['pos_x']
            : null;
        $posY = isset($row['pos_y']) && $row['pos_y'] !== null && $row['pos_y'] !== ''
            ? (float) $row['pos_y']
            : null;
        if (!self::isValidMapPosition($posX, $posY)) {
            [$gx, $gy] = self::parseGridRef(isset($row['grid_ref']) ? (string) $row['grid_ref'] : null);
            if (self::isValidMapPosition($gx, $gy)) {
                $posX = $gx;
                $posY = $gy;
            } else {
                $posX = null;
                $posY = null;
            }
        }
        $live = self::resolveLiveStatus(
            $dbStatus,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            $now
        );
        // Pas de position carte valide → ne pas afficher « En liaison ».
        if (($live === 'linked' || $live === 'delayed') && !self::isValidMapPosition($posX, $posY)) {
            $live = 'offline';
        }
        $row['pos_x'] = $posX;
        $row['pos_y'] = $posY;
        $row['db_status'] = $dbStatus;
        $row['status'] = $live;
        if (!self::isValidMapPosition($posX, $posY)) {
            $row['grid_ref'] = '';
        } elseif (trim((string) ($row['grid_ref'] ?? '')) === '' || trim((string) $row['grid_ref']) === '0 0') {
            $row['grid_ref'] = (string) round($posX) . ' ' . round($posY);
        }

        return $row;
    }

    /**
     * Passe en offline les unités encore « linked » sans mise à jour récente.
     */
    public function markStaleUnitsOffline(int $tenantId, int $mapId): int
    {
        $ttl = self::UNIT_LIVE_TTL_SECONDS;
        $stmt = $this->pdo->prepare(
            'UPDATE atak_units SET status = ?
             WHERE tenant_id = ? AND map_id = ?
               AND status = ?
               AND (updated_at IS NULL OR updated_at < (NOW() - INTERVAL ' . (int) $ttl . ' SECOND))'
        );
        $stmt->execute(['offline', $tenantId, $mapId, 'linked']);

        return $stmt->rowCount();
    }

    /**
     * @return array{created: bool}
     */
    public function upsertUnitPosition(int $tenantId, int $mapId, string $callSign, float $posX, float $posY, ?float $heading, string $role, string $extraJson): array
    {
        $validPos = self::isValidMapPosition($posX, $posY);
        $gridRef = $validPos ? ((string) round($posX) . ' ' . round($posY)) : '';
        $stmt = $this->pdo->prepare('SELECT id, grid_ref, pos_x, pos_y FROM atak_units WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $stmt->execute([$tenantId, $mapId, $callSign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $created = false;
        $unitId = 0;
        $hasPos = $this->hasPosColumns();

        if ($existing) {
            $unitId = (int) $existing['id'];
            // Conserver la dernière position valide si le payload est 0,0 / invalide.
            $storeX = $posX;
            $storeY = $posY;
            $storeGrid = $gridRef;
            if (!$validPos) {
                $prevX = isset($existing['pos_x']) && $existing['pos_x'] !== null ? (float) $existing['pos_x'] : null;
                $prevY = isset($existing['pos_y']) && $existing['pos_y'] !== null ? (float) $existing['pos_y'] : null;
                if (!self::isValidMapPosition($prevX, $prevY)) {
                    [$prevX, $prevY] = self::parseGridRef(isset($existing['grid_ref']) ? (string) $existing['grid_ref'] : null);
                }
                if (self::isValidMapPosition($prevX, $prevY)) {
                    $storeX = $prevX;
                    $storeY = $prevY;
                    $storeGrid = (string) round($prevX) . ' ' . round($prevY);
                } else {
                    $storeX = null;
                    $storeY = null;
                    $storeGrid = '';
                }
            }
            if ($hasPos) {
                $this->pdo->prepare(
                    'UPDATE atak_units SET grid_ref = ?, heading = ?, role = ?, extra = ?, status = ?, pos_x = ?, pos_y = ?, updated_at = NOW() WHERE id = ?'
                )->execute([$storeGrid, $heading, $role, $extraJson, 'linked', $storeX, $storeY, $unitId]);
            } else {
                $this->pdo->prepare(
                    'UPDATE atak_units SET grid_ref = ?, heading = ?, role = ?, extra = ?, status = ?, updated_at = NOW() WHERE id = ?'
                )->execute([$storeGrid, $heading, $role, $extraJson, 'linked', $unitId]);
            }
        } else {
            if ($hasPos) {
                $this->pdo->prepare(
                    'INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, heading, pos_x, pos_y, extra, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $tenantId,
                    $mapId,
                    $callSign,
                    $role,
                    'linked',
                    $validPos ? $gridRef : '',
                    $heading,
                    $validPos ? $posX : null,
                    $validPos ? $posY : null,
                    $extraJson,
                ]);
            } else {
                $this->pdo->prepare(
                    'INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, heading, extra, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([$tenantId, $mapId, $callSign, $role, 'linked', $validPos ? $gridRef : '', $heading, $extraJson]);
            }
            $created = true;
            $unitId = (int) $this->pdo->lastInsertId();
        }
        $this->setLastActivity($tenantId, $mapId);

        // ID militaire stable (best-effort, migration v2)
        if ($unitId > 0) {
            try {
                $opIds = new AtakOperatorIdRepository();
                if ($opIds->tablesReady() && $opIds->unitsMilitaryIdColumnReady()) {
                    $opIds->syncUnitMilitaryId($tenantId, $unitId, $callSign, null);
                }
            } catch (\Throwable) {
            }
        }

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

    public function getUnitById(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM atak_units WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateUnit(int $tenantId, int $id, array $data): ?array
    {
        $row = $this->getUnitById($tenantId, $id);
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

        return $this->getUnitById($tenantId, $id);
    }

    public function deleteUnit(int $tenantId, int $id): bool
    {
        if ($tenantId < 1 || $id < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM atak_units WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    public function getChatMessageById(int $tenantId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM atak_chat_messages WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
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
     * Filtre la fenêtre active (30 min depuis created_at, horloge MySQL) sauf si $includeExpired.
     *
     * @return list<array<string, mixed>>
     */
    public function getMedicalAlertsFromChat(int $tenantId, int $mapId, int $limit = 50, bool $includeExpired = false): array
    {
        $limit = max(1, min($limit, 200));
        // On lit un volume plus large puis on filtre côté PHP (préfixe accentué / WIA).
        $scan = min(500, max(100, $limit * 5));
        $windowSec = \App\Support\MedicalAlertParser::ACTIVE_WINDOW_SECONDS;
        // Fenêtre métier + marge fuseau (UTC ↔ Paris) — alignée sur MedicalAlertParser::isWithinActiveWindow.
        $scanWindowSec = $windowSec + (3 * 3600);
        // Filtre sur l’horloge MySQL (même référence que created_at) — évite les faux hors-délai PHP↔DB.
        $rows = !$includeExpired
            ? $this->getChatMessagesSince($tenantId, $mapId, $scan, $scanWindowSec)
            : $this->getChatMessages($tenantId, $mapId, $scan);
        $out = $this->enrichMedicalRows($rows, $includeExpired);
        // Fallback : si DATE_SUB a tout exclu (décalage horloge), rescanner les N derniers messages.
        if ($out === [] && !$includeExpired) {
            $fallbackRows = $this->getChatMessages($tenantId, $mapId, $scan);
            $out = $this->enrichMedicalRows($fallbackRows, false);
        }
        if (count($out) > $limit) {
            $out = array_slice($out, -$limit);
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichMedicalRows(array $rows, bool $includeExpired): array
    {
        $out = [];
        foreach ($rows as $row) {
            $enriched = \App\Support\MedicalAlertParser::enrichChatRow(is_array($row) ? $row : []);
            if ($enriched === null) {
                continue;
            }
            if (!$includeExpired) {
                $created = isset($enriched['created_at']) ? (string) $enriched['created_at'] : '';
                if (!\App\Support\MedicalAlertParser::isWithinActiveWindow($created)) {
                    continue;
                }
            }
            $out[] = $enriched;
        }

        return $out;
    }

    /**
     * Messages tchat récents selon l’horloge MySQL (évite les faux hors-délai PHP↔DB).
     *
     * @return list<array<string, mixed>>
     */
    public function getChatMessagesSince(int $tenantId, int $mapId, int $limit, int $withinSeconds): array
    {
        $limit = max(1, min($limit, 500));
        $withinSeconds = max(60, min($withinSeconds, 48 * 3600));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM atak_chat_messages
             WHERE tenant_id = ? AND map_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
             ORDER BY created_at DESC
             LIMIT ' . (int) $limit
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $mapId, PDO::PARAM_INT);
        $stmt->bindValue(3, $withinSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
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
        $stmt = $this->pdo->prepare('SELECT call_sign, updated_at FROM atak_units WHERE tenant_id = ? AND map_id = ? AND status = ? ORDER BY updated_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$tenantId, $mapId, 'linked']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn ($r) => ['call_sign' => $r['call_sign'] ?? '', 'updated_at' => $r['updated_at'] ?? ''], $rows);
    }

    /**
     * Marque une unité hors ligne (sortie Arma) sans supprimer l’historique de position.
     * Comparaison d’indicatif insensible à la casse (Operateur / OPERATEUR).
     */
    public function markUnitOfflineByCallSign(int $tenantId, int $mapId, string $callSign): bool
    {
        $callSign = trim($callSign);
        if ($callSign === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE atak_units SET status = ?, updated_at = NOW()
             WHERE tenant_id = ? AND map_id = ? AND LOWER(call_sign) = LOWER(?)'
        );
        $stmt->execute(['offline', $tenantId, $mapId, $callSign]);

        return $stmt->rowCount() > 0;
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
