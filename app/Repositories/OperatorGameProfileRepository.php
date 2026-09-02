<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\SteamId;
use PDO;

final class OperatorGameProfileRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo; }

    /** @return array<string, mixed>|null */
    public function referenceForSteam(int $tenantId, string $steamId): ?array
    {
        $sid = SteamId::normalize($steamId);
        if ($sid === null) {
            return null;
        }
        $userId = $this->findLinkedUserId($tenantId, $sid);
        if ($userId === null) {
            return null;
        }

        $sql = 'SELECT u.id AS user_id, u.steam_id,
                       COALESCE(NULLIF(TRIM(ucp.display_name), \'\'), u.display_name) AS display_name,
                       COALESCE(NULLIF(TRIM(ucp.callsign), \'\'), u.callsign) AS callsign,
                       pp.id AS personnel_id, pp.blood_type, pp.sex, NULL AS face_class
                FROM users u
                LEFT JOIN user_community_profiles ucp ON ucp.user_id = u.id AND ucp.tenant_id = u.tenant_id
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id AND pp.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.id = ?
                LIMIT 1';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$tenantId, $userId]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Persist an observation even when no Athena account is linked (user_id NULL).
     *
     * @param array<string, mixed> $reference
     * @param array<string, mixed> $payload
     * @return array{id:int, first_seen:bool, changed:bool}
     */
    public function upsertProfile(int $tenantId, array $reference, string $steamId, array $payload): array
    {
        $identity = is_array($payload['identity'] ?? null) ? $payload['identity'] : [];
        $equipment = is_array($payload['equipment'] ?? null) ? $payload['equipment'] : [];
        $versions = is_array($payload['versions'] ?? null) ? $payload['versions'] : [];
        $medical = is_array($payload['medical'] ?? null) ? $payload['medical'] : [];
        $json = static fn (mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $st = $this->pdo()->prepare('SELECT id, observation_hash FROM operator_game_profiles WHERE tenant_id=? AND steam_id=? LIMIT 1');
        $st->execute([$tenantId, $steamId]);
        $old = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $hash = hash('sha256', $json([$identity, $equipment, $versions, $medical, $payload['mission_id'] ?? null]));
        $userId = $this->positiveInt($reference['user_id'] ?? null);
        $personnelId = $this->positiveInt($reference['personnel_id'] ?? null);
        $values = [
            $userId, $personnelId,
            $identity['player_uid'] ?? $identity['arma_player_uid'] ?? $steamId,
            $identity['player_name'] ?? $identity['arma_player_name'] ?? null,
            $identity['callsign'] ?? null,
            $identity['display_name'] ?? null,
            $identity['sex'] ?? $identity['sex_detected'] ?? null,
            $medical['blood_type'] ?? null,
            $identity['face_class'] ?? null, $identity['face_texture'] ?? null, $identity['role'] ?? null,
            $identity['group_name'] ?? null, $identity['faction'] ?? null, $identity['side'] ?? null,
            $json($payload['loadout'] ?? $equipment['loadout'] ?? []), $json($equipment), $json($medical), $json($versions),
            $versions['overwatch'] ?? null, $versions['atak'] ?? null, $versions['arma'] ?? null,
            $payload['server_name'] ?? null, $payload['mission_name'] ?? null, $payload['mission_id'] ?? null,
            $payload['world_name'] ?? null, $json($payload), $hash, $tenantId, $steamId,
        ];
        $sql = 'INSERT INTO operator_game_profiles
            (user_id,personnel_id,arma_player_uid,arma_player_name,callsign,display_name,sex_detected,blood_type_detected,
             face_class,face_texture,role,group_name,faction,side,loadout_json,equipment_json,medical_json,versions_json,
             overwatch_version,atak_version,arma_version,server_name,mission_name,mission_id,world_name,raw_payload_json,
             observation_hash,tenant_id,steam_id,first_seen_at,last_seen_at,last_sync_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),personnel_id=VALUES(personnel_id),arma_player_uid=VALUES(arma_player_uid),
             arma_player_name=VALUES(arma_player_name),callsign=VALUES(callsign),display_name=VALUES(display_name),sex_detected=VALUES(sex_detected),
             blood_type_detected=VALUES(blood_type_detected),face_class=VALUES(face_class),face_texture=VALUES(face_texture),role=VALUES(role),
             group_name=VALUES(group_name),faction=VALUES(faction),side=VALUES(side),loadout_json=VALUES(loadout_json),
             equipment_json=VALUES(equipment_json),medical_json=VALUES(medical_json),versions_json=VALUES(versions_json),
             overwatch_version=VALUES(overwatch_version),atak_version=VALUES(atak_version),arma_version=VALUES(arma_version),
             server_name=VALUES(server_name),mission_name=VALUES(mission_name),mission_id=VALUES(mission_id),world_name=VALUES(world_name),
             raw_payload_json=VALUES(raw_payload_json),observation_hash=VALUES(observation_hash),last_seen_at=NOW(),last_sync_at=NOW(),id=LAST_INSERT_ID(id)';
        $this->pdo()->prepare($sql)->execute($values);
        return ['id' => (int) $this->pdo()->lastInsertId(), 'first_seen' => $old === null, 'changed' => $old === null || $old['observation_hash'] !== $hash];
    }

    public function snapshot(int $tenantId, int $profileId, string $reason, array $payload): int
    {
        $json = static fn (mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $st = $this->pdo()->prepare('INSERT INTO operator_game_snapshots (tenant_id,operator_game_profile_id,reason,server_name,mission_name,identity_json,equipment_json,medical_json,versions_json,raw_payload_json,observed_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
        $st->execute([$tenantId,$profileId,$reason,$payload['server_name']??null,$payload['mission_name']??null,$json($payload['identity']??[]),$json($payload['equipment']??[]),$json($payload['medical']??[]),$json($payload['versions']??[]),$json($payload)]);
        return (int) $this->pdo()->lastInsertId();
    }

    public function recordDiscrepancy(int $tenantId, ?int $userId, int $profileId, ?int $snapshotId, array $d): void
    {
        $fingerprint = hash('sha256', implode('|', [$tenantId, $profileId, $d['field'], $d['ne'], $d['no']]));
        $sql = "INSERT INTO operator_game_discrepancies (tenant_id,user_id,operator_game_profile_id,snapshot_id,field_key,category,expected_value,observed_value,normalized_expected,normalized_observed,severity,status,fingerprint,detected_at,last_detected_at,occurrence_count)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),1)
                ON DUPLICATE KEY UPDATE snapshot_id=VALUES(snapshot_id),last_detected_at=NOW(),
                 occurrence_count=IF(status='OPEN', occurrence_count+1, 1),
                 severity=VALUES(severity), status='OPEN', resolved_at=NULL, resolved_by=NULL, resolution_type=NULL";
        $this->pdo()->prepare($sql)->execute([
            $tenantId,
            $this->positiveInt($userId),
            $profileId,
            $snapshotId,
            $d['field'],
            $d['category'],
            $d['expected'],
            $d['actual'],
            $d['ne'],
            $d['no'],
            $d['severity'],
            'OPEN',
            $fingerprint,
        ]);
    }

    public function event(int $tenantId, ?int $profileId, string $steamId, string $type, array $metadata = []): void
    {
        $st = $this->pdo()->prepare('INSERT INTO operator_game_events (tenant_id,operator_game_profile_id,steam_id,event_type,metadata_json,occurred_at) VALUES (?,?,?,?,?,NOW())');
        $st->execute([$tenantId,$profileId,$steamId,$type,json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}']);
    }

    /** @return array<string, array{minimum:?string,recommended:?string}> */
    public function versionPolicies(int $tenantId): array
    {
        $st = $this->pdo()->prepare('SELECT component,minimum_version,recommended_version FROM operator_mod_versions WHERE tenant_id=?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['component']] = ['minimum' => $row['minimum_version'], 'recommended' => $row['recommended_version']];
        }
        return $out;
    }

    private function findLinkedUserId(int $tenantId, string $sid): ?int
    {
        $st = $this->pdo()->prepare('SELECT id FROM users WHERE tenant_id = ? AND steam_id = ? LIMIT 1');
        $st->execute([$tenantId, $sid]);
        $id = $st->fetchColumn();
        if ($id !== false && (int) $id > 0) {
            return (int) $id;
        }

        $st = $this->pdo()->prepare(
            "SELECT id, steam_id FROM users
             WHERE tenant_id = ? AND steam_id IS NOT NULL AND TRIM(steam_id) <> ''
             ORDER BY id DESC
             LIMIT 80"
        );
        $st->execute([$tenantId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $normalized = SteamId::normalize((string) ($row['steam_id'] ?? ''));
            if ($normalized === $sid) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }
}
