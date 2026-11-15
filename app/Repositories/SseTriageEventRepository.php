<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Journal des actions de « À trier » : chaque acquittement, mise en veille, rappel, assignation, et chaque action automatique
 * d'une règle Cerbère-Tri. L'état d'avant est gardé : c'est ce qui rend une action annulable.
 */
final class SseTriageEventRepository
{
    public function __construct(private ?Database $db = null, private ?\Closure $clock = null)
    {
        $this->db ??= Database::getInstance();
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(int $tenantId, string $batch, string $key, string $action, ?int $actorId, string $actorLabel, ?int $ruleId, ?array $before, ?array $after, string $note = ''): int
    {
        [$type, $id] = SseTriageStateRepository::split($key);
        $note = mb_substr(trim($note), 0, 500);

        return $this->db->insert(
            'INSERT INTO sse_triage_events (tenant_id, batch_id, item_type, item_key, action, actor_user_id, actor_label, rule_id, before_json, after_json, note, created_at)
             VALUES (:t, :b, :ty, :k, :a, :u, :l, :r, :bj, :aj, :n, :now)',
            [
                't' => $tenantId, 'b' => mb_substr($batch, 0, 32), 'ty' => $type, 'k' => $id, 'a' => mb_substr($action, 0, 24),
                'u' => ($actorId ?? 0) > 0 ? $actorId : null, 'l' => mb_substr($actorLabel, 0, 160), 'r' => ($ruleId ?? 0) > 0 ? $ruleId : null,
                'bj' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'aj' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
                'n' => $note !== '' ? $note : null, 'now' => $this->now(),
            ]
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $tenantId, int $limit = 50): array
    {
        return $this->decode($this->db->fetchAll('SELECT * FROM sse_triage_events WHERE tenant_id = :t ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)), ['t' => $tenantId]));
    }

    /** @return list<array<string, mixed>> événements d'un lot encore annulables */
    public function openBatch(int $tenantId, string $batch): array
    {
        return $this->decode($this->db->fetchAll("SELECT * FROM sse_triage_events WHERE tenant_id = :t AND batch_id = :b AND undone_at IS NULL AND action <> 'undo' ORDER BY id ASC", ['t' => $tenantId, 'b' => $batch]));
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        return $this->decode($this->db->fetchAll('SELECT * FROM sse_triage_events WHERE tenant_id = :t AND id = :id LIMIT 1', ['t' => $tenantId, 'id' => $id]))[0] ?? null;
    }

    public function markUndone(int $tenantId, int $id): void
    {
        $this->db->execute('UPDATE sse_triage_events SET undone_at = :now WHERE tenant_id = :t AND id = :id AND undone_at IS NULL', ['now' => $this->now(), 't' => $tenantId, 'id' => $id]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decode(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['key'] = $row['item_type'] . ':' . $row['item_key'];
            $before = json_decode((string) ($row['before_json'] ?? ''), true);
            $after = json_decode((string) ($row['after_json'] ?? ''), true);
            $row['before'] = is_array($before) ? $before : null;
            $row['after'] = is_array($after) ? $after : null;
        }

        return $rows;
    }
}
