<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

final class RoleplayGameSessionService
{
    public function __construct(
        private RoleplayGameSessionRepository $sessions,
        private UserRepository $users,
    ) {}

    public function qualifyMember(array $session, array $member): array
    {
        $raw = (int) ($member['raw_seconds'] ?? 0);
        $minMinutes = max(0, (int) ($session['min_minutes'] ?? 45));
        $minPercent = max(0, (int) ($session['min_percent'] ?? 50));
        $sessionSeconds = $this->sessionLengthSeconds($session);
        $okMinutes = $minMinutes <= 0 || $raw >= $minMinutes * 60;
        $okPercent = $minPercent <= 0 || $sessionSeconds <= 0 || $raw >= (int) floor($sessionSeconds * $minPercent / 100);
        $attendanceValid = ($okMinutes || $okPercent) && empty($member['rh_excluded']);
        $kind = (string) ($session['session_kind'] ?? 'libre');
        $validated = 0;
        if ($kind !== 'libre' && $attendanceValid) {
            $validated = $raw;
        }
        $checkedIn = trim((string) ($member['checked_in_at'] ?? '')) !== '';
        $detected = trim((string) ($member['joined_at'] ?? '')) !== '' || $raw > 0;
        $status = 'absent';
        if ($checkedIn && $detected) {
            $status = 'confirmed';
        } elseif ($detected) {
            $status = 'detected';
        } elseif ($checkedIn) {
            $status = 'declared';
        }

        return [
            'attendance_valid' => $attendanceValid ? 1 : 0,
            'eligible_seconds' => $attendanceValid ? $raw : 0,
            'validated_seconds' => $validated,
            'attendance_status' => $status,
        ];
    }

    public function sessionLengthSeconds(array $session): int
    {
        $start = trim((string) ($session['started_at'] ?? $session['planned_starts_at'] ?? ''));
        $end = trim((string) ($session['ended_at'] ?? $session['planned_ends_at'] ?? ''));
        if ($start === '' || $end === '') {
            return 0;
        }
        $a = strtotime($start);
        $b = strtotime($end);
        if ($a === false || $b === false || $b <= $a) {
            return 0;
        }

        return $b - $a;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function discover(int $tenantId, array $payload): array
    {
        if (!$this->sessions->schemaReady()) {
            return ['ok' => false, 'error' => 'schema'];
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId);
        if (empty($cfg['sync_enabled'])) {
            return ['ok' => true, 'skipped' => true];
        }
        $uid = trim((string) ($payload['session_uid'] ?? $payload['mission_id'] ?? ''));
        if ($uid === '') {
            $uid = 'auto-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        $existing = $this->sessions->findByUid($tenantId, $uid);
        if ($existing) {
            $this->sessions->update($tenantId, (int) $existing['id'], [
                'server_name' => $payload['server_name'] ?? $existing['server_name'],
                'mission_name' => $payload['mission_name'] ?? $existing['mission_name'],
                'status' => $existing['status'] === 'closed' ? $existing['status'] : 'open',
                'started_at' => $existing['started_at'] ?: date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'session_id' => (int) $existing['id'], 'session_uid' => $uid];
        }
        $id = $this->sessions->create($tenantId, [
            'session_uid' => $uid,
            'server_name' => $payload['server_name'] ?? null,
            'mission_name' => $payload['mission_name'] ?? null,
            'session_kind' => 'libre',
            'hour_category' => 'Libre',
            'status' => 'open',
            'started_at' => date('Y-m-d H:i:s'),
            'min_minutes' => $cfg['min_minutes'],
            'min_percent' => $cfg['min_percent'],
            'late_tolerance_minutes' => $cfg['late_tolerance_minutes'],
        ]);

        return ['ok' => true, 'session_id' => $id, 'session_uid' => $uid];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function join(int $tenantId, array $payload): array
    {
        $session = $this->resolveSession($tenantId, $payload);
        if ($session === null) {
            $created = $this->discover($tenantId, $payload);
            if (empty($created['session_id'])) {
                return ['ok' => false];
            }
            $session = $this->sessions->findById($tenantId, (int) $created['session_id']);
        }
        if ($session === null) {
            return ['ok' => false];
        }
        $user = $this->resolveUser($tenantId, $payload);
        $now = date('Y-m-d H:i:s');
        $memberId = $this->sessions->upsertMember($tenantId, (int) $session['id'], [
            'user_id' => $user['id'] ?? null,
            'steam_uid' => $user['steam'] ?? ($payload['steam_uid'] ?? null),
            'joined_at' => $now,
            'last_heartbeat_at' => $now,
        ]);
        if ($this->sessions->openSegment($tenantId, $memberId) === null) {
            $this->sessions->addSegment($tenantId, (int) $session['id'], $memberId, 'join', $now, null, 0);
        }
        $this->refreshMember($tenantId, (int) $session['id'], $memberId);

        return ['ok' => true, 'member_id' => $memberId];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function heartbeat(int $tenantId, array $payload): array
    {
        $session = $this->resolveSession($tenantId, $payload);
        $user = $this->resolveUser($tenantId, $payload);
        if ($session === null || empty($user['id'])) {
            return ['ok' => true, 'matched' => false];
        }
        $member = $this->sessions->findMember($tenantId, (int) $session['id'], (int) $user['id']);
        if (!$member) {
            return $this->join($tenantId, $payload);
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId);
        $now = new DateTimeImmutable('now');
        $last = trim((string) ($member['last_heartbeat_at'] ?? ''));
        $open = $this->sessions->openSegment($tenantId, (int) $member['id']);
        if ($last !== '' && $open) {
            try {
                $lastDt = new DateTimeImmutable($last);
                $gap = $now->getTimestamp() - $lastDt->getTimestamp();
                $timeout = (int) $cfg['heartbeat_timeout_minutes'] * 60;
                if ($gap > $timeout) {
                    $this->sessions->closeSegment($tenantId, (int) $open['id'], $last, max(0, $timeout));
                    $this->sessions->addSegment($tenantId, (int) $session['id'], (int) $member['id'], 'reconnect', $now->format('Y-m-d H:i:s'), null, 0);
                }
            } catch (\Throwable) {
            }
        }
        $this->sessions->updateMember($tenantId, (int) $member['id'], [
            'last_heartbeat_at' => $now->format('Y-m-d H:i:s'),
        ]);
        $this->refreshMember($tenantId, (int) $session['id'], (int) $member['id']);

        return ['ok' => true, 'matched' => true];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function checkIn(int $tenantId, array $payload): array
    {
        $session = $this->resolveSession($tenantId, $payload);
        $user = $this->resolveUser($tenantId, $payload);
        if ($session === null || empty($user['id'])) {
            return ['ok' => false];
        }
        $now = date('Y-m-d H:i:s');
        $memberId = $this->sessions->upsertMember($tenantId, (int) $session['id'], [
            'user_id' => $user['id'],
            'steam_uid' => $user['steam'] ?? null,
            'checked_in_at' => $now,
        ]);
        $this->sessions->addSegment($tenantId, (int) $session['id'], $memberId, 'check_in', $now, $now, 0);
        $this->refreshMember($tenantId, (int) $session['id'], $memberId);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function checkOut(int $tenantId, array $payload): array
    {
        $session = $this->resolveSession($tenantId, $payload);
        $user = $this->resolveUser($tenantId, $payload);
        if ($session === null || empty($user['id'])) {
            return ['ok' => false];
        }
        $member = $this->sessions->findMember($tenantId, (int) $session['id'], (int) $user['id']);
        if (!$member) {
            return ['ok' => true];
        }
        $now = date('Y-m-d H:i:s');
        $this->closeOpenSegment($tenantId, $member, 'check_out', $now);
        $this->sessions->updateMember($tenantId, (int) $member['id'], ['checked_out_at' => $now]);
        $this->refreshMember($tenantId, (int) $session['id'], (int) $member['id']);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function leave(int $tenantId, array $payload): array
    {
        $session = $this->resolveSession($tenantId, $payload);
        $user = $this->resolveUser($tenantId, $payload);
        if ($session === null || empty($user['id'])) {
            return ['ok' => true];
        }
        $member = $this->sessions->findMember($tenantId, (int) $session['id'], (int) $user['id']);
        if (!$member) {
            return ['ok' => true];
        }
        $now = date('Y-m-d H:i:s');
        $this->closeOpenSegment($tenantId, $member, 'leave', $now);
        $this->sessions->updateMember($tenantId, (int) $member['id'], ['left_at' => $now]);
        $this->refreshMember($tenantId, (int) $session['id'], (int) $member['id']);

        return ['ok' => true];
    }

    public function refreshMember(int $tenantId, int $sessionId, int $memberId): void
    {
        $session = $this->sessions->findById($tenantId, $sessionId);
        $members = $this->sessions->listMembers($tenantId, $sessionId);
        $member = null;
        foreach ($members as $row) {
            if ((int) $row['id'] === $memberId) {
                $member = $row;
                break;
            }
        }
        if (!$session || !$member) {
            return;
        }
        $raw = $this->sumClosedSegments($tenantId, $memberId);
        $open = $this->sessions->openSegment($tenantId, $memberId);
        if ($open) {
            $start = strtotime((string) $open['started_at']) ?: time();
            $raw += max(0, time() - $start);
        }
        $member['raw_seconds'] = $raw;
        $q = $this->qualifyMember($session, $member);
        $this->sessions->updateMember($tenantId, $memberId, array_merge(['raw_seconds' => $raw], $q));
    }

    public function staffValidate(int $tenantId, int $sessionId, int $memberId, int $actorId, bool $valid, bool $exclude): void
    {
        $this->sessions->updateMember($tenantId, $memberId, [
            'attendance_valid' => $valid ? 1 : 0,
            'rh_excluded' => $exclude ? 1 : 0,
            'staff_validated_at' => date('Y-m-d H:i:s'),
            'staff_validated_by' => $actorId,
            'validated_seconds' => $valid && !$exclude ? null : 0,
        ]);
        if ($valid && !$exclude) {
            $this->refreshMember($tenantId, $sessionId, $memberId);
        }
    }

    private function sumClosedSegments(int $tenantId, int $memberId): int
    {
        $st = $this->sessions->pdo()->prepare(
            'SELECT COALESCE(SUM(seconds), 0) FROM roleplay_game_session_segments WHERE tenant_id = ? AND member_id = ? AND ended_at IS NOT NULL'
        );
        $st->execute([$tenantId, $memberId]);

        return (int) $st->fetchColumn();
    }

    private function closeOpenSegment(int $tenantId, array $member, string $reason, string $endedAt): void
    {
        $open = $this->sessions->openSegment($tenantId, (int) $member['id']);
        if (!$open) {
            return;
        }
        $start = strtotime((string) $open['started_at']) ?: time();
        $end = strtotime($endedAt) ?: time();
        $this->sessions->closeSegment($tenantId, (int) $open['id'], $endedAt, max(0, $end - $start));
        $this->sessions->addSegment($tenantId, (int) $member['session_id'], (int) $member['id'], $reason, $endedAt, $endedAt, 0);
    }

    /** @param array<string, mixed> $payload */
    private function resolveSession(int $tenantId, array $payload): ?array
    {
        $id = (int) ($payload['session_id'] ?? 0);
        if ($id > 0) {
            return $this->sessions->findById($tenantId, $id);
        }
        $uid = trim((string) ($payload['session_uid'] ?? ''));
        if ($uid !== '') {
            return $this->sessions->findByUid($tenantId, $uid);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{id?: int, steam?: string}
     */
    private function resolveUser(int $tenantId, array $payload): array
    {
        $uid = (int) ($payload['user_id'] ?? 0);
        $steam = trim((string) ($payload['steam_uid'] ?? $payload['player_uid'] ?? $payload['steam_id'] ?? ''));
        if ($uid > 0) {
            $user = $this->users->findById($uid, $tenantId);

            return $user ? ['id' => $uid, 'steam' => $steam] : ['steam' => $steam];
        }
        if ($steam !== '') {
            $user = $this->users->findBySteamIdForTenant($tenantId, $steam);

            return $user ? ['id' => (int) $user['id'], 'steam' => $steam] : ['steam' => $steam];
        }

        return [];
    }
}
