<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentViewRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_views LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function recordView(int $tenantId, int $documentId, int $versionId, int $userId): void
    {
        if (!$this->tableExists() || $tenantId < 1 || $documentId < 1 || $versionId < 1 || $userId < 1) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_views (tenant_id, document_id, version_id, user_id, first_viewed_at, last_viewed_at, view_count)
             VALUES (?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE last_viewed_at = VALUES(last_viewed_at), view_count = view_count + 1'
        );
        $stmt->execute([$tenantId, $documentId, $versionId, $userId, $now, $now]);
    }

    public function findForUserVersion(int $tenantId, int $userId, int $versionId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_views WHERE tenant_id = ? AND user_id = ? AND version_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId, $versionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function hasViewed(int $tenantId, int $userId, int $versionId): bool
    {
        return $this->findForUserVersion($tenantId, $userId, $versionId) !== null;
    }
}
