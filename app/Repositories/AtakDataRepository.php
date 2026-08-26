<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use PDO;

class AtakDataRepository
{
    use LazyDatabaseConnection;

    /**
     * Délai sans position / heartbeat au-delà duquel une unité en liaison
     * est considérée hors liaison (effetifs, carte, journal).
     * Aligné sur public/assets/js/atak-units.js (LIVE_TTL_MS).
     */
    public const UNIT_LIVE_TTL_SECONDS = 120;

    /** Origine (0,0) = position non reçue / parse raté — jamais une vraie case jouable. */
    private const POS_ORIGIN_EPS = 0.5;


    private ?bool $hasPosColumns = null;

    /** @var array<string, list<array{id: int, call_sign: string}>> */
    private array $pendingStaleDisconnects = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function hasPosColumns(): bool
    {
        if ($this->hasPosColumns !== null) {
            return $this->hasPosColumns;
        }
        try {
            $st = $this->pdo()->query(
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
            // Grille compacte Arma (ex. 180097) → approx. monde.
            return self::mapGridToWorldApprox($gridRef);
        }
        $x = self::coerceFloat($parts[0]);
        $y = self::coerceFloat($parts[1]);

        return [$x, $y];
    }

    /**
     * Convertit une grille carte Arma (6/8/10 chiffres) en coords monde approximatives.
     * Ex. « 180097 » → centre de cellule 100 m (easting 180, northing 097).
     *
     * @return array{0: ?float, 1: ?float}
     */
    public static function mapGridToWorldApprox(?string $grid): array
    {
        $digits = preg_replace('/\D+/', '', (string) $grid) ?? '';
        $len = strlen($digits);
        if ($len < 6 || ($len % 2) !== 0) {
            return [null, null];
        }
        $half = intdiv($len, 2);
        $east = (int) substr($digits, 0, $half);
        $north = (int) substr($digits, $half);
        $cell = match ($half) {
            3 => 100.0,
            4 => 10.0,
            5 => 1.0,
            default => 100.0,
        };
        // Centre de cellule — assez précis pour un marker effectifs / carte.
        $x = ($east * $cell) + ($cell / 2.0);
        $y = ($north * $cell) + ($cell / 2.0);

        return [$x, $y];
    }

    /**
     * Résout une position carte : world x/y prioritaires, sinon grille (espace ou compacte).
     *
     * @return array{0: ?float, 1: ?float}
     */
    public static function resolveAlertPosition(mixed $posX, mixed $posY, ?string $grid = null): array
    {
        $x = self::coerceFloat($posX);
        $y = self::coerceFloat($posY);
        if (self::isValidMapPosition($x, $y)) {
            return [$x, $y];
        }
        [$gx, $gy] = self::parseGridRef($grid);

        return self::isValidMapPosition($gx, $gy) ? [$gx, $gy] : [null, null];
    }

    public static function coerceFloat(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            $f = (float) $raw;

            return is_finite($f) ? $f : null;
        }
        $s = trim(str_replace(',', '.', (string) $raw));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $f = (float) $s;

        return is_finite($f) ? $f : null;
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

    public function getUnitByCallSign(int $tenantId, int $mapId, string $callSign): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_units WHERE tenant_id = ? AND map_id = ? AND call_sign = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $mapId, $callSign]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Retrouve une unité BFT via steam_uid stocké dans extra (JSON).
     * Utile pour rattacher une alerte médicale émise sous le nom profil au bon indicatif.
     */
    public function findUnitBySteamUid(int $tenantId, int $mapId, string $steamUid): ?array
    {
        return $this->findUnitBySteamUidRaw($tenantId, $mapId, $steamUid, true);
    }

    /**
     * Scan brut (sans filtre fantômes) pour retrouver / retirer les frères Steam.
     *
     * @param bool $preferLive Si true, privilégie linked/delayed puis le plus récent.
     */
    public function findUnitBySteamUidRaw(int $tenantId, int $mapId, string $steamUid, bool $preferLive = true): ?array
    {
        $steamUid = trim($steamUid);
        if ($steamUid === '' || $tenantId < 1 || $mapId < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_units WHERE tenant_id = ? AND map_id = ?'
        );
        $stmt->execute([$tenantId, $mapId]);
        $best = null;
        $bestScore = -999999;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $unit) {
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
            $uid = trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? $extra['player_uid'] ?? ''));
            if ($uid === '' || strcasecmp($uid, $steamUid) !== 0) {
                continue;
            }
            $status = strtolower(trim((string) ($unit['status'] ?? '')));
            $ts = strtotime((string) ($unit['updated_at'] ?? '')) ?: 0;
            $score = $ts;
            if ($preferLive) {
                if ($status === 'linked') {
                    $score += 1_000_000_000;
                } elseif ($status === 'delayed') {
                    $score += 500_000_000;
                }
                $role = strtolower(trim((string) ($unit['role'] ?? '')));
                if ($role !== '' && $role !== 'operator') {
                    $score += 10_000;
                }
            }
            if ($best === null || $score >= $bestScore) {
                $best = $unit;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Marque hors-ligne une unité fantôme (ex. alerte médicale sous le mauvais indicatif).
     */
    public function markUnitOfflineById(int $tenantId, int $unitId): void
    {
        if ($unitId <= 0) {
            return;
        }
        try {
            $this->pdo()->prepare(
                'UPDATE atak_units SET status = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
            )->execute(['offline', $tenantId, $unitId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Statut métier : linked uniquement si pas offline et last update récente.
     *
     * Préférer $ageSeconds (TIMESTAMPDIFF MySQL) pour éviter les faux hors-ligne
     * quand PHP et MySQL ne partagent pas le même fuseau (DATETIME sans TZ).
     */
    public static function resolveLiveStatus(
        ?string $dbStatus,
        ?string $updatedAt,
        ?int $now = null,
        ?int $ageSeconds = null
    ): string {
        $status = strtolower(trim((string) $dbStatus));
        if ($status === 'offline') {
            return 'offline';
        }
        if ($ageSeconds === null) {
            $now ??= time();
            $ts = $updatedAt !== null && $updatedAt !== '' ? strtotime($updatedAt) : false;
            if ($ts === false) {
                return 'offline';
            }
            $ageSeconds = $now - $ts;
        }
        // Horodatage « futur » = écart de fuseau PHP↔MySQL : traiter comme frais.
        if ($ageSeconds < 0) {
            $ageSeconds = 0;
        }
        if ($ageSeconds > self::UNIT_LIVE_TTL_SECONDS) {
            return 'offline';
        }
        if ($ageSeconds > (int) (self::UNIT_LIVE_TTL_SECONDS * 0.6)) {
            return 'delayed';
        }
        if ($status === 'delayed') {
            return 'delayed';
        }

        return 'linked';
    }

    public function getMarkers(int $tenantId, int $mapId, ?string $since = null): array
    {
        try {
            $sql = 'SELECT id, layer_id, marker_data, updated_at FROM atak_markers WHERE tenant_id = ? AND map_id = ?';
            $params = [$tenantId, $mapId];
            if ($since !== null && $since !== '') {
                $sql .= ' AND (updated_at >= ? OR created_at >= ?)';
                $params[] = $since;
                $params[] = $since;
            }
            $sql .= ' ORDER BY id';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $raw = (string) ($r['marker_data'] ?? '');
                // Marqueurs retirés côté web : ne pas les renvoyer (évite le resync Arma).
                if ($this->markerDataIsSuppressed($raw)) {
                    continue;
                }
                $out[] = [
                    'id' => (int) $r['id'],
                    'layerId' => (int) $r['layer_id'],
                    'markerData' => $r['marker_data'],
                    'updated_at' => $r['updated_at'],
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            // Ne jamais renvoyer [] ici : le client ATAK traite une liste vide comme
            // « plus aucun marqueur » et efface la carte (faux négatif en micro-coupure BDD).
            throw $e;
        }
    }

    private function markerDataIsSuppressed(string $markerData): bool
    {
        if ($markerData === '' || !str_contains($markerData, 'suppressed')) {
            return false;
        }
        $decoded = json_decode($markerData, true);
        return is_array($decoded) && !empty($decoded['suppressed']);
    }

    public function addMarker(int $tenantId, int $mapId, int $layerId, string $markerData, ?string $armaName = null): array
    {
        $stmt = $this->pdo()->prepare('INSERT INTO atak_markers (tenant_id, map_id, layer_id, marker_data, arma_name) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $mapId, $layerId, $markerData, $armaName]);
        $id = (int) $this->pdo()->lastInsertId();
        return $this->getMarkerById($tenantId, $id);
    }

    public function getMarkerById(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT id, layer_id, marker_data, updated_at FROM atak_markers WHERE tenant_id = ? AND id = ?');
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
        $stmt = $this->pdo()->prepare('SELECT id, marker_data FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name = ?');
        $stmt->execute([$tenantId, $mapId, $armaName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            // Respecte une suppression web : le jeu ne doit pas republier le marqueur.
            if ($this->markerDataIsSuppressed((string) ($existing['marker_data'] ?? ''))) {
                return $this->getMarkerById($tenantId, (int) $existing['id'])
                    ?? ['id' => (int) $existing['id'], 'layerId' => $layerId, 'markerData' => (string) $existing['marker_data'], 'updated_at' => null];
            }
            $this->pdo()->prepare('UPDATE atak_markers SET layer_id = ?, marker_data = ? WHERE id = ?')->execute([$layerId, $markerData, $existing['id']]);
            return $this->getMarkerById($tenantId, (int) $existing['id']);
        }
        return $this->addMarker($tenantId, $mapId, $layerId, $markerData, $armaName);
    }

    /**
     * @return array{id:int, marker_data:string}|null
     */
    public function findMarkerByArmaName(int $tenantId, int $mapId, string $armaName): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT id, marker_data FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name = ? LIMIT 1');
        $stmt->execute([$tenantId, $mapId, $armaName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'marker_data' => (string) ($row['marker_data'] ?? ''),
        ];
    }

    public function updateMarker(int $tenantId, int $id, string $markerData, ?int $layerId = null): ?array
    {
        $existing = $this->getMarkerById($tenantId, $id);
        if ($existing === null) {
            return null;
        }
        if ($layerId !== null) {
            $stmt = $this->pdo()->prepare('UPDATE atak_markers SET marker_data = ?, layer_id = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$markerData, $layerId, $tenantId, $id]);
        } else {
            $stmt = $this->pdo()->prepare('UPDATE atak_markers SET marker_data = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
            $stmt->execute([$markerData, $tenantId, $id]);
        }
        return $this->getMarkerById($tenantId, $id);
    }

    public function deleteMarkerByArmaName(int $tenantId, int $mapId, string $armaName): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name = ?');
        $stmt->execute([$tenantId, $mapId, $armaName]);

        return $stmt->rowCount() > 0;
    }

    public function deleteMarker(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo()->prepare('SELECT id, arma_name, marker_data FROM atak_markers WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $armaName = trim((string) ($row['arma_name'] ?? ''));
        // Marqueur issu du jeu : soft-suppress pour ne pas le voir revenir au prochain sync.
        if ($armaName !== '') {
            $decoded = json_decode((string) ($row['marker_data'] ?? '{}'), true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
            $decoded['suppressed'] = true;
            $decoded['suppressed_at'] = gmdate('c');
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $upd = $this->pdo()->prepare('UPDATE atak_markers SET marker_data = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
            $upd->execute([is_string($encoded) ? $encoded : '{"suppressed":true}', $tenantId, $id]);

            return true;
        }
        $del = $this->pdo()->prepare('DELETE FROM atak_markers WHERE tenant_id = ? AND id = ?');
        $del->execute([$tenantId, $id]);

        return $del->rowCount() > 0;
    }

    public function getUnits(int $tenantId, int $mapId): array
    {
        try {
            $expired = $this->markStaleUnitsOffline($tenantId, $mapId);
            if ($expired !== []) {
                $key = $tenantId . ':' . $mapId;
                $this->pendingStaleDisconnects[$key] = array_merge(
                    $this->pendingStaleDisconnects[$key] ?? [],
                    $expired
                );
            }
            // age_seconds sur l’horloge MySQL (même référence que updated_at / markStale).
            $stmt = $this->pdo()->prepare(
                'SELECT *, TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age_seconds
                 FROM atak_units WHERE tenant_id = ? AND map_id = ? ORDER BY call_sign'
            );
            $stmt->execute([$tenantId, $mapId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $now = time();
            $out = [];
            foreach ($rows as $row) {
                $out[] = $this->normalizeUnitRow($row, $now);
            }

            return $this->suppressAlertGhostUnits($out);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Unités passées hors liaison par TTL depuis le dernier drain (pour journaliser).
     *
     * @return list<array{id: int, call_sign: string}>
     */
    public function drainStaleDisconnects(int $tenantId, int $mapId): array
    {
        $key = $tenantId . ':' . $mapId;
        $out = $this->pendingStaleDisconnects[$key] ?? [];
        unset($this->pendingStaleDisconnects[$key]);
        if ($out === []) {
            return [];
        }
        // Dédupliquer par id (plusieurs getUnits dans la même requête PHP).
        $seen = [];
        $unique = [];
        foreach ($out as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
            }
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * Retire les unités fantômes (alerte médicale / nom profil Athena)
     * quand un contact BFT réel existe déjà (même Steam, même compte, ou position très proche).
     *
     * Cas typique : N-10 (indicatif jeu) + Noopy (display_name Athena) pour le même joueur.
     *
     * @param list<array<string, mixed>> $units
     * @return list<array<string, mixed>>
     */
    private function suppressAlertGhostUnits(array $units): array
    {
        if (count($units) < 2) {
            return $units;
        }

        $parsed = [];
        foreach ($units as $idx => $unit) {
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
            $px = isset($unit['pos_x']) && $unit['pos_x'] !== null && $unit['pos_x'] !== '' ? (float) $unit['pos_x'] : null;
            $py = isset($unit['pos_y']) && $unit['pos_y'] !== null && $unit['pos_y'] !== '' ? (float) $unit['pos_y'] : null;
            $parsed[$idx] = [
                'unit' => $unit,
                'extra' => $extra,
                'src' => (string) ($extra['source'] ?? ''),
                'steam' => strtolower(trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''))),
                'role' => strtolower(trim((string) ($unit['role'] ?? $extra['role'] ?? ''))),
                'cs' => strtoupper(trim((string) ($unit['call_sign'] ?? ''))),
                'status' => strtolower(trim((string) ($unit['status'] ?? ''))),
                'px' => self::isValidMapPosition($px, $py) ? $px : null,
                'py' => self::isValidMapPosition($px, $py) ? $py : null,
                'ts' => strtotime((string) ($unit['updated_at'] ?? '')) ?: 0,
                'score' => $this->unitPresenceScore($unit, $extra),
            ];
        }

        $drop = [];
        $n = count($parsed);
        for ($i = 0; $i < $n; $i++) {
            if (isset($drop[$i])) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                if (isset($drop[$j])) {
                    continue;
                }
                $a = $parsed[$i];
                $b = $parsed[$j];
                $sameSteam = $a['steam'] !== '' && $a['steam'] === $b['steam'];
                $near = false;
                if ($a['px'] !== null && $b['px'] !== null) {
                    $dx = $a['px'] - $b['px'];
                    $dy = $a['py'] - $b['py'];
                    $near = ($dx * $dx + $dy * $dy) <= (120.0 * 120.0); // ≤ 120 m
                }
                if (!$sameSteam && !$near) {
                    continue;
                }

                // Même présence physique / Steam : garder le contact le plus « réel ».
                $aGhost = $this->unitLooksLikeAccountGhost($a);
                $bGhost = $this->unitLooksLikeAccountGhost($b);
                if ($aGhost && !$bGhost) {
                    $drop[$i] = true;
                    continue;
                }
                if ($bGhost && !$aGhost) {
                    $drop[$j] = true;
                    continue;
                }
                if ($a['score'] === $b['score']) {
                    // À score égal : garder le plus frais.
                    if ($a['ts'] >= $b['ts']) {
                        if ($bGhost || $b['src'] === 'medical_chat' || $b['src'] === 'tactical_alert') {
                            $drop[$j] = true;
                        }
                    } else {
                        if ($aGhost || $a['src'] === 'medical_chat' || $a['src'] === 'tactical_alert') {
                            $drop[$i] = true;
                        }
                    }
                    continue;
                }
                if ($a['score'] > $b['score'] && ($bGhost || $sameSteam || $near)) {
                    $drop[$j] = true;
                } elseif ($b['score'] > $a['score'] && ($aGhost || $sameSteam || $near)) {
                    $drop[$i] = true;
                }
            }
        }

        // Pass 2 : même Steam → un seul contact ; ou fantôme proche d’un BFT live.
        for ($i = 0; $i < $n; $i++) {
            if (isset($drop[$i])) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                if (isset($drop[$j])) {
                    continue;
                }
                $a = $parsed[$i];
                $b = $parsed[$j];
                $sameSteam = $a['steam'] !== '' && $a['steam'] === $b['steam'];
                $near = false;
                if ($a['px'] !== null && $b['px'] !== null) {
                    $dx = $a['px'] - $b['px'];
                    $dy = $a['py'] - $b['py'];
                    $near = ($dx * $dx + $dy * $dy) <= (400.0 * 400.0);
                }
                $aGhost = $this->unitLooksLikeAccountGhost($a);
                $bGhost = $this->unitLooksLikeAccountGhost($b);
                // Même joueur (Steam) : toujours une seule ligne.
                if ($sameSteam) {
                    $aLive = in_array($a['status'], ['linked', 'delayed'], true);
                    $bLive = in_array($b['status'], ['linked', 'delayed'], true);
                    if ($aLive && !$bLive) {
                        $drop[$j] = true;
                        continue;
                    }
                    if ($bLive && !$aLive) {
                        $drop[$i] = true;
                        continue;
                    }
                    if ($a['score'] !== $b['score']) {
                        if ($a['score'] > $b['score']) {
                            $drop[$j] = true;
                        } else {
                            $drop[$i] = true;
                        }
                        continue;
                    }
                    if ($a['ts'] >= $b['ts']) {
                        $drop[$j] = true;
                    } else {
                        $drop[$i] = true;
                    }
                    continue;
                }
                // Pas le même Steam : ne retirer que si l’un est un fantôme compte/alerte à proximité.
                if (!$near || (!$aGhost && !$bGhost)) {
                    continue;
                }
                $aLive = in_array($a['status'], ['linked', 'delayed'], true);
                $bLive = in_array($b['status'], ['linked', 'delayed'], true);
                if ($aGhost && !$bGhost) {
                    $drop[$i] = true;
                    continue;
                }
                if ($bGhost && !$aGhost) {
                    $drop[$j] = true;
                    continue;
                }
                if ($aLive && !$bLive) {
                    $drop[$j] = true;
                } elseif ($bLive && !$aLive) {
                    $drop[$i] = true;
                } elseif ($a['score'] >= $b['score']) {
                    $drop[$j] = true;
                } else {
                    $drop[$i] = true;
                }
            }
        }

        if ($drop === []) {
            return $units;
        }
        $out = [];
        foreach ($parsed as $idx => $row) {
            if (!isset($drop[$idx])) {
                $out[] = $row['unit'];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $extra
     */
    private function unitPresenceScore(array $unit, array $extra): int
    {
        $score = 0;
        $status = strtolower(trim((string) ($unit['status'] ?? '')));
        if ($status === 'linked') {
            $score += 40;
        } elseif ($status === 'delayed') {
            $score += 10;
        }
        $steam = trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
        if ($steam !== '') {
            $score += 25;
        }
        $src = (string) ($extra['source'] ?? '');
        if ($src === 'medical_chat' || $src === 'tactical_alert') {
            $score -= 30;
        }
        $role = strtolower(trim((string) ($unit['role'] ?? $extra['role'] ?? '')));
        if ($role !== '' && $role !== 'operator') {
            $score += 20;
        } elseif ($role === 'operator') {
            $score -= 5;
        }
        if (isset($extra['ammo']) || isset($extra['radio']) || isset($extra['radio_freq']) || isset($extra['fuel'])) {
            $score += 15;
        }
        $ts = strtotime((string) ($unit['updated_at'] ?? '')) ?: 0;
        if ($ts > 0) {
            $age = time() - $ts;
            if ($age < 0) {
                $age = 0;
            }
            if ($age < 30) {
                $score += 10;
            } elseif ($age < 90) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * @param array{src:string,role:string,status:string,steam:string,extra:array<string,mixed>} $row
     */
    private function unitLooksLikeAccountGhost(array $row): bool
    {
        if ($row['src'] === 'medical_chat' || $row['src'] === 'tactical_alert') {
            return true;
        }
        // Créé sous le nom profil / compte : rôle générique, sans télémétrie jeu.
        $extra = $row['extra'];
        $hasTelemetry = isset($extra['ammo']) || isset($extra['radio']) || isset($extra['radio_freq'])
            || isset($extra['fuel']) || isset($extra['steam_uid']) || isset($extra['steamId']);
        if ($row['role'] === 'operator' && !$hasTelemetry && in_array($row['status'], ['delayed', 'offline', 'linked'], true)) {
            // Sans steam + operator = très souvent le fantôme « compte Athena ».
            if ($row['steam'] === '') {
                return true;
            }
        }
        // Hors liaison + rôle générique + santé critique : souvent un fantôme d’alerte médicale
        // figé sous le nom de compte (Newp1) alors que le BFT vit sous N-10.
        if ($row['status'] === 'offline' && $row['role'] === 'operator') {
            $health = strtolower(trim((string) ($extra['health'] ?? '')));
            if (in_array($health, ['unconscious', 'cardiac_arrest', 'cardiac-arrest', 'dead', 'kia'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeUnitRow(array $row, ?int $now = null): array
    {
        $now ??= time();
        $dbStatus = isset($row['status']) ? (string) $row['status'] : '';
        $ageSeconds = null;
        if (array_key_exists('age_seconds', $row)) {
            $rawAge = $row['age_seconds'];
            unset($row['age_seconds']);
            if ($rawAge !== null && $rawAge !== '') {
                $ageSeconds = (int) $rawAge;
            }
        }
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
            $now,
            $ageSeconds
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
        $extra = self::decodeExtra($row['extra'] ?? null);
        $shown = self::displayCallSign((string) ($row['call_sign'] ?? ''), is_array($extra) ? $extra : []);
        $row['display_call_sign'] = $shown;

        return $row;
    }

    /**
     * Passe hors liaison les unités encore en liaison / signal différé sans heartbeat récent.
     *
     * @return list<array{id: int, call_sign: string}> Unités effectivement basculées (pour journal).
     */
    public function markStaleUnitsOffline(int $tenantId, int $mapId): array
    {
        $ttl = self::UNIT_LIVE_TTL_SECONDS;
        $select = $this->pdo()->prepare(
            'SELECT id, call_sign FROM atak_units
             WHERE tenant_id = ? AND map_id = ?
               AND status IN (?, ?)
               AND (updated_at IS NULL OR updated_at < (NOW() - INTERVAL ' . (int) $ttl . ' SECOND))'
        );
        $select->execute([$tenantId, $mapId, 'linked', 'delayed']);
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE atak_units SET status = ?
             WHERE tenant_id = ? AND map_id = ?
               AND status IN (?, ?)
               AND (updated_at IS NULL OR updated_at < (NOW() - INTERVAL ' . (int) $ttl . ' SECOND))'
        );
        $stmt->execute(['offline', $tenantId, $mapId, 'linked', 'delayed']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'call_sign' => trim((string) ($r['call_sign'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Clés TOC (notes effectifs) à préserver lors d’un upsert position jeu.
     * @return list<string>
     */
    public static function tocExtraKeys(): array
    {
        return ['toc_radio', 'toc_vehicle', 'toc_note'];
    }

    /**
     * @param array<string, mixed>|null $existingExtra
     * @param array<string, mixed> $incomingExtra
     * @return array<string, mixed>
     */
    public static function mergePreservedTocExtra(?array $existingExtra, array $incomingExtra): array
    {
        if (!is_array($existingExtra) || $existingExtra === []) {
            return $incomingExtra;
        }
        foreach (self::tocExtraKeys() as $key) {
            if (!array_key_exists($key, $existingExtra)) {
                continue;
            }
            // Le jeu n’envoie pas ces clés : on les conserve.
            if (!array_key_exists($key, $incomingExtra)) {
                $incomingExtra[$key] = $existingExtra[$key];
            }
        }

        return $incomingExtra;
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function decodeExtra($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $fixed = preg_replace('/(?<=[:\[\s])(-?\d+),(\d{1,6})(?=[,}\]\s])/', '$1.$2', $raw);
            if (is_string($fixed) && $fixed !== $raw) {
                $decoded = json_decode($fixed, true);
            }
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Contact relais (téléphone, IA alliée, balise GPS) : pas l’identité Steam du joueur pont.
     *
     * @param array<string, mixed> $extra
     */
    public static function isProxyContactExtra(array $extra): bool
    {
        foreach (['phone_geoloc', 'ally_ai', 'gps_beacon'] as $flag) {
            $val = $extra[$flag] ?? false;
            if ($val === true || $val === 1 || $val === '1' || $val === 'true') {
                return true;
            }
        }
        $src = strtolower(trim((string) ($extra['source'] ?? '')));

        return in_array($src, ['phone', 'ally', 'gps', 'gps_beacon'], true);
    }

    /**
     * Repli si extra n’a pas pu être décodé (JSON SQF cassé) mais le texte trahit un relais.
     */
    public static function extraLooksLikeProxy(mixed $raw, array $extra = []): bool
    {
        if (self::isProxyContactExtra($extra)) {
            return true;
        }
        if (!is_string($raw) || $raw === '') {
            return false;
        }

        return str_contains($raw, '"ally_ai"')
            || str_contains($raw, '"phone_geoloc"')
            || str_contains($raw, '"gps_beacon"')
            || str_contains($raw, '"source":"ally"')
            || str_contains($raw, '"source":"phone"')
            || str_contains($raw, '"source":"gps"');
    }

    /**
     * Indicatif relais stable (ALLY- / GPS- / TEL-) — ne jamais fusionner avec le joueur pont.
     */
    public static function callSignLooksLikeProxy(string $callSign): bool
    {
        $cs = trim($callSign);
        if ($cs === '') {
            return false;
        }
        $fold = function_exists('mb_strtoupper') ? mb_strtoupper($cs, 'UTF-8') : strtoupper($cs);

        return str_starts_with($fold, 'ALLY-')
            || str_starts_with($fold, 'GPS-')
            || str_starts_with($fold, 'TEL-')
            || str_starts_with($fold, 'TEL.')
            || str_starts_with($fold, 'TÉL');
    }

    /**
     * Identifiant auto ALLY-0-1780311 (netId), pas un indicatif choisi.
     */
    public static function looksLikeAutoAllyId(string $callSign): bool
    {
        $cs = trim($callSign);
        if ($cs === '') {
            return false;
        }

        return preg_match('/^ALLY-\d+-\d+(-\d+)*$/iu', $cs) === 1;
    }

    /**
     * Indicatif à afficher : jamais l’identifiant technique ALLY-0-….
     *
     * @param array<string, mixed> $extra
     */
    public static function displayCallSign(string $callSign, array $extra = []): string
    {
        $named = trim((string) ($extra['display_name'] ?? $extra['callsign_display'] ?? ''));
        if ($named !== '' && !self::looksLikeAutoAllyId($named) && !str_starts_with(strtoupper($named), 'ALLY-')) {
            return $named;
        }
        $cs = trim($callSign);
        if (preg_match('/^ALLY-\S+\s+[·\-–—]\s+(.+)$/u', $cs, $m) === 1) {
            $pretty = trim((string) ($m[1] ?? ''));
            if ($pretty !== '' && !self::looksLikeAutoAllyId($pretty)) {
                return $pretty;
            }
        }
        $isAlly = self::isProxyContactExtra($extra)
            || self::looksLikeAutoAllyId($cs)
            || str_starts_with(function_exists('mb_strtoupper') ? mb_strtoupper($cs, 'UTF-8') : strtoupper($cs), 'ALLY-');
        if ($isAlly && (self::looksLikeAutoAllyId($cs) || str_starts_with(strtoupper($cs), 'ALLY-'))) {
            $group = trim((string) ($extra['group_name'] ?? $extra['group'] ?? ''));
            if ($group !== '' && !self::looksLikeAutoAllyId($group)) {
                return $group;
            }

            return 'Unité alliée';
        }

        return $cs;
    }

    /**
     * Retrouve une IA alliée par identifiant interne (survît au changement d’indicatif affiché).
     *
     * @return array<string, mixed>|null
     */
    private function findAllyUnitByStableId(int $tenantId, int $mapId, string $allyId, bool $hasPos): ?array
    {
        $id = trim($allyId);
        if ($id === '' || preg_match('/^[A-Za-z0-9._-]{3,80}$/', $id) !== 1) {
            return null;
        }
        $cols = $hasPos
            ? 'id, grid_ref, pos_x, pos_y, extra, call_sign'
            : 'id, grid_ref, extra, call_sign';
        $stmt = $this->pdo()->prepare(
            "SELECT {$cols} FROM atak_units
             WHERE tenant_id = ? AND map_id = ?
               AND (call_sign = ? OR call_sign LIKE ? OR extra LIKE ?)
             ORDER BY updated_at DESC
             LIMIT 12"
        );
        $stmt->execute([$tenantId, $mapId, $id, $id . '%', '%"ally_id":"' . $id . '"%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return null;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $extra = self::decodeExtra($row['extra'] ?? null);
            $got = trim((string) ($extra['ally_id'] ?? ''));
            if ($got !== '' && strcasecmp($got, $id) === 0) {
                return $row;
            }
            $cs = trim((string) ($row['call_sign'] ?? ''));
            if (strcasecmp($cs, $id) === 0) {
                return $row;
            }
            if (preg_match('/^' . preg_quote($id, '/') . '(?:\s+[·\-–—]|$)/u', $cs) === 1) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{created: bool, unit_id: int}
     */
    public function upsertUnitPosition(int $tenantId, int $mapId, string $callSign, float $posX, float $posY, ?float $heading, string $role, string $extraJson): array
    {
        $validPos = self::isValidMapPosition($posX, $posY);
        $gridRef = $validPos ? ((string) round($posX) . ' ' . round($posY)) : '';
        // hasPos AVANT le SELECT : sans migration pos_x/pos_y, un SELECT pos_* plante
        // toute la méthode (pas d’INSERT, pas de setLastActivity) → /units vide.
        $hasPos = $this->hasPosColumns();
        $incomingExtra = self::decodeExtra($extraJson);
        $existing = null;
        $allyId = trim((string) ($incomingExtra['ally_id'] ?? ''));
        if ($allyId !== '' && (self::isProxyContactExtra($incomingExtra) || self::looksLikeAutoAllyId($allyId))) {
            $existing = $this->findAllyUnitByStableId($tenantId, $mapId, $allyId, $hasPos);
            // Clé carte = identifiant stable ALLY-… ; le libellé humain reste dans display_name.
            $label = trim($callSign);
            if ($label !== '' && !self::looksLikeAutoAllyId($label) && !str_starts_with(strtoupper($label), 'ALLY-')) {
                if (trim((string) ($incomingExtra['display_name'] ?? '')) === '') {
                    $incomingExtra['display_name'] = $label;
                }
            }
            $stableCs = $allyId;
            if (is_array($existing)) {
                $prevCs = trim((string) ($existing['call_sign'] ?? ''));
                if (self::looksLikeAutoAllyId($prevCs) || str_starts_with(strtoupper($prevCs), 'ALLY-')) {
                    $stableCs = $prevCs;
                }
            }
            $callSign = $stableCs;
            $extraJson = json_encode($incomingExtra, JSON_UNESCAPED_UNICODE);
            if ($extraJson === false) {
                $extraJson = '{}';
            }
        }
        if (!is_array($existing)) {
            $stmt = $this->pdo()->prepare(
                $hasPos
                    ? 'SELECT id, grid_ref, pos_x, pos_y, extra FROM atak_units WHERE tenant_id = ? AND map_id = ? AND call_sign = ?'
                    : 'SELECT id, grid_ref, extra FROM atak_units WHERE tenant_id = ? AND map_id = ? AND call_sign = ?'
            );
            $stmt->execute([$tenantId, $mapId, $callSign]);
            $hit = $stmt->fetch(PDO::FETCH_ASSOC);
            $existing = is_array($hit) ? $hit : null;
        }
        $created = false;
        $unitId = 0;

        if ($existing) {
            $unitId = (int) $existing['id'];
            // Conserver notes TOC (fréquence / véhicule / note) écrites depuis la Tacmap.
            $mergedExtra = self::mergePreservedTocExtra(self::decodeExtra($existing['extra'] ?? null), $incomingExtra);
            $extraJson = json_encode($mergedExtra, JSON_UNESCAPED_UNICODE);
            if ($extraJson === false) {
                $extraJson = '{}';
            }
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
                $this->pdo()->prepare(
                    'UPDATE atak_units SET call_sign = ?, grid_ref = ?, heading = ?, role = ?, extra = ?, status = ?, pos_x = ?, pos_y = ?, updated_at = NOW() WHERE id = ?'
                )->execute([$callSign, $storeGrid, $heading, $role, $extraJson, 'linked', $storeX, $storeY, $unitId]);
            } else {
                $this->pdo()->prepare(
                    'UPDATE atak_units SET call_sign = ?, grid_ref = ?, heading = ?, role = ?, extra = ?, status = ?, updated_at = NOW() WHERE id = ?'
                )->execute([$callSign, $storeGrid, $heading, $role, $extraJson, 'linked', $unitId]);
            }
        } else {
            if ($hasPos) {
                $this->pdo()->prepare(
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
                $this->pdo()->prepare(
                    'INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, heading, extra, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([$tenantId, $mapId, $callSign, $role, 'linked', $validPos ? $gridRef : '', $heading, $extraJson]);
            }
            $created = true;
            $unitId = (int) $this->pdo()->lastInsertId();
        }
        $this->setLastActivity($tenantId, $mapId);

        // ID BFT (military_id) lié à l’indicatif — et au compte Steam si connu.
        if ($unitId > 0) {
            try {
                $opIds = new AtakOperatorIdRepository();
                if ($opIds->tablesReady() && $opIds->unitsMilitaryIdColumnReady()) {
                    $extraArr = self::decodeExtra($extraJson);
                    $steam = trim((string) ($extraArr['steam_uid'] ?? $extraArr['steamId'] ?? $extraArr['player_uid'] ?? ''));
                    $userId = null;
                    if ($steam !== '') {
                        try {
                            $userRepo = new UserRepository();
                            $user = $userRepo->findBySteamIdForTenant($tenantId, $steam)
                                ?? $userRepo->findBySteamId($steam);
                            if (is_array($user)) {
                                $userId = (int) ($user['id'] ?? 0);
                                if ($userId < 1) {
                                    $userId = null;
                                }
                            }
                        } catch (\Throwable) {
                            $userId = null;
                        }
                    }
                    $mid = $opIds->syncUnitMilitaryId($tenantId, $unitId, $callSign, $userId);
                    // Miroir dans extra pour BFT / tablette / ATAK (même identité que l’indicatif).
                    if ($mid !== '') {
                        $extraArr['bft_id'] = $mid;
                        $extraArr['military_id'] = $mid;
                        $extraArr['atak_id'] = $mid;
                        $encoded = json_encode($extraArr, JSON_UNESCAPED_UNICODE);
                        if (is_string($encoded) && $encoded !== '') {
                            $this->pdo()->prepare(
                                'UPDATE atak_units SET extra = ? WHERE id = ? AND tenant_id = ?'
                            )->execute([$encoded, $unitId, $tenantId]);
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        // Même Steam / même joueur sous un autre indicatif (ex. Newp1 → N-10) → retirer le fantôme.
        try {
            $extraArr = json_decode($extraJson, true);
            if (!is_array($extraArr)) {
                $extraArr = [];
            }
            $steam = trim((string) ($extraArr['steam_uid'] ?? $extraArr['steamId'] ?? $extraArr['player_uid'] ?? ''));
            if ($steam !== '' && !self::isProxyContactExtra($extraArr) && !self::callSignLooksLikeProxy($callSign)) {
                $this->retireSteamSiblingUnits($tenantId, $mapId, $callSign, $steam);
            }
        } catch (\Throwable) {
        }

        return ['created' => $created, 'unit_id' => $unitId];
    }

    /**
     * Passe hors-ligne les autres lignes atak_units du même Steam (indicatif différent).
     */
    public function retireSteamSiblingUnits(int $tenantId, int $mapId, string $keepCallSign, string $steamUid): int
    {
        $steamUid = trim($steamUid);
        $keepCallSign = trim($keepCallSign);
        if ($tenantId < 1 || $mapId < 1 || $steamUid === '' || $keepCallSign === '') {
            return 0;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id, call_sign, extra, status FROM atak_units WHERE tenant_id = ? AND map_id = ?'
        );
        $stmt->execute([$tenantId, $mapId]);
        $retired = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cs = trim((string) ($row['call_sign'] ?? ''));
            if ($cs === '' || strcasecmp($cs, $keepCallSign) === 0) {
                continue;
            }
            $extra = [];
            $raw = $row['extra'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $extra = $decoded;
                }
            } elseif (is_array($raw)) {
                $extra = $raw;
            }
            $uid = trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? $extra['player_uid'] ?? ''));
            if ($uid === '' || strcasecmp($uid, $steamUid) !== 0) {
                continue;
            }
            if (self::isProxyContactExtra($extra) || self::callSignLooksLikeProxy($cs)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($status === 'offline') {
                continue;
            }
            $this->markUnitOfflineById($tenantId, $id);
            $retired++;
        }

        return $retired;
    }

    public function addUnit(int $tenantId, int $mapId, array $data): array
    {
        $stmt = $this->pdo()->prepare('INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, heading, extra) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
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
        $id = (int) $this->pdo()->lastInsertId();
        $row = $this->pdo()->prepare('SELECT * FROM atak_units WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function getUnitById(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_units WHERE tenant_id = ? AND id = ?');
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
        // IA alliée : l’indicatif saisi au poste est un libellé, pas la clé de suivi.
        $rowExtra = self::decodeExtra($row['extra'] ?? null);
        $rowCs = trim((string) ($row['call_sign'] ?? ''));
        $isAllyRow = self::isProxyContactExtra($rowExtra)
            || self::callSignLooksLikeProxy($rowCs)
            || self::looksLikeAutoAllyId($rowCs)
            || str_starts_with(strtoupper($rowCs), 'ALLY-');
        if ($isAllyRow && array_key_exists('call_sign', $data)) {
            $label = trim((string) ($data['call_sign'] ?? ''));
            unset($data['call_sign']);
            if ($label !== '' && !self::looksLikeAutoAllyId($label) && !str_starts_with(strtoupper($label), 'ALLY-')) {
                $merged = $rowExtra;
                if (array_key_exists('extra', $data)) {
                    $incoming = is_array($data['extra']) ? $data['extra'] : self::decodeExtra($data['extra']);
                    $merged = array_merge($merged, $incoming);
                }
                $merged['display_name'] = $label;
                if (trim((string) ($merged['ally_id'] ?? '')) === '' && (self::looksLikeAutoAllyId($rowCs) || str_starts_with(strtoupper($rowCs), 'ALLY-'))) {
                    $merged['ally_id'] = $rowCs;
                }
                $data['extra'] = $merged;
            }
        }
        // Fusion notes TOC dans extra (sans écraser la télémétrie jeu).
        if (array_key_exists('toc_radio', $data) || array_key_exists('toc_vehicle', $data) || array_key_exists('toc_note', $data)) {
            $extra = self::decodeExtra($row['extra'] ?? null);
            if (array_key_exists('extra', $data)) {
                $incoming = is_array($data['extra']) ? $data['extra'] : self::decodeExtra($data['extra']);
                $extra = array_merge($extra, $incoming);
            }
            foreach (self::tocExtraKeys() as $key) {
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $val = trim((string) ($data[$key] ?? ''));
                if ($val === '') {
                    unset($extra[$key]);
                } else {
                    // Limites raisonnables pour l’affichage effectifs.
                    $max = $key === 'toc_note' ? 500 : 80;
                    if (function_exists('mb_substr')) {
                        $val = mb_substr($val, 0, $max);
                    } else {
                        $val = substr($val, 0, $max);
                    }
                    $extra[$key] = $val;
                }
            }
            $data['extra'] = $extra;
            unset($data['toc_radio'], $data['toc_vehicle'], $data['toc_note']);
        } elseif (array_key_exists('extra', $data) && is_array($data['extra'])) {
            // Merge shallow pour ne pas perdre les clés TOC si le client envoie un extra partiel.
            $data['extra'] = array_merge(self::decodeExtra($row['extra'] ?? null), $data['extra']);
        }
        $fields = ['call_sign', 'role', 'status', 'grid_ref', 'heading', 'extra'];
        $updates = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $updates[] = "{$f} = ?";
                $params[] = is_array($data[$f] ?? null) ? json_encode($data[$f], JSON_UNESCAPED_UNICODE) : ($data[$f] ?? $row[$f]);
            }
        }
        if ($updates === []) {
            return $row;
        }
        $params[] = $id;
        $this->pdo()->prepare('UPDATE atak_units SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);

        return $this->getUnitById($tenantId, $id);
    }

    public function deleteUnit(int $tenantId, int $id): bool
    {
        if ($tenantId < 1 || $id < 1) {
            return false;
        }
        $stmt = $this->pdo()->prepare('DELETE FROM atak_units WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    public function getChatMessageById(int $tenantId, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_chat_messages WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function getChatMessages(int $tenantId, int $mapId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_chat_messages WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $mapId]);

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Messages plus récents qu’un id (poll jeu / TOC → Athena inbox).
     *
     * @return list<array<string, mixed>>
     */
    public function getChatMessagesAfter(int $tenantId, int $mapId, int $afterId, int $limit = 50): array
    {
        $afterId = max(0, $afterId);
        $limit = max(1, min($limit, 200));
        if ($afterId < 1) {
            return $this->getChatMessages($tenantId, $mapId, $limit);
        }
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_chat_messages
             WHERE tenant_id = ? AND map_id = ? AND id > ?
             ORDER BY id ASC
             LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $mapId, $afterId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addChatMessage(int $tenantId, int $mapId, string $author, string $body, string $source = 'game'): array
    {
        $source = self::normalizeChatSource($source);
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_chat_messages (tenant_id, map_id, author, body, source) VALUES (?, ?, ?, ?, ?)'
            )->execute([$tenantId, $mapId, $author, $body, $source]);
        } catch (\PDOException $e) {
            if (!str_contains($e->getMessage(), 'source')) {
                throw $e;
            }
            $this->pdo()->prepare(
                'INSERT INTO atak_chat_messages (tenant_id, map_id, author, body) VALUES (?, ?, ?, ?)'
            )->execute([$tenantId, $mapId, $author, $body]);
        }
        $id = (int) $this->pdo()->lastInsertId();
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_chat_messages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [];
        }
        $row['source'] = self::normalizeChatSource(isset($row['source']) ? (string) $row['source'] : $source);

        return $row;
    }

    public static function normalizeChatSource(?string $source): string
    {
        return strtolower(trim((string) $source)) === 'web' ? 'web' : 'game';
    }

    /**
     * Dédup courte : même indicatif + même type d’alerte médicale déjà posté récemment.
     * Évite de republier en boucle la même « ALERTE MÉDICALE » (mod / reconnexion).
     *
     * @return array<string, mixed>|null
     */
    public function findRecentDuplicateMedicalAlert(
        int $tenantId,
        int $mapId,
        string $callSign,
        string $kind,
        int $withinSeconds = 300
    ): ?array {
        $callSign = mb_strtoupper(trim($callSign));
        $kind = mb_strtolower(trim($kind));
        if ($callSign === '' || $kind === '') {
            return null;
        }
        $withinSeconds = max(30, min($withinSeconds, 1800));
        $rows = $this->getChatMessagesSince($tenantId, $mapId, 80, $withinSeconds);
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $row = $rows[$i];
            if (!is_array($row)) {
                continue;
            }
            $enriched = \App\Support\MedicalAlertParser::enrichChatRow($row);
            if ($enriched === null) {
                continue;
            }
            $ecs = mb_strtoupper(trim((string) ($enriched['call_sign'] ?? '')));
            if ($ecs === '') {
                $ecs = mb_strtoupper(trim((string) ($row['author'] ?? '')));
            }
            $ek = mb_strtolower(trim((string) ($enriched['kind'] ?? '')));
            if ($ecs === $callSign && $ek === $kind) {
                return $row;
            }
        }

        return null;
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
     * Alertes tactiques dérivées du tchat (préfixe ALERTE TACTIQUE).
     *
     * @return list<array<string, mixed>>
     */
    public function getTacticalAlertsFromChat(int $tenantId, int $mapId, int $limit = 50, bool $includeExpired = false): array
    {
        $limit = max(1, min($limit, 200));
        $scan = min(500, max(100, $limit * 5));
        $windowSec = \App\Support\TacticalAlertParser::ACTIVE_WINDOW_SECONDS;
        $scanWindowSec = $windowSec + (3 * 3600);
        $rows = !$includeExpired
            ? $this->getChatMessagesSince($tenantId, $mapId, $scan, $scanWindowSec)
            : $this->getChatMessages($tenantId, $mapId, $scan);
        $out = [];
        foreach ($rows as $row) {
            $enriched = \App\Support\TacticalAlertParser::enrichChatRow(is_array($row) ? $row : []);
            if ($enriched === null) {
                continue;
            }
            if (!$includeExpired) {
                $created = isset($enriched['created_at']) ? (string) $enriched['created_at'] : '';
                if (!\App\Support\TacticalAlertParser::isWithinActiveWindow($created)) {
                    continue;
                }
            }
            $out[] = $enriched;
        }
        if ($out === [] && !$includeExpired) {
            $fallbackRows = $this->getChatMessages($tenantId, $mapId, $scan);
            foreach ($fallbackRows as $row) {
                $enriched = \App\Support\TacticalAlertParser::enrichChatRow(is_array($row) ? $row : []);
                if ($enriched === null) {
                    continue;
                }
                $created = isset($enriched['created_at']) ? (string) $enriched['created_at'] : '';
                if (!\App\Support\TacticalAlertParser::isWithinActiveWindow($created)) {
                    continue;
                }
                $out[] = $enriched;
            }
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
        $stmt = $this->pdo()->prepare(
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
        // Index Steam → indicatifs BFT « réels » (hors source alerte seule).
        $steamToBft = [];
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
            $src = (string) ($extra['source'] ?? '');
            $steam = trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
            if ($steam === '' || $src === 'medical_chat' || $src === 'tactical_alert') {
                continue;
            }
            $steamToBft[strtolower($steam)] = true;
        }

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
            // Fantôme alerte médicale / tactique alors qu’un BFT Steam existe déjà.
            $src = (string) ($extra['source'] ?? '');
            $steam = trim((string) ($extra['steam_uid'] ?? $extra['steamId'] ?? ''));
            if (
                ($src === 'medical_chat' || $src === 'tactical_alert')
                && $steam !== ''
                && isset($steamToBft[strtolower($steam)])
            ) {
                continue;
            }
            // Même heuristique proximité (fantômes sans steam_uid).
            if ($src === 'medical_chat' || $src === 'tactical_alert') {
                $gpx = isset($unit['pos_x']) && $unit['pos_x'] !== null && $unit['pos_x'] !== '' ? (float) $unit['pos_x'] : null;
                $gpy = isset($unit['pos_y']) && $unit['pos_y'] !== null && $unit['pos_y'] !== '' ? (float) $unit['pos_y'] : null;
                if (self::isValidMapPosition($gpx, $gpy)) {
                    $near = false;
                    foreach ($units as $other) {
                        if (($other['id'] ?? null) === ($unit['id'] ?? null)) {
                            continue;
                        }
                        $oExtra = [];
                        $oRaw = $other['extra'] ?? null;
                        if (is_string($oRaw) && $oRaw !== '') {
                            $od = json_decode($oRaw, true);
                            if (is_array($od)) {
                                $oExtra = $od;
                            }
                        } elseif (is_array($oRaw)) {
                            $oExtra = $oRaw;
                        }
                        $oSrc = (string) ($oExtra['source'] ?? '');
                        if ($oSrc === 'medical_chat' || $oSrc === 'tactical_alert') {
                            continue;
                        }
                        $ox = isset($other['pos_x']) && $other['pos_x'] !== null && $other['pos_x'] !== '' ? (float) $other['pos_x'] : null;
                        $oy = isset($other['pos_y']) && $other['pos_y'] !== null && $other['pos_y'] !== '' ? (float) $other['pos_y'] : null;
                        if (!self::isValidMapPosition($ox, $oy)) {
                            continue;
                        }
                        $dx = $gpx - $ox;
                        $dy = $gpy - $oy;
                        if (($dx * $dx + $dy * $dy) <= (100.0 * 100.0)) {
                            $near = true;
                            break;
                        }
                    }
                    if ($near) {
                        continue;
                    }
                }
            }
            // Unité offline : ne pas la lister comme à secourir.
            if (strtolower(trim((string) ($unit['status'] ?? ''))) === 'offline') {
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
        $limit = max(1, min($limit, 200));
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_pings WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $mapId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addPing(int $tenantId, int $mapId, string $author, float $posX, float $posY, string $message): array
    {
        $this->pdo()->prepare('INSERT INTO atak_pings (tenant_id, map_id, author, pos_x, pos_y, message) VALUES (?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $author, $posX, $posY, $message]);
        $id = (int) $this->pdo()->lastInsertId();
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_pings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deletePing(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM atak_pings WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);

        return $stmt->rowCount() > 0;
    }

    public function getNineLines(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND map_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addNineLine(int $tenantId, int $mapId, string $author, array $lines): array
    {
        $stmt = $this->pdo()->prepare('INSERT INTO atak_nine_line (tenant_id, map_id, author, line1, line2, line3, line4, line5, line6, line7, line8, line9, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
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
        $id = (int) $this->pdo()->lastInsertId();
        $row = $this->pdo()->prepare('SELECT * FROM atak_nine_line WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function updateNineLineStatus(int $tenantId, int $id, string $status): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        if (!$stmt->fetch()) {
            return null;
        }
        $this->pdo()->prepare('UPDATE atak_nine_line SET status = ? WHERE id = ?')->execute([$status, $id]);
        $row = $this->pdo()->prepare('SELECT * FROM atak_nine_line WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function getDesignators(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_designator_targets WHERE tenant_id = ? AND map_id = ?');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertDesignator(int $tenantId, int $mapId, string $callSign, float $posX, float $posY): array
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM atak_designator_targets WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $stmt->execute([$tenantId, $mapId, $callSign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->pdo()->prepare('UPDATE atak_designator_targets SET pos_x = ?, pos_y = ? WHERE id = ?')->execute([$posX, $posY, $existing['id']]);
        } else {
            $this->pdo()->prepare('INSERT INTO atak_designator_targets (tenant_id, map_id, call_sign, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $callSign, $posX, $posY]);
        }
        $row = $this->pdo()->prepare('SELECT * FROM atak_designator_targets WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $row->execute([$tenantId, $mapId, $callSign]);
        return $row->fetch(PDO::FETCH_ASSOC);
    }

    public function addSigint(int $tenantId, int $mapId, string $callSign, float $posX, float $posY, ?float $bearing = null): array
    {
        $this->pdo()->prepare('INSERT INTO atak_sigint_reports (tenant_id, map_id, call_sign, pos_x, pos_y, bearing) VALUES (?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $callSign, $posX, $posY, $bearing]);
        $id = (int) $this->pdo()->lastInsertId();
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_sigint_reports WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSigintZones(int $tenantId, int $mapId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_sigint_reports WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $mapId]);
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
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_intel_photos WHERE tenant_id = ? AND map_id = ? ORDER BY created_at DESC');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addIntelPhoto(int $tenantId, int $mapId, string $filename, string $path, string $author, ?float $posX = null, ?float $posY = null): array
    {
        $this->pdo()->prepare('INSERT INTO atak_intel_photos (tenant_id, map_id, filename, path, author, pos_x, pos_y) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$tenantId, $mapId, $filename, $path, $author, $posX, $posY]);
        $id = (int) $this->pdo()->lastInsertId();
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_intel_photos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function setLastActivity(int $tenantId, int $mapId): void
    {
        $stmt = $this->pdo()->prepare('INSERT INTO atak_last_activity (tenant_id, map_id, last_activity_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_activity_at = NOW()');
        $stmt->execute([$tenantId, $mapId]);
    }

    public function getLastActivity(int $tenantId, int $mapId): ?string
    {
        $stmt = $this->pdo()->prepare('SELECT last_activity_at FROM atak_last_activity WHERE tenant_id = ? AND map_id = ?');
        $stmt->execute([$tenantId, $mapId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['last_activity_at'] : null;
    }

    /** @return list<array{call_sign: string, updated_at: string}> */
    public function getActiveUnitsSummary(int $tenantId, int $mapId, int $limit = 20): array
    {
        $stmt = $this->pdo()->prepare('SELECT call_sign, updated_at FROM atak_units WHERE tenant_id = ? AND map_id = ? AND status = ? ORDER BY updated_at DESC LIMIT ' . (int) $limit);
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
        $stmt = $this->pdo()->prepare(
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
        $occupancy = \App\Services\Tactical\AtakAirAssetMergeService::isOccupancyPayload($data);
        $existing = $this->findAirAssetForUpsert($tenantId, $mapId, $callsign, $data);
        $now = date('Y-m-d H:i:s');
        $pos = $data['pos'] ?? null;
        $incomingSource = $occupancy
            ? \App\Services\Tactical\AtakAirAssetMergeService::SOURCE_OCCUPANCY
            : \App\Services\Tactical\AtakAirAssetMergeService::SOURCE_MANIFEST;
        $keepCallsign = $callsign;
        if ($existing && $occupancy) {
            $prevCs = trim((string) ($existing['callsign'] ?? ''));
            $prevSource = strtolower(trim((string) ($existing['source'] ?? '')));
            if ($prevCs !== '' && ($prevSource === 'manifest' || $prevCs !== $callsign)) {
                $keepCallsign = $prevCs;
            }
        }
        $fields = [
            'mission_id' => $data['mission_id'] ?? $data['missionId'] ?? null,
            'model' => $data['model'] ?? null,
            'aircraft_type' => $data['aircraft_type'] ?? $data['aircraftType'] ?? null,
            'freq' => $data['freq'] ?? $data['radioMain'] ?? $data['radio_main'] ?? null,
            'radio_main' => $data['radio_main'] ?? $data['radioMain'] ?? null,
            'radio_aux' => $data['radio_aux'] ?? $data['radioAux'] ?? null,
            'laser' => array_key_exists('laser', $data) ? $data['laser'] : ($occupancy ? null : '1688'),
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
            'source' => $incomingSource,
            'vehicle_id' => trim((string) ($data['vehicle_id'] ?? $data['vehicleId'] ?? '')),
            'updated_at' => $now,
        ];
        if ($occupancy && is_array($existing)) {
            $prevSource = strtolower(trim((string) ($existing['source'] ?? '')));
            if ($prevSource === 'manifest') {
                $fields['source'] = 'manifest';
            }
            foreach (['freq', 'radio_main', 'radio_aux', 'laser', 'auth', 'auth_code', 'pilot', 'crew', 'ordnance', 'station', 'eta_minutes', 'bingo_fuel', 'checklist', 'mission_id'] as $keep) {
                $incomingEmpty = $fields[$keep] === null || $fields[$keep] === '';
                $prev = $existing[$keep] ?? null;
                if ($incomingEmpty && $prev !== null && $prev !== '') {
                    $fields[$keep] = $prev;
                }
            }
        }
        if ($fields['vehicle_id'] === '' && is_array($existing)) {
            $fields['vehicle_id'] = trim((string) ($existing['vehicle_id'] ?? ''));
        }
        if ($fields['vehicle_id'] === '') {
            $fields['vehicle_id'] = null;
        }
        if ($existing) {
            if (!$occupancy && $keepCallsign !== trim((string) ($existing['callsign'] ?? ''))) {
                $fields['callsign'] = $keepCallsign;
            } elseif ($occupancy) {
                unset($fields['callsign']);
            }
            $set = implode(', ', array_map(fn ($k) => "`$k` = ?", array_keys($fields)));
            $params = array_values($fields);
            $params[] = $existing['id'];
            try {
                $this->pdo()->prepare("UPDATE atak_air_assets SET $set WHERE id = ?")->execute($params);
            } catch (\Throwable) {
                unset($fields['source'], $fields['vehicle_id']);
                $set = implode(', ', array_map(fn ($k) => "`$k` = ?", array_keys($fields)));
                $params = array_values($fields);
                $params[] = $existing['id'];
                $this->pdo()->prepare("UPDATE atak_air_assets SET $set WHERE id = ?")->execute($params);
            }
            $id = (int) $existing['id'];
        } else {
            $cols = array_keys($fields);
            $placeholders = implode(', ', array_merge(['?', '?', '?'], array_fill(0, count($cols), '?')));
            try {
                $this->pdo()->prepare(
                    'INSERT INTO atak_air_assets (tenant_id, map_id, callsign, ' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')'
                )->execute(array_merge([$tenantId, $mapId, $keepCallsign], array_values($fields)));
            } catch (\Throwable) {
                unset($fields['source'], $fields['vehicle_id']);
                $cols = array_keys($fields);
                $placeholders = implode(', ', array_merge(['?', '?', '?'], array_fill(0, count($cols), '?')));
                $this->pdo()->prepare(
                    'INSERT INTO atak_air_assets (tenant_id, map_id, callsign, ' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')'
                )->execute(array_merge([$tenantId, $mapId, $keepCallsign], array_values($fields)));
            }
            $id = (int) $this->pdo()->lastInsertId();
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_air_assets WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function findAirAssetForUpsert(int $tenantId, int $mapId, string $callsign, array $data): ?array
    {
        $vehicleId = trim((string) ($data['vehicle_id'] ?? $data['vehicleId'] ?? ''));
        if ($vehicleId !== '') {
            try {
                $stmt = $this->pdo()->prepare(
                    'SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND vehicle_id = ? ORDER BY updated_at DESC LIMIT 1'
                );
                $stmt->execute([$tenantId, $mapId, $vehicleId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (\Throwable) {
            }
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND callsign = ?');
        $stmt->execute([$tenantId, $mapId, $callsign]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        $pos = $data['pos'] ?? null;
        $x = isset($data['pos_x']) ? (float) $data['pos_x'] : (is_array($pos) && isset($pos[0]) ? (float) $pos[0] : null);
        $y = isset($data['pos_y']) ? (float) $data['pos_y'] : (is_array($pos) && isset($pos[1]) ? (float) $pos[1] : null);
        if ($x === null || $y === null) {
            return null;
        }
        $cutoff = date('Y-m-d H:i:s', time() - 90);
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND updated_at >= ?'
            );
            $stmt->execute([$tenantId, $mapId, $cutoff]);
            $incoming = [
                'callsign' => $callsign,
                'model' => $data['model'] ?? null,
                'aircraft_type' => $data['aircraft_type'] ?? $data['aircraftType'] ?? null,
                'pos_x' => $x,
                'pos_y' => $y,
                'vehicle_id' => $vehicleId,
                'source' => \App\Services\Tactical\AtakAirAssetMergeService::isOccupancyPayload($data)
                    ? \App\Services\Tactical\AtakAirAssetMergeService::SOURCE_OCCUPANCY
                    : \App\Services\Tactical\AtakAirAssetMergeService::SOURCE_MANIFEST,
            ];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cand) {
                if (\App\Services\Tactical\AtakAirAssetMergeService::sameAirframe($cand, $incoming)) {
                    return $cand;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    public function getActiveAirAssets(int $tenantId, int $mapId): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::AIR_ASSET_TTL_SECONDS);
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND updated_at >= ? ORDER BY callsign');
        $stmt->execute([$tenantId, $mapId, $cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['status'] = $r['status'] ?? 'IN-FLIGHT';
        }
        return $rows;
    }

    public function updateAirAssetPilotStatus(int $tenantId, int $mapId, string $callsign, string $pilotStatus): ?array
    {
        $stmt = $this->pdo()->prepare('UPDATE atak_air_assets SET pilot_status = ?, updated_at = NOW() WHERE tenant_id = ? AND map_id = ? AND callsign = ?');
        $stmt->execute([$pilotStatus, $tenantId, $mapId, $callsign]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_air_assets WHERE tenant_id = ? AND map_id = ? AND callsign = ?');
        $stmt->execute([$tenantId, $mapId, $callsign]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLayers(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_layers WHERE tenant_id = ? AND map_id = ? ORDER BY `order`');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
