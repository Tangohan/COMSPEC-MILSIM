<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlanningEntryRepository
{
    private PDO $pdo;

    /** @var array<string, bool> */
    private static array $tableExistsCache = [];

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Indique si les tables du tableau opérationnel (extensions DDL pipeline) sont présentes. */
    public function isOperationalBoardSchemaReady(): bool
    {
        if (!$this->hasTable('planning_entries')) {
            return false;
        }
        // Colonnes minimales pour les listes / publication
        foreach (['operational_status', 'validation_status', 'chief_user_id', 'priority', 'entry_type', 'status'] as $col) {
            if (!$this->columnExists('planning_entries', $col)) {
                return false;
            }
        }

        return true;
    }

    /** Expression SQL : libellé membre (display_name → indicatif → e-mail). */
    private function sqlUserDisplayLabel(string $alias): string
    {
        return "COALESCE(NULLIF(TRIM({$alias}.display_name), ''), NULLIF(TRIM({$alias}.callsign), ''), {$alias}.email)";
    }

    /**
     * Fragments SELECT partagés board / portail.
     *
     * @return array{0:string,1:string} [select extras, joins]
     */
    private function boardListSelectFragments(): array
    {
        $chief = $this->sqlUserDisplayLabel('chief');
        $deputy = $this->sqlUserDisplayLabel('deputy');
        $repl = $this->sqlUserDisplayLabel('repl');
        $tags = $this->hasTable('planning_entry_tags')
            ? '(SELECT GROUP_CONCAT(DISTINCT t.tag ORDER BY t.tag SEPARATOR ", ") FROM planning_entry_tags t WHERE t.planning_entry_id = e.id) AS tags_list'
            : 'NULL AS tags_list';
        $clReq = $this->hasTable('planning_entry_checklists')
            ? '(SELECT COUNT(*) FROM planning_entry_checklists cl WHERE cl.planning_entry_id = e.id AND cl.is_required = 1) AS checklist_required'
            : '0 AS checklist_required';
        $clDone = $this->hasTable('planning_entry_checklists')
            ? '(SELECT COUNT(*) FROM planning_entry_checklists cl WHERE cl.planning_entry_id = e.id AND cl.is_required = 1 AND cl.is_done = 1) AS checklist_done'
            : '0 AS checklist_done';
        $catJoin = $this->hasTable('planning_categories')
            ? 'LEFT JOIN planning_categories c ON c.id = e.category_id'
            : '';
        $catCols = $this->hasTable('planning_categories')
            ? 'c.name AS category_name, c.color AS category_color'
            : 'NULL AS category_name, NULL AS category_color';

        $select = "{$catCols},
                {$chief} AS chief_name, {$deputy} AS deputy_name, {$repl} AS replacement_name,
                {$tags},
                {$clReq},
                {$clDone}";
        $joins = trim("{$catJoin}
            LEFT JOIN users chief ON chief.id = e.chief_user_id
            LEFT JOIN users deputy ON deputy.id = e.deputy_user_id
            LEFT JOIN users repl ON repl.id = e.replacement_user_id");

        return [$select, $joins];
    }

    private function hasTable(string $table): bool
    {
        if (isset(self::$tableExistsCache[$table])) {
            return self::$tableExistsCache[$table];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            self::$tableExistsCache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$tableExistsCache[$table] = false;
        }

        return self::$tableExistsCache[$table];
    }

    /** @return list<array<string,mixed>> */
    public function listForBoard(int $tenantId, array $filters = []): array
    {
        if (!$this->hasTable('planning_entries')) {
            return [];
        }
        $where = ['e.tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenantId];

        $status = trim((string) ($filters['status'] ?? 'active'));
        if ($status !== '' && $status !== 'all') {
            $where[] = 'e.status = :status';
            $params['status'] = $status;
        }

        foreach (['operational_status', 'entry_type'] as $filterKey) {
            $value = trim((string) ($filters[$filterKey] ?? ''));
            if ($value !== '') {
                $where[] = 'e.' . $filterKey . ' = :' . $filterKey;
                $params[$filterKey] = $value;
            }
        }

        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM planning_entry_tags t WHERE t.planning_entry_id = e.id AND t.tag = :tag)';
            $params['tag'] = $tag;
        }

        $mode = trim((string) ($filters['mode'] ?? 'standard'));
        if ($mode === 'crise') {
            $where[] = '(e.priority = "critical" OR e.entry_type = "permanence" OR e.operational_status = "in_progress")';
        }
        if (!empty($filters['critical_only'])) {
            $where[] = 'e.priority = "critical"';
        }

        $periodStart = trim((string) ($filters['period_start'] ?? ''));
        $periodEnd = trim((string) ($filters['period_end'] ?? ''));
        if ($periodStart !== '' && $periodEnd !== '') {
            $where[] = '((e.start_date IS NULL OR e.start_date <= :period_end) AND (e.end_date IS NULL OR e.end_date >= :period_start))';
            $params['period_start'] = $periodStart;
            $params['period_end'] = $periodEnd;
        }

        [$selectExtras, $joins] = $this->boardListSelectFragments();
        $query = "SELECT e.*, {$selectExtras}
            FROM planning_entries e
            {$joins}
            WHERE " . implode(' AND ', $where) . '
            ORDER BY FIELD(e.priority, "critical", "high", "normal", "low"), e.display_order ASC, COALESCE(e.start_date, e.created_at) ASC, e.id DESC';

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log('[PlanningEntryRepository::listForBoard] ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function listCategories(int $tenantId): array
    {
        if (!$this->hasTable('planning_categories')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id, name, color, icon FROM planning_categories WHERE tenant_id = ? ORDER BY name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPlanningCategory(int $tenantId, string $name, string $color = '#334155'): ?int
    {
        if (!$this->hasTable('planning_categories') || $tenantId < 1) {
            return null;
        }
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $color = trim($color);
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#334155';
        }
        try {
            $stmt = $this->pdo->prepare('INSERT INTO planning_categories (tenant_id, name, color) VALUES (?, ?, ?)');
            $stmt->execute([$tenantId, $name, $color]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException) {
            return null;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listTemplates(int $tenantId): array
    {
        if (!$this->hasTable('planning_templates')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id, name, template_type, payload_json FROM planning_templates WHERE tenant_id = ? AND is_active = 1 ORDER BY name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPlanningEntriesByStatus(int $tenantId, string $status): int
    {
        if (!$this->hasTable('planning_entries') || $tenantId < 1) {
            return 0;
        }
        if (!in_array($status, ['draft', 'active', 'archived', 'cancelled'], true)) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM planning_entries WHERE tenant_id = ? AND status = ?');
            $stmt->execute([$tenantId, $status]);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * Insère des modèles d’exemple lorsque le tenant n’en a aucun (idempotent par comptage).
     */
    public function ensureDefaultPlanningTemplatesIfEmpty(int $tenantId, int $actorUserId): void
    {
        if (!$this->hasTable('planning_templates') || $tenantId < 1) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM planning_templates WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        $blank = static function (): array {
            return [
                'category_id' => 0,
                'linked_type' => null,
                'linked_id' => null,
                'all_day' => 1,
                'priority' => 'normal',
                'display_order' => 100,
                'visibility_scope' => 'tenant',
                'security_level' => 'unit_public',
                'operational_status' => 'planned',
                'phase_current' => 'phase_1',
                'chief_user_id' => null,
                'deputy_user_id' => null,
                'replacement_user_id' => null,
                'replacement_auto_activate' => 0,
                'command_chain' => null,
                'accountability_note' => null,
                'location_lat' => null,
                'location_lng' => null,
                'operation_zone' => null,
                'map_link' => null,
                'dossier_ref' => null,
                'legal_constraints' => null,
                'fire_window_start' => null,
                'fire_window_end' => null,
            ];
        };
        $defs = [
            [
                'name' => 'Permanence de formation',
                'type' => 'instruction',
                'payload' => array_merge($blank(), [
                    'title' => 'Permanence de formation — intitulé à préciser',
                    'description' => "Dispositif d’accueil et de veille pédagogique pendant une période de formation : point d’information, liaison avec l’encadrement, consignes de sécurité et d’organisation. Indiquez les dates et le public concernés avant mise en ligne.",
                    'entry_type' => 'permanence',
                ]),
            ],
            [
                'name' => 'Mission type — ordre général',
                'type' => 'mission_judiciaire',
                'payload' => array_merge($blank(), [
                    'title' => 'Mission — objet à préciser',
                    'description' => "Squelette de mission : contexte, objectifs, effectifs prévus, moyens, fenêtre d’action. Complétez chaque bloc puis validez avant publication sur le mur.",
                    'entry_type' => 'mission',
                ]),
            ],
            [
                'name' => 'Flash information (court)',
                'type' => 'custom',
                'payload' => array_merge($blank(), [
                    'title' => 'Information courte',
                    'description' => "Texte du flash lisible en quelques secondes. Précisez la période de validité dans les dates du tableau.",
                    'entry_type' => 'flash_info',
                ]),
            ],
        ];
        foreach ($defs as $def) {
            $this->createPlanningTemplate($tenantId, (string) $def['name'], (string) $def['type'], $def['payload'], $actorUserId);
        }
    }

    /**
     * @param array<string, mixed> $payload Champs acceptés par {@see create()} (sans tenant_id / created_by obligatoires).
     */
    public function createPlanningTemplate(int $tenantId, string $name, string $templateType, array $payload, int $createdBy): ?int
    {
        if (!$this->hasTable('planning_templates') || $tenantId < 1) {
            return null;
        }
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $allowed = ['permanence_opj', 'mission_judiciaire', 'instruction', 'dispositif_securite', 'exercice', 'custom'];
        if (!in_array($templateType, $allowed, true)) {
            $templateType = 'custom';
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO planning_templates (tenant_id, name, template_type, payload_json, is_active, created_by) VALUES (?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([
                $tenantId,
                $name,
                $templateType,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $createdBy > 0 ? $createdBy : null,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * Extrait de la ligne SQL d’une fiche les champs réutilisables comme modèle (sans dates ni liaison métier).
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function planningEntryRowToTemplatePayload(array $row): array
    {
        $keys = [
            'title', 'description', 'entry_type', 'category_id', 'all_day', 'priority', 'display_order',
            'visibility_scope', 'security_level', 'operational_status', 'phase_current',
            'chief_user_id', 'deputy_user_id', 'replacement_user_id', 'replacement_auto_activate',
            'command_chain', 'accountability_note', 'location_lat', 'location_lng',
            'operation_zone', 'map_link', 'dossier_ref', 'legal_constraints',
            'fire_window_start', 'fire_window_end',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if ($v === null || $v === '') {
                $out[$k] = null;

                continue;
            }
            if (is_string($v)) {
                $trim = trim($v);
                $out[$k] = $trim === '' ? null : $trim;
            } elseif (is_int($v) || is_float($v)) {
                $out[$k] = $v;
            } else {
                $out[$k] = $v;
            }
        }
        if ($this->columnExists('planning_entries', 'visibility_unit_id')) {
            $uid = isset($row['visibility_unit_id']) ? (int) $row['visibility_unit_id'] : 0;
            $out['visibility_unit_id'] = $uid > 0 ? $uid : null;
            $jr = $row['visibility_job_role_ids'] ?? null;
            $out['visibility_job_role_ids'] = is_string($jr) && trim($jr) !== '' ? trim($jr) : null;
        }
        $out['start_date'] = null;
        $out['end_date'] = null;
        $out['linked_type'] = null;
        $out['linked_id'] = null;
        $cat = isset($row['category_id']) ? (int) $row['category_id'] : 0;
        $out['category_id'] = $cat > 0 ? $cat : 0;
        if (!isset($out['all_day'])) {
            $out['all_day'] = 1;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function normalizePayloadForNewEntryFromTemplate(array $payload, int $tenantId, int $actorUserId, string $fallbackTitle): array
    {
        unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['frago_parent_entry_id'], $payload['frago_version']);
        $payload['tenant_id'] = $tenantId;
        $payload['created_by'] = $actorUserId;
        $payload['status'] = 'draft';
        $payload['validation_status'] = 'draft';
        $payload['operational_status'] = 'planned';
        $payload['start_date'] = null;
        $payload['end_date'] = null;
        $payload['linked_type'] = null;
        $payload['linked_id'] = null;
        $t = trim((string) ($payload['title'] ?? ''));
        $payload['title'] = $t !== '' ? $t : $fallbackTitle;
        if (!isset($payload['all_day'])) {
            $payload['all_day'] = 1;
        }
        if (!isset($payload['entry_type']) || (string) $payload['entry_type'] === '') {
            $payload['entry_type'] = 'task';
        }
        if (!isset($payload['visibility_scope']) || (string) $payload['visibility_scope'] === '') {
            $payload['visibility_scope'] = 'tenant';
        }
        if (!isset($payload['security_level']) || (string) $payload['security_level'] === '') {
            $payload['security_level'] = 'unit_public';
        }
        if (!isset($payload['priority']) || (string) $payload['priority'] === '') {
            $payload['priority'] = 'normal';
        }
        if (!isset($payload['display_order'])) {
            $payload['display_order'] = 100;
        }
        if (!isset($payload['phase_current']) || (string) $payload['phase_current'] === '') {
            $payload['phase_current'] = 'phase_1';
        }
        if (isset($payload['visibility_job_role_ids']) && is_array($payload['visibility_job_role_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $payload['visibility_job_role_ids']), static fn (int $i): bool => $i > 0));
            $payload['visibility_job_role_ids'] = $ids === [] ? null : json_encode($ids, JSON_UNESCAPED_UNICODE);
        }

        return $payload;
    }

    /** @return list<array<string,mixed>> */
    public function listTags(int $tenantId): array
    {
        if (!$this->hasTable('planning_entry_tags') || !$this->hasTable('planning_entries')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT t.tag, COUNT(*) AS total
            FROM planning_entry_tags t
            INNER JOIN planning_entries e ON e.id = t.planning_entry_id
            WHERE e.tenant_id = ?
            GROUP BY t.tag ORDER BY total DESC, t.tag ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function getPosture(int $tenantId): ?array
    {
        if (!$this->hasTable('operational_postures')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM operational_postures WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function setPosture(int $tenantId, string $posture, int $actorUserId): void
    {
        if (!$this->hasTable('operational_postures')) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO operational_postures (tenant_id, posture_level, updated_by)
             VALUES (:tenant_id, :posture_level, :updated_by)
             ON DUPLICATE KEY UPDATE posture_level = VALUES(posture_level), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(['tenant_id' => $tenantId, 'posture_level' => $posture, 'updated_by' => $actorUserId]);
    }

    /** @param array<string,mixed> $payload */
    public function create(array $payload): int
    {
        if (!$this->hasTable('planning_entries')) {
            return 0;
        }
        $hasVisUnit = $this->columnExists('planning_entries', 'visibility_unit_id');
        $visUnitSql = $hasVisUnit ? ', visibility_unit_id, visibility_job_role_ids' : '';
        $stmt = $this->pdo->prepare('INSERT INTO planning_entries (
                tenant_id, title, description, entry_type, category_id, linked_type, linked_id,
                start_date, end_date, all_day, status, validation_status, priority, display_order,
                visibility_scope' . $visUnitSql . ', security_level, operational_status, phase_current, created_by,
                chief_user_id, deputy_user_id, replacement_user_id, replacement_auto_activate,
                command_chain, accountability_note, location_lat, location_lng, operation_zone, map_link,
                dossier_ref, legal_constraints, fire_window_start, fire_window_end
            ) VALUES (
                :tenant_id, :title, :description, :entry_type, :category_id, :linked_type, :linked_id,
                :start_date, :end_date, :all_day, :status, :validation_status, :priority, :display_order,
                :visibility_scope' . ($hasVisUnit ? ', :visibility_unit_id, :visibility_job_role_ids' : '') . ', :security_level, :operational_status, :phase_current, :created_by,
                :chief_user_id, :deputy_user_id, :replacement_user_id, :replacement_auto_activate,
                :command_chain, :accountability_note, :location_lat, :location_lng, :operation_zone, :map_link,
                :dossier_ref, :legal_constraints, :fire_window_start, :fire_window_end
            )');

        $execParams = [
            'tenant_id' => (int) $payload['tenant_id'], 'title' => (string) $payload['title'],
            'description' => $payload['description'] ?: null, 'entry_type' => (string) $payload['entry_type'],
            'category_id' => $payload['category_id'] ?: null, 'linked_type' => $payload['linked_type'] ?: null,
            'linked_id' => $payload['linked_id'] ?: null, 'start_date' => $payload['start_date'] ?: null,
            'end_date' => $payload['end_date'] ?: null, 'all_day' => !empty($payload['all_day']) ? 1 : 0,
            'status' => (string) ($payload['status'] ?? 'draft'), 'validation_status' => (string) ($payload['validation_status'] ?? 'draft'),
            'priority' => (string) ($payload['priority'] ?? 'normal'), 'display_order' => (int) ($payload['display_order'] ?? 100),
            'visibility_scope' => (string) ($payload['visibility_scope'] ?? 'tenant'), 'security_level' => (string) ($payload['security_level'] ?? 'unit_public'),
            'operational_status' => (string) ($payload['operational_status'] ?? 'planned'), 'phase_current' => (string) ($payload['phase_current'] ?? 'phase_1'),
            'created_by' => (int) ($payload['created_by'] ?? 0), 'chief_user_id' => $payload['chief_user_id'] ?: null,
            'deputy_user_id' => $payload['deputy_user_id'] ?: null, 'replacement_user_id' => $payload['replacement_user_id'] ?: null,
            'replacement_auto_activate' => !empty($payload['replacement_auto_activate']) ? 1 : 0,
            'command_chain' => $payload['command_chain'] ?: null, 'accountability_note' => $payload['accountability_note'] ?: null,
            'location_lat' => $payload['location_lat'] ?: null, 'location_lng' => $payload['location_lng'] ?: null,
            'operation_zone' => $payload['operation_zone'] ?: null, 'map_link' => $payload['map_link'] ?: null,
            'dossier_ref' => $payload['dossier_ref'] ?: null, 'legal_constraints' => $payload['legal_constraints'] ?: null,
            'fire_window_start' => $payload['fire_window_start'] ?: null, 'fire_window_end' => $payload['fire_window_end'] ?: null,
        ];
        if ($hasVisUnit) {
            $execParams['visibility_unit_id'] = !empty($payload['visibility_unit_id']) ? (int) $payload['visibility_unit_id'] : null;
            $jr = $payload['visibility_job_role_ids'] ?? null;
            $execParams['visibility_job_role_ids'] = is_string($jr) && $jr !== '' ? $jr : null;
        }
        $stmt->execute($execParams);

        $entryId = (int) $this->pdo->lastInsertId();
        $this->logAction($entryId, (int) ($payload['created_by'] ?? 0), 'create', 'Entrée créée');
        $this->registerRealtimeEvent((int) $payload['tenant_id'], $entryId, 'entry_created', ['title' => (string) $payload['title']]);

        return $entryId;
    }

    public function transitionValidation(int $tenantId, int $entryId, string $validationStatus, ?string $reason, int $actorUserId): bool
    {
        if (!$this->hasTable('planning_entries')) {
            return false;
        }
        $allowed = ['draft', 'validated', 'active', 'rejected'];
        if (!in_array($validationStatus, $allowed, true)) {
            return false;
        }
        $status = in_array($validationStatus, ['active', 'validated'], true) ? 'active' : 'draft';
        if ($validationStatus === 'rejected') {
            $status = 'cancelled';
        }

        $stmt = $this->pdo->prepare('UPDATE planning_entries
            SET validation_status = :validation_status, status = :status, validation_comment = :validation_comment,
                validated_by = :validated_by, validated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND tenant_id = :tenant_id');
        $ok = $stmt->execute([
            'validation_status' => $validationStatus, 'status' => $status, 'validation_comment' => $reason,
            'validated_by' => $actorUserId, 'id' => $entryId, 'tenant_id' => $tenantId,
        ]);
        if ($ok) {
            $this->logAction($entryId, $actorUserId, 'validation', 'Validation: ' . $validationStatus . ($reason ? ' (' . $reason . ')' : ''));
            $this->registerRealtimeEvent($tenantId, $entryId, 'validation', ['status' => $validationStatus]);
        }
        return $ok;
    }

    public function transitionOperationalStatus(int $tenantId, int $entryId, string $operationalStatus, int $actorUserId): bool
    {
        if (!$this->hasTable('planning_entries')) {
            return false;
        }
        $allowed = ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'];
        if (!in_array($operationalStatus, $allowed, true)) {
            return false;
        }
        if ($operationalStatus === 'completed' && !$this->isChecklistCompliant($tenantId, $entryId)) {
            $this->logAction($entryId, $actorUserId, 'status_change', 'Clôture bloquée: checklist incomplète');
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE planning_entries SET operational_status = :operational_status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND tenant_id = :tenant_id');
        $ok = $stmt->execute(['operational_status' => $operationalStatus, 'id' => $entryId, 'tenant_id' => $tenantId]);
        if ($ok) {
            $this->logAction($entryId, $actorUserId, 'status_change', 'Statut opérationnel: ' . $operationalStatus);
            $this->registerRealtimeEvent($tenantId, $entryId, 'status_change', ['status' => $operationalStatus]);
        }
        return $ok;
    }

    /**
     * Retire l’entrée du mur opérationnel : elle n’apparaît plus sur le portail (statut publication annulé).
     */
    public function retireFromBoard(int $tenantId, int $entryId, int $actorUserId, ?string $reason = null): bool
    {
        if (!$this->hasTable('planning_entries')) {
            return false;
        }
        $entry = $this->findByIdForTenant($tenantId, $entryId);
        if ($entry === null) {
            return false;
        }
        $comment = $reason !== null && trim($reason) !== '' ? trim($reason) : 'Retirée du mur opérationnel.';

        $stmt = $this->pdo->prepare('UPDATE planning_entries SET
            status = \'cancelled\',
            validation_status = \'rejected\',
            operational_status = \'cancelled\',
            validation_comment = :comment,
            validated_by = :actor,
            validated_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND tenant_id = :tenant_id');
        $ok = $stmt->execute([
            'comment' => $comment,
            'actor' => $actorUserId,
            'id' => $entryId,
            'tenant_id' => $tenantId,
        ]);
        if ($ok) {
            $this->logAction($entryId, $actorUserId, 'retire', $comment);
            $this->registerRealtimeEvent($tenantId, $entryId, 'retired', ['title' => (string) ($entry['title'] ?? '')]);
        }

        return $ok;
    }

    /**
     * Fiche encore ouverte (publication non annulée) pour la même source métier liée.
     * Les fiches retirées du mur (`status = cancelled`) ne comptent pas : on peut en recréer une.
     */
    public function findOpenEntryIdForLink(int $tenantId, string $linkedType, int $linkedId): ?int
    {
        if (!$this->hasTable('planning_entries') || $tenantId < 1 || $linkedId < 1 || $linkedType === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM planning_entries
             WHERE tenant_id = :tenant_id AND linked_type = :linked_type AND linked_id = :linked_id
             AND status <> :cancelled
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'cancelled' => 'cancelled',
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** @return list<array<string,mixed>> */
    public function listRecentLogs(int $tenantId, int $limit = 40): array
    {
        if (!$this->hasTable('planning_entry_logs') || !$this->hasTable('planning_entries')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT l.*, e.title FROM planning_entry_logs l
            INNER JOIN planning_entries e ON e.id = l.planning_entry_id
            WHERE e.tenant_id = ? ORDER BY l.id DESC LIMIT ?');
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function listRealtimeSnapshot(int $tenantId, int $sinceId = 0, int $limit = 60): array
    {
        if (!$this->hasTable('planning_realtime_stream')) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare('SELECT id, event_type, payload_json, created_at FROM planning_realtime_stream
                WHERE tenant_id = :tenant_id AND id > :since_id ORDER BY id ASC LIMIT :limit_rows');
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmt->bindValue(':since_id', $sinceId, PDO::PARAM_INT);
            $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {
            return [];
        }
    }

    public function createFromTemplate(int $tenantId, int $templateId, int $actorUserId): ?int
    {
        if (!$this->hasTable('planning_templates') || !$this->hasTable('planning_entries')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM planning_templates WHERE id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$templateId, $tenantId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            return null;
        }

        $payload = json_decode((string) ($template['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $fallbackTitle = trim((string) ($template['name'] ?? '')) !== ''
            ? trim((string) $template['name'])
            : 'Nouvelle fiche';
        $payload = $this->normalizePayloadForNewEntryFromTemplate($payload, $tenantId, $actorUserId, $fallbackTitle);

        $entryId = $this->create($payload);
        if ($entryId < 1) {
            return null;
        }
        $this->logAction($entryId, $actorUserId, 'template_apply', 'Créé depuis template #' . $templateId);
        $this->registerRealtimeEvent($tenantId, $entryId, 'template_apply', ['template_id' => $templateId]);

        return $entryId;
    }

    public function createFrago(int $tenantId, int $parentEntryId, int $actorUserId): ?int
    {
        if (!$this->hasTable('planning_entries')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM planning_entries WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$parentEntryId, $tenantId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parent) {
            return null;
        }

        $nextVersion = (int) ($parent['frago_version'] ?? 1) + 1;
        $entryId = $this->create([
            'tenant_id' => $tenantId,
            'title' => '[FRAGO] ' . (string) ($parent['title'] ?? 'Entrée'),
            'description' => (string) ($parent['description'] ?? ''),
            'entry_type' => (string) ($parent['entry_type'] ?? 'task'),
            'category_id' => (int) ($parent['category_id'] ?? 0),
            'linked_type' => $parent['linked_type'] ?? null,
            'linked_id' => $parent['linked_id'] ?? null,
            'start_date' => $parent['start_date'] ?? null,
            'end_date' => $parent['end_date'] ?? null,
            'all_day' => (int) ($parent['all_day'] ?? 1),
            'priority' => (string) ($parent['priority'] ?? 'normal'),
            'display_order' => (int) ($parent['display_order'] ?? 100),
            'visibility_scope' => (string) ($parent['visibility_scope'] ?? 'tenant'),
            'security_level' => (string) ($parent['security_level'] ?? 'unit_public'),
            'chief_user_id' => $parent['chief_user_id'] ?? null,
            'deputy_user_id' => $parent['deputy_user_id'] ?? null,
            'replacement_user_id' => $parent['replacement_user_id'] ?? null,
            'replacement_auto_activate' => (int) ($parent['replacement_auto_activate'] ?? 0),
            'command_chain' => $parent['command_chain'] ?? null,
            'accountability_note' => $parent['accountability_note'] ?? null,
            'location_lat' => $parent['location_lat'] ?? null,
            'location_lng' => $parent['location_lng'] ?? null,
            'operation_zone' => $parent['operation_zone'] ?? null,
            'map_link' => $parent['map_link'] ?? null,
            'dossier_ref' => $parent['dossier_ref'] ?? null,
            'legal_constraints' => $parent['legal_constraints'] ?? null,
            'fire_window_start' => $parent['fire_window_start'] ?? null,
            'fire_window_end' => $parent['fire_window_end'] ?? null,
            'phase_current' => (string) ($parent['phase_current'] ?? 'phase_1'),
            'created_by' => $actorUserId,
        ]);
        if ($entryId < 1) {
            return null;
        }

        $this->pdo->prepare('UPDATE planning_entries SET frago_parent_entry_id = :parent_id, frago_version = :frago_version WHERE id = :id')
            ->execute(['parent_id' => $parentEntryId, 'frago_version' => $nextVersion, 'id' => $entryId]);

        $this->copyEntryChildren($parentEntryId, $entryId);
        $this->snapshotVersion($entryId, $nextVersion, $actorUserId);
        $this->logAction($entryId, $actorUserId, 'update', 'FRAGO généré depuis #' . $parentEntryId . ' v' . $nextVersion);

        return $entryId;
    }

    public function markChecklistItem(int $tenantId, int $entryId, int $itemId, bool $isDone, int $actorUserId): bool
    {
        if (!$this->hasTable('planning_entry_checklists') || !$this->hasTable('planning_entries')) {
            return false;
        }
        $exists = $this->pdo->prepare('SELECT id FROM planning_entries WHERE id = ? AND tenant_id = ? LIMIT 1');
        $exists->execute([$entryId, $tenantId]);
        if (!$exists->fetchColumn()) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE planning_entry_checklists
            SET is_done = :is_done, done_by = :done_by, done_at = CASE WHEN :is_done = 1 THEN CURRENT_TIMESTAMP ELSE NULL END
            WHERE id = :id AND planning_entry_id = :planning_entry_id');
        $ok = $stmt->execute([
            'is_done' => $isDone ? 1 : 0,
            'done_by' => $isDone ? $actorUserId : null,
            'id' => $itemId,
            'planning_entry_id' => $entryId,
        ]);
        if ($ok) {
            $this->logAction($entryId, $actorUserId, 'assignment', 'Checklist #' . $itemId . ' = ' . ($isDone ? 'done' : 'todo'));
        }
        return $ok;
    }

    /**
     * Entrées du tableau opérationnel auxquelles l’utilisateur est affecté (lignes actives, période non terminée).
     *
     * @return list<array<string,mixed>>
     */
    public function listActiveEntriesForAssignedUser(int $tenantId, int $userId, int $limit = 15): array
    {
        if (!$this->hasTable('planning_entries') || !$this->hasTable('planning_entry_personnel')) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT e.*, p.role_label AS personnel_role_label, p.is_lead AS personnel_is_lead
                 FROM planning_entry_personnel p
                 INNER JOIN planning_entries e ON e.id = p.planning_entry_id
                 WHERE e.tenant_id = :tenant_id
                   AND p.user_id = :user_id
                   AND e.status = :status
                   AND (e.end_date IS NULL OR e.end_date >= CURDATE())
                 ORDER BY (e.start_date IS NULL) ASC, e.start_date ASC, e.id ASC
                 LIMIT ' . $limit
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'status' => 'active',
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function findQualificationAlerts(int $tenantId): array
    {
        if (!$this->hasTable('planning_entries')
            || !$this->hasTable('planning_entry_personnel')
            || !$this->hasTable('planning_entry_skills')
            || !$this->hasTable('personnel_skill_validity')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT e.title AS entry_title, s.skill_code,
                COALESCE(NULLIF(TRIM(u.display_name), \'\'), NULLIF(TRIM(u.callsign), \'\'), u.email) AS person_label
            FROM planning_entry_personnel p
            INNER JOIN planning_entries e ON e.id = p.planning_entry_id
            INNER JOIN planning_entry_skills s ON s.planning_entry_id = e.id AND s.is_mandatory = 1
            LEFT JOIN users u ON u.id = p.user_id AND u.tenant_id = e.tenant_id
            LEFT JOIN personnel_skill_validity v ON v.tenant_id = e.tenant_id AND v.user_id = p.user_id AND v.skill_code = s.skill_code
            WHERE e.tenant_id = :tenant_id
              AND (v.id IS NULL OR (v.valid_until IS NOT NULL AND v.valid_until < CURDATE()))');
        try {
            $stmt->execute(['tenant_id' => $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function findFireWindowConflicts(int $tenantId): array
    {
        if (!$this->hasTable('planning_entries')) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare('SELECT id, title, fire_window_start, fire_window_end, legal_constraints
                FROM planning_entries
                WHERE tenant_id = :tenant_id
                  AND fire_window_start IS NOT NULL
                  AND fire_window_end IS NOT NULL
                  AND fire_window_start > fire_window_end');
            $stmt->execute(['tenant_id' => $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function findByIdForTenant(int $tenantId, int $entryId): ?array
    {
        if (!$this->hasTable('planning_entries') || $tenantId < 1 || $entryId < 1) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM planning_entries WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$entryId, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * Liste portail : entrées actives / validées, filtrées par niveau de sensibilité et périmètre (unité / emplois).
     *
     * @param list<int> $viewerUnitIds
     * @param list<int> $viewerJobRoleIds
     *
     * @return list<array<string, mixed>>
     */
    public function listForPortal(
        int $tenantId,
        array $filters,
        int $viewerUserId,
        bool $viewerCanSeeRestricted,
        array $viewerUnitIds = [],
        array $viewerJobRoleIds = []
    ): array {
        if (!$this->hasTable('planning_entries')) {
            return [];
        }
        $where = [
            'e.tenant_id = :tenant_id',
            'e.status = \'active\'',
            'e.validation_status NOT IN (\'draft\',\'rejected\')',
        ];
        $params = ['tenant_id' => $tenantId];
        if (!$viewerCanSeeRestricted) {
            $where[] = 'e.security_level = \'unit_public\'';
            $where[] = '(e.visibility_scope != \'private\' OR e.created_by = :viewer_uid)';
            $params['viewer_uid'] = $viewerUserId;
        }

        foreach (['entry_type', 'operational_status'] as $filterKey) {
            $value = trim((string) ($filters[$filterKey] ?? ''));
            if ($value !== '') {
                $where[] = 'e.' . $filterKey . ' = :' . $filterKey;
                $params[$filterKey] = $value;
            }
        }

        $periodStart = trim((string) ($filters['period_start'] ?? ''));
        $periodEnd = trim((string) ($filters['period_end'] ?? ''));
        if ($periodStart !== '' && $periodEnd !== '') {
            $where[] = '((e.start_date IS NULL OR e.start_date <= :period_end) AND (e.end_date IS NULL OR e.end_date >= :period_start))';
            $params['period_start'] = $periodStart;
            $params['period_end'] = $periodEnd;
        }

        [$selectExtras, $joins] = $this->boardListSelectFragments();
        $query = "SELECT e.*, {$selectExtras}
            FROM planning_entries e
            {$joins}
            WHERE " . implode(' AND ', $where) . '
            ORDER BY FIELD(e.priority, "critical", "high", "normal", "low"), e.display_order ASC, COALESCE(e.start_date, e.created_at) ASC, e.id DESC';

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log('[PlanningEntryRepository::listForPortal] ' . $e->getMessage());

            return [];
        }

        if ($viewerCanSeeRestricted || !$this->columnExists('planning_entries', 'visibility_unit_id')) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $e): bool => self::entryVisibleForPortalAudience($e, $viewerUserId, $viewerUnitIds, $viewerJobRoleIds)
        ));
    }

    /**
     * @param array<string, mixed> $e
     * @param list<int> $viewerUnitIds
     * @param list<int> $viewerJobRoleIds
     */
    private static function entryVisibleForPortalAudience(
        array $e,
        int $viewerUserId,
        array $viewerUnitIds,
        array $viewerJobRoleIds
    ): bool {
        $scope = (string) ($e['visibility_scope'] ?? 'tenant');
        if ($scope === 'tenant') {
            return true;
        }
        if ($scope === 'private') {
            return (int) ($e['created_by'] ?? 0) === $viewerUserId;
        }
        if ($scope === 'unit') {
            $uid = (int) ($e['visibility_unit_id'] ?? 0);
            if ($uid < 1) {
                return false;
            }

            return in_array($uid, $viewerUnitIds, true);
        }
        if ($scope === 'role') {
            $raw = trim((string) ($e['visibility_job_role_ids'] ?? ''));
            if ($raw === '') {
                return true;
            }
            $needed = json_decode($raw, true);
            if (!is_array($needed) || $needed === []) {
                return true;
            }
            foreach ($needed as $rid) {
                if (in_array((int) $rid, $viewerJobRoleIds, true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function listPersonnelRowsForEntry(int $entryId): array
    {
        if (!$this->hasTable('planning_entry_personnel')) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.*, COALESCE(NULLIF(TRIM(u.display_name), \'\'), NULLIF(TRIM(u.callsign), \'\'), u.email) AS user_label
             FROM planning_entry_personnel p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.planning_entry_id = ?
             ORDER BY p.is_lead DESC, p.id ASC'
        );
        $stmt->execute([$entryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAssetRowsForEntry(int $entryId): array
    {
        if (!$this->hasTable('planning_entry_assets')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM planning_entry_assets WHERE planning_entry_id = ? ORDER BY id ASC');
        $stmt->execute([$entryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listNoteRowsForEntry(int $entryId): array
    {
        if (!$this->hasTable('planning_entry_notes')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM planning_entry_notes WHERE planning_entry_id = ? ORDER BY is_pinned DESC, id ASC');
        $stmt->execute([$entryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listChecklistRowsForEntry(int $entryId): array
    {
        if (!$this->hasTable('planning_entry_checklists')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM planning_entry_checklists WHERE planning_entry_id = ? ORDER BY id ASC');
        $stmt->execute([$entryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array{user_id: int, role_label: string, is_lead: bool}> $rows
     */
    public function replacePersonnelForEntry(int $tenantId, int $entryId, array $rows, int $actorUserId): void
    {
        if (!$this->hasTable('planning_entry_personnel') || !$this->findByIdForTenant($tenantId, $entryId)) {
            return;
        }
        $this->pdo->prepare('DELETE FROM planning_entry_personnel WHERE planning_entry_id = ?')->execute([$entryId]);
        $ins = $this->pdo->prepare('INSERT INTO planning_entry_personnel (planning_entry_id, user_id, role_label, is_lead) VALUES (?, ?, ?, ?)');
        foreach ($rows as $r) {
            $uid = (int) ($r['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $rl = isset($r['role_label']) ? trim((string) $r['role_label']) : '';
            $ins->execute([
                $entryId,
                $uid,
                $rl !== '' ? $rl : null,
                !empty($r['is_lead']) ? 1 : 0,
            ]);
        }
        $this->logAction($entryId, $actorUserId, 'assignment', 'Affectations personnel mises à jour');
    }

    /**
     * @param list<array{type: string, label: string, reference: string, state: string}> $rows
     */
    public function replaceAssetsForEntry(int $tenantId, int $entryId, array $rows, int $actorUserId): void
    {
        if (!$this->hasTable('planning_entry_assets') || !$this->findByIdForTenant($tenantId, $entryId)) {
            return;
        }
        $this->pdo->prepare('DELETE FROM planning_entry_assets WHERE planning_entry_id = ?')->execute([$entryId]);
        $hasState = $this->columnExists('planning_entry_assets', 'asset_state');
        if ($hasState) {
            $ins = $this->pdo->prepare('INSERT INTO planning_entry_assets (planning_entry_id, asset_type, asset_label, asset_reference, asset_state) VALUES (?, ?, ?, ?, ?)');
        } else {
            $ins = $this->pdo->prepare('INSERT INTO planning_entry_assets (planning_entry_id, asset_type, asset_label, asset_reference) VALUES (?, ?, ?, ?)');
        }
        foreach ($rows as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = trim((string) ($r['type'] ?? 'moyen')) ?: 'moyen';
            $ref = trim((string) ($r['reference'] ?? ''));
            $state = trim((string) ($r['state'] ?? 'available'));
            if ($hasState) {
                $ins->execute([$entryId, $type, $label, $ref !== '' ? $ref : null, $state !== '' ? $state : 'available']);
            } else {
                $ins->execute([$entryId, $type, $label, $ref !== '' ? $ref : null]);
            }
        }
        $this->logAction($entryId, $actorUserId, 'update', 'Moyens mis à jour');
    }

    /**
     * @param list<array{type: string, content: string, pinned: bool}> $rows
     */
    public function replaceNotesForEntry(int $tenantId, int $entryId, array $rows, int $actorUserId): void
    {
        if (!$this->hasTable('planning_entry_notes') || !$this->findByIdForTenant($tenantId, $entryId)) {
            return;
        }
        $this->pdo->prepare('DELETE FROM planning_entry_notes WHERE planning_entry_id = ?')->execute([$entryId]);
        $ins = $this->pdo->prepare('INSERT INTO planning_entry_notes (planning_entry_id, note_type, content, is_pinned) VALUES (?, ?, ?, ?)');
        foreach ($rows as $r) {
            $content = trim((string) ($r['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $nt = trim((string) ($r['type'] ?? 'consigne'));
            $allowed = ['consigne', 'info', 'restriction', 'brief'];
            if (!in_array($nt, $allowed, true)) {
                $nt = 'consigne';
            }
            $ins->execute([$entryId, $nt, $content, !empty($r['pinned']) ? 1 : 0]);
        }
        $this->logAction($entryId, $actorUserId, 'update', 'Consignes / notes mises à jour');
    }

    /** @param list<string> $tags */
    public function replaceTagsForEntry(int $tenantId, int $entryId, array $tags, int $actorUserId): void
    {
        if (!$this->hasTable('planning_entry_tags') || !$this->findByIdForTenant($tenantId, $entryId)) {
            return;
        }
        $this->pdo->prepare('DELETE FROM planning_entry_tags WHERE planning_entry_id = ?')->execute([$entryId]);
        $ins = $this->pdo->prepare('INSERT INTO planning_entry_tags (planning_entry_id, tag) VALUES (?, ?)');
        foreach ($tags as $raw) {
            $t = strtolower(trim((string) $raw));
            if ($t === '' || strlen($t) > 80) {
                continue;
            }
            try {
                $ins->execute([$entryId, $t]);
            } catch (\PDOException) {
            }
        }
        $this->logAction($entryId, $actorUserId, 'update', 'Étiquettes mises à jour');
    }

    /** @param array<string, mixed> $payload same shape as create() */
    public function updateEntry(int $tenantId, int $entryId, array $payload, int $actorUserId): bool
    {
        if (!$this->hasTable('planning_entries') || !$this->findByIdForTenant($tenantId, $entryId)) {
            return false;
        }
        $hasVis = $this->columnExists('planning_entries', 'visibility_unit_id');
        $visSql = $hasVis ? ', visibility_unit_id = :visibility_unit_id, visibility_job_role_ids = :visibility_job_role_ids' : '';
        $stmt = $this->pdo->prepare('UPDATE planning_entries SET
            title = :title, description = :description, entry_type = :entry_type, category_id = :category_id,
            linked_type = :linked_type, linked_id = :linked_id,
            start_date = :start_date, end_date = :end_date, all_day = :all_day,
            priority = :priority, display_order = :display_order,
            visibility_scope = :visibility_scope' . $visSql . ', security_level = :security_level,
            operational_status = :operational_status, phase_current = :phase_current,
            chief_user_id = :chief_user_id, deputy_user_id = :deputy_user_id,
            replacement_user_id = :replacement_user_id, replacement_auto_activate = :replacement_auto_activate,
            command_chain = :command_chain, accountability_note = :accountability_note,
            location_lat = :location_lat, location_lng = :location_lng, operation_zone = :operation_zone, map_link = :map_link,
            dossier_ref = :dossier_ref, legal_constraints = :legal_constraints,
            fire_window_start = :fire_window_start, fire_window_end = :fire_window_end,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND tenant_id = :tenant_id');
        $exec = [
            'title' => (string) $payload['title'],
            'description' => $payload['description'] ?: null,
            'entry_type' => (string) $payload['entry_type'],
            'category_id' => $payload['category_id'] ?: null,
            'linked_type' => $payload['linked_type'] ?: null,
            'linked_id' => $payload['linked_id'] ?: null,
            'start_date' => $payload['start_date'] ?: null,
            'end_date' => $payload['end_date'] ?: null,
            'all_day' => !empty($payload['all_day']) ? 1 : 0,
            'priority' => (string) ($payload['priority'] ?? 'normal'),
            'display_order' => (int) ($payload['display_order'] ?? 100),
            'visibility_scope' => (string) ($payload['visibility_scope'] ?? 'tenant'),
            'security_level' => (string) ($payload['security_level'] ?? 'unit_public'),
            'operational_status' => (string) ($payload['operational_status'] ?? 'planned'),
            'phase_current' => (string) ($payload['phase_current'] ?? 'phase_1'),
            'chief_user_id' => $payload['chief_user_id'] ?: null,
            'deputy_user_id' => $payload['deputy_user_id'] ?: null,
            'replacement_user_id' => $payload['replacement_user_id'] ?: null,
            'replacement_auto_activate' => !empty($payload['replacement_auto_activate']) ? 1 : 0,
            'command_chain' => $payload['command_chain'] ?: null,
            'accountability_note' => $payload['accountability_note'] ?: null,
            'location_lat' => $payload['location_lat'] ?: null,
            'location_lng' => $payload['location_lng'] ?: null,
            'operation_zone' => $payload['operation_zone'] ?: null,
            'map_link' => $payload['map_link'] ?: null,
            'dossier_ref' => $payload['dossier_ref'] ?: null,
            'legal_constraints' => $payload['legal_constraints'] ?: null,
            'fire_window_start' => $payload['fire_window_start'] ?: null,
            'fire_window_end' => $payload['fire_window_end'] ?: null,
            'id' => $entryId,
            'tenant_id' => $tenantId,
        ];
        if ($hasVis) {
            $exec['visibility_unit_id'] = !empty($payload['visibility_unit_id']) ? (int) $payload['visibility_unit_id'] : null;
            $jr = $payload['visibility_job_role_ids'] ?? null;
            $exec['visibility_job_role_ids'] = is_string($jr) && $jr !== '' ? $jr : null;
        }
        $ok = $stmt->execute($exec);
        if ($ok) {
            $this->logAction($entryId, $actorUserId, 'update', 'Fiche entrée mise à jour');
            $this->registerRealtimeEvent($tenantId, $entryId, 'entry_updated', ['title' => (string) $payload['title']]);
        }

        return (bool) $ok;
    }

    public function duplicateEntry(int $tenantId, int $entryId, int $actorUserId): ?int
    {
        $src = $this->findByIdForTenant($tenantId, $entryId);
        if ($src === null) {
            return null;
        }
        unset($src['id']);
        $src['title'] = '[Copie] ' . (string) ($src['title'] ?? 'Entrée');
        $src['tenant_id'] = $tenantId;
        $src['created_by'] = $actorUserId;
        $src['status'] = 'draft';
        $src['validation_status'] = 'draft';
        $src['operational_status'] = 'planned';
        $newId = $this->create($src);
        if ($newId < 1) {
            return null;
        }
        $this->copyEntryChildren($entryId, $newId);
        $this->logAction($newId, $actorUserId, 'create', 'Duplication depuis #' . $entryId);

        return $newId;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table, $column]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function registerRealtimeEvent(int $tenantId, ?int $entryId, string $eventType, array $payload = []): void
    {
        if (!$this->hasTable('planning_realtime_stream')) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO planning_realtime_stream (tenant_id, entry_id, event_type, payload_json)
            VALUES (?, ?, ?, ?)');
        $stmt->execute([$tenantId, $entryId, $eventType, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    private function isChecklistCompliant(int $tenantId, int $entryId): bool
    {
        if (!$this->hasTable('planning_entry_checklists')) {
            return true;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM planning_entry_checklists c
            INNER JOIN planning_entries e ON e.id = c.planning_entry_id
            WHERE e.tenant_id = :tenant_id AND c.planning_entry_id = :entry_id AND c.is_required = 1 AND c.is_done = 0');
        $stmt->execute(['tenant_id' => $tenantId, 'entry_id' => $entryId]);
        return ((int) $stmt->fetchColumn()) === 0;
    }

    private function copyEntryChildren(int $fromEntryId, int $toEntryId): void
    {
        if ($this->hasTable('planning_entry_personnel')) {
        $this->pdo->prepare('INSERT INTO planning_entry_personnel (planning_entry_id, user_id, role_label, is_lead)
            SELECT :to_entry, user_id, role_label, is_lead FROM planning_entry_personnel WHERE planning_entry_id = :from_entry')
            ->execute(['to_entry' => $toEntryId, 'from_entry' => $fromEntryId]);
        }
        if ($this->hasTable('planning_entry_assets')) {
        $this->pdo->prepare('INSERT INTO planning_entry_assets (planning_entry_id, asset_type, asset_label, asset_reference, asset_state, asset_metadata)
            SELECT :to_entry, asset_type, asset_label, asset_reference, asset_state, asset_metadata FROM planning_entry_assets WHERE planning_entry_id = :from_entry')
            ->execute(['to_entry' => $toEntryId, 'from_entry' => $fromEntryId]);
        }
        if ($this->hasTable('planning_entry_notes')) {
        $this->pdo->prepare('INSERT INTO planning_entry_notes (planning_entry_id, note_type, content, is_pinned)
            SELECT :to_entry, note_type, content, is_pinned FROM planning_entry_notes WHERE planning_entry_id = :from_entry')
            ->execute(['to_entry' => $toEntryId, 'from_entry' => $fromEntryId]);
        }
        if ($this->hasTable('planning_entry_checklists')) {
        $this->pdo->prepare('INSERT INTO planning_entry_checklists (planning_entry_id, label, is_required, is_done, done_by, done_at)
            SELECT :to_entry, label, is_required, 0, NULL, NULL FROM planning_entry_checklists WHERE planning_entry_id = :from_entry')
            ->execute(['to_entry' => $toEntryId, 'from_entry' => $fromEntryId]);
        }
    }

    private function snapshotVersion(int $entryId, int $version, int $actorUserId): void
    {
        if (!$this->hasTable('planning_entry_versions')) {
            return;
        }
        $entryStmt = $this->pdo->prepare('SELECT * FROM planning_entries WHERE id = ? LIMIT 1');
        $entryStmt->execute([$entryId]);
        $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO planning_entry_versions (planning_entry_id, version_number, payload_json, created_by)
            VALUES (?, ?, ?, ?)');
        $stmt->execute([$entryId, $version, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $actorUserId]);
    }

    private function logAction(int $entryId, int $actorUserId, string $actionType, string $summary): void
    {
        if (!$this->hasTable('planning_entry_logs')) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO planning_entry_logs (planning_entry_id, actor_user_id, action_type, summary) VALUES (?, ?, ?, ?)');
        $stmt->execute([$entryId, $actorUserId, $actionType, $summary]);
    }
}
