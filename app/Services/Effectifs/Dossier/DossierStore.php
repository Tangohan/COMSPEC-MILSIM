<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

use PDO;
use Throwable;

/**
 * Tables du suivi de dossier : étapes personnalisées, compléments posés sur une étape d'un autre parcours et
 * historique. Tout est borné par communauté et par membre. Sans la migration (étape member_dossier_steps), `available()`
 * est faux : l'onglet reste en lecture seule.
 */
final class DossierStore
{
    public const TABLE_STEPS = 'member_dossier_steps';
    public const TABLE_EVENTS = 'member_dossier_step_events';

    private ?bool $available = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function available(): bool
    {
        if ($this->available === null) {
            try {
                $this->pdo->query('SELECT 1 FROM ' . self::TABLE_STEPS . ' WHERE 1 = 0');
                $this->pdo->query('SELECT 1 FROM ' . self::TABLE_EVENTS . ' WHERE 1 = 0');
                $this->available = true;
            } catch (Throwable) {
                $this->available = false;
            }
        }

        return $this->available;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function rowsForUsers(int $tenantId, array $userIds): array
    {
        if ($userIds === [] || !$this->available()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE_STEPS . ' WHERE tenant_id = ? AND user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ') ORDER BY id ASC'
        );
        $st->execute(array_merge([$tenantId], $userIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['user_id']][] = $row;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $userId, int $id): ?array
    {
        if (!$this->available()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM ' . self::TABLE_STEPS . ' WHERE tenant_id = ? AND user_id = ? AND id = ?');
        $st->execute([$tenantId, $userId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findOverlay(int $tenantId, int $userId, string $sourceType, string $sourceId): ?array
    {
        if (!$this->available()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM ' . self::TABLE_STEPS . ' WHERE tenant_id = ? AND user_id = ? AND source_type = ? AND source_id = ?');
        $st->execute([$tenantId, $userId, $sourceType, $sourceId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $f track, title, status, owner_user_id, due_on, done_at, note, created_by, source_type, source_id */
    public function insert(int $tenantId, int $userId, array $f): int
    {
        $now = date('Y-m-d H:i:s');
        $st = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE_STEPS . ' (tenant_id, user_id, track, title, status, owner_user_id, due_on, done_at, note, created_by, created_at, updated_at, source_type, source_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId, $userId, (string) ($f['track'] ?? DossierCatalog::TRACK_INTEGRATION), (string) ($f['title'] ?? ''),
            (string) ($f['status'] ?? 'todo'), ($f['owner_user_id'] ?? 0) > 0 ? (int) $f['owner_user_id'] : null,
            $f['due_on'] ?? null, $f['done_at'] ?? null, $f['note'] ?? null, ($f['created_by'] ?? 0) > 0 ? (int) $f['created_by'] : null,
            $now, $now, $f['source_type'] ?? null, $f['source_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields colonnes modifiables : title, status, owner_user_id, due_on, done_at, note */
    public function update(int $tenantId, int $userId, int $id, array $fields): bool
    {
        $allowed = ['title', 'status', 'owner_user_id', 'due_on', 'done_at', 'note'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = $col . ' = ?';
                $params[] = $fields[$col];
            }
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = ?';
        $params[] = date('Y-m-d H:i:s');
        array_push($params, $tenantId, $userId, $id);

        return $this->pdo->prepare('UPDATE ' . self::TABLE_STEPS . ' SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND user_id = ? AND id = ?')->execute($params);
    }

    public function delete(int $tenantId, int $userId, int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM ' . self::TABLE_STEPS . ' WHERE tenant_id = ? AND user_id = ? AND id = ?')->execute([$tenantId, $userId, $id]);
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function addEvent(int $tenantId, int $userId, string $stepKey, string $stepTitle, string $action, int $actorId, ?array $before, ?array $after, string $reason = ''): void
    {
        if (!$this->available()) {
            return;
        }
        $json = static fn (?array $v): ?string => $v === null || $v === [] ? null : json_encode($v, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE_EVENTS . ' (tenant_id, user_id, step_key, step_title, action, actor_user_id, before_json, after_json, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $tenantId, $userId, mb_substr($stepKey, 0, 80), mb_substr($stepTitle, 0, 180), $action, $actorId > 0 ? $actorId : null,
            $json($before), $json($after), $reason !== '' ? mb_substr($reason, 0, 500) : null, date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> du plus récent au plus ancien */
    public function events(int $tenantId, int $userId, int $limit = 200, int $offset = 0): array
    {
        if (!$this->available()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE_EVENTS . ' WHERE tenant_id = ? AND user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset)
        );
        $st->execute([$tenantId, $userId]);

        return array_values($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function eventCount(int $tenantId, int $userId): int
    {
        if (!$this->available()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE_EVENTS . ' WHERE tenant_id = ? AND user_id = ?');
        $st->execute([$tenantId, $userId]);

        return (int) $st->fetchColumn();
    }

    /** Date du dernier événement d'un geste sur une étape (pour la relance sans doublon), ou null. */
    public function lastEventAt(int $tenantId, int $userId, string $stepKey, string $action): ?string
    {
        if (!$this->available()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT MAX(created_at) FROM ' . self::TABLE_EVENTS . ' WHERE tenant_id = ? AND user_id = ? AND step_key = ? AND action = ?');
        $st->execute([$tenantId, $userId, $stepKey, $action]);
        $v = $st->fetchColumn();

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Dernière relance par membre (toutes étapes), pour la vue équipe.
     *
     * @param list<int> $userIds
     * @return array<int, string>
     */
    public function lastRemindByUser(int $tenantId, array $userIds): array
    {
        if ($userIds === [] || !$this->available()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT user_id, MAX(created_at) AS last_at FROM ' . self::TABLE_EVENTS . " WHERE tenant_id = ? AND action = 'remind' AND user_id IN ("
            . implode(',', array_fill(0, count($userIds), '?')) . ') GROUP BY user_id'
        );
        $st->execute(array_merge([$tenantId], $userIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['user_id']] = (string) $r['last_at'];
        }

        return $out;
    }
}
