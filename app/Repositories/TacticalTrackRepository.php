<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakTacticalTracksSchema;
use PDO;

final class TacticalTrackRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        AtakTacticalTracksSchema::ensure();
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsertTrack(int $tenantId, int $mapId, array $data): array
    {
        $uid = trim((string) ($data['track_uid'] ?? $data['track_id'] ?? ''));
        if ($uid === '') {
            $uid = 'TRK-' . strtoupper(bin2hex(random_bytes(3)));
        }
        $now = gmdate('Y-m-d H:i:s');
        $label = trim((string) ($data['label'] ?? ''));
        $type = strtoupper(trim((string) ($data['type'] ?? 'UNKNOWN')));
        $affiliation = strtoupper(trim((string) ($data['affiliation'] ?? 'UNKNOWN')));
        $layer = strtolower(trim((string) ($data['layer'] ?? 'observation')));
        if (!in_array($layer, ['reality', 'observation', 'assessment'], true)) {
            $layer = 'observation';
        }
        $status = strtolower(trim((string) ($data['status'] ?? 'candidate')));
        if (!in_array($status, ['candidate', 'active', 'confirmed', 'stale', 'dismissed'], true)) {
            $status = 'candidate';
        }
        $confidence = isset($data['confidence']) && is_numeric($data['confidence'])
            ? max(0.0, min(1.0, (float) $data['confidence']))
            : 0.5;
        $source = strtolower(trim((string) ($data['source'] ?? 'manual')));
        $posX = isset($data['pos_x']) && is_numeric($data['pos_x']) ? (float) $data['pos_x'] : (isset($data['x']) && is_numeric($data['x']) ? (float) $data['x'] : null);
        $posY = isset($data['pos_y']) && is_numeric($data['pos_y']) ? (float) $data['pos_y'] : (isset($data['y']) && is_numeric($data['y']) ? (float) $data['y'] : null);
        $posZ = isset($data['pos_z']) && is_numeric($data['pos_z']) ? (float) $data['pos_z'] : (isset($data['z']) && is_numeric($data['z']) ? (float) $data['z'] : null);
        $callRef = trim((string) ($data['call_sign_ref'] ?? $data['call_sign'] ?? ''));
        $meta = $data['meta'] ?? $data['meta_json'] ?? [];
        $metaJson = is_string($meta) ? $meta : json_encode(is_array($meta) ? $meta : [], JSON_UNESCAPED_UNICODE);

        $existing = $this->findByUid($tenantId, $mapId, $uid);
        if ($existing !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE tactical_tracks SET label = ?, type = ?, affiliation = ?, layer = ?, status = ?, confidence = ?, source = ?,
                 pos_x = ?, pos_y = ?, pos_z = ?, call_sign_ref = ?, meta_json = ?, updated_at = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $label !== '' ? $label : ($existing['label'] ?? null),
                $type,
                $affiliation,
                $layer,
                $status,
                $confidence,
                $source,
                $posX,
                $posY,
                $posZ,
                $callRef !== '' ? $callRef : ($existing['call_sign_ref'] ?? null),
                $metaJson,
                $now,
                (int) $existing['id'],
            ]);

            return $this->findById((int) $existing['id']) ?? $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tactical_tracks
             (track_uid, tenant_id, map_id, label, type, affiliation, layer, status, confidence, source, pos_x, pos_y, pos_z, call_sign_ref, meta_json, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $uid, $tenantId, $mapId,
            $label !== '' ? $label : $uid,
            $type, $affiliation, $layer, $status, $confidence, $source,
            $posX, $posY, $posZ,
            $callRef !== '' ? $callRef : null,
            $metaJson, $now, $now,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? ['id' => $id, 'track_uid' => $uid];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addObservation(int $tenantId, int $mapId, array $data): array
    {
        $uid = trim((string) ($data['obs_uid'] ?? ''));
        if ($uid === '') {
            $uid = 'OBS-' . strtoupper(bin2hex(random_bytes(4)));
        }
        $now = gmdate('Y-m-d H:i:s');
        $kind = strtolower(trim((string) ($data['kind'] ?? 'obs')));
        $layer = strtolower(trim((string) ($data['layer'] ?? 'observation')));
        $actor = trim((string) ($data['actor'] ?? $data['call_sign'] ?? ''));
        $trackId = isset($data['track_id']) && is_numeric($data['track_id']) ? (int) $data['track_id'] : null;
        $posX = isset($data['pos_x']) && is_numeric($data['pos_x']) ? (float) $data['pos_x'] : (isset($data['x']) && is_numeric($data['x']) ? (float) $data['x'] : null);
        $posY = isset($data['pos_y']) && is_numeric($data['pos_y']) ? (float) $data['pos_y'] : (isset($data['y']) && is_numeric($data['y']) ? (float) $data['y'] : null);
        $bearing = isset($data['bearing']) && is_numeric($data['bearing']) ? (float) $data['bearing'] : null;
        $confidence = isset($data['confidence']) && is_numeric($data['confidence'])
            ? max(0.0, min(1.0, (float) $data['confidence']))
            : 0.5;
        $payload = $data['payload'] ?? $data['payload_json'] ?? $data;
        if (is_array($payload)) {
            unset($payload['payload'], $payload['payload_json']);
        }
        $payloadJson = is_string($payload) ? $payload : json_encode(is_array($payload) ? $payload : [], JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            'INSERT INTO tactical_observations
             (obs_uid, tenant_id, map_id, track_id, kind, layer, actor, pos_x, pos_y, bearing, confidence, payload_json, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        try {
            $stmt->execute([
                $uid, $tenantId, $mapId, $trackId, $kind, $layer,
                $actor !== '' ? $actor : null,
                $posX, $posY, $bearing, $confidence, $payloadJson, $now,
            ]);
        } catch (\Throwable) {
            // Unique conflict: return existing
            $ex = $this->pdo->prepare('SELECT * FROM tactical_observations WHERE tenant_id = ? AND map_id = ? AND obs_uid = ? LIMIT 1');
            $ex->execute([$tenantId, $mapId, $uid]);
            $row = $ex->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $this->normalizeObs($row) : ['obs_uid' => $uid];
        }

        $id = (int) $this->pdo->lastInsertId();
        $row = $this->pdo->prepare('SELECT * FROM tactical_observations WHERE id = ?');
        $row->execute([$id]);
        $fetched = $row->fetch(PDO::FETCH_ASSOC);

        return is_array($fetched) ? $this->normalizeObs($fetched) : ['id' => $id, 'obs_uid' => $uid];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTracks(int $tenantId, int $mapId, int $limit = 100, ?string $layer = null): array
    {
        $limit = max(1, min(300, $limit));
        $sql = 'SELECT * FROM tactical_tracks WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if ($layer !== null && $layer !== '') {
            $sql .= ' AND layer = ?';
            $params[] = strtolower($layer);
        }
        $sql .= ' AND status NOT IN (\'dismissed\') ORDER BY updated_at DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = $this->normalizeTrack($row);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listObservations(int $tenantId, int $mapId, ?string $from = null, ?string $to = null, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $sql = 'SELECT * FROM tactical_observations WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if ($from !== null && $from !== '') {
            $sql .= ' AND created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND created_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY created_at ASC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = $this->normalizeObs($row);
        }

        return $out;
    }

    public function findByUid(int $tenantId, int $mapId, string $uid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tactical_tracks WHERE tenant_id = ? AND map_id = ? AND track_uid = ? LIMIT 1');
        $stmt->execute([$tenantId, $mapId, $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeTrack($row) : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tactical_tracks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeTrack($row) : null;
    }

    public function confirmTrack(
        int $tenantId,
        int $mapId,
        string $trackUid,
        string $status = 'confirmed',
        ?string $affiliation = null,
        ?string $assessment = null
    ): ?array {
        $track = $this->findByUid($tenantId, $mapId, $trackUid);
        if ($track === null) {
            return null;
        }
        $status = strtolower($status);
        if (!in_array($status, ['confirmed', 'active', 'dismissed', 'stale'], true)) {
            $status = 'confirmed';
        }
        $aff = $affiliation !== null ? strtoupper(trim($affiliation)) : (string) ($track['affiliation'] ?? 'UNKNOWN');
        $layer = $status === 'confirmed' ? 'assessment' : (string) ($track['layer'] ?? 'observation');
        if ($status === 'dismissed') {
            $layer = (string) ($track['layer'] ?? 'observation');
        }
        $meta = is_array($track['meta'] ?? null) ? $track['meta'] : [];
        if ($assessment !== null && $assessment !== '') {
            $assessment = strtoupper(trim($assessment));
            if (in_array($assessment, ['DESTROYED', 'DAMAGED', 'UNKNOWN', 'NO_DAMAGE'], true)) {
                $meta['assessment'] = $assessment;
                $meta['requires_confirmation'] = false;
                $meta['confirmed_at'] = gmdate('c');
            }
        }
        if ($status === 'confirmed') {
            $meta['requires_confirmation'] = false;
        }
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'UPDATE tactical_tracks SET status = ?, affiliation = ?, layer = ?, confidence = ?, meta_json = ?, updated_at = ? WHERE id = ?'
        );
        $conf = max((float) ($track['confidence'] ?? 0.5), $status === 'confirmed' ? 0.75 : 0.55);
        $stmt->execute([$status, $aff, $layer, $conf, $metaJson, gmdate('Y-m-d H:i:s'), (int) $track['id']]);

        return $this->findById((int) $track['id']);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeTrack(array $row): array
    {
        $meta = $row['meta_json'] ?? null;
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            $row['meta'] = is_array($decoded) ? $decoded : [];
        } elseif (is_array($meta)) {
            $row['meta'] = $meta;
        } else {
            $row['meta'] = [];
        }
        unset($row['meta_json']);
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['confidence'] = isset($row['confidence']) ? (float) $row['confidence'] : 0.5;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeObs(array $row): array
    {
        $payload = $row['payload_json'] ?? null;
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            $row['payload'] = is_array($decoded) ? $decoded : [];
        } elseif (is_array($payload)) {
            $row['payload'] = $payload;
        } else {
            $row['payload'] = [];
        }
        unset($row['payload_json']);
        $row['id'] = (int) ($row['id'] ?? 0);
        if (isset($row['track_id'])) {
            $row['track_id'] = (int) $row['track_id'];
        }

        return $row;
    }
}
