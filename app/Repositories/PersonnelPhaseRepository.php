<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PersonnelPhaseRepository
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
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_phase_definitions' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function countDefinitions(int $tenantId): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM personnel_phase_definitions WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    public function countRuleSets(int $tenantId): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM personnel_phase_rule_sets WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    public function countConditions(int $tenantId): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM personnel_phase_rule_conditions WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listPhases(int $tenantId, bool $activeOnly = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT * FROM personnel_phase_definitions WHERE tenant_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY position ASC, id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findPhase(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM personnel_phase_definitions WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function createPhase(int $tenantId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_phase_definitions (tenant_id, label, position, target_member_status, is_active)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            mb_substr(trim((string) ($data['label'] ?? '')), 0, 120),
            max(1, (int) ($data['position'] ?? 1)),
            mb_substr(trim((string) ($data['target_member_status'] ?? 'En formation')), 0, 80) ?: 'En formation',
            !empty($data['is_active']) || !array_key_exists('is_active', $data) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updatePhase(int $tenantId, int $id, array $data): void
    {
        $allowed = ['label', 'position', 'target_member_status', 'is_active'];
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
            'UPDATE personnel_phase_definitions SET ' . implode(', ', $set) . ' WHERE tenant_id = ? AND id = ?'
        );
        $st->execute($params);
    }

    public function seedDefaultPhases(int $tenantId): void
    {
        if ($this->countDefinitions($tenantId) > 0) {
            return;
        }
        $defaults = [
            ['label' => 'Intégration', 'position' => 1, 'target_member_status' => 'En formation', 'is_active' => 1],
            ['label' => 'Formation', 'position' => 2, 'target_member_status' => 'En formation', 'is_active' => 1],
            ['label' => 'Intégré', 'position' => 3, 'target_member_status' => 'Disponible', 'is_active' => 1],
            ['label' => 'Actif', 'position' => 4, 'target_member_status' => 'Actif', 'is_active' => 1],
        ];
        foreach ($defaults as $row) {
            $this->createPhase($tenantId, $row);
        }
    }

    /** @return array<string, mixed>|null */
    public function ruleSetForPhase(int $tenantId, int $phaseId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM personnel_phase_rule_sets WHERE tenant_id = ? AND phase_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $phaseId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{logic?: string, effect?: string, is_active?: bool|int} $data
     */
    public function saveRuleSet(int $tenantId, int $phaseId, array $data): int
    {
        $existing = $this->ruleSetForPhase($tenantId, $phaseId);
        $logic = (($data['logic'] ?? 'all') === 'any') ? 'any' : 'all';
        $effect = (($data['effect'] ?? 'manual_gate') === 'automatic') ? 'automatic' : 'manual_gate';
        $active = !empty($data['is_active']) || !array_key_exists('is_active', $data) ? 1 : 0;
        if ($existing) {
            $st = $this->pdo->prepare(
                'UPDATE personnel_phase_rule_sets SET logic = ?, effect = ?, is_active = ? WHERE tenant_id = ? AND id = ?'
            );
            $st->execute([$logic, $effect, $active, $tenantId, (int) $existing['id']]);

            return (int) $existing['id'];
        }
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_phase_rule_sets (tenant_id, phase_id, logic, effect, is_active) VALUES (?,?,?,?,?)'
        );
        $st->execute([$tenantId, $phaseId, $logic, $effect, $active]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listConditions(int $tenantId, int $ruleSetId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM personnel_phase_rule_conditions WHERE tenant_id = ? AND rule_set_id = ? ORDER BY position ASC, id ASC'
        );
        $st->execute([$tenantId, $ruleSetId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replaceConditions(int $tenantId, int $ruleSetId, array $rows): void
    {
        $del = $this->pdo->prepare('DELETE FROM personnel_phase_rule_conditions WHERE tenant_id = ? AND rule_set_id = ?');
        $del->execute([$tenantId, $ruleSetId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO personnel_phase_rule_conditions
                (tenant_id, rule_set_id, condition_type, qualification_id, training_module_id, threshold_value,
                 window_days, require_validity, hour_category, session_kind, position)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $pos = 1;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = trim((string) ($row['condition_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $ins->execute([
                $tenantId,
                $ruleSetId,
                mb_substr($type, 0, 64),
                isset($row['qualification_id']) && (int) $row['qualification_id'] > 0 ? (int) $row['qualification_id'] : null,
                isset($row['training_module_id']) && (int) $row['training_module_id'] > 0 ? (int) $row['training_module_id'] : null,
                (float) ($row['threshold_value'] ?? 0),
                isset($row['window_days']) && (int) $row['window_days'] > 0 ? (int) $row['window_days'] : null,
                !empty($row['require_validity']) ? 1 : 0,
                $row['hour_category'] ?? null,
                $row['session_kind'] ?? null,
                $pos++,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    public function addTransition(int $tenantId, int $userId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_phase_transitions
                (tenant_id, user_id, from_phase_id, to_phase_id, rule_set_id, trigger_kind, actor_user_id, override_reason, evaluation_snapshot_json)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            $userId,
            $data['from_phase_id'] ?? null,
            (int) ($data['to_phase_id'] ?? 0),
            $data['rule_set_id'] ?? null,
            $data['trigger_kind'] ?? 'manual',
            $data['actor_user_id'] ?? null,
            $data['override_reason'] ?? null,
            isset($data['evaluation_snapshot_json'])
                ? (is_string($data['evaluation_snapshot_json']) ? $data['evaluation_snapshot_json'] : json_encode($data['evaluation_snapshot_json'], JSON_UNESCAPED_UNICODE))
                : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listTransitionsForUser(int $tenantId, int $userId, int $limit = 40): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT t.*, pf.label AS from_label, pt.label AS to_label, a.display_name AS actor_name
             FROM personnel_phase_transitions t
             LEFT JOIN personnel_phase_definitions pf ON pf.id = t.from_phase_id
             LEFT JOIN personnel_phase_definitions pt ON pt.id = t.to_phase_id
             LEFT JOIN users a ON a.id = t.actor_user_id
             WHERE t.tenant_id = ? AND t.user_id = ?
             ORDER BY t.id DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function lastTransitionMatches(int $tenantId, int $userId, int $fromId, int $toId): bool
    {
        $st = $this->pdo->prepare(
            'SELECT from_phase_id, to_phase_id FROM personnel_phase_transitions
             WHERE tenant_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$tenantId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return (int) ($row['from_phase_id'] ?? 0) === $fromId && (int) ($row['to_phase_id'] ?? 0) === $toId;
    }

    public function recordAutoError(int $tenantId, int $userId, ?int $phaseId, string $code, string $label): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_phase_auto_errors (tenant_id, user_id, phase_id, error_code, error_label)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([$tenantId, $userId, $phaseId, mb_substr($code, 0, 64), mb_substr($label, 0, 255)]);
    }

    public function countOpenAutoErrors(int $tenantId): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT COUNT(*) FROM personnel_phase_auto_errors WHERE tenant_id = ? AND resolved_at IS NULL'
            );
            $st->execute([$tenantId]);

            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function countRecentAutoErrors(int $tenantId, int $hours = 1): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM personnel_phase_auto_errors WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)'
        );
        $st->execute([$tenantId, max(1, $hours)]);

        return (int) $st->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listOpenAutoErrors(int $tenantId, int $limit = 20): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT e.*, u.display_name, u.callsign
             FROM personnel_phase_auto_errors e
             INNER JOIN users u ON u.id = e.user_id AND u.tenant_id = e.tenant_id
             WHERE e.tenant_id = ? AND e.resolved_at IS NULL
             ORDER BY e.id DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
