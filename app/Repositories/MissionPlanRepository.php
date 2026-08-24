<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;
use Throwable;

final class MissionPlanRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tablesReady(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM mission_plans LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM mission_to_slots LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM mission_plan_documents LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT p.*,
                    e.title AS event_title,
                    e.starts_at AS event_starts_at
             FROM mission_plans p
             LEFT JOIN community_events e ON e.id = p.event_id
             WHERE p.tenant_id = ?
             ORDER BY p.updated_at DESC, p.id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForTenant(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT p.*,
                    e.title AS event_title,
                    e.starts_at AS event_starts_at
             FROM mission_plans p
             LEFT JOIN community_events e ON e.id = p.event_id
             WHERE p.tenant_id = ? AND p.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findLiveForTenant(int $tenantId): ?array
    {
        $st = $this->pdo()->prepare(
            "SELECT * FROM mission_plans
             WHERE tenant_id = ? AND status = 'live'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, array $data, ?int $createdBy): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_plans (
                tenant_id, event_id, cycle_id, map_id, mission_code, title, operation_name,
                task_force_name, dtg, classification, status, opord_version, created_by_user_id
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            $data['event_id'] ?? null,
            $data['cycle_id'] ?? null,
            $data['map_id'] ?? null,
            (string) ($data['mission_code'] ?? ''),
            (string) ($data['title'] ?? 'Mission'),
            (string) ($data['operation_name'] ?? ''),
            (string) ($data['task_force_name'] ?? 'TF DAGGER'),
            (string) ($data['dtg'] ?? ''),
            (string) ($data['classification'] ?? 'EXERCISE / MILSIM'),
            (string) ($data['status'] ?? 'draft'),
            (string) ($data['opord_version'] ?? '1.0'),
            $createdBy,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateMeta(int $tenantId, int $id, array $data): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE mission_plans SET
                event_id = ?, cycle_id = ?, map_id = ?, mission_code = ?, title = ?,
                operation_name = ?, task_force_name = ?, dtg = ?, classification = ?,
                opord_version = ?
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([
            $data['event_id'] ?? null,
            $data['cycle_id'] ?? null,
            $data['map_id'] ?? null,
            (string) ($data['mission_code'] ?? ''),
            (string) ($data['title'] ?? ''),
            (string) ($data['operation_name'] ?? ''),
            (string) ($data['task_force_name'] ?? ''),
            (string) ($data['dtg'] ?? ''),
            (string) ($data['classification'] ?? 'EXERCISE / MILSIM'),
            (string) ($data['opord_version'] ?? '1.0'),
            $tenantId,
            $id,
        ]);
    }

    public function setStatus(int $tenantId, int $id, string $status): void
    {
        $published = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $closed = $status === 'closed' ? date('Y-m-d H:i:s') : null;
        $st = $this->pdo()->prepare(
            'UPDATE mission_plans SET status = ?,
                published_at = COALESCE(?, published_at),
                closed_at = COALESCE(?, closed_at)
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$status, $published, $closed, $tenantId, $id]);
    }

    public function saveSnapshot(int $id, string $column, string $json): void
    {
        if (!in_array($column, ['planned_snapshot_json', 'final_snapshot_json'], true)) {
            return;
        }
        $st = $this->pdo()->prepare("UPDATE mission_plans SET {$column} = ? WHERE id = ?");
        $st->execute([$json, $id]);
    }

    public function clearOrganization(int $planId): void
    {
        if ($planId < 1) {
            return;
        }
        $st = $this->pdo()->prepare('DELETE FROM mission_to_elements WHERE plan_id = ?');
        $st->execute([$planId]);
    }

    public function insertElement(int $planId, ?int $parentId, string $code, string $label, string $kind, int $auth, int $order): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_to_elements (plan_id, parent_id, code, label, kind, authorized_strength, display_order)
             VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([$planId, $parentId, $code, $label, $kind, $auth, $order]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function insertSlot(int $planId, int $elementId, string $callsign, string $function, string $role, int $order): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_to_slots (plan_id, element_id, callsign, function_label, role_code, display_order)
             VALUES (?,?,?,?,?,?)'
        );
        $st->execute([$planId, $elementId, $callsign, $function, $role, $order]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function insertAssignment(int $planId, int $slotId): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_to_assignments (plan_id, slot_id, presence_status) VALUES (?,?,?)'
        );
        $st->execute([$planId, $slotId, 'vacant']);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function elementsForPlan(int $planId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM mission_to_elements WHERE plan_id = ? ORDER BY display_order ASC, id ASC'
        );
        $st->execute([$planId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function rosterRows(int $planId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT s.*,
                    e.code AS element_code,
                    e.label AS element_label,
                    e.kind AS element_kind,
                    e.authorized_strength,
                    a.id AS assignment_id,
                    a.planned_user_id,
                    a.current_user_id,
                    a.detected_user_id,
                    a.assignment_mode,
                    a.presence_status,
                    a.arma_uid,
                    a.notes AS assignment_notes,
                    pu.callsign AS planned_callsign,
                    pu.display_name AS planned_name,
                    pu.steam_id AS planned_steam,
                    cu.callsign AS current_callsign,
                    cu.display_name AS current_name,
                    cu.steam_id AS current_steam,
                    du.callsign AS detected_callsign,
                    du.display_name AS detected_name
             FROM mission_to_slots s
             INNER JOIN mission_to_elements e ON e.id = s.element_id
             LEFT JOIN mission_to_assignments a ON a.slot_id = s.id
             LEFT JOIN users pu ON pu.id = a.planned_user_id
             LEFT JOIN users cu ON cu.id = a.current_user_id
             LEFT JOIN users du ON du.id = a.detected_user_id
             WHERE s.plan_id = ?
             ORDER BY e.display_order ASC, s.display_order ASC, s.id ASC'
        );
        $st->execute([$planId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSlotForPlan(int $planId, int $slotId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT s.*, a.id AS assignment_id, a.planned_user_id, a.current_user_id, a.detected_user_id,
                    a.assignment_mode, a.presence_status, a.arma_uid
             FROM mission_to_slots s
             LEFT JOIN mission_to_assignments a ON a.slot_id = s.id
             WHERE s.plan_id = ? AND s.id = ?
             LIMIT 1'
        );
        $st->execute([$planId, $slotId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findSlotByCallsign(int $planId, string $callsign): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT s.*, a.id AS assignment_id, a.planned_user_id, a.current_user_id,
                    a.detected_user_id, a.assignment_mode, a.presence_status, a.arma_uid, a.notes
             FROM mission_to_slots s
             LEFT JOIN mission_to_assignments a ON a.slot_id = s.id
             WHERE s.plan_id = ? AND UPPER(s.callsign) = UPPER(?)
             LIMIT 1'
        );
        $st->execute([$planId, $callsign]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateSlot(int $slotId, array $data): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE mission_to_slots SET
                callsign = ?, function_label = ?, role_code = ?, rank_label = ?,
                vehicle_label = ?, radio_primary = ?, radio_secondary = ?, equipment_notes = ?,
                element_id = ?, display_order = ?
             WHERE id = ?'
        );
        $st->execute([
            (string) ($data['callsign'] ?? ''),
            (string) ($data['function_label'] ?? ''),
            (string) ($data['role_code'] ?? ''),
            (string) ($data['rank_label'] ?? ''),
            (string) ($data['vehicle_label'] ?? ''),
            (string) ($data['radio_primary'] ?? ''),
            (string) ($data['radio_secondary'] ?? ''),
            (string) ($data['equipment_notes'] ?? ''),
            (int) ($data['element_id'] ?? 0),
            (int) ($data['display_order'] ?? 0),
            $slotId,
        ]);
    }

    public function updateAssignment(int $slotId, array $data): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE mission_to_assignments SET
                planned_user_id = ?, current_user_id = ?, detected_user_id = ?,
                assignment_mode = ?, presence_status = ?, arma_uid = ?, notes = ?
             WHERE slot_id = ?'
        );
        $st->execute([
            $data['planned_user_id'] ?? null,
            $data['current_user_id'] ?? null,
            $data['detected_user_id'] ?? null,
            (string) ($data['assignment_mode'] ?? 'preassigned'),
            (string) ($data['presence_status'] ?? 'vacant'),
            (string) ($data['arma_uid'] ?? ''),
            (string) ($data['notes'] ?? ''),
            $slotId,
        ]);
    }

    public function moveSlot(int $slotId, int $elementId, int $order): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE mission_to_slots SET element_id = ?, display_order = ? WHERE id = ?'
        );
        $st->execute([$elementId, $order, $slotId]);
    }

    public function findAssignmentByPlannedUser(int $planId, int $userId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT a.*, s.callsign, s.id AS slot_id
             FROM mission_to_assignments a
             INNER JOIN mission_to_slots s ON s.id = a.slot_id
             WHERE a.plan_id = ? AND a.planned_user_id = ?
             LIMIT 1'
        );
        $st->execute([$planId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findAssignmentByCurrentUser(int $planId, int $userId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT a.*, s.callsign
             FROM mission_to_assignments a
             INNER JOIN mission_to_slots s ON s.id = a.slot_id
             WHERE a.plan_id = ? AND a.current_user_id = ?
             LIMIT 1'
        );
        $st->execute([$planId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function documentForPlan(int $planId): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM mission_plan_documents WHERE plan_id = ? LIMIT 1');
        $st->execute([$planId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function upsertDocument(int $planId, array $data): void
    {
        $existing = $this->documentForPlan($planId);
        $cols = [
            'situation_enemy', 'situation_friendly', 'situation_attachments', 'situation_civil',
            'mission_task', 'mission_location', 'mission_nlt', 'mission_purpose',
            'execution_intent', 'execution_concept', 'execution_tasks', 'execution_coordinating',
            'sustainment_logistics', 'sustainment_medical', 'sustainment_resupply',
            'command_command', 'command_signal',
        ];
        if ($existing) {
            $sets = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", $cols));
            $params = [];
            foreach ($cols as $c) {
                $params[] = (string) ($data[$c] ?? '');
            }
            $params[] = $planId;
            $st = $this->pdo()->prepare("UPDATE mission_plan_documents SET {$sets} WHERE plan_id = ?");
            $st->execute($params);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($cols) + 1, '?'));
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_plan_documents (plan_id, ' . implode(',', $cols) . ") VALUES ({$placeholders})"
        );
        $params = [$planId];
        foreach ($cols as $c) {
            $params[] = (string) ($data[$c] ?? '');
        }
        $st->execute($params);
    }

    public function addLog(int $planId, string $message, ?int $actorId): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_to_log (plan_id, actor_user_id, message) VALUES (?,?,?)'
        );
        $st->execute([$planId, $actorId, mb_substr($message, 0, 255)]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function logForPlan(int $planId, int $limit = 40): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM mission_to_log WHERE plan_id = ? ORDER BY occurred_at DESC, id DESC LIMIT ' . (int) $limit
        );
        $st->execute([$planId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Plan en session, sinon le plus récent publié (préférence carte).
     */
    public function findActiveForAtak(int $tenantId, int $mapId): ?array
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT * FROM mission_plans
                 WHERE tenant_id = ? AND `status` IN ('live', 'published')
                 ORDER BY CASE `status` WHEN 'live' THEN 0 ELSE 1 END,
                          CASE WHEN map_id = ? THEN 0 WHEN map_id IS NULL THEN 1 ELSE 2 END,
                          updated_at DESC, id DESC
                 LIMIT 1"
            );
            $st->execute([$tenantId, $mapId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function setPhase(int $tenantId, int $id, string $phase): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE mission_plans SET phase_label = ? WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([mb_substr($phase, 0, 80), $tenantId, $id]);
    }

    public function setHHourIfEmpty(int $id): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE mission_plans SET h_hour_at = COALESCE(h_hour_at, NOW()) WHERE id = ?'
        );
        $st->execute([$id]);
    }

    public function graphicsReady(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM mission_plan_graphics LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM mission_plan_timeline LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function graphicsForPlan(int $planId): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM mission_plan_graphics WHERE plan_id = ? ORDER BY display_order ASC, id ASC'
            );
            $st->execute([$planId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    public function findGraphicForPlan(int $planId, int $graphicId): ?array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM mission_plan_graphics WHERE plan_id = ? AND id = ? LIMIT 1'
            );
            $st->execute([$planId, $graphicId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function insertGraphic(
        int $planId,
        string $code,
        string $label,
        string $kind,
        string $geomType,
        string $elementCode,
        int $order,
    ): int {
        try {
            $st = $this->pdo()->prepare(
                'INSERT INTO mission_plan_graphics
                    (plan_id, code, label, kind, geom_type, element_code, display_order)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $st->execute([
                $planId,
                mb_substr($code, 0, 48),
                mb_substr($label, 0, 80),
                mb_substr($kind, 0, 16),
                $geomType === 'line' ? 'line' : 'point',
                mb_substr($elementCode, 0, 32),
                $order,
            ]);

            return (int) $this->pdo()->lastInsertId();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $patch
     */
    public function updateGraphic(int $planId, int $graphicId, array $patch): void
    {
        $allowed = ['code', 'label', 'kind', 'geom_type', 'draw_state', 'element_code', 'world_x', 'world_y', 'path_json', 'display_order'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $patch)) {
                continue;
            }
            $sets[] = "{$col} = ?";
            $params[] = $patch[$col];
        }
        if ($sets === []) {
            return;
        }
        $params[] = $planId;
        $params[] = $graphicId;
        $st = $this->pdo()->prepare(
            'UPDATE mission_plan_graphics SET ' . implode(', ', $sets) . ' WHERE plan_id = ? AND id = ?'
        );
        $st->execute($params);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function timelineForPlan(int $planId, int $limit = 80): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM mission_plan_timeline WHERE plan_id = ?
                 ORDER BY
                    CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END,
                    occurred_at ASC,
                    scheduled_offset_sec ASC,
                    id ASC
                 LIMIT ' . (int) $limit
            );
            $st->execute([$planId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    public function insertTimeline(
        int $planId,
        string $source,
        string $eventCode,
        string $label,
        ?int $scheduledOffsetSec,
        ?string $occurredAt,
    ): int {
        $src = in_array($source, ['planned', 'arma', 'c2'], true) ? $source : 'c2';
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_plan_timeline
                (plan_id, source, event_code, label, scheduled_offset_sec, occurred_at)
             VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            $planId,
            $src,
            mb_substr($eventCode, 0, 32),
            mb_substr($label, 0, 255),
            $scheduledOffsetSec,
            $occurredAt,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function begin(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }
}
