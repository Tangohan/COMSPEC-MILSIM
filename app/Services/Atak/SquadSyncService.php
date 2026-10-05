<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\UserRepository;
use App\Support\LazyDatabaseConnection;
use App\Support\SteamId;
use PDO;

/**
 * Escouades et équipes de feu envoyées par les téléphones ATAK natifs (COMSPEC Link « Squad.Sync »,
 * POST /api/atak/squads/sync). Un téléphone par groupe Arma envoie la photo complète de son escouade :
 * l'escouade est rangée dans atak_squads, ses équipes dans fire_teams (équipes de mission, clé game_key)
 * avec leurs membres et rôles ; une équipe absente de la photo est dissoute.
 */
final class SquadSyncService
{
    use LazyDatabaseConnection;

    private const HEX = '/^#[0-9A-Fa-f]{6}$/';

    public function __construct(?PDO $pdo = null, private ?UserRepository $users = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND ((TABLE_NAME = 'atak_squads' AND COLUMN_NAME = 'game_key') OR (TABLE_NAME = 'fire_teams' AND COLUMN_NAME = 'atak_squad_id')
                      OR (TABLE_NAME = 'fire_team_members' AND COLUMN_NAME = 'role_label'))"
            );

            return $st !== false && (int) $st->fetchColumn() === 3;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Contrôle et nettoie la photo envoyée par le jeu. Null si elle est inexploitable.
     *
     * @param array<string, mixed> $body
     * @return array{mission_key: string, squad: array<string, mixed>, teams: list<array<string, mixed>>, unassigned: list<array<string, mixed>>}|null
     */
    public static function normalizePayload(array $body): ?array
    {
        $missionKey = self::str($body['mission_key'] ?? '', 190);
        $squad = is_array($body['squad'] ?? null) ? $body['squad'] : [];
        $key = self::str($squad['key'] ?? '', 80);
        $name = self::str($squad['name'] ?? '', 120);
        if ($missionKey === '' || $key === '' || $name === '') {
            return null;
        }
        $member = static function (mixed $m): ?array {
            if (!is_array($m)) {
                return null;
            }
            $callsign = self::str($m['callsign'] ?? '', 64);
            $label = self::str($m['name'] ?? '', 120);
            if ($callsign === '' && $label === '') {
                return null;
            }

            return [
                'callsign' => $callsign !== '' ? $callsign : $label,
                'name' => $label,
                'uid' => SteamId::normalize((string) ($m['uid'] ?? '')),
                'role' => strtoupper(self::str($m['role'] ?? '', 16)),
                'role_label' => self::str($m['role_label'] ?? '', 64),
                'leader' => !empty($m['leader']),
                'player' => !empty($m['player']),
                'alive' => !array_key_exists('alive', $m) || !empty($m['alive']),
                'online' => !empty($m['online']),
            ];
        };
        $teams = [];
        foreach (array_slice(is_array($body['teams'] ?? null) ? $body['teams'] : [], 0, 12) as $t) {
            if (!is_array($t)) {
                continue;
            }
            $tKey = self::str($t['key'] ?? '', 24);
            $tName = self::str($t['name'] ?? '', 120);
            if ($tKey === '' || $tName === '') {
                continue;
            }
            $color = (string) ($t['color'] ?? '');
            $members = array_values(array_filter(array_map($member, array_slice(is_array($t['members'] ?? null) ? $t['members'] : [], 0, 40))));
            $teams[] = [
                'key' => $tKey,
                'name' => $tName,
                'color' => preg_match(self::HEX, $color) ? strtoupper($color) : '#64748B',
                'icon' => strtoupper(self::str($t['icon'] ?? '', 16)),
                'description' => self::str($t['description'] ?? '', 500),
                'locked' => !empty($t['locked']),
                'members' => $members,
            ];
        }
        $unassigned = array_values(array_filter(array_map($member, array_slice(is_array($body['unassigned'] ?? null) ? $body['unassigned'] : [], 0, 60))));

        return [
            'mission_key' => $missionKey,
            'squad' => [
                'key' => $key,
                'name' => $name,
                'side' => strtoupper(self::str($squad['side'] ?? '', 16)),
                'type' => strtoupper(self::str($squad['type'] ?? '', 16)),
                'type_label' => self::str($squad['type_label'] ?? '', 64),
                'leader' => self::str($squad['leader'] ?? '', 64),
                'members' => max(0, min(200, (int) ($squad['members'] ?? 0))),
                'locked' => !empty($squad['locked']),
            ],
            'teams' => $teams,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * Range la photo d'une escouade. Renvoie le bilan (id de l'escouade, équipes créées, mises à jour, dissoutes).
     *
     * @param array{mission_key: string, squad: array<string, mixed>, teams: list<array<string, mixed>>, unassigned: list<array<string, mixed>>} $snap
     * @return array{squad_id: int, teams: int, created: int, updated: int, dissolved: int}
     */
    public function sync(int $tenantId, int $mapId, array $snap, ?int $reporterUserId = null): array
    {
        $pdo = $this->pdo();
        $hash = sha1($snap['mission_key']);
        $sq = $snap['squad'];
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'INSERT INTO atak_squads (tenant_id, map_id, mission_key, mission_hash, game_key, name, squad_type, squad_type_label, side,
                    leader_callsign, member_count, team_count, locked, unassigned_json, reported_by_user_id, synced_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), map_id = VALUES(map_id), name = VALUES(name), squad_type = VALUES(squad_type),
                    squad_type_label = VALUES(squad_type_label), side = VALUES(side), leader_callsign = VALUES(leader_callsign),
                    member_count = VALUES(member_count), team_count = VALUES(team_count), locked = VALUES(locked),
                    unassigned_json = VALUES(unassigned_json), reported_by_user_id = VALUES(reported_by_user_id), synced_at = NOW()'
            );
            $st->execute([
                $tenantId, $mapId > 0 ? $mapId : null, $snap['mission_key'], $hash, $sq['key'], $sq['name'],
                $sq['type'] !== '' ? $sq['type'] : null, $sq['type_label'] !== '' ? $sq['type_label'] : null, $sq['side'] !== '' ? $sq['side'] : null,
                $sq['leader'] !== '' ? $sq['leader'] : null, $sq['members'], count($snap['teams']), $sq['locked'] ? 1 : 0,
                json_encode($snap['unassigned'], JSON_UNESCAPED_UNICODE), $reporterUserId,
            ]);
            $squadId = (int) $pdo->lastInsertId();

            $existing = [];
            $q = $pdo->prepare('SELECT id, game_key FROM fire_teams WHERE tenant_id = ? AND atak_squad_id = ? AND deleted_at IS NULL');
            $q->execute([$tenantId, $squadId]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[(string) $row['game_key']] = (int) $row['id'];
            }

            $created = 0;
            $updated = 0;
            $seen = [];
            $missionShort = mb_substr($snap['mission_key'], 0, 64);
            foreach ($snap['teams'] as $i => $t) {
                $gameKey = substr($hash, 0, 16) . ':' . mb_substr($sq['key'], 0, 40) . ':' . $t['key'];
                $notes = $t['description'] !== '' ? $t['description'] : null;
                if (isset($existing[$gameKey])) {
                    $teamId = $existing[$gameKey];
                    $pdo->prepare(
                        'UPDATE fire_teams SET label = ?, color = ?, icon = ?, side = ?, notes = ?, map_id = ?, dissolved_at = NULL WHERE id = ? AND tenant_id = ?'
                    )->execute([$t['name'], $t['color'], $t['icon'] ?: null, $sq['side'] ?: null, $notes, $mapId > 0 ? $mapId : 1, $teamId, $tenantId]);
                    $updated++;
                } else {
                    $pdo->prepare(
                        "INSERT INTO fire_teams (tenant_id, kind, label, color, icon, side, map_id, mission_key, game_key, atak_squad_id, notes, created_by_user_id)
                         VALUES (?, 'ephemeral', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([$tenantId, $t['name'], $t['color'], $t['icon'] ?: null, $sq['side'] ?: null, $mapId > 0 ? $mapId : 1, $missionShort, $gameKey, $squadId, $notes, $reporterUserId]);
                    $teamId = (int) $pdo->lastInsertId();
                    $created++;
                }
                $seen[$gameKey] = true;
                $this->replaceMembers($tenantId, $teamId, $t['members']);
            }

            $dissolved = 0;
            foreach ($existing as $gameKey => $teamId) {
                if (!isset($seen[$gameKey])) {
                    $up = $pdo->prepare('UPDATE fire_teams SET dissolved_at = NOW() WHERE id = ? AND tenant_id = ? AND dissolved_at IS NULL');
                    $up->execute([$teamId, $tenantId]);
                    $dissolved += $up->rowCount();
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['squad_id' => $squadId, 'teams' => count($snap['teams']), 'created' => $created, 'updated' => $updated, 'dissolved' => $dissolved];
    }

    /**
     * Escouades reçues ces derniers jours, les plus récentes d'abord, avec leurs équipes et membres.
     *
     * @return list<array<string, mixed>>
     */
    public function recentSquads(int $tenantId, int $days = 14, int $limit = 120): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_squads WHERE tenant_id = ? AND synced_at >= (NOW() - INTERVAL ? DAY) ORDER BY synced_at DESC LIMIT ' . max(1, min(500, $limit))
        );
        $st->execute([$tenantId, max(1, $days)]);
        $squads = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($squads === []) {
            return [];
        }
        $ids = array_map(static fn (array $s): int => (int) $s['id'], $squads);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $tq = $this->pdo()->prepare(
            "SELECT id, atak_squad_id, label, color, icon, notes, dissolved_at FROM fire_teams
             WHERE tenant_id = ? AND deleted_at IS NULL AND atak_squad_id IN ({$in}) ORDER BY dissolved_at IS NOT NULL, id"
        );
        $tq->execute(array_merge([$tenantId], $ids));
        $teams = $tq->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $members = [];
        if ($teams !== []) {
            $tids = array_map(static fn (array $t): int => (int) $t['id'], $teams);
            $min = implode(',', array_fill(0, count($tids), '?'));
            $mq = $this->pdo()->prepare(
                "SELECT m.fire_team_id, m.callsign, m.role, m.role_key, m.role_label, m.user_id, u.display_name
                 FROM fire_team_members m LEFT JOIN users u ON u.id = m.user_id
                 WHERE m.fire_team_id IN ({$min}) ORDER BY m.role = 'leader' DESC, m.display_order, m.id"
            );
            $mq->execute($tids);
            foreach ($mq->fetchAll(PDO::FETCH_ASSOC) ?: [] as $m) {
                $members[(int) $m['fire_team_id']][] = $m;
            }
        }
        $bySquad = [];
        foreach ($teams as $t) {
            $t['members'] = $members[(int) $t['id']] ?? [];
            $bySquad[(int) $t['atak_squad_id']][] = $t;
        }
        foreach ($squads as &$s) {
            $s['teams'] = $bySquad[(int) $s['id']] ?? [];
            $decoded = json_decode((string) ($s['unassigned_json'] ?? ''), true);
            $s['unassigned'] = is_array($decoded) ? $decoded : [];
        }
        unset($s);

        return $squads;
    }

    /**
     * @param list<array<string, mixed>> $members
     */
    private function replaceMembers(int $tenantId, int $teamId, array $members): void
    {
        $pdo = $this->pdo();
        $pdo->prepare('DELETE FROM fire_team_members WHERE fire_team_id = ?')->execute([$teamId]);
        $ins = $pdo->prepare(
            'INSERT INTO fire_team_members (fire_team_id, user_id, callsign, steam_id, role, role_key, role_label, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $seenUsers = [];
        foreach ($members as $order => $m) {
            $userId = null;
            if (($m['uid'] ?? null) !== null && $this->users !== null) {
                $u = $this->users->findBySteamIdForTenant($tenantId, (string) $m['uid']);
                $userId = $u !== null ? (int) $u['id'] : null;
            }
            // Contrainte unique (équipe, membre) : un compte lié une seule fois.
            if ($userId !== null) {
                if (isset($seenUsers[$userId])) {
                    continue;
                }
                $seenUsers[$userId] = true;
            }
            $ins->execute([
                $teamId, $userId, $m['callsign'], $m['uid'] ?? null,
                $m['leader'] || $m['role'] === 'CDE' ? 'leader' : 'member',
                $m['role'] !== '' ? $m['role'] : null, $m['role_label'] !== '' ? $m['role_label'] : null, $order,
            ]);
        }
    }

    private static function str(mixed $v, int $max): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $v) ?? '');

        return mb_substr($s, 0, $max);
    }
}
