<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RoleplayGameSessionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roleplay_game_sessions' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM roleplay_game_sessions WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByUid(int $tenantId, string $uid): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM roleplay_game_sessions WHERE tenant_id = ? AND session_uid = ? LIMIT 1');
        $st->execute([$tenantId, $uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, int $limit = 80): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM roleplay_game_sessions WHERE tenant_id = ? ORDER BY COALESCE(started_at, created_at) DESC LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO roleplay_game_sessions
                (tenant_id, session_uid, server_name, mission_name, session_kind, hour_category, community_event_id,
                 is_official, attendance_enabled, status, planned_starts_at, planned_ends_at, started_at, ended_at,
                 min_minutes, min_percent, late_tolerance_minutes, created_by_user_id, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            (string) ($data['session_uid'] ?? ('sess-' . bin2hex(random_bytes(8)))),
            $data['server_name'] ?? null,
            $data['mission_name'] ?? null,
            $data['session_kind'] ?? 'libre',
            $data['hour_category'] ?? 'Libre',
            $data['community_event_id'] ?? null,
            !empty($data['is_official']) ? 1 : 0,
            !empty($data['attendance_enabled']) ? 1 : 0,
            $data['status'] ?? 'discovered',
            $data['planned_starts_at'] ?? null,
            $data['planned_ends_at'] ?? null,
            $data['started_at'] ?? null,
            $data['ended_at'] ?? null,
            (int) ($data['min_minutes'] ?? 45),
            (int) ($data['min_percent'] ?? 50),
            (int) ($data['late_tolerance_minutes'] ?? 15),
            $data['created_by_user_id'] ?? null,
            $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): void
    {
        $allowed = [
            'server_name', 'mission_name', 'session_kind', 'hour_category', 'community_event_id',
            'is_official', 'attendance_enabled', 'status', 'planned_starts_at', 'planned_ends_at',
            'started_at', 'ended_at', 'min_minutes', 'min_percent', 'late_tolerance_minutes', 'notes',
        ];
        $set = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $set[] = '`' . $key . '` = ?';
                $params[] = $data[$key];
            }
        }
        if ($set === []) {
            return;
        }
        $params[] = $tenantId;
        $params[] = $id;
        $st = $this->pdo->prepare(
            'UPDATE roleplay_game_sessions SET ' . implode(', ', $set) . ' WHERE tenant_id = ? AND id = ?'
        );
        $st->execute($params);
    }

    /** @return list<array<string, mixed>> */
    public function listMembers(int $tenantId, int $sessionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT m.*, u.display_name, u.callsign, u.email
             FROM roleplay_game_session_members m
             LEFT JOIN users u ON u.id = m.user_id AND u.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.session_id = ?
             ORDER BY COALESCE(u.display_name, m.steam_uid)'
        );
        $st->execute([$tenantId, $sessionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findMember(int $tenantId, int $sessionId, int $userId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM roleplay_game_session_members WHERE tenant_id = ? AND session_id = ? AND user_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $sessionId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findMemberBySteam(int $tenantId, int $sessionId, string $steamUid): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM roleplay_game_session_members WHERE tenant_id = ? AND session_id = ? AND steam_uid = ? LIMIT 1'
        );
        $st->execute([$tenantId, $sessionId, $steamUid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function upsertMember(int $tenantId, int $sessionId, array $data): int
    {
        $existing = null;
        $userId = (int) ($data['user_id'] ?? 0);
        $steam = trim((string) ($data['steam_uid'] ?? ''));
        if ($userId > 0) {
            $existing = $this->findMember($tenantId, $sessionId, $userId);
        } elseif ($steam !== '') {
            $existing = $this->findMemberBySteam($tenantId, $sessionId, $steam);
        }
        if ($existing) {
            $this->updateMember($tenantId, (int) $existing['id'], $data);

            return (int) $existing['id'];
        }
        $st = $this->pdo->prepare(
            'INSERT INTO roleplay_game_session_members
                (tenant_id, session_id, user_id, steam_uid, joined_at, left_at, last_heartbeat_at,
                 raw_seconds, eligible_seconds, validated_seconds, attendance_status, attendance_source,
                 checked_in_at, checked_out_at, attendance_valid, rh_excluded)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            $sessionId,
            $userId > 0 ? $userId : null,
            $steam !== '' ? $steam : null,
            $data['joined_at'] ?? null,
            $data['left_at'] ?? null,
            $data['last_heartbeat_at'] ?? null,
            (int) ($data['raw_seconds'] ?? 0),
            (int) ($data['eligible_seconds'] ?? 0),
            (int) ($data['validated_seconds'] ?? 0),
            $data['attendance_status'] ?? 'absent',
            $data['attendance_source'] ?? null,
            $data['checked_in_at'] ?? null,
            $data['checked_out_at'] ?? null,
            !empty($data['attendance_valid']) ? 1 : 0,
            !empty($data['rh_excluded']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateMember(int $tenantId, int $memberId, array $data): void
    {
        $allowed = [
            'user_id', 'steam_uid', 'joined_at', 'left_at', 'last_heartbeat_at',
            'raw_seconds', 'eligible_seconds', 'validated_seconds', 'attendance_status',
            'attendance_source', 'checked_in_at', 'checked_out_at', 'attendance_valid',
            'rh_excluded', 'staff_validated_at', 'staff_validated_by',
        ];
        $set = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $set[] = '`' . $key . '` = ?';
                $params[] = $data[$key];
            }
        }
        if ($set === []) {
            return;
        }
        $params[] = $tenantId;
        $params[] = $memberId;
        $st = $this->pdo->prepare(
            'UPDATE roleplay_game_session_members SET ' . implode(', ', $set) . ' WHERE tenant_id = ? AND id = ?'
        );
        $st->execute($params);
    }

    public function addSegment(int $tenantId, int $sessionId, int $memberId, string $reason, string $startedAt, ?string $endedAt, int $seconds): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO roleplay_game_session_segments
                (tenant_id, session_id, member_id, reason, started_at, ended_at, seconds)
             VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([$tenantId, $sessionId, $memberId, $reason, $startedAt, $endedAt, max(0, $seconds)]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function openSegment(int $tenantId, int $memberId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM roleplay_game_session_segments
             WHERE tenant_id = ? AND member_id = ? AND ended_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$tenantId, $memberId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function closeSegment(int $tenantId, int $segmentId, string $endedAt, int $seconds): void
    {
        $st = $this->pdo->prepare(
            'UPDATE roleplay_game_session_segments SET ended_at = ?, seconds = ? WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$endedAt, max(0, $seconds), $tenantId, $segmentId]);
    }

    /**
     * @return array{raw: int, validated: int, by_category: array<string, int>, sessions: int, presences: int, rate: float}
     */
    public function memberActivity30d(int $tenantId, int $userId): array
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(m.raw_seconds), 0) AS raw_sum,
                    COALESCE(SUM(CASE WHEN m.rh_excluded = 0 THEN m.validated_seconds ELSE 0 END), 0) AS validated_sum,
                    COUNT(*) AS session_count,
                    SUM(CASE WHEN m.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS presence_count,
                    SUM(CASE WHEN m.attendance_valid = 1 THEN 1 ELSE 0 END) AS valid_count
             FROM roleplay_game_session_members m
             INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.user_id = ?
               AND COALESCE(s.started_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $st->execute([$tenantId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $sessions = (int) ($row['session_count'] ?? 0);
        $presences = (int) ($row['presence_count'] ?? 0);
        $catSt = $this->pdo->prepare(
            "SELECT s.hour_category, COALESCE(SUM(CASE WHEN m.rh_excluded = 0 THEN m.validated_seconds ELSE 0 END), 0) AS secs
             FROM roleplay_game_session_members m
             INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.user_id = ?
               AND COALESCE(s.started_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY s.hour_category"
        );
        $catSt->execute([$tenantId, $userId]);
        $byCat = [];
        foreach ($catSt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
            $byCat[(string) ($c['hour_category'] ?? 'Libre')] = (int) ($c['secs'] ?? 0);
        }

        return [
            'raw' => (int) ($row['raw_sum'] ?? 0),
            'validated' => (int) ($row['validated_sum'] ?? 0),
            'by_category' => $byCat,
            'sessions' => $sessions,
            'presences' => $presences,
            'rate' => $sessions > 0 ? round(100 * $presences / $sessions, 1) : 0.0,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $tenantId, int $userId, int $limit = 30): array
    {
        $st = $this->pdo->prepare(
            'SELECT s.*, m.raw_seconds, m.validated_seconds, m.attendance_status, m.checked_in_at, m.attendance_valid, m.rh_excluded
             FROM roleplay_game_session_members m
             INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.user_id = ?
             ORDER BY COALESCE(s.started_at, s.created_at) DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sumValidatedSeconds(int $tenantId, int $userId, ?string $category = null, ?string $kind = null, ?int $windowDays = null): int
    {
        $sql = 'SELECT COALESCE(SUM(m.validated_seconds), 0)
                FROM roleplay_game_session_members m
                INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
                WHERE m.tenant_id = ? AND m.user_id = ? AND m.rh_excluded = 0 AND m.attendance_valid = 1';
        $params = [$tenantId, $userId];
        if ($category !== null && $category !== '') {
            $sql .= ' AND s.hour_category = ?';
            $params[] = $category;
        }
        if ($kind !== null && $kind !== '') {
            $sql .= ' AND s.session_kind = ?';
            $params[] = $kind;
        }
        if ($windowDays !== null && $windowDays > 0) {
            $sql .= ' AND COALESCE(s.started_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $windowDays;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    public function countValidatedSessions(int $tenantId, int $userId, ?string $kind = null, ?int $windowDays = null): int
    {
        $sql = 'SELECT COUNT(*)
                FROM roleplay_game_session_members m
                INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
                WHERE m.tenant_id = ? AND m.user_id = ? AND m.rh_excluded = 0 AND m.attendance_valid = 1';
        $params = [$tenantId, $userId];
        if ($kind !== null && $kind !== '') {
            $sql .= ' AND s.session_kind = ?';
            $params[] = $kind;
        }
        if ($windowDays !== null && $windowDays > 0) {
            $sql .= ' AND COALESCE(s.started_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $windowDays;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    public function countCheckedIn(int $tenantId, int $userId, ?int $windowDays = null): int
    {
        $sql = 'SELECT COUNT(*)
                FROM roleplay_game_session_members m
                INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
                WHERE m.tenant_id = ? AND m.user_id = ? AND m.checked_in_at IS NOT NULL AND m.rh_excluded = 0';
        $params = [$tenantId, $userId];
        if ($windowDays !== null && $windowDays > 0) {
            $sql .= ' AND COALESCE(s.started_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $windowDays;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    /**
     * @return array{present: int, total: int, rate: float}
     */
    public function attendanceRate(int $tenantId, int $userId, int $windowDays = 90): array
    {
        $windowDays = max(1, min(365, $windowDays));
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN m.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS present
             FROM roleplay_game_session_members m
             INNER JOIN roleplay_game_sessions s ON s.id = m.session_id AND s.tenant_id = m.tenant_id
             WHERE m.tenant_id = ? AND m.user_id = ? AND s.session_kind = 'officielle' AND s.attendance_enabled = 1
               AND COALESCE(s.started_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $st->execute([$tenantId, $userId, $windowDays]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) ($row['total'] ?? 0);
        $present = (int) ($row['present'] ?? 0);

        return [
            'present' => $present,
            'total' => $total,
            'rate' => $total > 0 ? round(100 * $present / $total, 1) : 0.0,
        ];
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
