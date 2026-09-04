<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\MemberIntegrationCatalog;
use PDO;
use PDOException;

final class MemberIntegrationRepository
{
    private PDO $pdo;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesExist(): bool
    {
        return $this->hasTable('member_integrations') && $this->hasTable('member_integration_steps');
    }

    private function hasTable(string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($t === '') {
            return false;
        }
        if (array_key_exists($t, $this->tableExistsCache)) {
            return $this->tableExistsCache[$t];
        }
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $st = $this->pdo->prepare(
                    "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1"
                );
                $st->execute([$t]);
                $this->tableExistsCache[$t] = (bool) $st->fetchColumn();

                return $this->tableExistsCache[$t];
            }
            $st = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($t));
            $this->tableExistsCache[$t] = $st !== false && (bool) $st->fetchColumn();
        } catch (PDOException) {
            $this->tableExistsCache[$t] = false;
        }

        return $this->tableExistsCache[$t];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
        if ($t === '' || $c === '') {
            return false;
        }
        $key = $t . '.' . $c;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $st = $this->pdo->query('PRAGMA table_info(' . $t . ')');
                $found = false;
                if ($st !== false) {
                    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                        if (strcasecmp((string) ($row['name'] ?? ''), $c) === 0) {
                            $found = true;
                            break;
                        }
                    }
                }
                $this->columnExistsCache[$key] = $found;

                return $found;
            }
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$t, $c]);
            $this->columnExistsCache[$key] = (bool) $st->fetchColumn();
        } catch (PDOException) {
            $this->columnExistsCache[$key] = false;
        }

        return $this->columnExistsCache[$key];
    }

    /**
     * Photo de compte (users.avatar_url) et portrait opérateur (personnel_profiles.character_portrait_path).
     * La colonne users.avatar_path n’existe pas : ne jamais la sélectionner.
     */
    private function memberPhotoSelectSql(string $userAlias = 'u', string $profileAlias = 'pp'): string
    {
        $avatar = 'NULL AS avatar_url';
        if ($this->hasColumn('users', 'avatar_url')) {
            $avatar = $userAlias . '.avatar_url';
        } elseif ($this->hasColumn('users', 'avatar_path')) {
            $avatar = $userAlias . '.avatar_path AS avatar_url';
        }

        $portrait = 'NULL AS character_portrait_path';
        if ($this->hasTable('personnel_profiles') && $this->hasColumn('personnel_profiles', 'character_portrait_path')) {
            $portrait = $profileAlias . '.character_portrait_path';
        }

        return $avatar . ', ' . $portrait;
    }

    private function personnelProfileJoinSql(string $userAlias = 'u', string $profileAlias = 'pp'): string
    {
        if (!$this->hasTable('personnel_profiles')) {
            return '';
        }

        return 'LEFT JOIN personnel_profiles ' . $profileAlias . ' ON ' . $profileAlias . '.user_id = ' . $userAlias . '.id';
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesExist() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT i.*, u.display_name, u.callsign, u.email, u.created_at AS user_created_at, '
            . $this->memberPhotoSelectSql() . ',
                    t.name AS template_name,
                    ru.display_name AS referent_display_name, ru.callsign AS referent_callsign, ru.email AS referent_email
             FROM member_integrations i
             INNER JOIN users u ON u.id = i.user_id AND u.tenant_id = i.tenant_id
             ' . $this->personnelProfileJoinSql() . '
             LEFT JOIN member_integration_templates t ON t.id = i.template_id AND t.tenant_id = i.tenant_id
             LEFT JOIN users ru ON ru.id = i.primary_referent_user_id
             WHERE i.tenant_id = ? AND i.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findActiveForUser(int $tenantId, int $userId): ?array
    {
        if (!$this->tablesExist() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integrations
             WHERE tenant_id = ? AND user_id = ? AND status NOT IN (?, ?)
             ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$tenantId, $userId, MemberIntegrationCatalog::STATUS_COMPLETED, MemberIntegrationCatalog::STATUS_CANCELLED]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listDashboard(int $tenantId, array $filters = [], int $limit = 200): array
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $where = ['i.tenant_id = ?'];
        $params = [$tenantId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.display_name LIKE ? OR u.callsign LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, MemberIntegrationCatalog::STATUSES, true)) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }
        $referent = (int) ($filters['referent_user_id'] ?? 0);
        if ($referent > 0) {
            $where[] = 'i.primary_referent_user_id = ?';
            $params[] = $referent;
        }
        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0 && $this->hasTable('personnel_profiles')) {
            $where[] = 'pp.primary_unit_id = ?';
            $params[] = $unitId;
        }
        $roleId = (int) ($filters['role_id'] ?? 0);
        if ($roleId > 0) {
            $where[] = '(u.role_id = ? OR EXISTS (SELECT 1 FROM tenant_user_roles tur WHERE tur.user_id = u.id AND tur.tenant_id = i.tenant_id AND tur.role_id = ?))';
            $params[] = $roleId;
            $params[] = $roleId;
        }
        $matrixId = (int) ($filters['matrix_id'] ?? 0);
        if ($matrixId > 0 && $this->hasTable('training_competency_matrix_assignments')) {
            $where[] = 'EXISTS (SELECT 1 FROM training_competency_matrix_assignments a WHERE a.tenant_id = i.tenant_id AND a.user_id = i.user_id AND a.matrix_id = ?)';
            $params[] = $matrixId;
        }
        $from = trim((string) ($filters['arrived_from'] ?? ''));
        if ($from !== '') {
            $where[] = 'u.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        $to = trim((string) ($filters['arrived_to'] ?? ''));
        if ($to !== '') {
            $where[] = 'u.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        if (!empty($filters['dossier_incomplete'])) {
            $where[] = 'i.dossier_complete = 0';
        }
        if (!empty($filters['overdue'])) {
            $where[] = 'i.overdue_count > 0';
        }

        $hasProfiles = $this->hasTable('personnel_profiles');
        $unitNameSelect = ($hasProfiles && $this->hasTable('units'))
            ? 'un.name AS unit_name'
            : 'NULL AS unit_name';
        $unitsJoin = ($hasProfiles && $this->hasTable('units'))
            ? 'LEFT JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = i.tenant_id'
            : '';
        $sql = 'SELECT i.*, u.display_name, u.callsign, u.email, u.created_at AS user_created_at, '
            . $this->memberPhotoSelectSql() . ',
                       u.role_id, r.name AS role_name, ' . $unitNameSelect . ',
                       t.name AS template_name, cs.title AS current_step_title,
                       ru.display_name AS referent_display_name, ru.callsign AS referent_callsign
                FROM member_integrations i
                INNER JOIN users u ON u.id = i.user_id AND u.tenant_id = i.tenant_id
                LEFT JOIN roles r ON r.id = u.role_id
                ' . $this->personnelProfileJoinSql() . '
                ' . $unitsJoin . '
                LEFT JOIN member_integration_templates t ON t.id = i.template_id AND t.tenant_id = i.tenant_id
                LEFT JOIN member_integration_steps cs ON cs.id = i.current_step_id AND cs.tenant_id = i.tenant_id
                LEFT JOIN users ru ON ru.id = i.primary_referent_user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY i.created_at DESC
                LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO member_integrations
                (tenant_id, user_id, template_id, template_version, status, progress_percent, primary_referent_user_id,
                 source, started_at, target_completion_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            (int) $data['user_id'],
            !empty($data['template_id']) ? (int) $data['template_id'] : null,
            max(1, (int) ($data['template_version'] ?? 1)),
            in_array((string) ($data['status'] ?? MemberIntegrationCatalog::STATUS_TO_START), MemberIntegrationCatalog::STATUSES, true)
                ? (string) ($data['status'] ?? MemberIntegrationCatalog::STATUS_TO_START)
                : MemberIntegrationCatalog::STATUS_TO_START,
            !empty($data['primary_referent_user_id']) ? (int) $data['primary_referent_user_id'] : null,
            (string) ($data['source'] ?? MemberIntegrationCatalog::SOURCE_MANUAL),
            $data['started_at'] ?? null,
            $data['target_completion_at'] ?? null,
            !empty($data['created_by']) ? (int) $data['created_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $tenantId, int $id, array $fields): bool
    {
        if ($fields === [] || !$this->findForTenant($tenantId, $id)) {
            return false;
        }
        $allowed = [
            'status', 'progress_percent', 'primary_referent_user_id', 'current_step_id', 'overdue_count',
            'dossier_complete', 'next_appointment_at', 'started_at', 'target_completion_at', 'completed_at',
            'cancelled_at',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $params[] = $fields[$col];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $tenantId;
        $params[] = $id;
        $st = $this->pdo->prepare('UPDATE member_integrations SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?');

        return $st->execute($params);
    }

    /** @return list<array<string, mixed>> */
    public function listSteps(int $tenantId, int $integrationId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integration_steps WHERE tenant_id = ? AND integration_id = ? ORDER BY position ASC, id ASC'
        );
        $st->execute([$tenantId, $integrationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findStep(int $tenantId, int $stepId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM member_integration_steps WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $stepId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createStep(int $tenantId, int $integrationId, array $data): int
    {
        $cfg = $data['configuration_json'] ?? null;
        if (is_array($cfg)) {
            $cfg = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_steps
                (tenant_id, integration_id, template_step_id, step_key, position, title, description, step_type,
                 responsible_kind, responsible_user_id, status, due_at, is_required, is_member_visible,
                 linked_matrix_id, linked_course_id, linked_document_id, configuration_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $integrationId,
            !empty($data['template_step_id']) ? (int) $data['template_step_id'] : null,
            mb_substr((string) ($data['step_key'] ?? 'step'), 0, 64),
            max(1, (int) ($data['position'] ?? 1)),
            mb_substr(trim((string) ($data['title'] ?? 'Étape')), 0, 180),
            $data['description'] ?? null,
            (string) ($data['step_type'] ?? MemberIntegrationCatalog::TYPE_TASK),
            (string) ($data['responsible_kind'] ?? MemberIntegrationCatalog::RESP_MEMBER),
            !empty($data['responsible_user_id']) ? (int) $data['responsible_user_id'] : null,
            (string) ($data['status'] ?? MemberIntegrationCatalog::STEP_PENDING),
            $data['due_at'] ?? null,
            !isset($data['is_required']) || !empty($data['is_required']) ? 1 : 0,
            !isset($data['is_member_visible']) || !empty($data['is_member_visible']) ? 1 : 0,
            !empty($data['linked_matrix_id']) ? (int) $data['linked_matrix_id'] : null,
            !empty($data['linked_course_id']) ? (int) $data['linked_course_id'] : null,
            !empty($data['linked_document_id']) ? (int) $data['linked_document_id'] : null,
            $cfg !== false && $cfg !== null && $cfg !== '' ? $cfg : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateStep(int $tenantId, int $stepId, array $fields): bool
    {
        $allowed = [
            'status', 'responsible_user_id', 'due_at', 'started_at', 'completed_at', 'validated_by',
            'force_reason', 'linked_personnel_bilan_id',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $params[] = $fields[$col];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $tenantId;
        $params[] = $stepId;
        $st = $this->pdo->prepare('UPDATE member_integration_steps SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?');

        return $st->execute($params);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function addEvent(
        int $tenantId,
        int $integrationId,
        string $eventType,
        string $visibility,
        ?string $message,
        ?int $actorUserId,
        ?int $stepId = null,
        array $meta = []
    ): int {
        if (!$this->hasTable('member_integration_events')) {
            return 0;
        }
        $json = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_events
                (tenant_id, integration_id, step_id, actor_user_id, event_type, visibility, message, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $integrationId,
            $stepId,
            $actorUserId,
            mb_substr($eventType, 0, 64),
            $visibility === MemberIntegrationCatalog::VISIBILITY_MEMBER
                ? MemberIntegrationCatalog::VISIBILITY_MEMBER
                : MemberIntegrationCatalog::VISIBILITY_STAFF,
            $message,
            $json !== false ? $json : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function hasEventType(int $tenantId, int $integrationId, string $eventType): bool
    {
        if (!$this->hasTable('member_integration_events')) {
            return false;
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM member_integration_events WHERE tenant_id = ? AND integration_id = ? AND event_type = ? LIMIT 1'
        );
        $st->execute([$tenantId, $integrationId, $eventType]);

        return (bool) $st->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listEvents(int $tenantId, int $integrationId, ?string $visibility = null, int $limit = 200): array
    {
        if (!$this->hasTable('member_integration_events')) {
            return [];
        }
        $limit = max(1, min(400, $limit));
        $sql = 'SELECT e.*, u.display_name AS actor_display_name, u.callsign AS actor_callsign
                FROM member_integration_events e
                LEFT JOIN users u ON u.id = e.actor_user_id
                WHERE e.tenant_id = ? AND e.integration_id = ?';
        $params = [$tenantId, $integrationId];
        if ($visibility === MemberIntegrationCatalog::VISIBILITY_MEMBER) {
            $sql .= ' AND e.visibility = ?';
            $params[] = MemberIntegrationCatalog::VISIBILITY_MEMBER;
        }
        $sql .= ' ORDER BY e.created_at DESC, e.id DESC LIMIT ' . $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setReferents(int $tenantId, int $integrationId, int $primaryUserId, array $secondaryUserIds): void
    {
        if (!$this->hasTable('member_integration_referents')) {
            return;
        }
        $this->pdo->prepare('DELETE FROM member_integration_referents WHERE tenant_id = ? AND integration_id = ?')
            ->execute([$tenantId, $integrationId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO member_integration_referents (tenant_id, integration_id, user_id, is_primary, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        if ($primaryUserId > 0) {
            $ins->execute([$tenantId, $integrationId, $primaryUserId, 1]);
        }
        foreach (array_unique(array_map('intval', $secondaryUserIds)) as $uid) {
            if ($uid < 1 || $uid === $primaryUserId) {
                continue;
            }
            $ins->execute([$tenantId, $integrationId, $uid, 0]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listReferents(int $tenantId, int $integrationId): array
    {
        if (!$this->hasTable('member_integration_referents')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT r.*, u.display_name, u.callsign, u.email
             FROM member_integration_referents r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.tenant_id = ? AND r.integration_id = ?
             ORDER BY r.is_primary DESC, u.display_name ASC'
        );
        $st->execute([$tenantId, $integrationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<int> */
    public function listActiveUserIds(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT user_id FROM member_integrations WHERE tenant_id = ? AND status NOT IN (?, ?)'
        );
        $st->execute([$tenantId, MemberIntegrationCatalog::STATUS_COMPLETED, MemberIntegrationCatalog::STATUS_CANCELLED]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
