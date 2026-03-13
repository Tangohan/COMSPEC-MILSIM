<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantMatriculeConfigRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function get(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_matricule_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Initialise ou récupère la config matricule pour un tenant. */
    public function getOrCreate(int $tenantId, string $prefix = '', string $formatPattern = '{prefix}-{seq}', int $nextNumber = 1): array
    {
        $config = $this->get($tenantId);
        if ($config) {
            return $config;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $prefix, $formatPattern, $nextNumber]);
        return [
            'tenant_id' => $tenantId,
            'prefix' => $prefix,
            'format_pattern' => $formatPattern,
            'next_number' => $nextNumber,
            'updated_at' => null,
        ];
    }

    /** Incrémente et retourne le prochain numéro (atomique). */
    public function consumeNextNumber(int $tenantId): ?int
    {
        $this->getOrCreate($tenantId);
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_matricule_config SET next_number = next_number + 1, updated_at = NOW() WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT next_number - 1 AS consumed FROM tenant_matricule_config WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['consumed'] : null;
    }

    public function updateConfig(int $tenantId, string $prefix, string $formatPattern, ?int $nextNumber = null): bool
    {
        $this->getOrCreate($tenantId, $prefix, $formatPattern);
        if ($nextNumber !== null) {
            $stmt = $this->pdo->prepare('UPDATE tenant_matricule_config SET prefix = ?, format_pattern = ?, next_number = ?, updated_at = NOW() WHERE tenant_id = ?');
            $stmt->execute([$prefix, $formatPattern, $nextNumber, $tenantId]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE tenant_matricule_config SET prefix = ?, format_pattern = ?, updated_at = NOW() WHERE tenant_id = ?');
            $stmt->execute([$prefix, $formatPattern, $tenantId]);
        }
        return $stmt->rowCount() > 0;
    }
}
