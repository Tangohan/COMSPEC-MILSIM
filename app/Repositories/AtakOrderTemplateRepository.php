<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Modèles d’ordres personnalisés par tenant (libellé + consignes par défaut).
 */
class AtakOrderTemplateRepository
{
    private PDO $pdo;

    private ?bool $tablesReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesReady(): bool
    {
        if ($this->tablesReady !== null) {
            return $this->tablesReady;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_order_templates' LIMIT 1"
            );
            $this->tablesReady = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->tablesReady = false;
        }

        return $this->tablesReady;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT id, tenant_id, label, default_payload, created_by_user_id, sort_order, created_at, updated_at
             FROM atak_order_templates
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
        $st = $this->pdo->prepare(
            'SELECT id, tenant_id, label, default_payload, created_by_user_id, sort_order, created_at, updated_at
             FROM atak_order_templates
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
    public function create(int $tenantId, string $label, string $defaultPayload = '', ?int $createdByUserId = null): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return null;
        }
        $label = mb_substr(trim($label), 0, 120);
        if ($label === '') {
            return null;
        }
        $defaultPayload = mb_substr(trim($defaultPayload), 0, 400);
        if ($createdByUserId !== null && $createdByUserId < 1) {
            $createdByUserId = null;
        }

        $st = $this->pdo->prepare(
            'INSERT INTO atak_order_templates (tenant_id, label, default_payload, created_by_user_id, sort_order)
             VALUES (?, ?, ?, ?, 0)'
        );
        $st->execute([
            $tenantId,
            $label,
            $defaultPayload !== '' ? $defaultPayload : null,
            $createdByUserId,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->findForTenant($tenantId, $id);
    }

    public function deleteForTenant(int $tenantId, int $id): bool
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM atak_order_templates WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function serialize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'default_payload' => (string) ($row['default_payload'] ?? ''),
            'created_by_user_id' => isset($row['created_by_user_id']) && $row['created_by_user_id'] !== null
                ? (int) $row['created_by_user_id']
                : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'source' => 'server',
        ];
    }
}
