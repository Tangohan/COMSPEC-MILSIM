<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use PDO;

/**
 * Types d’ordres personnalisés par tenant (libellés du sélecteur « Type d’ordre »).
 */
class AtakOrderTypeRepository
{
    use LazyDatabaseConnection;


    private ?bool $tablesReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tablesReady(): bool
    {
        if ($this->tablesReady !== null) {
            return $this->tablesReady;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_order_types' LIMIT 1"
            );
            $this->tablesReady = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->tablesReady = false;
        }

        return $this->tablesReady;
    }

    public static function codeForId(int $id): string
    {
        return 'TYP_' . max(1, $id);
    }

    public static function idFromCode(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^TYP_(\d+)$/', $code, $m) !== 1) {
            return null;
        }
        $id = (int) $m[1];

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT id, tenant_id, label, description, created_by_user_id, sort_order, created_at, updated_at
             FROM atak_order_types
             WHERE tenant_id = ?
             ORDER BY sort_order ASC, id ASC
             LIMIT 100'
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'serialize'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT id, tenant_id, label, description, created_by_user_id, sort_order, created_at, updated_at
             FROM atak_order_types
             WHERE tenant_id = ? AND id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->serialize($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function create(
        int $tenantId,
        string $label,
        string $description = '',
        ?int $createdByUserId = null
    ): ?array {
        if (!$this->tablesReady() || $tenantId < 1) {
            return null;
        }
        $label = mb_substr(trim($label), 0, 120);
        if ($label === '') {
            return null;
        }
        $description = mb_substr(trim($description), 0, 400);
        if ($createdByUserId !== null && $createdByUserId < 1) {
            $createdByUserId = null;
        }

        $st = $this->pdo()->prepare(
            'INSERT INTO atak_order_types (tenant_id, label, description, created_by_user_id, sort_order)
             VALUES (?, ?, ?, ?, 0)'
        );
        $st->execute([
            $tenantId,
            $label,
            $description !== '' ? $description : null,
            $createdByUserId,
        ]);
        $id = (int) $this->pdo()->lastInsertId();

        return $this->findForTenant($tenantId, $id);
    }

    public function deleteForTenant(int $tenantId, int $id): bool
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare('DELETE FROM atak_order_types WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function serialize(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);

        return [
            'id' => $id,
            'code' => self::codeForId($id),
            'label' => (string) ($row['label'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'created_by_user_id' => isset($row['created_by_user_id']) && $row['created_by_user_id'] !== null
                ? (int) $row['created_by_user_id']
                : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
