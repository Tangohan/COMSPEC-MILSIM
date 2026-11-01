<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Training\CompetencyPathwayGenerator;
use PDO;

/**
 * Liens d’une compétence du référentiel vers les autres modules (cours Academy, formations, modules ALPHA → DELTA,
 * qualifications, emplois, dotation). Table competency_requirement_links (étape 3340).
 */
class CompetencyLinkRepository
{
    private ?PDO $pdo;
    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM competency_requirement_links WHERE 1 = 0');
                $this->ready = true;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /** @return array<int, list<array{type:string, ref:string}>> liens par compétence */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT requirement_id, target_type, target_ref FROM competency_requirement_links WHERE tenant_id = ? ORDER BY id ASC');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['requirement_id']][] = ['type' => (string) $row['target_type'], 'ref' => (string) $row['target_ref']];
        }

        return $out;
    }

    /**
     * Remplace les liens d’une compétence. Les types inconnus et les doublons sont ignorés.
     *
     * @param array<string, list<string>> $refsByType
     */
    public function replaceForRequirement(int $tenantId, int $requirementId, array $refsByType): void
    {
        if (!$this->schemaReady()) {
            return;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM competency_requirement_links WHERE tenant_id = ? AND requirement_id = ?')->execute([$tenantId, $requirementId]);
            $ins = $pdo->prepare('INSERT INTO competency_requirement_links (tenant_id, requirement_id, target_type, target_ref, created_at) VALUES (?, ?, ?, ?, ?)');
            $now = date('Y-m-d H:i:s');
            foreach ($refsByType as $type => $refs) {
                if (!isset(CompetencyPathwayGenerator::LINK_TYPES[$type])) {
                    continue;
                }
                $seen = [];
                foreach ($refs as $ref) {
                    $ref = mb_substr(trim((string) $ref), 0, 64);
                    if ($ref === '' || isset($seen[$ref])) {
                        continue;
                    }
                    $seen[$ref] = true;
                    $ins->execute([$tenantId, $requirementId, $type, $ref, $now]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deleteForRequirement(int $tenantId, int $requirementId): void
    {
        if ($this->schemaReady()) {
            $this->pdo()->prepare('DELETE FROM competency_requirement_links WHERE tenant_id = ? AND requirement_id = ?')->execute([$tenantId, $requirementId]);
        }
    }
}
