<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class QualificationDefinitionRepository
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
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualification_definitions' LIMIT 1"
            );

            return (bool) $st?->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $includeArchived = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT d.*,
                       c.name AS category_name,
                       t.name AS type_name,
                       t.code AS type_code,
                       (SELECT COUNT(*) FROM qualification_levels ql WHERE ql.qualification_id = d.id) AS levels_count,
                       (SELECT COUNT(*) FROM personnel_qualifications pq
                         WHERE pq.definition_id = d.id
                           AND COALESCE(pq.admin_status, pq.status) IN (\'obtained\',\'valid\',\'expiring\')) AS holders_count
                FROM personnel_qualification_definitions d
                LEFT JOIN qualification_categories c ON c.id = d.category_id
                LEFT JOIN qualification_types t ON t.id = d.type_id
                WHERE d.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND d.archived_at IS NULL AND COALESCE(d.is_active, 1) = 1';
        }
        $sql .= ' ORDER BY d.name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT d.*, c.name AS category_name, t.name AS type_name, t.code AS type_code
             FROM personnel_qualification_definitions d
             LEFT JOIN qualification_categories c ON c.id = d.category_id
             LEFT JOIN qualification_types t ON t.id = d.type_id
             WHERE d.tenant_id = ? AND d.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM personnel_qualification_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data, ?int $actorId = null): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_qualification_definitions
                (tenant_id, code, name, short_name, description, category_id, type_id,
                 uses_levels, is_permanent, default_validity_months, alert_before_expiry_days,
                 grace_period_days, enforce_level_progression, requires_panel, qualification_scope,
                 badge_media_path, certificate_template_id, certificate_number_format,
                 validity_days, currency_days, requires_exam, renewal_required, is_active,
                 created_by, updated_by, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?,
                 ?, ?, ?, ?, 1,
                 ?, ?, NOW(), NOW())'
        );
        $validityMonths = isset($data['default_validity_months']) && $data['default_validity_months'] !== ''
            ? (int) $data['default_validity_months'] : null;
        $validityDays = $validityMonths !== null ? $validityMonths * 30 : ($data['validity_days'] ?? null);
        $st->execute([
            $tenantId,
            trim((string) ($data['code'] ?? '')),
            trim((string) ($data['name'] ?? '')),
            $this->nullIfEmpty($data['short_name'] ?? null),
            $this->nullIfEmpty($data['description'] ?? null),
            $this->intOrNull($data['category_id'] ?? null),
            $this->intOrNull($data['type_id'] ?? null),
            !empty($data['uses_levels']) ? 1 : 0,
            !empty($data['is_permanent']) ? 1 : 0,
            $validityMonths,
            $this->intOrNull($data['alert_before_expiry_days'] ?? null),
            $this->intOrNull($data['grace_period_days'] ?? null),
            !empty($data['enforce_level_progression']) ? 1 : 0,
            !empty($data['requires_panel']) ? 1 : 0,
            in_array(($data['qualification_scope'] ?? 'global'), ['global', 'unit'], true)
                ? $data['qualification_scope'] : 'global',
            $this->nullIfEmpty($data['badge_media_path'] ?? null),
            $this->intOrNull($data['certificate_template_id'] ?? null),
            $this->nullIfEmpty($data['certificate_number_format'] ?? null),
            $validityDays,
            $this->intOrNull($data['currency_days'] ?? null),
            !empty($data['requires_exam']) ? 1 : 0,
            !empty($data['renewal_required']) ? 1 : 0,
            $actorId,
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data, ?int $actorId = null): bool
    {
        $validityMonths = array_key_exists('default_validity_months', $data)
            ? ($data['default_validity_months'] !== '' && $data['default_validity_months'] !== null
                ? (int) $data['default_validity_months'] : null)
            : null;
        $validityDays = $validityMonths !== null ? $validityMonths * 30 : null;

        $st = $this->pdo->prepare(
            'UPDATE personnel_qualification_definitions SET
                name = ?, short_name = ?, description = ?, category_id = ?, type_id = ?,
                uses_levels = ?, is_permanent = ?, default_validity_months = ?,
                alert_before_expiry_days = ?, grace_period_days = ?,
                enforce_level_progression = ?, requires_panel = ?, qualification_scope = ?,
                badge_media_path = COALESCE(?, badge_media_path),
                certificate_template_id = ?, certificate_number_format = ?,
                validity_days = COALESCE(?, validity_days),
                currency_days = ?, requires_exam = ?, renewal_required = ?,
                updated_by = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );

        return $st->execute([
            trim((string) ($data['name'] ?? '')),
            $this->nullIfEmpty($data['short_name'] ?? null),
            $this->nullIfEmpty($data['description'] ?? null),
            $this->intOrNull($data['category_id'] ?? null),
            $this->intOrNull($data['type_id'] ?? null),
            !empty($data['uses_levels']) ? 1 : 0,
            !empty($data['is_permanent']) ? 1 : 0,
            $validityMonths,
            $this->intOrNull($data['alert_before_expiry_days'] ?? null),
            $this->intOrNull($data['grace_period_days'] ?? null),
            !empty($data['enforce_level_progression']) ? 1 : 0,
            !empty($data['requires_panel']) ? 1 : 0,
            in_array(($data['qualification_scope'] ?? 'global'), ['global', 'unit'], true)
                ? $data['qualification_scope'] : 'global',
            array_key_exists('badge_media_path', $data) ? $this->nullIfEmpty($data['badge_media_path']) : null,
            $this->intOrNull($data['certificate_template_id'] ?? null),
            $this->nullIfEmpty($data['certificate_number_format'] ?? null),
            $validityDays,
            $this->intOrNull($data['currency_days'] ?? null),
            !empty($data['requires_exam']) ? 1 : 0,
            !empty($data['renewal_required']) ? 1 : 0,
            $actorId,
            $tenantId,
            $id,
        ]);
    }

    public function archive(int $tenantId, int $id, ?int $actorId = null): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE personnel_qualification_definitions
             SET archived_at = NOW(), is_active = 0, updated_by = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );

        return $st->execute([$actorId, $tenantId, $id]);
    }

    public function setBadgePath(int $tenantId, int $id, ?string $path): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE personnel_qualification_definitions SET badge_media_path = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([$path, $tenantId, $id]);
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }
}
