<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseTriagePresets;

/**
 * Règles automatiques « Cerbère-Tri » d'une communauté (table sse_triage_rules). Une règle créée à partir d'un modèle fourni garde sa
 * clé (preset_key) : une seule par modèle et par communauté.
 */
final class SseTriageRuleRepository
{
    public const NAME_MAX = 160;

    public function __construct(private ?Database $db = null, private ?\Closure $clock = null)
    {
        $this->db ??= Database::getInstance();
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : gmdate('Y-m-d H:i:s');
    }

    /** @return list<array<string, mixed>> */
    public function all(int $tenantId): array
    {
        return $this->db->fetchAll('SELECT * FROM sse_triage_rules WHERE tenant_id = :t ORDER BY id ASC', ['t' => $tenantId]);
    }

    /** @return list<array<string, mixed>> */
    public function enabled(int $tenantId): array
    {
        return $this->db->fetchAll('SELECT * FROM sse_triage_rules WHERE tenant_id = :t AND enabled = 1 ORDER BY id ASC', ['t' => $tenantId]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sse_triage_rules WHERE tenant_id = :t AND id = :id LIMIT 1', ['t' => $tenantId, 'id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findPreset(int $tenantId, string $preset): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sse_triage_rules WHERE tenant_id = :t AND preset_key = :p LIMIT 1', ['t' => $tenantId, 'p' => $preset]);
    }

    /** Un modèle est-il activé ? Sans ligne, $default (la résolution automatique du moteur est active tant qu'on ne la coupe pas). */
    public function presetEnabled(int $tenantId, string $preset, bool $default): bool
    {
        $row = $this->findPreset($tenantId, $preset);

        return $row === null ? $default : (int) $row['enabled'] === 1;
    }

    /**
     * Crée ou met à jour une règle.
     *
     * @param array<string, mixed> $fields name, source_key, type_key, keyword, min_age_days, min_severity, max_severity, scope, action, action_value, enabled, preset_key
     * @return int identifiant de la règle
     */
    public function save(int $tenantId, ?int $id, array $fields, ?int $userId): int
    {
        $severity = static fn (mixed $v): ?string => is_string($v) && isset(SseTriagePresets::SEVERITIES[$v]) ? $v : null;
        $row = [
            'name' => mb_substr(trim((string) ($fields['name'] ?? '')), 0, self::NAME_MAX),
            'source_key' => mb_substr(trim((string) ($fields['source_key'] ?? '')), 0, 32),
            'type_key' => mb_substr(trim((string) ($fields['type_key'] ?? '')), 0, 80),
            'keyword' => mb_substr(trim((string) ($fields['keyword'] ?? '')), 0, 80),
            'min_age_days' => max(0, min(3650, (int) ($fields['min_age_days'] ?? 0))),
            'min_severity' => $severity($fields['min_severity'] ?? null),
            'max_severity' => $severity($fields['max_severity'] ?? null),
            'scope' => isset(SseTriagePresets::SCOPES[(string) ($fields['scope'] ?? 'all')]) ? (string) ($fields['scope'] ?? 'all') : 'all',
            'action' => isset(SseTriagePresets::ACTIONS[(string) ($fields['action'] ?? '')]) ? (string) $fields['action'] : 'acknowledge',
            'action_value' => max(0, min(100000, (int) ($fields['action_value'] ?? 0))),
            'enabled' => !empty($fields['enabled']) ? 1 : 0,
            'updated_at' => $this->now(),
        ];
        if ($id !== null && $this->find($tenantId, $id) !== null) {
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($row)));
            $this->db->execute('UPDATE sse_triage_rules SET ' . $set . ' WHERE tenant_id = :t AND id = :id', $row + ['t' => $tenantId, 'id' => $id]);

            return $id;
        }
        $preset = trim((string) ($fields['preset_key'] ?? ''));
        $row += ['tenant_id' => $tenantId, 'preset_key' => $preset !== '' ? mb_substr($preset, 0, 32) : null, 'created_by' => ($userId ?? 0) > 0 ? $userId : null, 'created_at' => $this->now(), 'fired_count' => 0];

        return $this->db->insert('INSERT INTO sse_triage_rules (' . implode(', ', array_keys($row)) . ') VALUES (:' . implode(', :', array_keys($row)) . ')', $row);
    }

    public function setEnabled(int $tenantId, int $id, bool $enabled): bool
    {
        return $this->db->execute('UPDATE sse_triage_rules SET enabled = :e, updated_at = :now WHERE tenant_id = :t AND id = :id', ['e' => $enabled ? 1 : 0, 'now' => $this->now(), 't' => $tenantId, 'id' => $id]) >= 0
            && $this->find($tenantId, $id) !== null;
    }

    public function recordFired(int $tenantId, int $id, int $count): void
    {
        $this->db->execute('UPDATE sse_triage_rules SET last_fired_at = :now, fired_count = fired_count + :n WHERE tenant_id = :t AND id = :id', ['now' => $this->now(), 'n' => max(0, $count), 't' => $tenantId, 'id' => $id]);
    }

    /** Annulation d'un déclenchement : le compteur redescend. */
    public function recordUndone(int $tenantId, int $id, int $count): void
    {
        $this->db->execute('UPDATE sse_triage_rules SET fired_count = CASE WHEN fired_count >= :n THEN fired_count - :n2 ELSE 0 END WHERE tenant_id = :t AND id = :id', ['n' => $count, 'n2' => $count, 't' => $tenantId, 'id' => $id]);
    }
}
