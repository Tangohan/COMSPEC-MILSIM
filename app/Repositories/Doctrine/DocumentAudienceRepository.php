<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentAudienceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_audiences LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForDocument(int $documentId, int $tenantId): array
    {
        if (!$this->tableExists() || $documentId < 1 || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_audiences WHERE document_id = ? AND tenant_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$documentId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replaceForDocument(int $documentId, int $tenantId, array $rows): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $del = $this->pdo->prepare('DELETE FROM document_audiences WHERE document_id = ? AND tenant_id = ?');
        $del->execute([$documentId, $tenantId]);
        if ($rows === []) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO document_audiences (document_id, tenant_id, audience_type, audience_value, include_children)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($rows as $row) {
            $ins->execute([
                $documentId,
                $tenantId,
                (string) ($row['audience_type'] ?? ''),
                (string) ($row['audience_value'] ?? ''),
                !empty($row['include_children']) ? 1 : 0,
            ]);
        }
    }

    public function hasAllMembersAudience(int $documentId, int $tenantId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM document_audiences WHERE document_id = ? AND tenant_id = ? AND audience_type = ? LIMIT 1'
        );
        $stmt->execute([$documentId, $tenantId, 'all_members']);

        return (bool) $stmt->fetchColumn();
    }
}
