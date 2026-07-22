<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class FireTeamRepository
{
    public const KIND_EPHEMERAL = 'ephemeral';
    public const KIND_PERMANENT = 'permanent';

    public const ROLE_LEADER = 'leader';
    public const ROLE_MEMBER = 'member';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fire_teams' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{
     *   kind?: string,
     *   map_id?: int|null,
     *   unit_id?: int|null,
     *   mission_key?: string|null,
     *   include_dissolved?: bool,
     *   include_deleted?: bool
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        if ($tenantId < 1 || !$this->tablesReady()) {
            return [];
        }

        $sql = 'SELECT ft.*, u.name AS unit_name
                FROM fire_teams ft
                LEFT JOIN units u ON u.id = ft.unit_id AND u.tenant_id = ft.tenant_id
                WHERE ft.tenant_id = ?';
        $params = [$tenantId];

        if (empty($filters['include_deleted'])) {
            $sql .= ' AND ft.deleted_at IS NULL';
        }
        if (empty($filters['include_dissolved'])) {
            $sql .= ' AND ft.dissolved_at IS NULL';
        }

        $kind = isset($filters['kind']) ? trim((string) $filters['kind']) : '';
        if ($kind === self::KIND_EPHEMERAL || $kind === self::KIND_PERMANENT) {
            $sql .= ' AND ft.kind = ?';
            $params[] = $kind;
        }

        if (array_key_exists('map_id', $filters) && $filters['map_id'] !== null && (int) $filters['map_id'] > 0) {
            $sql .= ' AND ft.map_id = ?';
            $params[] = (int) $filters['map_id'];
        }

        if (array_key_exists('unit_id', $filters) && $filters['unit_id'] !== null && (int) $filters['unit_id'] > 0) {
            $sql .= ' AND ft.unit_id = ?';
            $params[] = (int) $filters['unit_id'];
        }

        $missionKey = isset($filters['mission_key']) ? trim((string) $filters['mission_key']) : '';
        if ($missionKey !== '') {
            $sql .= ' AND ft.mission_key = ?';
            $params[] = $missionKey;
        }

        $sql .= ' ORDER BY ft.kind ASC, ft.label ASC, ft.id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $membersByTeam = $this->membersForTeams($ids);

        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $row['members'] = $membersByTeam[$id] ?? [];
            $row['member_count'] = count($row['members']);
            $row['is_active'] = empty($row['dissolved_at']) && empty($row['deleted_at']);
        }
        unset($row);

        return $rows;
    }

    public function findByIdForTenant(int $id, int $tenantId, bool $includeDeleted = false): ?array
    {
        if ($id < 1 || $tenantId < 1 || !$this->tablesReady()) {
            return null;
        }

        $sql = 'SELECT ft.*, u.name AS unit_name
                FROM fire_teams ft
                LEFT JOIN units u ON u.id = ft.unit_id AND u.tenant_id = ft.tenant_id
                WHERE ft.id = ? AND ft.tenant_id = ?';
        $params = [$id, $tenantId];
        if (!$includeDeleted) {
            $sql .= ' AND ft.deleted_at IS NULL';
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['members'] = $this->membersForTeams([(int) $row['id']])[(int) $row['id']] ?? [];
        $row['member_count'] = count($row['members']);
        $row['is_active'] = empty($row['dissolved_at']) && empty($row['deleted_at']);

        return $row;
    }

    /**
     * @param array{
     *   kind: string,
     *   label: string,
     *   color?: string,
     *   map_id?: int|null,
     *   mission_key?: string|null,
     *   unit_id?: int|null,
     *   notes?: string|null,
     *   created_by_user_id?: int|null
     * } $data
     */
    public function create(int $tenantId, array $data): ?array
    {
        if ($tenantId < 1 || !$this->tablesReady()) {
            return null;
        }

        $kind = ($data['kind'] ?? '') === self::KIND_PERMANENT ? self::KIND_PERMANENT : self::KIND_EPHEMERAL;
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $color = $this->normalizeColor((string) ($data['color'] ?? '#2563eb'));
        $mapId = isset($data['map_id']) && (int) $data['map_id'] > 0 ? (int) $data['map_id'] : null;
        $unitId = isset($data['unit_id']) && (int) $data['unit_id'] > 0 ? (int) $data['unit_id'] : null;
        $missionKey = isset($data['mission_key']) ? trim((string) $data['mission_key']) : '';
        $missionKey = $missionKey !== '' ? mb_substr($missionKey, 0, 64) : null;
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';
        $notes = $notes !== '' ? mb_substr($notes, 0, 500) : null;
        $createdBy = isset($data['created_by_user_id']) && (int) $data['created_by_user_id'] > 0
            ? (int) $data['created_by_user_id']
            : null;

        if ($kind === self::KIND_EPHEMERAL) {
            if ($mapId === null) {
                $mapId = 1;
            }
            $unitId = null;
        } else {
            $mapId = null;
            $missionKey = null;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO fire_teams
                (tenant_id, kind, label, color, map_id, mission_key, unit_id, notes, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $kind,
            mb_substr($label, 0, 120),
            $color,
            $mapId,
            $missionKey,
            $unitId,
            $notes,
            $createdBy,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findByIdForTenant($id, $tenantId);
    }

    /**
     * @param array{
     *   label?: string,
     *   color?: string,
     *   map_id?: int|null,
     *   mission_key?: string|null,
     *   unit_id?: int|null,
     *   notes?: string|null
     * } $data
     */
    public function update(int $id, int $tenantId, array $data): ?array
    {
        $existing = $this->findByIdForTenant($id, $tenantId);
        if (!$existing || !empty($existing['dissolved_at'])) {
            return null;
        }

        $label = array_key_exists('label', $data)
            ? trim((string) $data['label'])
            : (string) ($existing['label'] ?? '');
        if ($label === '') {
            return null;
        }

        $color = array_key_exists('color', $data)
            ? $this->normalizeColor((string) $data['color'])
            : $this->normalizeColor((string) ($existing['color'] ?? '#2563eb'));

        $notes = array_key_exists('notes', $data)
            ? trim((string) $data['notes'])
            : (string) ($existing['notes'] ?? '');
        $notes = $notes !== '' ? mb_substr($notes, 0, 500) : null;

        $kind = (string) ($existing['kind'] ?? self::KIND_EPHEMERAL);
        $mapId = isset($existing['map_id']) ? (int) $existing['map_id'] : null;
        $unitId = isset($existing['unit_id']) ? (int) $existing['unit_id'] : null;
        $missionKey = isset($existing['mission_key']) ? (string) $existing['mission_key'] : null;

        if ($kind === self::KIND_EPHEMERAL) {
            if (array_key_exists('map_id', $data) && (int) $data['map_id'] > 0) {
                $mapId = (int) $data['map_id'];
            }
            if (array_key_exists('mission_key', $data)) {
                $mk = trim((string) $data['mission_key']);
                $missionKey = $mk !== '' ? mb_substr($mk, 0, 64) : null;
            }
            $unitId = null;
        } else {
            if (array_key_exists('unit_id', $data)) {
                $unitId = (int) $data['unit_id'] > 0 ? (int) $data['unit_id'] : null;
            }
            $mapId = null;
            $missionKey = null;
        }

        $this->pdo->prepare(
            'UPDATE fire_teams
             SET label = ?, color = ?, map_id = ?, mission_key = ?, unit_id = ?, notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL'
        )->execute([
            mb_substr($label, 0, 120),
            $color,
            $mapId,
            $missionKey,
            $unitId,
            $notes,
            $id,
            $tenantId,
        ]);

        return $this->findByIdForTenant($id, $tenantId);
    }

    public function dissolve(int $id, int $tenantId): bool
    {
        if ($id < 1 || $tenantId < 1 || !$this->tablesReady()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE fire_teams
             SET dissolved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL AND dissolved_at IS NULL'
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** Soft-delete (retire aussi de la liste active). */
    public function softDelete(int $id, int $tenantId): bool
    {
        if ($id < 1 || $tenantId < 1 || !$this->tablesReady()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE fire_teams
             SET deleted_at = NOW(), dissolved_at = COALESCE(dissolved_at, NOW()), updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array{user_id?: int|null, callsign?: string|null, role?: string, display_order?: int} $data
     * @return array<string, mixed>|null
     */
    public function addMember(int $fireTeamId, int $tenantId, array $data): ?array
    {
        $team = $this->findByIdForTenant($fireTeamId, $tenantId);
        if (!$team || !empty($team['dissolved_at'])) {
            return null;
        }

        $userId = isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null;
        $callsign = isset($data['callsign']) ? trim((string) $data['callsign']) : '';
        $callsign = $callsign !== '' ? mb_substr($callsign, 0, 64) : null;
        $role = ($data['role'] ?? '') === self::ROLE_LEADER ? self::ROLE_LEADER : self::ROLE_MEMBER;
        $order = isset($data['display_order']) ? (int) $data['display_order'] : 0;

        if ($userId === null && $callsign === null) {
            return null;
        }

        if ($userId !== null) {
            $chk = $this->pdo->prepare('SELECT id, callsign FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
            $chk->execute([$userId, $tenantId]);
            $user = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return null;
            }
            if ($callsign === null) {
                $cs = trim((string) ($user['callsign'] ?? ''));
                $callsign = $cs !== '' ? mb_substr($cs, 0, 64) : null;
            }
        }

        if ($role === self::ROLE_LEADER) {
            $this->pdo->prepare(
                "UPDATE fire_team_members SET role = 'member' WHERE fire_team_id = ? AND role = 'leader'"
            )->execute([$fireTeamId]);
        }

        // Remplace l’affectation existante du même user sur cette équipe.
        if ($userId !== null) {
            $this->pdo->prepare('DELETE FROM fire_team_members WHERE fire_team_id = ? AND user_id = ?')
                ->execute([$fireTeamId, $userId]);
        }

        $this->pdo->prepare(
            'INSERT INTO fire_team_members (fire_team_id, user_id, callsign, role, display_order)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$fireTeamId, $userId, $callsign, $role, $order]);

        $memberId = (int) $this->pdo->lastInsertId();
        $members = $this->membersForTeams([$fireTeamId])[$fireTeamId] ?? [];
        foreach ($members as $m) {
            if ((int) ($m['id'] ?? 0) === $memberId) {
                return $m;
            }
        }

        return ['id' => $memberId, 'fire_team_id' => $fireTeamId, 'user_id' => $userId, 'callsign' => $callsign, 'role' => $role];
    }

    public function updateMember(int $fireTeamId, int $memberId, int $tenantId, array $data): ?array
    {
        $team = $this->findByIdForTenant($fireTeamId, $tenantId);
        if (!$team || !empty($team['dissolved_at'])) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM fire_team_members WHERE id = ? AND fire_team_id = ? LIMIT 1'
        );
        $stmt->execute([$memberId, $fireTeamId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            return null;
        }

        $role = array_key_exists('role', $data)
            ? (($data['role'] ?? '') === self::ROLE_LEADER ? self::ROLE_LEADER : self::ROLE_MEMBER)
            : (string) ($existing['role'] ?? self::ROLE_MEMBER);

        $callsign = array_key_exists('callsign', $data)
            ? trim((string) $data['callsign'])
            : (string) ($existing['callsign'] ?? '');
        $callsign = $callsign !== '' ? mb_substr($callsign, 0, 64) : null;

        $order = array_key_exists('display_order', $data)
            ? (int) $data['display_order']
            : (int) ($existing['display_order'] ?? 0);

        if ($role === self::ROLE_LEADER) {
            $this->pdo->prepare(
                "UPDATE fire_team_members SET role = 'member' WHERE fire_team_id = ? AND role = 'leader' AND id <> ?"
            )->execute([$fireTeamId, $memberId]);
        }

        $this->pdo->prepare(
            'UPDATE fire_team_members SET callsign = ?, role = ?, display_order = ? WHERE id = ? AND fire_team_id = ?'
        )->execute([$callsign, $role, $order, $memberId, $fireTeamId]);

        $members = $this->membersForTeams([$fireTeamId])[$fireTeamId] ?? [];
        foreach ($members as $m) {
            if ((int) ($m['id'] ?? 0) === $memberId) {
                return $m;
            }
        }

        return null;
    }

    public function removeMember(int $fireTeamId, int $memberId, int $tenantId): bool
    {
        $team = $this->findByIdForTenant($fireTeamId, $tenantId);
        if (!$team) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM fire_team_members WHERE id = ? AND fire_team_id = ?'
        );
        $stmt->execute([$memberId, $fireTeamId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Remplace toute la composition (utilisé par le formulaire web).
     *
     * @param list<array{user_id: int, role?: string}> $members
     */
    public function replaceMembers(int $fireTeamId, int $tenantId, array $members): bool
    {
        $team = $this->findByIdForTenant($fireTeamId, $tenantId);
        if (!$team || !empty($team['dissolved_at'])) {
            return false;
        }

        $this->pdo->prepare('DELETE FROM fire_team_members WHERE fire_team_id = ?')->execute([$fireTeamId]);

        $order = 0;
        $leaderSet = false;
        foreach ($members as $m) {
            $userId = (int) ($m['user_id'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            $role = ($m['role'] ?? '') === self::ROLE_LEADER ? self::ROLE_LEADER : self::ROLE_MEMBER;
            if ($role === self::ROLE_LEADER) {
                if ($leaderSet) {
                    $role = self::ROLE_MEMBER;
                } else {
                    $leaderSet = true;
                }
            }
            $this->addMember($fireTeamId, $tenantId, [
                'user_id' => $userId,
                'role' => $role,
                'display_order' => $order++,
            ]);
        }

        return true;
    }

    /**
     * @param list<int> $teamIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function membersForTeams(array $teamIds): array
    {
        $teamIds = array_values(array_filter(array_map('intval', $teamIds), static fn (int $id): bool => $id > 0));
        if ($teamIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $sql = "SELECT m.*, u.display_name, u.callsign AS user_callsign, u.avatar_url
                FROM fire_team_members m
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.fire_team_id IN ($placeholders)
                ORDER BY CASE WHEN m.role = 'leader' THEN 0 ELSE 1 END, m.display_order ASC, m.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($teamIds);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['fire_team_id'] ?? 0);
            if ($tid < 1) {
                continue;
            }
            $cs = trim((string) ($row['callsign'] ?? ''));
            if ($cs === '') {
                $cs = trim((string) ($row['user_callsign'] ?? ''));
            }
            $row['effective_callsign'] = $cs;
            $out[$tid][] = $row;
        }

        return $out;
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1) {
            return strtoupper($color);
        }
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1) {
            return '#' . strtoupper($color);
        }

        return '#2563EB';
    }
}
