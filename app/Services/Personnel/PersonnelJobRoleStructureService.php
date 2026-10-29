<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\GradeRepository;
use InvalidArgumentException;
use PDO;

/** Optional descriptive links. They never assign a member, move an ORBAT unit or grant permissions. */
final class PersonnelJobRoleStructureService
{
    public const KINDS = ['employment' => 'Emploi', 'function' => 'Fonction'];

    private PDO $pdo;
    private ?GradeRepository $grades;
    private ?bool $ready = null;
    private bool $lockingHierarchyRead = false;

    public function __construct(?PDO $pdo = null, ?GradeRepository $grades = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->grades = $grades;
    }

    public function schemaReady(): bool
    {
        return $this->ready ??= $this->tableExists('personnel_job_role_structure');
    }

    /** @return array{ready:bool,roles:array,units:array,grades:array,specialties:array,kinds:array} */
    public function options(int $tenantId): array
    {
        if ($tenantId < 1) {
            return ['ready'=>$this->schemaReady(), 'roles'=>[], 'units'=>[], 'grades'=>[], 'specialties'=>[], 'kinds'=>self::KINDS];
        }
        $roles = $this->pdo->prepare('SELECT id, name, category_id FROM personnel_job_roles WHERE tenant_id = ? ORDER BY name, id');
        $roles->execute([$tenantId]);
        $units = $this->pdo->prepare('SELECT id, name FROM units WHERE tenant_id = ? ORDER BY name, id');
        $units->execute([$tenantId]);
        $specialties = $this->tableExists('military_specialties')
            ? $this->pdo->query('SELECT * FROM military_specialties ORDER BY label_fr, id')->fetchAll(PDO::FETCH_ASSOC)
            : [];

        return [
            'ready'=>$this->schemaReady(),
            'roles'=>$roles->fetchAll(PDO::FETCH_ASSOC),
            'units'=>$units->fetchAll(PDO::FETCH_ASSOC),
            // The native list includes tenant overrides and the tenant-owned custom grade system.
            'grades'=>($this->grades ??= new GradeRepository())->listForTenant($tenantId),
            'specialties'=>$specialties,
            'kinds'=>self::KINDS,
        ];
    }

    /** @return array<int,array{parent_job_role_id:?int,linked_unit_id:?int,grade_id:?int,specialty_id:?int,code:string,kind:string}> */
    public function listMetadata(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT s.* FROM personnel_job_role_structure s
             INNER JOIN personnel_job_roles r ON r.id = s.job_role_id AND r.tenant_id = s.tenant_id
             WHERE s.tenant_id = ? ORDER BY s.job_role_id'
            . ($this->lockingHierarchyRead && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['job_role_id']] = $this->normalizeStored($row);
        }
        return $out;
    }

    /**
     * roleId=0 validates a creation before the role itself is inserted.
     * The returned metadata is descriptive, not an eligibility or permission rule.
     * @return array{metadata:array{parent_job_role_id:?int,linked_unit_id:?int,grade_id:?int,specialty_id:?int,code:string,kind:string},errors:array<string,string>}
     */
    public function validate(int $tenantId, int $roleId, array $input): array
    {
        $errors = [];
        $metadata = ['parent_job_role_id'=>null, 'linked_unit_id'=>null, 'grade_id'=>null, 'specialty_id'=>null, 'code'=>'', 'kind'=>'employment'];
        if ($tenantId < 1 || $roleId < 0 || ($roleId > 0 && !$this->belongsToTenant('personnel_job_roles', $roleId, $tenantId))) {
            $errors['job_role_id'] = 'Cet emploi n’appartient pas à votre communauté.';
        }
        if (!$this->schemaReady()) {
            $errors['schema'] = 'Les rattachements des emplois nécessitent la migration du module.';
        }
        foreach (['parent_job_role_id','linked_unit_id','grade_id','specialty_id'] as $field) {
            $value = $input[$field] ?? null;
            if ($value === null || $value === '' || $value === 0 || $value === '0') continue;
            if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || (int) $value < 1) {
                $errors[$field] = 'Choisissez une référence valide.';
                continue;
            }
            $metadata[$field] = (int) $value;
        }
        $rawCode = $input['code'] ?? '';
        if (!is_string($rawCode) || mb_strlen($rawCode) > 40 || preg_match('/[\x00-\x1f\x7f]/u', $rawCode)) {
            $errors['code'] = 'Le code doit contenir au maximum 40 caractères, sans caractère de contrôle.';
        } else {
            $metadata['code'] = trim($rawCode);
        }
        $rawKind = $input['kind'] ?? 'employment';
        if (!is_string($rawKind) || !isset(self::KINDS[$rawKind])) {
            $errors['kind'] = 'Choisissez « Emploi » ou « Fonction ».';
        } else {
            $metadata['kind'] = $rawKind;
        }
        $parent = $metadata['parent_job_role_id'];
        if ($parent !== null) {
            if (!$this->belongsToTenant('personnel_job_roles', $parent, $tenantId)) {
                $errors['parent_job_role_id'] = 'L’emploi parent doit appartenir à votre communauté.';
            } elseif ($parent === $roleId || $this->wouldCycle($tenantId, $roleId, $parent)) {
                $errors['parent_job_role_id'] = 'Ce rattachement créerait une boucle dans les emplois et fonctions.';
            }
        }
        if ($metadata['linked_unit_id'] !== null && !$this->belongsToTenant('units', $metadata['linked_unit_id'], $tenantId)) {
            $errors['linked_unit_id'] = 'L’unité ORBAT doit appartenir à votre communauté.';
        }
        if ($metadata['grade_id'] !== null) {
            $allowed = array_map(static fn (array $grade): int => (int) $grade['id'], ($this->grades ??= new GradeRepository())->listForTenant($tenantId));
            if (!in_array($metadata['grade_id'], $allowed, true)) {
                $errors['grade_id'] = 'Le grade sélectionné n’est pas disponible dans le référentiel de votre communauté.';
            }
        }
        if ($metadata['specialty_id'] !== null) {
            $found = false;
            if ($this->tableExists('military_specialties')) {
                $stmt = $this->pdo->prepare('SELECT id FROM military_specialties WHERE id = ?');
                $stmt->execute([$metadata['specialty_id']]);
                $found = $stmt->fetchColumn() !== false;
            }
            if (!$found) $errors['specialty_id'] = 'La spécialité sélectionnée n’existe pas dans le référentiel.';
        }
        return ['metadata'=>$metadata, 'errors'=>$errors];
    }

    /** Revalidates while holding the tenant lock; participates in an existing transaction without committing it. */
    public function save(int $tenantId, int $roleId, array $metadata): void
    {
        if (!$this->schemaReady()) {
            throw new InvalidArgumentException('Les rattachements des emplois nécessitent la migration du module.');
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            // Every hierarchy write for a tenant is serialized, including two opposite parent changes.
            $lockSql = 'SELECT id FROM tenants WHERE id = ?' . ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE');
            $lock = $this->pdo->prepare($lockSql);
            $lock->execute([$tenantId]);
            if ($lock->fetchColumn() === false || $roleId < 1) {
                throw new InvalidArgumentException('Emploi ou communauté introuvable.');
            }
            $this->lockingHierarchyRead = true;
            try {
                // A locking read sees committed changes even if the caller's transaction already has a snapshot.
                $result = $this->validate($tenantId, $roleId, $metadata);
            } finally {
                $this->lockingHierarchyRead = false;
            }
            if ($result['errors'] !== []) throw new InvalidArgumentException((string) reset($result['errors']));
            $m = $result['metadata'];
            $sql = 'INSERT INTO personnel_job_role_structure (tenant_id, job_role_id, parent_job_role_id, linked_unit_id, grade_id, specialty_id, code, kind, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) ';
            $sql .= $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'ON CONFLICT (tenant_id, job_role_id) DO UPDATE SET parent_job_role_id=excluded.parent_job_role_id, linked_unit_id=excluded.linked_unit_id, grade_id=excluded.grade_id, specialty_id=excluded.specialty_id, code=excluded.code, kind=excluded.kind, updated_at=CURRENT_TIMESTAMP'
                : 'ON DUPLICATE KEY UPDATE parent_job_role_id=VALUES(parent_job_role_id), linked_unit_id=VALUES(linked_unit_id), grade_id=VALUES(grade_id), specialty_id=VALUES(specialty_id), code=VALUES(code), kind=VALUES(kind), updated_at=CURRENT_TIMESTAMP';
            $this->pdo->prepare($sql)->execute([$tenantId,$roleId,$m['parent_job_role_id'],$m['linked_unit_id'],$m['grade_id'],$m['specialty_id'],$m['code'],$m['kind']]);
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function wouldCycle(int $tenantId, int $roleId, int $parent): bool
    {
        $metadata = $this->listMetadata($tenantId);
        $seen = [];
        while ($parent > 0) {
            if ($parent === $roleId || isset($seen[$parent])) return true;
            $seen[$parent] = true;
            $parent = (int) ($metadata[$parent]['parent_job_role_id'] ?? 0);
        }
        return false;
    }

    private function belongsToTenant(string $table, int $id, int $tenantId): bool
    {
        // Table names are fixed internal callers, never request input.
        // A caller may already hold a REPEATABLE READ snapshot. During save, check
        // the current rows so a reference deleted since that snapshot is rejected.
        $stmt = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE id = ? AND tenant_id = ?'
            . ($this->lockingHierarchyRead && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : ''));
        $stmt->execute([$id,$tenantId]);
        return $stmt->fetchColumn() !== false;
    }

    private function tableExists(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        } else {
            $stmt = $this->pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME = ?');
        }
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }

    private function normalizeStored(array $row): array
    {
        $out = [];
        foreach (['parent_job_role_id','linked_unit_id','grade_id','specialty_id'] as $field) $out[$field] = (int)($row[$field]??0)>0?(int)$row[$field]:null;
        $out['code'] = (string)($row['code']??'');
        $out['kind'] = (string)($row['kind']??'employment');
        return $out;
    }
}
