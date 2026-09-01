<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\MemberIntegrationCatalog;
use PDO;
use PDOException;

final class MemberIntegrationTemplateRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesExist(): bool
    {
        return $this->hasTable('member_integration_templates')
            && $this->hasTable('member_integration_template_steps');
    }

    private function hasTable(string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($t === '') {
            return false;
        }
        try {
            $st = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($t));

            return $st !== false && (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return [];
        }
        $sql = 'SELECT * FROM member_integration_templates WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesExist() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM member_integration_templates WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function hasActiveTemplate(int $tenantId): bool
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM member_integration_templates WHERE tenant_id = ? AND is_active = 1 LIMIT 1');
        $st->execute([$tenantId]);

        return (bool) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data, ?int $createdBy): int
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_templates
                (tenant_id, name, description, version, duration_days, auto_rules_json, default_referent_user_id, referent_rule, is_active, created_by, created_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $rules = $data['auto_rules_json'] ?? null;
        if (is_array($rules)) {
            $rules = json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $st->execute([
            $tenantId,
            mb_substr(trim((string) ($data['name'] ?? 'Parcours')), 0, 160),
            isset($data['description']) ? mb_substr(trim((string) $data['description']), 0, 4000) : null,
            isset($data['duration_days']) ? max(0, (int) $data['duration_days']) : null,
            $rules !== false && $rules !== null && $rules !== '' ? $rules : null,
            !empty($data['default_referent_user_id']) ? (int) $data['default_referent_user_id'] : null,
            in_array((string) ($data['referent_rule'] ?? 'none'), ['none', 'fixed_user', 'unit_leader', 'template_role'], true)
                ? (string) ($data['referent_rule'] ?? 'none')
                : 'none',
            !empty($data['is_active']) ? 1 : 0,
            $createdBy !== null && $createdBy > 0 ? $createdBy : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data, bool $bumpVersion = false): bool
    {
        $row = $this->findForTenant($tenantId, $id);
        if (!$row) {
            return false;
        }
        $version = (int) ($row['version'] ?? 1);
        if ($bumpVersion) {
            $version++;
        }
        $rules = $data['auto_rules_json'] ?? $row['auto_rules_json'] ?? null;
        if (is_array($rules)) {
            $rules = json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $st = $this->pdo->prepare(
            'UPDATE member_integration_templates
             SET name = ?, description = ?, version = ?, duration_days = ?, auto_rules_json = ?,
                 default_referent_user_id = ?, referent_rule = ?, is_active = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([
            mb_substr(trim((string) ($data['name'] ?? $row['name'])), 0, 160),
            array_key_exists('description', $data) ? mb_substr(trim((string) $data['description']), 0, 4000) : ($row['description'] ?? null),
            $version,
            array_key_exists('duration_days', $data) ? (int) $data['duration_days'] : ($row['duration_days'] ?? null),
            $rules !== false && $rules !== '' ? $rules : null,
            array_key_exists('default_referent_user_id', $data)
                ? ((int) $data['default_referent_user_id'] > 0 ? (int) $data['default_referent_user_id'] : null)
                : ($row['default_referent_user_id'] ?? null),
            in_array((string) ($data['referent_rule'] ?? $row['referent_rule'] ?? 'none'), ['none', 'fixed_user', 'unit_leader', 'template_role'], true)
                ? (string) ($data['referent_rule'] ?? $row['referent_rule'] ?? 'none')
                : 'none',
            array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : (int) ($row['is_active'] ?? 1),
            $tenantId,
            $id,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listSteps(int $tenantId, int $templateId): array
    {
        if (!$this->tablesExist() || $tenantId < 1 || $templateId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integration_template_steps WHERE tenant_id = ? AND template_id = ? ORDER BY position ASC, id ASC'
        );
        $st->execute([$tenantId, $templateId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createStep(int $tenantId, int $templateId, array $data): int
    {
        if (!$this->findForTenant($tenantId, $templateId)) {
            return 0;
        }
        $types = MemberIntegrationCatalog::STEP_TYPES;
        $type = (string) ($data['step_type'] ?? MemberIntegrationCatalog::TYPE_TASK);
        if (!in_array($type, $types, true)) {
            $type = MemberIntegrationCatalog::TYPE_CUSTOM;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_template_steps
                (tenant_id, template_id, position, step_key, title, description, step_type, responsible_kind,
                 responsible_role_id, due_after_days, is_required, is_member_visible, linked_matrix_id, linked_course_id,
                 linked_document_id, configuration_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $cfg = $data['configuration_json'] ?? null;
        if (is_array($cfg)) {
            $cfg = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $key = trim((string) ($data['step_key'] ?? ''));
        if ($key === '') {
            $key = 'step_' . bin2hex(random_bytes(4));
        }
        $st->execute([
            $tenantId,
            $templateId,
            max(1, (int) ($data['position'] ?? 1)),
            mb_substr($key, 0, 64),
            mb_substr(trim((string) ($data['title'] ?? 'Étape')), 0, 180),
            isset($data['description']) ? mb_substr(trim((string) $data['description']), 0, 4000) : null,
            $type,
            in_array((string) ($data['responsible_kind'] ?? 'member'), array_keys(MemberIntegrationCatalog::responsibleLabels()), true)
                ? (string) ($data['responsible_kind'] ?? 'member')
                : MemberIntegrationCatalog::RESP_MEMBER,
            !empty($data['responsible_role_id']) ? (int) $data['responsible_role_id'] : null,
            isset($data['due_after_days']) ? max(0, (int) $data['due_after_days']) : null,
            !isset($data['is_required']) || !empty($data['is_required']) ? 1 : 0,
            !isset($data['is_member_visible']) || !empty($data['is_member_visible']) ? 1 : 0,
            !empty($data['linked_matrix_id']) ? (int) $data['linked_matrix_id'] : null,
            !empty($data['linked_course_id']) ? (int) $data['linked_course_id'] : null,
            !empty($data['linked_document_id']) ? (int) $data['linked_document_id'] : null,
            $cfg !== false && $cfg !== null && $cfg !== '' ? $cfg : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteStep(int $tenantId, int $stepId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM member_integration_template_steps WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $stepId]);

        return $st->rowCount() > 0;
    }

    public function replaceSteps(int $tenantId, int $templateId, array $steps): void
    {
        $this->pdo->prepare('DELETE FROM member_integration_template_steps WHERE tenant_id = ? AND template_id = ?')
            ->execute([$tenantId, $templateId]);
        $pos = 1;
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $step['position'] = $pos++;
            $this->createStep($tenantId, $templateId, $step);
        }
    }

    /**
     * Premier modèle actif dont les règles correspondent, sinon le premier actif.
     *
     * @param array{role_ids?: list<int>, unit_ids?: list<int>, source?: string} $context
     */
    public function matchTemplate(int $tenantId, array $context = []): ?array
    {
        $templates = $this->listForTenant($tenantId, true);
        $source = (string) ($context['source'] ?? '');
        $roleIds = array_map('intval', $context['role_ids'] ?? []);
        $unitIds = array_map('intval', $context['unit_ids'] ?? []);
        $fallback = null;
        foreach ($templates as $tpl) {
            $rawRules = $tpl['auto_rules_json'] ?? null;
            $rules = is_array($rawRules) ? $rawRules : json_decode((string) $rawRules, true);
            if (!is_array($rules) || $rules === []) {
                if ($fallback === null) {
                    $fallback = $tpl;
                }
                continue;
            }
            $ok = true;
            $ruleSources = $rules['sources'] ?? $rules['source'] ?? null;
            if (is_string($ruleSources) && $ruleSources !== '') {
                $ok = $ok && ($source === '' || $source === $ruleSources);
            } elseif (is_array($ruleSources) && $ruleSources !== []) {
                $ok = $ok && ($source === '' || in_array($source, $ruleSources, true));
            }
            $ruleRoles = array_map('intval', $rules['role_ids'] ?? []);
            if ($ruleRoles !== []) {
                $ok = $ok && array_intersect($ruleRoles, $roleIds) !== [];
            }
            $ruleUnits = array_map('intval', $rules['unit_ids'] ?? []);
            if ($ruleUnits !== []) {
                $ok = $ok && array_intersect($ruleUnits, $unitIds) !== [];
            }
            if ($ok) {
                return $tpl;
            }
        }

        return $fallback ?? ($templates[0] ?? null);
    }

    /**
     * Ne matche que si des règles auto sont présentes et satisfaites (pas de repli).
     *
     * @param array{source?: string, role_ids?: list<int>, unit_ids?: list<int>} $context
     * @return array<string, mixed>|null
     */
    public function matchTemplateByRules(int $tenantId, array $context = []): ?array
    {
        $templates = $this->listForTenant($tenantId, true);
        $source = (string) ($context['source'] ?? '');
        $roleIds = array_map('intval', $context['role_ids'] ?? []);
        $unitIds = array_map('intval', $context['unit_ids'] ?? []);
        foreach ($templates as $tpl) {
            $rawRules = $tpl['auto_rules_json'] ?? null;
            $rules = is_array($rawRules) ? $rawRules : json_decode((string) $rawRules, true);
            if (!is_array($rules) || $rules === []) {
                continue;
            }
            $ok = true;
            $ruleSources = $rules['sources'] ?? $rules['source'] ?? null;
            if (is_string($ruleSources) && $ruleSources !== '') {
                $ok = $ok && ($source === '' || $source === $ruleSources);
            } elseif (is_array($ruleSources) && $ruleSources !== []) {
                $ok = $ok && ($source === '' || in_array($source, $ruleSources, true));
            }
            $ruleRoles = array_map('intval', $rules['role_ids'] ?? []);
            if ($ruleRoles !== []) {
                $ok = $ok && array_intersect($ruleRoles, $roleIds) !== [];
            }
            $ruleUnits = array_map('intval', $rules['unit_ids'] ?? []);
            if ($ruleUnits !== []) {
                $ok = $ok && array_intersect($ruleUnits, $unitIds) !== [];
            }
            if ($ok) {
                return $tpl;
            }
        }

        return null;
    }

    public function ensureDefaultRecruitTemplate(int $tenantId, ?int $createdBy): int
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return 0;
        }
        $existing = $this->listForTenant($tenantId, false);
        foreach ($existing as $row) {
            if (trim((string) ($row['name'] ?? '')) === 'Intégration recrue') {
                return (int) $row['id'];
            }
        }
        $id = $this->create($tenantId, [
            'name' => 'Intégration recrue',
            'description' => 'Parcours d’accueil d’un nouveau membre, de l’arrivée à la validation par l’encadrement.',
            'duration_days' => 30,
            'is_active' => 1,
            'referent_rule' => 'none',
            'auto_rules_json' => ['sources' => ['recruitment', 'manual', 'invitation']],
        ], $createdBy);
        if ($id < 1) {
            return 0;
        }
        $this->replaceSteps($tenantId, $id, [
            [
                'step_key' => 'dossier_personnel',
                'title' => 'Compléter le dossier personnel',
                'description' => 'Renseigner les informations demandées sur la fiche personnelle.',
                'step_type' => MemberIntegrationCatalog::TYPE_PERSONNEL_DOSSIER,
                'responsible_kind' => MemberIntegrationCatalog::RESP_MEMBER,
                'due_after_days' => 7,
                'is_required' => 1,
            ],
            [
                'step_key' => 'contact_referent',
                'title' => 'Prendre contact avec le référent',
                'description' => 'Échanger une première fois avec la personne qui accompagne l’arrivée.',
                'step_type' => MemberIntegrationCatalog::TYPE_TASK,
                'responsible_kind' => MemberIntegrationCatalog::RESP_MEMBER,
                'due_after_days' => 3,
                'is_required' => 1,
            ],
            [
                'step_key' => 'entretien_accueil',
                'title' => 'Entretien d’accueil',
                'description' => 'Rendez-vous d’accueil avec le référent ou l’encadrement.',
                'step_type' => MemberIntegrationCatalog::TYPE_APPOINTMENT,
                'responsible_kind' => MemberIntegrationCatalog::RESP_REFERENT,
                'due_after_days' => 14,
                'is_required' => 1,
            ],
            [
                'step_key' => 'validation_staff',
                'title' => 'Validation de l’intégration',
                'description' => 'L’encadrement confirme que l’arrivée est complète.',
                'step_type' => MemberIntegrationCatalog::TYPE_MANUAL_VALIDATION,
                'responsible_kind' => MemberIntegrationCatalog::RESP_HR,
                'due_after_days' => 30,
                'is_required' => 1,
            ],
        ]);

        return $id;
    }
}
