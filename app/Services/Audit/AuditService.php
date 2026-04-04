<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Core\Database;
use PDO;

class AuditService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function log(
        string $action,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $ip = $ip ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if (is_string($userAgent) && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, old_value, new_value, ip, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValue,
            $newValue,
            $ip,
            $userAgent,
        ]);
    }

    public function logDocumentUploaded(int $tenantId, int $userId, int $documentId, int $versionId): void
    {
        $this->log('document_uploaded', $tenantId, $userId, 'document', $documentId, null, (string) $versionId);
    }

    public function logDocumentDownloaded(int $tenantId, int $userId, int $documentId): void
    {
        $this->log('document_downloaded', $tenantId, $userId, 'document', $documentId);
    }

    public function logDocumentUpdated(int $tenantId, int $userId, int $documentId, ?string $oldValue = null, ?string $newValue = null): void
    {
        $this->log('document_updated', $tenantId, $userId, 'document', $documentId, $oldValue, $newValue);
    }

    public function logDocumentArchived(int $tenantId, int $userId, int $documentId): void
    {
        $this->log('document_archived', $tenantId, $userId, 'document', $documentId);
    }
}
