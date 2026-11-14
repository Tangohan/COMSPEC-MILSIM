<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Circuits d'avis composés par une communauté (workflow_circuits), un par type de demande.
 *
 * SQL portable (MySQL en production, SQLite dans les tests) : les dates sont passées en paramètre.
 */
class WorkflowCircuitRepository
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

    public function tableReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM workflow_circuits LIMIT 1');
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /**
     * Étapes enregistrées par la communauté, par type de demande (seulement les types configurés).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function allForTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT subject_type, steps_json FROM workflow_circuits WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $steps = json_decode((string) ($row['steps_json'] ?? '[]'), true);
            $out[(string) $row['subject_type']] = is_array($steps) ? array_values(array_filter($steps, 'is_array')) : [];
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $steps */
    public function save(int $tenantId, string $subjectType, array $steps, int $userId, string $now): void
    {
        $json = (string) json_encode(array_values($steps), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $this->pdo()->prepare('UPDATE workflow_circuits SET steps_json = ?, updated_by = ?, updated_at = ? WHERE tenant_id = ? AND subject_type = ?');
        $st->execute([$json, $userId > 0 ? $userId : null, $now, $tenantId, $subjectType]);
        if ($st->rowCount() > 0) {
            return;
        }
        $this->pdo()->prepare(
            'INSERT INTO workflow_circuits (tenant_id, subject_type, steps_json, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $subjectType, $json, $userId > 0 ? $userId : null, $now, $now]);
    }

    /** Revient au circuit par défaut pour ce type de demande. */
    public function reset(int $tenantId, string $subjectType): void
    {
        if (!$this->tableReady()) {
            return;
        }
        $this->pdo()->prepare('DELETE FROM workflow_circuits WHERE tenant_id = ? AND subject_type = ?')->execute([$tenantId, $subjectType]);
    }
}
