<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * État de traitement d'un élément de « À trier », par communauté : ouvert (rappelé), acquitté, en veille, assigné.
 * Un élément est identifié par « type:id » (ex. signal:412) ; la clé unique (communauté, type, élément) est dans la table.
 * Une ligne « open » garde la trace d'un rappel ou d'une annulation : les règles automatiques ne reprennent pas l'élément.
 */
final class SseTriageStateRepository
{
    public const OPEN = 'open';
    public const ACKNOWLEDGED = 'acknowledged';
    public const SNOOZED = 'snoozed';

    private const FIELDS = ['status', 'snoozed_until', 'acknowledged_by', 'acknowledged_at', 'assigned_to', 'priority_boost', 'note', 'rule_id'];

    public function __construct(private ?Database $db = null, private ?\Closure $clock = null)
    {
        $this->db ??= Database::getInstance();
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : gmdate('Y-m-d H:i:s');
    }

    /** @return array{0: string, 1: string} [type, élément] */
    public static function split(string $key): array
    {
        $pos = strpos($key, ':');

        return $pos === false ? ['item', mb_substr($key, 0, 64)] : [mb_substr(substr($key, 0, $pos), 0, 32), mb_substr(substr($key, $pos + 1), 0, 64)];
    }

    /**
     * @param list<string> $keys « type:id »
     * @return array<string, array<string, mixed>> état par clé (les éléments sans ligne sont absents)
     */
    public function forKeys(int $tenantId, array $keys): array
    {
        $byType = [];
        foreach ($keys as $key) {
            [$type, $id] = self::split($key);
            $byType[$type][$id] = $key;
        }
        $out = [];
        foreach ($byType as $type => $ids) {
            foreach (array_chunk(array_keys($ids), 200) as $chunk) {
                $in = implode(',', array_map(static fn (int $i): string => ':k' . $i, array_keys($chunk)));
                $params = ['t' => $tenantId, 'ty' => (string) $type];
                foreach ($chunk as $i => $id) {
                    $params['k' . $i] = (string) $id;
                }
                foreach ($this->db->fetchAll('SELECT * FROM sse_triage_state WHERE tenant_id = :t AND item_type = :ty AND item_key IN (' . $in . ')', $params) as $row) {
                    $out[$ids[(string) $row['item_key']] ?? ($type . ':' . $row['item_key'])] = $row;
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, string $key): ?array
    {
        [$type, $id] = self::split($key);

        return $this->db->fetchOne('SELECT * FROM sse_triage_state WHERE tenant_id = :t AND item_type = :ty AND item_key = :k LIMIT 1', ['t' => $tenantId, 'ty' => $type, 'k' => $id]);
    }

    /**
     * Pose l'état d'un élément (insère ou met à jour la ligne unique). On écrit l'état complet, jamais un fragment : les champs
     * absents de $fields reprennent leur valeur neutre.
     *
     * @param array<string, mixed> $fields status, snoozed_until, acknowledged_by, acknowledged_at, assigned_to, priority_boost, note, rule_id
     */
    public function save(int $tenantId, string $key, array $fields): void
    {
        [$type, $id] = self::split($key);
        $status = (string) ($fields['status'] ?? self::OPEN);
        $note = mb_substr(trim((string) ($fields['note'] ?? '')), 0, 500);
        $row = [
            'status' => in_array($status, [self::OPEN, self::ACKNOWLEDGED, self::SNOOZED], true) ? $status : self::OPEN,
            'snoozed_until' => ($fields['snoozed_until'] ?? null) ?: null,
            'acknowledged_by' => ((int) ($fields['acknowledged_by'] ?? 0)) ?: null,
            'acknowledged_at' => ($fields['acknowledged_at'] ?? null) ?: null,
            'assigned_to' => ((int) ($fields['assigned_to'] ?? 0)) ?: null,
            'priority_boost' => max(-100, min(100, (int) ($fields['priority_boost'] ?? 0))),
            'note' => $note !== '' ? $note : null,
            'rule_id' => ((int) ($fields['rule_id'] ?? 0)) ?: null,
            'updated_at' => $this->now(),
        ];
        if ($this->find($tenantId, $key) !== null) {
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($row)));
            $this->db->execute('UPDATE sse_triage_state SET ' . $set . ' WHERE tenant_id = :t AND item_type = :ty AND item_key = :k', $row + ['t' => $tenantId, 'ty' => $type, 'k' => $id]);

            return;
        }
        $row += ['tenant_id' => $tenantId, 'item_type' => $type, 'item_key' => $id];
        $this->db->execute('INSERT INTO sse_triage_state (' . implode(', ', array_keys($row)) . ') VALUES (:' . implode(', :', array_keys($row)) . ')', $row);
    }

    public function delete(int $tenantId, string $key): void
    {
        [$type, $id] = self::split($key);
        $this->db->execute('DELETE FROM sse_triage_state WHERE tenant_id = :t AND item_type = :ty AND item_key = :k', ['t' => $tenantId, 'ty' => $type, 'k' => $id]);
    }

    /**
     * État réduit aux champs d'écriture (avant et après d'un événement, restauration d'une annulation).
     *
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    public static function snapshot(?array $row): ?array
    {
        return $row === null ? null : array_intersect_key($row, array_flip(self::FIELDS));
    }
}
