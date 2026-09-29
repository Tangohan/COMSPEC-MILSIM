<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Référentiel tenant des types d’unité (Commandement / Opérationnel / Soutien / Formation…).
 */
final class UnitTypeDefinitionRepository
{
    private ?PDO $pdo = null;

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::connection();
    }

    public function tableReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_type_definitions' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM unit_type_definitions
             WHERE tenant_id = ?
             ORDER BY sort_order ASC, label ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableReady() || $id < 1 || $tenantId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM unit_type_definitions WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $code = strtolower(trim($code));
        if (!$this->tableReady() || $tenantId < 1 || $code === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM unit_type_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{code?: string, label?: string, sort_order?: int, is_system?: bool} $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->tableReady() || $tenantId < 1) {
            return 0;
        }
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $label = trim((string) ($data['label'] ?? ''));
        if ($code === '' || $label === '') {
            return 0;
        }
        $sort = (int) ($data['sort_order'] ?? 100);
        $isSystem = !empty($data['is_system']) ? 1 : 0;
        $st = $this->pdo()->prepare(
            'INSERT INTO unit_type_definitions (tenant_id, code, label, sort_order, is_system)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$tenantId, $code, mb_substr($label, 0, 150), $sort, $isSystem]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Assure le seed système pour un tenant (création / bootstrap).
     */
    public function ensureSystemDefaults(int $tenantId): void
    {
        if (!$this->tableReady() || $tenantId < 1) {
            return;
        }
        $defaults = [
            ['commandement', 'Commandement', 10],
            ['operationnel', 'Opérationnel', 20],
            ['soutien', 'Soutien', 30],
            ['formation', 'Formation', 40],
        ];
        foreach ($defaults as [$code, $label, $sort]) {
            if ($this->findByCode($tenantId, $code) !== null) {
                continue;
            }
            $this->create($tenantId, [
                'code' => $code,
                'label' => $label,
                'sort_order' => $sort,
                'is_system' => true,
            ]);
        }
    }
}
