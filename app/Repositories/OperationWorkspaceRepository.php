<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\SilentSchemaMigration;
use PDO;

final class OperationWorkspaceRepository
{
    use LazyDatabaseConnection;

    protected function onDatabaseConnected(PDO $pdo): void
    {
        SilentSchemaMigration::run(base_path('bootstrap/operations_workspace_migration.php'), $pdo);
    }

    public function tableReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operations' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, ?string $status = null): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return [];
        }
        $sql = 'SELECT o.*, p.name AS phase_name, p.code AS phase_code, p.seq AS phase_seq
                FROM operations o
                LEFT JOIN operation_phases p ON p.id = o.current_phase_id AND p.tenant_id = o.tenant_id
                WHERE o.tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(o.status, \'active\', \'planned\', \'paused\', \'draft\', \'closed\'), o.updated_at DESC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForTenant(int $tenantId): int
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM operations WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1 || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT o.*, p.name AS phase_name, p.code AS phase_code, p.seq AS phase_seq
             FROM operations o
             LEFT JOIN operation_phases p ON p.id = o.current_phase_id AND p.tenant_id = o.tenant_id
             WHERE o.tenant_id = ? AND o.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(int $tenantId, string $uuid): ?array
    {
        if ($tenantId < 1 || $uuid === '' || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT o.*, p.name AS phase_name, p.code AS phase_code, p.seq AS phase_seq
             FROM operations o
             LEFT JOIN operation_phases p ON p.id = o.current_phase_id AND p.tenant_id = o.tenant_id
             WHERE o.tenant_id = ? AND o.uuid = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $uuid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO operations
                (tenant_id, uuid, code, name, classification, status, commander_user_id,
                 start_at, end_at, map_id, workspace_key, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            (string) $data['uuid'],
            (string) $data['code'],
            (string) $data['name'],
            (string) ($data['classification'] ?? 'restricted'),
            (string) ($data['status'] ?? 'draft'),
            $data['commander_user_id'] ?? null,
            $data['start_at'] ?? null,
            $data['end_at'] ?? null,
            $data['map_id'] ?? null,
            $data['workspace_key'] ?? null,
            $data['description'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $operationId, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['name', 'classification', 'status', 'commander_user_id', 'start_at', 'end_at', 'current_phase_id', 'map_id', 'workspace_key', 'description', 'mission_plan_id', 'cycle_id'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = $col . ' = ?';
                $params[] = $data[$col];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $operationId;
        $params[] = $tenantId;
        $this->pdo()->prepare(
            'UPDATE operations SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?'
        )->execute($params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPhases(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM operation_phases WHERE tenant_id = ? AND operation_id = ? ORDER BY seq ASC, id ASC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertPhase(int $tenantId, int $operationId, int $seq, string $code, string $name, ?string $intent = null): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO operation_phases (tenant_id, operation_id, seq, code, name, intent) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $operationId, $seq, $code, $name, $intent]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOverlays(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM planning_overlays WHERE tenant_id = ? AND operation_id = ? ORDER BY id ASC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOverlay(int $tenantId, int $overlayId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM planning_overlays WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $overlayId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insertOverlay(int $tenantId, int $operationId, string $name, string $kind, string $visibility, ?int $createdBy): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO planning_overlays
                (tenant_id, operation_id, name, kind, visibility, workflow, current_version, created_by)
             VALUES (?, ?, ?, ?, ?, \'draft\', 1, ?)'
        );
        $st->execute([$tenantId, $operationId, $name, $kind, $visibility, $createdBy]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateOverlay(int $tenantId, int $overlayId, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['name', 'kind', 'visibility', 'workflow', 'current_version', 'published_version'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = $col . ' = ?';
                $params[] = $data[$col];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $overlayId;
        $params[] = $tenantId;
        $this->pdo()->prepare(
            'UPDATE planning_overlays SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?'
        )->execute($params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOverlayVersions(int $tenantId, int $overlayId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM planning_overlay_versions WHERE tenant_id = ? AND overlay_id = ? ORDER BY version DESC'
        );
        $st->execute([$tenantId, $overlayId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertOverlayVersion(int $tenantId, int $overlayId, int $version, string $workflow, string $snapshotJson, ?string $note, ?int $createdBy): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO planning_overlay_versions
                (tenant_id, overlay_id, version, workflow, snapshot_json, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $overlayId, $version, $workflow, $snapshotJson, $note, $createdBy]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLayers(int $tenantId, int $operationId, ?int $overlayId = null): array
    {
        $sql = 'SELECT * FROM planning_layers WHERE tenant_id = ? AND operation_id = ?';
        $params = [$tenantId, $operationId];
        if ($overlayId !== null) {
            $sql .= ' AND overlay_id = ?';
            $params[] = $overlayId;
        }
        $sql .= ' ORDER BY display_order ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertLayer(int $tenantId, int $operationId, int $overlayId, string $name, string $kind, int $order = 0): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO planning_layers
                (tenant_id, operation_id, overlay_id, name, kind, visible, display_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $st->execute([$tenantId, $operationId, $overlayId, $name, $kind, $order]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listObjects(int $tenantId, int $operationId, ?int $overlayId = null, bool $publishedOnly = false): array
    {
        $sql = 'SELECT o.* FROM planning_objects o
                INNER JOIN planning_overlays ov ON ov.id = o.overlay_id AND ov.tenant_id = o.tenant_id
                WHERE o.tenant_id = ? AND o.operation_id = ?';
        $params = [$tenantId, $operationId];
        if ($overlayId !== null) {
            $sql .= ' AND o.overlay_id = ?';
            $params[] = $overlayId;
        }
        if ($publishedOnly) {
            $sql .= ' AND ov.workflow = \'published\' AND ov.visibility = \'published\'';
        }
        $sql .= ' ORDER BY o.id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findObjectByUuid(int $tenantId, string $uuid): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM planning_objects WHERE tenant_id = ? AND uuid = ? LIMIT 1'
        );
        $st->execute([$tenantId, $uuid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertObject(int $tenantId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO planning_objects
                (tenant_id, operation_id, overlay_id, layer_id, uuid, graphic_type, name, affiliation,
                 status, phase_id, all_phases, element_code, description, classification,
                 geometry_json, meta_json, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $data['operation_id'],
            $data['overlay_id'],
            $data['layer_id'] ?? null,
            $data['uuid'],
            $data['graphic_type'],
            $data['name'],
            $data['affiliation'] ?? 'friendly',
            $data['status'] ?? 'planned',
            $data['phase_id'] ?? null,
            !empty($data['all_phases']) ? 1 : 0,
            $data['element_code'] ?? null,
            $data['description'] ?? null,
            $data['classification'] ?? 'restricted',
            $data['geometry_json'] ?? null,
            $data['meta_json'] ?? null,
            $data['created_by'] ?? null,
            $data['updated_by'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateObject(int $tenantId, string $uuid, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['name', 'affiliation', 'status', 'phase_id', 'all_phases', 'element_code', 'description', 'classification', 'geometry_json', 'meta_json', 'overlay_id', 'layer_id', 'updated_by'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = $col . ' = ?';
                $params[] = $data[$col];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $uuid;
        $params[] = $tenantId;
        $this->pdo()->prepare(
            'UPDATE planning_objects SET ' . implode(', ', $fields) . ' WHERE uuid = ? AND tenant_id = ?'
        )->execute($params);
    }

    public function deleteObject(int $tenantId, string $uuid): void
    {
        $this->pdo()->prepare('DELETE FROM planning_objects WHERE tenant_id = ? AND uuid = ?')->execute([$tenantId, $uuid]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTasks(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM operation_tasks WHERE tenant_id = ? AND operation_id = ? ORDER BY id ASC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTask(int $tenantId, int $operationId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO operation_tasks
                (tenant_id, operation_id, code, title, assigned_element, supporting_element,
                 h_offset, status, overlay_object_id, order_ref, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $operationId,
            $data['code'],
            $data['title'],
            $data['assigned_element'] ?? null,
            $data['supporting_element'] ?? null,
            $data['h_offset'] ?? null,
            $data['status'] ?? 'upcoming',
            $data['overlay_object_id'] ?? null,
            $data['order_ref'] ?? null,
            $data['description'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateTaskStatus(int $tenantId, int $taskId, string $status): void
    {
        $this->pdo()->prepare(
            'UPDATE operation_tasks SET status = ? WHERE id = ? AND tenant_id = ?'
        )->execute([$status, $taskId, $tenantId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTargets(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM target_nodes WHERE tenant_id = ? AND operation_id = ? ORDER BY id ASC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTarget(int $tenantId, int $operationId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO target_nodes
                (tenant_id, operation_id, target_code, name, classification, target_type, category,
                 mgrs, confidence, source_reliability, sse_person_id, sse_case_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $operationId,
            $data['target_code'],
            $data['name'],
            $data['classification'] ?? 'restricted',
            $data['target_type'] ?? null,
            $data['category'] ?? null,
            $data['mgrs'] ?? null,
            $data['confidence'] ?? null,
            $data['source_reliability'] ?? null,
            $data['sse_person_id'] ?? null,
            $data['sse_case_id'] ?? null,
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM operation_orders WHERE tenant_id = ? AND operation_id = ? ORDER BY id DESC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertOrder(int $tenantId, int $operationId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO operation_orders
                (tenant_id, operation_id, kind, code, title, workflow, overlay_refs_json, sections_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $operationId,
            $data['kind'] ?? 'opord',
            $data['code'],
            $data['title'],
            $data['workflow'] ?? 'draft',
            $data['overlay_refs_json'] ?? null,
            $data['sections_json'] ?? null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateOrder(int $tenantId, int $orderId, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['title', 'workflow', 'overlay_refs_json', 'sections_json', 'current_version', 'published_version'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = $col . ' = ?';
                $params[] = $data[$col];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $orderId;
        $params[] = $tenantId;
        $this->pdo()->prepare(
            'UPDATE operation_orders SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?'
        )->execute($params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listElements(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM operation_elements WHERE tenant_id = ? AND operation_id = ? ORDER BY display_order ASC, id ASC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertElement(int $tenantId, int $operationId, string $code, string $name, string $kind, int $order = 0, ?int $parentId = null): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO operation_elements
                (tenant_id, operation_id, parent_id, code, name, kind, display_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $operationId, $parentId, $code, $name, $kind, $order]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMembers(int $tenantId, int $operationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT m.*, u.callsign, u.display_name, u.email
             FROM operation_members m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.tenant_id = ? AND m.operation_id = ?
             ORDER BY m.id ASC'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertMember(int $tenantId, int $operationId, int $userId, ?string $billet, ?string $elementCode): void
    {
        $st = $this->pdo()->prepare(
            'INSERT IGNORE INTO operation_members
                (tenant_id, operation_id, user_id, billet, element_code, status)
             VALUES (?, ?, ?, ?, ?, \'assigned\')'
        );
        $st->execute([$tenantId, $operationId, $userId, $billet, $elementCode]);
    }

    public function logActivity(int $tenantId, int $operationId, ?int $actorUserId, string $action, ?string $objectLabel, ?array $details = null): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO operation_activity
                (tenant_id, operation_id, actor_user_id, action, object_label, details_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $operationId,
            $actorUserId,
            $action,
            $objectLabel,
            $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActivity(int $tenantId, int $operationId, int $limit = 80): array
    {
        $st = $this->pdo()->prepare(
            'SELECT a.*, u.callsign, u.display_name
             FROM operation_activity a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE a.tenant_id = ? AND a.operation_id = ?
             ORDER BY a.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLock(int $tenantId, int $operationId, string $objectUuid): ?array
    {
        $this->pdo()->prepare(
            'DELETE FROM realtime_object_locks WHERE expires_at < NOW()'
        )->execute();
        $st = $this->pdo()->prepare(
            'SELECT * FROM realtime_object_locks
             WHERE tenant_id = ? AND operation_id = ? AND object_uuid = ? LIMIT 1'
        );
        $st->execute([$tenantId, $operationId, $objectUuid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function claimLock(int $tenantId, int $operationId, string $objectUuid, int $userId, int $seconds = 90): bool
    {
        $existing = $this->findLock($tenantId, $operationId, $objectUuid);
        if ($existing !== null && (int) $existing['user_id'] !== $userId) {
            return false;
        }
        if ($existing !== null) {
            $this->pdo()->prepare(
                'UPDATE realtime_object_locks SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                 WHERE id = ? AND tenant_id = ?'
            )->execute([$seconds, $existing['id'], $tenantId]);

            return true;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO realtime_object_locks
                (tenant_id, operation_id, object_uuid, user_id, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
        );
        $st->execute([$tenantId, $operationId, $objectUuid, $userId, $seconds]);

        return true;
    }

    public function releaseLock(int $tenantId, int $operationId, string $objectUuid, int $userId): void
    {
        $this->pdo()->prepare(
            'DELETE FROM realtime_object_locks
             WHERE tenant_id = ? AND operation_id = ? AND object_uuid = ? AND user_id = ?'
        )->execute([$tenantId, $operationId, $objectUuid, $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLocks(int $tenantId, int $operationId): array
    {
        $this->pdo()->prepare('DELETE FROM realtime_object_locks WHERE expires_at < NOW()')->execute();
        $st = $this->pdo()->prepare(
            'SELECT l.*, u.callsign, u.display_name
             FROM realtime_object_locks l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.tenant_id = ? AND l.operation_id = ?'
        );
        $st->execute([$tenantId, $operationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function codeExists(int $tenantId, string $code): bool
    {
        $st = $this->pdo()->prepare('SELECT 1 FROM operations WHERE tenant_id = ? AND code = ? LIMIT 1');
        $st->execute([$tenantId, $code]);

        return (bool) $st->fetchColumn();
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
