<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use App\Support\SqlText;
use PDO;

/**
 * Emplois de dossier dérivés de l’ORBAT : une unité crée un emploi du même nom.
 * Pas de catalogue militaire semé. La synchronisation des postes nommés à l’affectation est facultative.
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
            'SELECT id FROM personnel_job_role_categories WHERE tenant_id = ? AND ' . SqlText::equals($this->pdo, 'slug') . ' LIMIT 1'
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
            // The job label may have been customised after creation; unit sync must not overwrite it.
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
        if ($tenantId < 1 || $userId < 1) {
            return;
        }
        $settings = $this->pdo->prepare('SELECT settings FROM tenants WHERE id = ? LIMIT 1');
        $settings->execute([$tenantId]);
        $raw = json_decode((string) ($settings->fetchColumn() ?: '{}'), true);
        if (!PersonnelJobRoleAssignmentsSettings::resolve(is_array($raw) ? $raw : [])['sync_jobs_from_assignments']) {
            return;
        }
        if (!$this->jobRoles->tablesExist() || !$this->jobRoles->pivotTableExists()) {
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
        // Automatic migration/maintenance cleanup must never delete an organisation's reference data.
        // Deletion is an explicit tenant action; being unused or resembling a seed is not ownership evidence.
        return 0;
    }

    public function purgeAllUnusedCatalogJobs(): int
    {
        $n = 0;
        foreach ($this->tenantIds() as $tenantId) {
            $n += $this->purgeUnusedCatalogJobs($tenantId);
        }

        return $n;
    }

    /**
     * Retire les emplois auto-créés depuis une unité s’ils ne sont collés à aucun dossier.
     * Ne touche pas aux emplois saisis à la main par la communauté.
     */
    public function purgeUnusedUnitDerivedJobs(int $tenantId): int
    {
        // Automatic migration/maintenance cleanup must never delete an organisation's reference data.
        // Deletion is an explicit tenant action; being unused or resembling a seed is not ownership evidence.
        return 0;
    }

    /**
     * Catalogue militaire inutilisé + emplois d’unité inutilisés.
     * Conserve toute attribution et tout emploi saisi à la main.
     */
    public function purgeUnusedAutoCreatedJobs(int $tenantId): int
    {
        return $this->purgeUnusedCatalogJobs($tenantId) + $this->purgeUnusedUnitDerivedJobs($tenantId);
    }

    public function purgeAllUnusedAutoCreatedJobs(): int
    {
        $n = 0;
        foreach ($this->tenantIds() as $tenantId) {
            $n += $this->purgeUnusedAutoCreatedJobs($tenantId);
        }

        return $n;
    }

    /**
     * Réservé à la création d’une communauté (unités déjà posées).
     * Interdit depuis le pipeline de migrations : un référentiel vidé ne doit pas revenir.
     */
    public function backfillAllTenants(): int
    {
        $n = 0;
        foreach ($this->tenantIds() as $tenantId) {
            $n += $this->backfillFromUnits($tenantId);
        }

        return $n;
    }

    /**
     * @return list<int>
     */
    private function tenantIds(): array
    {
        $st = $this->pdo->query('SELECT id FROM tenants');
        if (!$st) {
            return [];
        }
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $st->closeCursor();

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
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
            'SELECT id FROM personnel_job_roles WHERE tenant_id = ? AND ' . SqlText::equals($this->pdo, 'slug') . ' LIMIT 1'
        );
        $st->execute([$tenantId, self::slugForUnit($unitId)]);
        $id = (int) ($st->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
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
