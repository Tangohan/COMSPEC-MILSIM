<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Lore de session : une mission (communauté + code d'opération), sa phase, son verrou, ses dates de synchronisation avec
 * le jeu et ses personnes (identités du registre, même modèle que les fichiers : aucune donnée en double).
 */
final class SseLoreRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseFileRecordRepository::ensureSchema();
    }

    public static function validOp(string $op): bool
    {
        return preg_match('/^[A-Za-z0-9_.:\-]{2,64}$/', $op) === 1;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM sse_lore_sessions WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $r === null ? null : self::hydrate($r);
    }

    /** @return array<string, mixed>|null */
    public function findByOp(int $tenantId, string $op): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM sse_lore_sessions WHERE tenant_id = :t AND op_key = :o', ['t' => $tenantId, 'o' => $op]);

        return $r === null ? null : self::hydrate($r);
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            'SELECT s.*, (SELECT COUNT(*) FROM sse_lore_members m WHERE m.session_id = s.id AND m.tenant_id = s.tenant_id) AS members_count
             FROM sse_lore_sessions s WHERE s.tenant_id = :t ORDER BY COALESCE(s.updated_at, s.created_at) DESC LIMIT ' . max(1, min(500, $limit)),
            ['t' => $tenantId]
        );

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** Phase en cours de chaque session de la communauté (pour la visibilité des données). @return array<int, int> */
    public function phases(int $tenantId): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, phase FROM sse_lore_sessions WHERE tenant_id = :t', ['t' => $tenantId]) as $r) {
            $out[(int) $r['id']] = (int) $r['phase'];
        }

        return $out;
    }

    /** @param array{title: string, op_key: string, summary?: ?string, country?: ?string, context_id?: ?int} $data */
    public function create(int $tenantId, array $data, ?int $userId): int
    {
        $now = gmdate('Y-m-d H:i:s');

        return $this->db->insert(
            'INSERT INTO sse_lore_sessions (tenant_id, op_key, context_id, title, summary, country, phase, created_by_user_id, created_at, updated_at, content_updated_at)
             VALUES (:t, :o, :c, :ti, :s, :co, 1, :u, :now, :now, :now)',
            [
                't' => $tenantId, 'o' => $data['op_key'], 'c' => ($data['context_id'] ?? 0) > 0 ? (int) $data['context_id'] : null,
                'ti' => mb_substr(trim($data['title']), 0, 160), 's' => isset($data['summary']) && trim((string) $data['summary']) !== '' ? mb_substr(trim((string) $data['summary']), 0, 2000) : null,
                'co' => isset($data['country']) && preg_match('/^[A-Z]{3}$/', (string) $data['country']) === 1 ? $data['country'] : null,
                'u' => $userId, 'now' => $now,
            ]
        );
    }

    /** @param array<string, mixed> $data title, summary, country, phase, auto_refresh_s */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $set = [];
        $params = ['id' => $id, 't' => $tenantId, 'now' => gmdate('Y-m-d H:i:s')];
        if (isset($data['title']) && trim((string) $data['title']) !== '') {
            $set[] = 'title = :ti';
            $params['ti'] = mb_substr(trim((string) $data['title']), 0, 160);
        }
        if (array_key_exists('summary', $data)) {
            $set[] = 'summary = :s';
            $params['s'] = trim((string) $data['summary']) !== '' ? mb_substr(trim((string) $data['summary']), 0, 2000) : null;
        }
        if (array_key_exists('country', $data)) {
            $set[] = 'country = :co';
            $params['co'] = preg_match('/^[A-Z]{3}$/', (string) $data['country']) === 1 ? $data['country'] : null;
        }
        if (isset($data['phase'])) {
            $set[] = 'phase = :ph';
            $params['ph'] = max(1, min(20, (int) $data['phase']));
        }
        if (isset($data['auto_refresh_s'])) {
            $v = (int) $data['auto_refresh_s'];
            $set[] = 'auto_refresh_s = :ar';
            $params['ar'] = $v <= 0 ? 0 : max(60, min(3600, $v));
        }
        if ($set === []) {
            return false;
        }

        return $this->db->execute('UPDATE sse_lore_sessions SET ' . implode(', ', $set) . ', updated_at = :now, content_updated_at = :now WHERE id = :id AND tenant_id = :t', $params) > 0;
    }

    public function setLocked(int $tenantId, int $id, bool $locked, ?int $userId): bool
    {
        return $this->db->execute(
            'UPDATE sse_lore_sessions SET locked_at = :l, locked_by_user_id = :u, updated_at = :now WHERE id = :id AND tenant_id = :t',
            ['l' => $locked ? gmdate('Y-m-d H:i:s') : null, 'u' => $locked ? $userId : null, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** Contenu modifié sur le web (la prochaine récupération par le jeu le prendra). */
    public function touch(int $tenantId, int $id): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->execute('UPDATE sse_lore_sessions SET content_updated_at = :now, updated_at = :now WHERE id = :id AND tenant_id = :t', ['now' => $now, 'id' => $id, 't' => $tenantId]);
    }

    public function markPush(int $tenantId, int $id, int $count): void
    {
        $this->db->execute(
            'UPDATE sse_lore_sessions SET last_push_at = :now, last_push_count = :c WHERE id = :id AND tenant_id = :t',
            ['now' => gmdate('Y-m-d H:i:s'), 'c' => max(0, $count), 'id' => $id, 't' => $tenantId]
        );
    }

    public function markPull(int $tenantId, int $id): void
    {
        $this->db->execute('UPDATE sse_lore_sessions SET last_pull_at = :now WHERE id = :id AND tenant_id = :t', ['now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]);
    }

    // ------------------------------------------------------------ Membres

    public function addMember(int $tenantId, int $sessionId, int $identityId, ?string $aiUid, string $origin = 'web'): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->db->fetchOne('SELECT id FROM sse_lore_members WHERE session_id = :s AND identity_id = :i AND tenant_id = :t', ['s' => $sessionId, 'i' => $identityId, 't' => $tenantId]);
        if ($row !== null) {
            $this->db->execute(
                'UPDATE sse_lore_members SET last_seen_at = :now, ai_uid = COALESCE(:u, ai_uid) WHERE id = :id AND tenant_id = :t',
                ['now' => $now, 'u' => $aiUid, 'id' => (int) $row['id'], 't' => $tenantId]
            );

            return;
        }
        $this->db->insert(
            'INSERT INTO sse_lore_members (tenant_id, session_id, identity_id, ai_uid, origin, last_seen_at, created_at) VALUES (:t, :s, :i, :u, :o, :seen, :now)',
            ['t' => $tenantId, 's' => $sessionId, 'i' => $identityId, 'u' => $aiUid, 'o' => $origin === 'game' ? 'game' : 'web', 'seen' => $origin === 'game' ? $now : null, 'now' => $now]
        );
    }

    public function removeMember(int $tenantId, int $sessionId, int $identityId): bool
    {
        return $this->db->execute('DELETE FROM sse_lore_members WHERE session_id = :s AND identity_id = :i AND tenant_id = :t', ['s' => $sessionId, 'i' => $identityId, 't' => $tenantId]) > 0;
    }

    /** @return list<array{identity_id: int, ai_uid: ?string, origin: string, last_seen_at: ?string}> */
    public function members(int $tenantId, int $sessionId, int $limit = 500, int $offset = 0): array
    {
        $rows = $this->db->fetchAll(
            'SELECT identity_id, ai_uid, origin, last_seen_at FROM sse_lore_members WHERE tenant_id = :t AND session_id = :s ORDER BY id ASC LIMIT ' . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset),
            ['t' => $tenantId, 's' => $sessionId]
        );

        return array_map(static fn (array $r): array => [
            'identity_id' => (int) $r['identity_id'],
            'ai_uid' => ($r['ai_uid'] ?? null) ?: null,
            'origin' => (string) $r['origin'],
            'last_seen_at' => $r['last_seen_at'] ?? null,
        ], $rows);
    }

    public function membersCount(int $tenantId, int $sessionId): int
    {
        $r = $this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_lore_members WHERE tenant_id = :t AND session_id = :s', ['t' => $tenantId, 's' => $sessionId]);

        return (int) ($r['n'] ?? 0);
    }

    public function isMember(int $tenantId, int $sessionId, int $identityId): bool
    {
        return $this->db->fetchOne('SELECT 1 AS x FROM sse_lore_members WHERE tenant_id = :t AND session_id = :s AND identity_id = :i', ['t' => $tenantId, 's' => $sessionId, 'i' => $identityId]) !== null;
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    public static function hydrate(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'op_key' => (string) $r['op_key'],
            'context_id' => ($r['context_id'] ?? null) !== null ? (int) $r['context_id'] : null,
            'title' => (string) $r['title'],
            'summary' => $r['summary'] ?? null,
            'country' => ($r['country'] ?? null) ?: null,
            'phase' => (int) ($r['phase'] ?? 1),
            'locked' => ($r['locked_at'] ?? null) !== null,
            'locked_at' => $r['locked_at'] ?? null,
            'auto_refresh_s' => (int) ($r['auto_refresh_s'] ?? 0),
            'last_push_at' => $r['last_push_at'] ?? null,
            'last_push_count' => (int) ($r['last_push_count'] ?? 0),
            'last_pull_at' => $r['last_pull_at'] ?? null,
            'content_updated_at' => $r['content_updated_at'] ?? null,
            'members_count' => (int) ($r['members_count'] ?? 0),
            'created_at' => (string) ($r['created_at'] ?? ''),
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }
}
