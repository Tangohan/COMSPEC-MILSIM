<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use InvalidArgumentException;
use PDO;

/** Uses the existing audit journal. Snapshots contain definitions, never grants or holders. */
final class PersonnelJobRoleHistoryService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::getPdo();
    }

    public function record(int $tenantId, int $actorId, string $entity, int $id, string $operation, array $before, array $after): void
    {
        if (!in_array($entity, ['job_role', 'job_category'], true) || !in_array($operation, ['created', 'updated', 'archived', 'restored', 'deleted'], true)) {
            throw new InvalidArgumentException('Événement d’emploi invalide.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (tenant_id,user_id,action,entity_type,entity_id,old_value,new_value,created_at)
            VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $stmt->execute([$tenantId, $actorId, 'personnel.'.$entity.'.'.$operation, 'personnel_'.$entity, $id,
            json_encode($this->snapshot($before), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            json_encode($this->snapshot($after), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /** Bounded, keyset-paginated journal; deleted authors remain readable. */
    public function recent(int $tenantId, int $beforeId = 0): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, u.display_name AS actor_name FROM audit_logs a
            LEFT JOIN users u ON u.id=a.user_id
            WHERE a.tenant_id=? AND a.entity_type IN ('personnel_job_role','personnel_job_category')"
            . ($beforeId > 0 ? ' AND a.id<?' : '') . ' ORDER BY a.id DESC LIMIT 51');
        $stmt->execute($beforeId > 0 ? [$tenantId, $beforeId] : [$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function archives(int $tenantId, int $beforeId = 0): array
    {
        $stmt = $this->pdo->prepare("SELECT a.* FROM audit_logs a
            WHERE a.tenant_id=? AND a.action='personnel.job_role.archived'
            AND NOT EXISTS (SELECT 1 FROM audit_logs r WHERE r.tenant_id=a.tenant_id
                AND r.entity_id=a.entity_id AND r.action='personnel.job_role.restored' AND r.id>a.id)"
            . ($beforeId > 0 ? ' AND a.id<?' : '') . ' ORDER BY a.id DESC LIMIT 51');
        $stmt->execute($beforeId > 0 ? [$tenantId, $beforeId] : [$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Caller holds the tenant lock and transaction, including the archive and restore journal writes. */
    public function restore(int $tenantId, int $actorId, int $auditId, PersonnelJobRoleRepository $roles): int
    {
        if (!$this->pdo->inTransaction()) throw new \LogicException('La restauration nécessite une transaction.');
        $stmt = $this->pdo->prepare("SELECT * FROM audit_logs WHERE id=? AND tenant_id=? AND action='personnel.job_role.archived'");
        $stmt->execute([$auditId, $tenantId]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$archive) throw new InvalidArgumentException('Archive introuvable dans votre communauté.');
        $used = $this->pdo->prepare("SELECT id FROM audit_logs WHERE tenant_id=? AND entity_id=? AND action='personnel.job_role.restored' AND id>? LIMIT 1");
        $used->execute([$tenantId, $archive['entity_id'], $auditId]);
        if ($used->fetchColumn()) throw new InvalidArgumentException('Cette archive a déjà été restaurée.');
        try {
            $saved = json_decode((string)$archive['old_value'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('L’archive est illisible. Aucun emploi n’a été modifié.');
        }
        if (!is_array($saved) || empty($saved['name']) || empty($saved['slug'])) {
            throw new InvalidArgumentException('Cette archive ne contient pas une définition complète.');
        }
        $categoryId = (int)($saved['category_id'] ?? 0);
        if (!$roles->findCategoryById($categoryId, $tenantId)) {
            throw new InvalidArgumentException('La catégorie d’origine a été supprimée. Recréez l’emploi à partir des informations de l’historique.');
        }
        if ($roles->findRoleIdBySlug($tenantId, (string)$saved['slug']) !== null) {
            throw new InvalidArgumentException('Un emploi utilise déjà cette référence. Aucun emploi existant n’a été remplacé.');
        }
        $structure = new PersonnelJobRoleStructureService($this->pdo);
        $metadata = $saved['structure'] ?? [];
        if ($metadata !== []) {
            $check = $structure->validate($tenantId, 0, $metadata);
            if ($check['errors'] !== []) throw new InvalidArgumentException('Un rattachement d’origine n’est plus disponible. Consultez l’historique pour recréer cet emploi avec des références actuelles.');
        }
        $id = $roles->restoreRoleDefinition($tenantId, $saved);
        if ($metadata !== []) $structure->save($tenantId, $id, $metadata);
        $saved['restored_role_id'] = $id;
        $this->record($tenantId, $actorId, 'job_role', (int)$archive['entity_id'], 'restored', [], $saved);
        return $id;
    }

    private function snapshot(array $row): array
    {
        return array_intersect_key($row, array_flip(['name','slug','description','category_id','parent_id','sort_order',
            'mos_code','mos_specialty_title','label_en','source_unit_id','structure','restored_role_id']));
    }
}
