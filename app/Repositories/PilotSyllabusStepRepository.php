<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Paliers créés par la communauté dans son cursus pilote (pilot_syllabus_steps) : une ligne par qualification
 * délivrée, avec la filière, l'ordre du parcours et l'archivage. Les conditions d'obtention sont la règle automatique
 * de la qualification (qualification_award_rules), évaluée par le moteur commun des qualifications.
 */
final class PilotSyllabusStepRepository
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
                $this->pdo()->query('SELECT 1 FROM pilot_syllabus_steps LIMIT 1');
                $this->ready = true;
            } catch (Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /**
     * Paliers de la communauté avec le nom de la qualification délivrée, par filière puis dans l'ordre.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $withArchived = true): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT s.*, d.name AS definition_name, d.code AS definition_code, d.short_name AS definition_short_name
             FROM pilot_syllabus_steps s
             JOIN personnel_qualification_definitions d ON d.id = s.definition_id AND d.tenant_id = s.tenant_id
             WHERE s.tenant_id = ?' . ($withArchived ? '' : ' AND s.archived_at IS NULL') . '
             ORDER BY s.track ASC, s.sort_order ASC, s.id ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM pilot_syllabus_steps WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByDefinition(int $tenantId, int $definitionId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM pilot_syllabus_steps WHERE tenant_id = ? AND definition_id = ? LIMIT 1');
        $st->execute([$tenantId, $definitionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function nextSort(int $tenantId, string $track): int
    {
        $st = $this->pdo()->prepare('SELECT MAX(sort_order) FROM pilot_syllabus_steps WHERE tenant_id = ? AND track = ?');
        $st->execute([$tenantId, $track]);

        return (int) $st->fetchColumn() + 10;
    }

    public function create(int $tenantId, int $definitionId, string $track, int $sort, ?int $actorId): int
    {
        $now = date('Y-m-d H:i:s');
        $this->pdo()->prepare(
            'INSERT INTO pilot_syllabus_steps (tenant_id, definition_id, track, sort_order, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $definitionId, $track, $sort, $actorId, $now, $now]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $tenantId, int $id, string $track, int $sort): void
    {
        $this->pdo()->prepare('UPDATE pilot_syllabus_steps SET track = ?, sort_order = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$track, $sort, date('Y-m-d H:i:s'), $tenantId, $id]);
    }

    public function setArchived(int $tenantId, int $id, bool $archived): void
    {
        $this->pdo()->prepare('UPDATE pilot_syllabus_steps SET archived_at = ?, updated_at = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$archived ? date('Y-m-d H:i:s') : null, date('Y-m-d H:i:s'), $tenantId, $id]);
    }
}
