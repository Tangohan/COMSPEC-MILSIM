<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;
use PDO;

/**
 * Emplois de dossier dérivés de l’ORBAT : une unité crée un emploi du même nom.
 * Pas de catalogue militaire semé. Les postes nommés à l’affectation sont créés à la demande.
 */
final class UnitJobRoleSyncService
{
    public const CATEGORY_SLUG = 'organisation';

    public const CATEGORY_NAME = 'Organisation';

    private PDO $pdo;

    private PersonnelJobRoleRepository $jobRoles;

    private static ?bool $sourceUnitColumn = null;

    public function __construct(?PDO $pdo = null, ?PersonnelJobRoleRepository $jobRoles = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->jobRoles = $jobRoles ?? new PersonnelJobRoleRepository();
    }

    public static function slugForUnit(int $unitId): string
    {
        return 'unit-' . max(0, $unitId);
    }

    public static function isGenericAssignmentLabel(string $label): bool
    {
        $n = mb_strtolower(trim($label), 'UTF-8');

        return $n === '' || $n === 'membre' || $n === 'member';
    }

    public function ensureOrganisationCategory(int $tenantId): ?int
    {
        if ($tenantId < 1 || !$this->jobRoles->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT id FROM personnel_job_role_categories WHERE tenant_id = ? AND slug = ? LIMIT 1'
        );
        $st->execute([$tenantId, self::CATEGORY_SLUG]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        return $this->jobRoles->createCategory($tenantId, null, self::CATEGORY_NAME, self::CATEGORY_SLUG, 0);
    }

    public function ensureForUnit(int $tenantId, int $unitId, string $unitName): ?int
    {
        if ($tenantId < 1 || $unitId < 1 || !$this->jobRoles->tablesExist()) {
            return null;
        }
        $unitName = trim($unitName);
        if ($unitName === '') {
            return null;
        }
        $categoryId = $this->ensureOrganisationCategory($tenantId);
        if ($categoryId === null || $categoryId < 1) {
            return null;
        }

        $existingId = $this->findIdForUnit($tenantId, $unitId);
        if ($existingId !== null) {
            $this->renameIfNeeded($existingId, $tenantId, $unitName);
            $this->attachSourceUnit($existingId, $tenantId, $unitId);

            return $existingId;
        }

        $slug = self::slugForUnit($unitId);
        $id = $this->jobRoles->createRole(
            $tenantId,
            $categoryId,
            $unitName,
            $slug,
            'Emploi lié à l’unité « ' . $unitName . ' » de l’organigramme.',
            0,
            false
        );
        if ($id < 1) {
            return null;
        }
        $this->attachSourceUnit($id, $tenantId, $unitId);

        return $id;
    }

    public function backfillFromUnits(int $tenantId): int
    {
        if ($tenantId < 1 || !$this->jobRoles->tablesExist()) {
            return 0;
        }
        $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY id ASC');
        $st->execute([$tenantId]);
        $n = 0;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = $this->ensureForUnit($tenantId, (int) ($row['id'] ?? 0), (string) ($row['name'] ?? ''));
            if ($id !== null && $id > 0) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param list<array{unit_id:int, role_name?:string}> $assignments
     */
    public function applyAssignments(int $tenantId, int $userId, array $assignments): void
    {
        if ($tenantId < 1 || $userId < 1 || !$this->jobRoles->tablesExist() || !$this->jobRoles->pivotTableExists()) {
            return;
        }
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                continue;
            }
            $unitId = (int) ($assignment['unit_id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $unitName = $this->unitName($tenantId, $unitId);
            if ($unitName === '') {
                continue;
            }
            $unitJobId = $this->ensureForUnit($tenantId, $unitId, $unitName);
            $poste = trim((string) ($assignment['role_name'] ?? ''));
            $jobId = $unitJobId;
            if (!self::isGenericAssignmentLabel($poste) && mb_strtolower($poste, 'UTF-8') !== mb_strtolower($unitName, 'UTF-8')) {
                $customId = $this->jobRoles->findOrCreateImportedRoleByLabel($tenantId, $poste);
                if ($customId !== null && $customId > 0) {
                    $jobId = $customId;
                }
            }
            if ($jobId !== null && $jobId > 0) {
                $this->jobRoles->ensureUserHasJobRole($tenantId, $userId, $jobId);
            }
        }
    }

    /**
     * Retire les emplois issus du catalogue militaire s’ils ne sont collés à aucun dossier.
     * Conserve les emplois créés par la communauté, ceux liés à une unité, et ceux déjà attribués.
     */
    public function purgeUnusedCatalogJobs(int $tenantId): int
    {
        if ($tenantId < 1 || !$this->jobRoles->tablesExist()) {
            return 0;
        }

        $keep = [];
        if ($this->jobRoles->pivotTableExists()) {
            $st = $this->pdo->prepare('SELECT DISTINCT personnel_job_role_id FROM personnel_profile_job_roles WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            while ($id = $st->fetchColumn()) {
                $keep[(int) $id] = true;
            }
        }
        if ($this->tableExists('orbat_billets') && $this->columnExists('orbat_billets', 'job_role_id')) {
            $st = $this->pdo->prepare(
                'SELECT DISTINCT job_role_id FROM orbat_billets WHERE tenant_id = ? AND job_role_id IS NOT NULL'
            );
            $st->execute([$tenantId]);
            while ($id = $st->fetchColumn()) {
                $keep[(int) $id] = true;
            }
        }

        $catalogSlugs = MilitaryOperationalRoleCatalog::catalogSlugSet();
        $sourceSql = $this->hasSourceUnitColumn()
            ? ' AND (source_unit_id IS NULL OR source_unit_id = 0)'
            : '';
        $st = $this->pdo->prepare(
            'SELECT id, slug, is_system FROM personnel_job_roles
             WHERE tenant_id = ? AND slug NOT LIKE \'unit-%\'' . $sourceSql
        );
        $st->execute([$tenantId]);
        $deleted = 0;
        $delPerms = null;
        try {
            $delPerms = $this->pdo->prepare('DELETE FROM personnel_job_role_permissions WHERE personnel_job_role_id = ?');
        } catch (\Throwable) {
        }
        $del = $this->pdo->prepare('DELETE FROM personnel_job_roles WHERE id = ? AND tenant_id = ?');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            $slug = (string) ($row['slug'] ?? '');
            $isSystem = (int) ($row['is_system'] ?? 0) === 1;
            if ($id < 1 || isset($keep[$id])) {
                continue;
            }
            $fromCatalog = $slug !== '' && isset($catalogSlugs[$slug]);
            if (!$fromCatalog && !$isSystem) {
                continue;
            }
            if ($delPerms !== null) {
                try {
                    $delPerms->execute([$id]);
                } catch (\Throwable) {
                }
            }
            $del->execute([$id, $tenantId]);
            $deleted += $del->rowCount();
        }

        $unlock = $this->pdo->prepare(
            'UPDATE personnel_job_roles SET is_system = 0 WHERE tenant_id = ? AND is_system = 1 AND slug NOT LIKE \'unit-%\''
        );
        $unlock->execute([$tenantId]);

        $this->deleteEmptyCategories($tenantId);

        return $deleted;
    }

    public function purgeAllUnusedCatalogJobs(): int
    {
        $n = 0;
        $st = $this->pdo->query('SELECT id FROM tenants');
        if (!$st) {
            return 0;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $n += $this->purgeUnusedCatalogJobs((int) ($row['id'] ?? 0));
        }

        return $n;
    }

    public function backfillAllTenants(): int
    {
        $n = 0;
        $st = $this->pdo->query('SELECT id FROM tenants');
        if (!$st) {
            return 0;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $n += $this->backfillFromUnits((int) ($row['id'] ?? 0));
        }

        return $n;
    }

    private function findIdForUnit(int $tenantId, int $unitId): ?int
    {
        if ($this->hasSourceUnitColumn()) {
            $st = $this->pdo->prepare(
                'SELECT id FROM personnel_job_roles WHERE tenant_id = ? AND source_unit_id = ? LIMIT 1'
            );
            $st->execute([$tenantId, $unitId]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }
        $st = $this->pdo->prepare(
            'SELECT id FROM personnel_job_roles WHERE tenant_id = ? AND slug = ? LIMIT 1'
        );
        $st->execute([$tenantId, self::slugForUnit($unitId)]);
        $id = (int) ($st->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    }

    private function renameIfNeeded(int $jobRoleId, int $tenantId, string $unitName): void
    {
        $st = $this->pdo->prepare(
            'UPDATE personnel_job_roles SET name = ? WHERE id = ? AND tenant_id = ? AND name <> ?'
        );
        $st->execute([$unitName, $jobRoleId, $tenantId, $unitName]);
    }

    private function attachSourceUnit(int $jobRoleId, int $tenantId, int $unitId): void
    {
        if (!$this->hasSourceUnitColumn() || $jobRoleId < 1 || $unitId < 1) {
            return;
        }
        $st = $this->pdo->prepare(
            'UPDATE personnel_job_roles SET source_unit_id = ? WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$unitId, $jobRoleId, $tenantId]);
    }

    private function unitName(int $tenantId, int $unitId): string
    {
        $st = $this->pdo->prepare('SELECT name FROM units WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$unitId, $tenantId]);
        $name = $st->fetchColumn();

        return is_string($name) ? trim($name) : '';
    }

    private function deleteEmptyCategories(int $tenantId): void
    {
        $guard = 0;
        while ($guard++ < 20) {
            $st = $this->pdo->prepare(
                'DELETE c FROM personnel_job_role_categories c
                 WHERE c.tenant_id = ?
                   AND c.slug <> ?
                   AND NOT EXISTS (SELECT 1 FROM personnel_job_roles r WHERE r.category_id = c.id)
                   AND NOT EXISTS (SELECT 1 FROM personnel_job_role_categories ch WHERE ch.parent_id = c.id)'
            );
            $st->execute([$tenantId, self::CATEGORY_SLUG]);
            if ($st->rowCount() < 1) {
                break;
            }
        }
    }

    private function hasSourceUnitColumn(): bool
    {
        if (self::$sourceUnitColumn !== null) {
            return self::$sourceUnitColumn;
        }
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_job_roles' AND COLUMN_NAME = 'source_unit_id' LIMIT 1"
        );
        self::$sourceUnitColumn = (bool) ($st && $st->fetchColumn());

        return self::$sourceUnitColumn;
    }

    public static function resetColumnCache(): void
    {
        self::$sourceUnitColumn = null;
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }
}
