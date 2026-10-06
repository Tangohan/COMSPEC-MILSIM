<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class AwardDefinitionRepository
{
    private PDO $pdo;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('award_definitions');
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $includeArchived = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT d.*,
                       (SELECT COUNT(*) FROM personnel_awards a WHERE a.definition_id = d.id) AS holders_count
                FROM award_definitions d WHERE d.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND d.archived_at IS NULL';
        }
        $sql .= ' ORDER BY d.sort_order ASC, d.name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM award_definitions WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM award_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, strtoupper(trim($code))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $actorId): int
    {
        $cols = ['tenant_id', 'code', 'name', 'decoration_grade', 'award_criterion', 'sort_order'];
        $vals = [
            $tenantId,
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['name'] ?? '')),
            self::nullIfBlank($data['decoration_grade'] ?? null),
            self::nullIfBlank($data['award_criterion'] ?? null),
            (int) ($data['sort_order'] ?? 0),
        ];
        if ($this->hasColumn('branch')) {
            $cols[] = 'branch';
            $vals[] = self::nullIfBlank($data['branch'] ?? null);
        }
        if ($this->hasColumn('image_path')) {
            $cols[] = 'image_path';
            $vals[] = self::nullIfBlank($data['image_path'] ?? null);
        }
        $cols[] = 'created_by';
        $vals[] = $actorId;
        $st = $this->pdo->prepare(
            'INSERT INTO award_definitions (' . implode(', ', $cols) . ', created_at, updated_at)
             VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', NOW(), NOW())'
        );
        $st->execute($vals);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour une décoration. Les clés absentes de $data ne sont pas modifiées
     * (sauf code / nom, obligatoires). `restore` => true désarchive la décoration (réimport d’un insigne).
     *
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): void
    {
        $sets = ['code = ?', 'name = ?'];
        $vals = [
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['name'] ?? '')),
        ];
        foreach (['decoration_grade', 'award_criterion'] as $key) {
            if (array_key_exists($key, $data)) {
                $sets[] = $key . ' = ?';
                $vals[] = self::nullIfBlank($data[$key]);
            }
        }
        if (array_key_exists('sort_order', $data)) {
            $sets[] = 'sort_order = ?';
            $vals[] = (int) $data['sort_order'];
        }
        foreach (['branch', 'image_path'] as $key) {
            if (array_key_exists($key, $data) && $this->hasColumn($key)) {
                $sets[] = $key . ' = ?';
                $vals[] = self::nullIfBlank($data[$key]);
            }
        }
        $where = 'tenant_id = ? AND id = ?';
        if (!empty($data['restore'])) {
            $sets[] = 'archived_at = NULL';
        } else {
            $where .= ' AND archived_at IS NULL';
        }
        $vals[] = $tenantId;
        $vals[] = $id;
        $st = $this->pdo->prepare(
            'UPDATE award_definitions SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE ' . $where
        );
        $st->execute($vals);
    }

    /** Les colonnes branch / image_path existent-elles (run-migrations.php passé) ? */
    public function supportsImages(): bool
    {
        return $this->hasColumn('image_path');
    }

    public function archive(int $tenantId, int $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE award_definitions SET archived_at = NOW(), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );
        $st->execute([$tenantId, $id]);
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v === '' ? null : $v;
    }

    private function hasColumn(string $column): bool
    {
        if (!array_key_exists($column, $this->columnCache)) {
            try {
                $st = $this->pdo->prepare(
                    'SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
                );
                $st->execute(['award_definitions', $column]);
                $this->columnCache[$column] = (bool) $st->fetchColumn();
            } catch (Throwable) {
                $this->columnCache[$column] = false;
            }
        }

        return $this->columnCache[$column];
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
}
