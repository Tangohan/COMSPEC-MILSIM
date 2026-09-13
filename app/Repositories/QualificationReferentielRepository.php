<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class QualificationReferentielRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listCategories(int $tenantId): array
    {
        if (!$this->tableExists('qualification_categories')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_categories WHERE tenant_id = ? ORDER BY sort_order ASC, name ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createCategory(int $tenantId, string $name, ?int $parentId = null, int $sortOrder = 0): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_categories (tenant_id, name, parent_category_id, sort_order)
             VALUES (?, ?, ?, ?)'
        );
        $st->execute([$tenantId, trim($name), $parentId, $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $tenantId, int $id, string $name, ?int $parentId, int $sortOrder): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE qualification_categories SET name = ?, parent_category_id = ?, sort_order = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([trim($name), $parentId, $sortOrder, $tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listTypes(int $tenantId): array
    {
        if (!$this->tableExists('qualification_types')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_types WHERE tenant_id = ? ORDER BY name ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createType(int $tenantId, string $name, string $code): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_types (tenant_id, name, code) VALUES (?, ?, ?)'
        );
        $st->execute([$tenantId, trim($name), strtoupper(trim($code))]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listIssuers(int $tenantId, ?string $search = null): array
    {
        if (!$this->tableExists('qualification_issuers')) {
            return [];
        }
        $search = $search !== null ? trim($search) : '';
        if ($search !== '') {
            $term = '%' . (function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120)) . '%';
            $st = $this->pdo->prepare(
                'SELECT i.*, p.name AS parent_name
                 FROM qualification_issuers i
                 LEFT JOIN qualification_issuers p ON p.id = i.parent_issuer_id
                 WHERE i.tenant_id = ?
                   AND (i.name LIKE ? OR (i.short_name IS NOT NULL AND i.short_name LIKE ?) OR i.issuer_kind LIKE ?)
                 ORDER BY i.name ASC'
            );
            $st->execute([$tenantId, $term, $term, $term]);
        } else {
            $st = $this->pdo->prepare(
                'SELECT i.*, p.name AS parent_name
                 FROM qualification_issuers i
                 LEFT JOIN qualification_issuers p ON p.id = i.parent_issuer_id
                 WHERE i.tenant_id = ?
                 ORDER BY i.name ASC'
            );
            $st->execute([$tenantId]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findIssuerByName(int $tenantId, string $name): ?array
    {
        if (!$this->tableExists('qualification_issuers') || $tenantId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_issuers WHERE tenant_id = ? AND name = ? LIMIT 1'
        );
        $st->execute([$tenantId, trim($name)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createIssuer(
        int $tenantId,
        string $name,
        ?string $shortName = null,
        string $kind = 'unit',
        ?int $parentId = null
    ): int {
        $kind = in_array($kind, ['school', 'unit', 'external'], true) ? $kind : 'unit';
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_issuers (tenant_id, name, short_name, issuer_kind, parent_issuer_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            trim($name),
            $shortName !== null && trim($shortName) !== '' ? trim($shortName) : null,
            $kind,
            $parentId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insère les organismes US Army d’exemple manquants (idempotent par nom).
     *
     * @return array{created: int, skipped: int}
     */
    public function seedUsArmyExampleIssuers(int $tenantId): array
    {
        $created = 0;
        $skipped = 0;
        $byKey = [];
        foreach (\App\Support\QualificationUsArmyIssuerExamples::catalog() as $row) {
            $key = (string) $row['key'];
            $name = (string) $row['name'];
            $existing = $this->findIssuerByName($tenantId, $name);
            if ($existing !== null) {
                $byKey[$key] = (int) $existing['id'];
                $skipped++;
                continue;
            }
            $parentId = null;
            $parentKey = $row['parent_key'] ?? null;
            if (is_string($parentKey) && $parentKey !== '' && isset($byKey[$parentKey])) {
                $parentId = $byKey[$parentKey];
            }
            $id = $this->createIssuer(
                $tenantId,
                $name,
                isset($row['short_name']) ? (string) $row['short_name'] : null,
                (string) ($row['issuer_kind'] ?? 'unit'),
                $parentId
            );
            $byKey[$key] = $id;
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return list<array<string, mixed>> */
    public function listLevels(int $tenantId, int $qualificationId): array
    {
        if (!$this->tableExists('qualification_levels')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_levels
             WHERE tenant_id = ? AND qualification_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute([$tenantId, $qualificationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createLevel(int $tenantId, int $qualificationId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_levels
                (tenant_id, qualification_id, name, short_name, sort_order, description, previous_level_id, badge_media_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $qualificationId,
            trim((string) ($data['name'] ?? '')),
            isset($data['short_name']) && trim((string) $data['short_name']) !== ''
                ? trim((string) $data['short_name']) : null,
            (int) ($data['sort_order'] ?? 0),
            isset($data['description']) && trim((string) $data['description']) !== ''
                ? trim((string) $data['description']) : null,
            isset($data['previous_level_id']) && $data['previous_level_id'] !== ''
                ? (int) $data['previous_level_id'] : null,
            isset($data['badge_media_path']) && trim((string) $data['badge_media_path']) !== ''
                ? trim((string) $data['badge_media_path']) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateLevel(int $tenantId, int $id, array $data): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE qualification_levels SET
                name = ?, short_name = ?, sort_order = ?, description = ?,
                previous_level_id = ?,
                badge_media_path = COALESCE(?, badge_media_path),
                updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([
            trim((string) ($data['name'] ?? '')),
            isset($data['short_name']) && trim((string) $data['short_name']) !== ''
                ? trim((string) $data['short_name']) : null,
            (int) ($data['sort_order'] ?? 0),
            isset($data['description']) && trim((string) $data['description']) !== ''
                ? trim((string) $data['description']) : null,
            isset($data['previous_level_id']) && $data['previous_level_id'] !== ''
                ? (int) $data['previous_level_id'] : null,
            array_key_exists('badge_media_path', $data)
                ? (trim((string) ($data['badge_media_path'] ?? '')) ?: null)
                : null,
            $tenantId,
            $id,
        ]);
    }

    public function deleteLevel(int $tenantId, int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM qualification_levels WHERE tenant_id = ? AND id = ?');

        return $st->execute([$tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listPrerequisites(int $tenantId, int $qualificationId): array
    {
        if (!$this->tableExists('qualification_prerequisites')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT p.*, d.name AS required_name, d.code AS required_code
             FROM qualification_prerequisites p
             JOIN personnel_qualification_definitions d ON d.id = p.required_qualification_id
             WHERE p.tenant_id = ? AND p.qualification_id = ?
             ORDER BY p.id ASC'
        );
        $st->execute([$tenantId, $qualificationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addPrerequisite(
        int $tenantId,
        int $qualificationId,
        int $requiredId,
        ?int $minLevelId,
        string $requirementType = 'obtention'
    ): int {
        $type = in_array($requirementType, ['obtention', 'recyclage'], true) ? $requirementType : 'obtention';
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_prerequisites
                (tenant_id, qualification_id, required_qualification_id, minimum_level_id, requirement_type)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $qualificationId, $requiredId, $minLevelId, $type]);

        return (int) $this->pdo->lastInsertId();
    }

    public function removePrerequisite(int $tenantId, int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM qualification_prerequisites WHERE tenant_id = ? AND id = ?');

        return $st->execute([$tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listCertificateTemplates(?int $tenantId = null): array
    {
        if (!$this->tableExists('qualification_certificate_templates')) {
            return [];
        }
        if ($tenantId === null) {
            $st = $this->pdo->query(
                'SELECT * FROM qualification_certificate_templates
                 WHERE tenant_id IS NULL OR is_system = 1
                 ORDER BY is_default DESC, name ASC'
            );

            return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_certificate_templates
             WHERE tenant_id IS NULL OR tenant_id = ? OR is_system = 1
             ORDER BY is_default DESC, name ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCertificateTemplate(?int $id): ?array
    {
        if ($id === null || $id <= 0 || !$this->tableExists('qualification_certificate_templates')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM qualification_certificate_templates WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function defaultCertificateTemplate(): ?array
    {
        if (!$this->tableExists('qualification_certificate_templates')) {
            return null;
        }
        $st = $this->pdo->query(
            'SELECT * FROM qualification_certificate_templates
             WHERE is_system = 1 AND is_default = 1
             ORDER BY id ASC LIMIT 1'
        );
        $row = $st?->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listCustomFields(int $tenantId, int $qualificationId): array
    {
        if (!$this->tableExists('qualification_custom_fields')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_custom_fields
             WHERE tenant_id = ? AND qualification_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute([$tenantId, $qualificationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCustomField(int $tenantId, int $qualificationId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_custom_fields
                (tenant_id, qualification_id, name, code, field_type, is_required, default_value, sort_order, visibility)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $qualificationId,
            trim((string) ($data['name'] ?? '')),
            strtoupper(trim((string) ($data['code'] ?? ''))),
            (string) ($data['field_type'] ?? 'text_short'),
            !empty($data['is_required']) ? 1 : 0,
            $data['default_value'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['visibility'] ?? 'normal'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteCustomField(int $tenantId, int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM qualification_custom_fields WHERE tenant_id = ? AND id = ?');

        return $st->execute([$tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listPermissionGrants(int $tenantId, int $qualificationId): array
    {
        if (!$this->tableExists('qualification_grants_permission')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM qualification_grants_permission
             WHERE tenant_id = ? AND qualification_id = ?
             ORDER BY id ASC'
        );
        $st->execute([$tenantId, $qualificationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addPermissionGrant(
        int $tenantId,
        int $qualificationId,
        string $permissionCode,
        ?int $levelId = null
    ): int {
        $st = $this->pdo->prepare(
            'INSERT INTO qualification_grants_permission
                (tenant_id, qualification_id, qualification_level_id, permission_code)
             VALUES (?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $qualificationId, $levelId, trim($permissionCode)]);

        return (int) $this->pdo->lastInsertId();
    }

    public function removePermissionGrant(int $tenantId, int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM qualification_grants_permission WHERE tenant_id = ? AND id = ?');

        return $st->execute([$tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listPostRequirements(int $tenantId, ?int $postId = null): array
    {
        if (!$this->tableExists('post_qualification_requirements')) {
            return [];
        }
        if ($postId !== null) {
            $st = $this->pdo->prepare(
                'SELECT r.*, d.name AS qualification_name, d.code AS qualification_code
                 FROM post_qualification_requirements r
                 JOIN personnel_qualification_definitions d ON d.id = r.qualification_id
                 WHERE r.tenant_id = ? AND r.post_id = ?'
            );
            $st->execute([$tenantId, $postId]);
        } else {
            $st = $this->pdo->prepare(
                'SELECT r.*, d.name AS qualification_name, d.code AS qualification_code
                 FROM post_qualification_requirements r
                 JOIN personnel_qualification_definitions d ON d.id = r.qualification_id
                 WHERE r.tenant_id = ?
                 ORDER BY r.post_id ASC'
            );
            $st->execute([$tenantId]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertPostRequirement(
        int $tenantId,
        int $postId,
        int $qualificationId,
        ?int $minLevelId,
        string $requirementType = 'required'
    ): void {
        $type = in_array($requirementType, ['required', 'recommended'], true) ? $requirementType : 'required';
        $st = $this->pdo->prepare(
            'INSERT INTO post_qualification_requirements
                (tenant_id, post_id, qualification_id, minimum_level_id, requirement_type)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE minimum_level_id = VALUES(minimum_level_id),
                                     requirement_type = VALUES(requirement_type)'
        );
        $st->execute([$tenantId, $postId, $qualificationId, $minLevelId, $type]);
    }
}
