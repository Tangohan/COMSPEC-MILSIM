<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use PDO;

/** National nomenclatures are shared; every personnel/teaching relationship is tenant scoped. */
final class MilitarySpecialtyRepository
{
    private PDO $pdo;
    private ?bool $readyCache = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->readyCache !== null) {
            return $this->readyCache;
        }
        $schema = new SchemaInspector($this->pdo);
        foreach (['military_specialty_systems', 'military_specialty_codes', 'military_specialty_levels',
            'personnel_job_role_specialty_codes', 'personnel_profile_specialty_codes', 'qualification_specialty_targets'] as $table) {
            if (!$schema->tableExists($table)) {
                return $this->readyCache = false;
            }
        }
        return $this->readyCache = true;
    }

    /** Includes unverified references for declared records, never archived references as new choices. */
    public function catalog(?string $country = null): array
    {
        if (!$this->ready()) {
            return [];
        }
        $where = ' WHERE c.archived_at IS NULL AND s.archived_at IS NULL';
        $params = [];
        if ($country !== null && trim($country) !== '') {
            $where .= ' AND p.iso2 = ?';
            $params[] = strtoupper(trim($country));
        }
        $st = $this->pdo->prepare($this->codeSelect() . $where . ' ORDER BY p.name_fr, b.name, s.name, c.code, c.official_title, c.id');
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $levels = $this->levels(array_column($rows, 'id'), false);
        foreach ($rows as &$row) {
            $row['levels'] = $levels[(int) $row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    /** Historical dossiers must remain readable after a code/system/level is archived. */
    public function findCode(int $id, bool $forUpdate = false): ?array
    {
        if ($id < 1 || !$this->ready()) {
            return null;
        }
        $locking = $forUpdate && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';
        $st = $this->pdo->prepare($this->codeSelect() . ' WHERE c.id = ?' . ($locking ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['levels'] = $this->levels([$id], true, $locking)[$id] ?? [];
        return $row;
    }

    public function listForMember(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->ready()) {
            return [];
        }
        $st = $this->pdo->prepare($this->relationshipSelect('a') . '
            FROM personnel_profile_specialty_codes a' . $this->relationshipJoins() . '
            WHERE a.tenant_id = ? AND a.user_id = ? ORDER BY a.status, p.name_fr, s.name, c.code, a.id');
        $st->execute([$tenantId, $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Public summary for members already authorized by the directory/ORBAT caller.
     * No dossier proof, validation author or dates leave this projection.
     * @param list<int|string> $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function confirmedForMembers(int $tenantId, array $userIds): array
    {
        if ($tenantId < 1 || $userIds === [] || !$this->ready()) {
            return [];
        }
        $ids = [];
        foreach ($userIds as $raw) {
            if ((is_int($raw) || (is_string($raw) && ctype_digit($raw)))
                && filter_var($raw, FILTER_VALIDATE_INT) !== false && (int) $raw > 0) {
                $ids[(int) $raw] = (int) $raw;
            }
        }
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk(array_values($ids), 400) as $batch) {
            $st = $this->pdo->prepare($this->relationshipSelect('a', true) . '
                FROM personnel_profile_specialty_codes a' . $this->relationshipJoins() . '
                WHERE a.tenant_id = ? AND a.user_id IN (' . implode(',', array_fill(0, count($batch), '?')) . ")
                    AND a.status = 'confirmed' AND c.verification_status = 'verified'
                    AND c.archived_at IS NULL AND s.archived_at IS NULL
                    AND (a.specialty_level_id IS NULL OR (l.id IS NOT NULL AND l.verification_status = 'verified' AND l.archived_at IS NULL))
                ORDER BY a.user_id, p.name_fr, s.name, c.code, c.id");
            $st->execute([$tenantId, ...$batch]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['user_id']][] = $row;
            }
        }
        return $out;
    }

    /** @return list<int> */
    public function jobCodeIds(int $tenantId, int $jobId, bool $forUpdate = false): array
    {
        if ($tenantId < 1 || $jobId < 1 || !$this->ready()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT a.specialty_code_id FROM personnel_job_role_specialty_codes a
            INNER JOIN personnel_job_roles j ON j.id = a.job_role_id AND j.tenant_id = a.tenant_id
            WHERE a.tenant_id = ? AND a.job_role_id = ? ORDER BY a.specialty_code_id'
            . ($forUpdate && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : ''));
        $st->execute([$tenantId, $jobId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function qualificationTargets(int $tenantId, int $definitionId, bool $forUpdate = false): array
    {
        if ($tenantId < 1 || $definitionId < 1 || !$this->ready()) {
            return [];
        }
        $st = $this->pdo->prepare($this->relationshipSelect('a') . '
            FROM qualification_specialty_targets a
            INNER JOIN personnel_qualification_definitions d ON d.id = a.definition_id AND d.tenant_id = a.tenant_id'
            . $this->relationshipJoins() . '
            WHERE a.tenant_id = ? AND a.definition_id = ? ORDER BY a.specialty_code_id'
            . ($forUpdate && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : ''));
        $st->execute([$tenantId, $definitionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function codeSelect(): string
    {
        return 'SELECT c.*, s.system_key, s.name AS system_name, s.personnel_category,
            s.archived_at AS system_archived_at, s.service_id,
            b.code AS service_code, b.name AS service_name, b.country_id,
            p.iso2 AS country_code, p.name_fr AS country_name_fr, p.name_en AS country_name_en,
            r.name AS source_name, r.url AS source_url, r.publisher AS source_publisher,
            r.published_at AS source_published_at, r.checked_at AS source_checked_at
            FROM military_specialty_codes c
            INNER JOIN military_specialty_systems s ON s.id = c.system_id
            INNER JOIN military_services b ON b.id = s.service_id
            INNER JOIN countries p ON p.id = b.country_id
            LEFT JOIN military_sources r ON r.id = c.source_id';
    }

    /** @param list<int|string> $ids @return array<int, list<array<string, mixed>>> */
    private function levels(array $ids, bool $includeArchived, bool $forUpdate = false): array
    {
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT l.*, r.name AS source_name, r.url AS source_url,
            r.publisher AS source_publisher FROM military_specialty_levels l
            LEFT JOIN military_sources r ON r.id = l.source_id
            WHERE l.specialty_code_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            . ($includeArchived ? '' : ' AND l.archived_at IS NULL') . ' ORDER BY l.sort_order, l.id'
            . ($forUpdate ? ' FOR UPDATE' : ''));
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['specialty_code_id']][] = $row;
        }
        return $out;
    }

    private function relationshipSelect(string $alias, bool $publicSummary = false): string
    {
        $assignment = $publicSummary
            ? $alias . '.user_id, ' . $alias . '.specialty_code_id, ' . $alias . '.specialty_level_id, ' . $alias . '.status'
            : $alias . '.*';
        return 'SELECT ' . $assignment . ', c.code, c.reference_key, c.official_title, c.label_fr, c.label_en,
            c.kind, c.is_pattern, c.description, c.verification_status, c.verified_at,
            c.archived_at AS code_archived_at, s.archived_at AS system_archived_at,
            s.system_key, s.name AS system_name, s.personnel_category,
            b.name AS service_name, b.code AS service_code, p.iso2 AS country_code,
            p.name_fr AS country_name_fr, p.name_en AS country_name_en,
            l.level_code, l.label_fr AS level_label_fr, l.label_en AS level_label_en,
            l.official_title AS level_official_title, l.awarded_code,
            l.verification_status AS level_verification_status, l.archived_at AS level_archived_at,
            r.name AS source_name, r.url AS source_url';
    }

    private function relationshipJoins(): string
    {
        return '
            INNER JOIN military_specialty_codes c ON c.id = a.specialty_code_id
            INNER JOIN military_specialty_systems s ON s.id = c.system_id
            INNER JOIN military_services b ON b.id = s.service_id
            INNER JOIN countries p ON p.id = b.country_id
            LEFT JOIN military_specialty_levels l ON l.id = a.specialty_level_id AND l.specialty_code_id = a.specialty_code_id
            LEFT JOIN military_sources r ON r.id = c.source_id';
    }
}
