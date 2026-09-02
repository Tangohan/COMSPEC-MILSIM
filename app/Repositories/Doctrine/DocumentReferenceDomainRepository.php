<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentReferenceDomainRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_reference_domains LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_reference_domains
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY sort_order ASC, label ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAllForTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_reference_domains WHERE tenant_id = ? ORDER BY sort_order ASC, label ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists() || $id < 1 || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_reference_domains WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_reference_domains WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listSubdomainsForDomain(int $tenantId, int $domainId, bool $activeOnly = true): array
    {
        if (!$this->tableExists() || $tenantId < 1 || $domainId < 1) {
            return [];
        }
        $sql = 'SELECT * FROM document_reference_subdomains WHERE tenant_id = ? AND domain_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, label ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId, $domainId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $tenantId, string $code, string $label, string $docPrefix, ?string $color, int $sortOrder): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_reference_domains (tenant_id, code, label, doc_prefix, color, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$tenantId, strtoupper(trim($code)), trim($label), strtoupper(trim($docPrefix)), $color, $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $fields): bool
    {
        $allowed = ['code', 'label', 'doc_prefix', 'color', 'sort_order', 'is_active'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = $key . ' = ?';
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare(
            'UPDATE document_reference_domains SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
