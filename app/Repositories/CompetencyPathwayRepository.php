<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Parcours de compétence enregistrés par les formateurs (table competency_pathways, étape 3340).
 * Les étapes sont stockées en JSON, sans adresse : les liens sont recalculés à l’affichage.
 */
class CompetencyPathwayRepository
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
                $this->pdo()->query('SELECT 1 FROM competency_pathways WHERE 1 = 0');
                $this->ready = true;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /** @return list<array<string, mixed>> parcours avec leurs étapes décodées */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT * FROM competency_pathways WHERE tenant_id = ? ORDER BY name ASC, id ASC');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $steps = json_decode((string) $row['steps_json'], true);
            $row['steps'] = is_array($steps) ? array_values(array_filter($steps, 'is_array')) : [];
            unset($row['steps_json']);
            $out[] = $row;
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $steps */
    public function create(int $tenantId, int $userId, string $name, string $summary, string $targetKind, string $targetRef, array $steps): int
    {
        $now = date('Y-m-d H:i:s');
        $st = $this->pdo()->prepare(
            'INSERT INTO competency_pathways (tenant_id, name, summary, target_kind, target_ref, steps_json, created_by_user_id, updated_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            mb_substr($name, 0, 150),
            $summary !== '' ? mb_substr($summary, 0, 500) : null,
            mb_substr($targetKind, 0, 20),
            mb_substr($targetRef, 0, 64),
            json_encode(array_values($steps), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $userId > 0 ? $userId : null,
            $userId > 0 ? $userId : null,
            $now,
            $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        if (!$this->schemaReady()) {
            return false;
        }
        $st = $this->pdo()->prepare('DELETE FROM competency_pathways WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }
}
