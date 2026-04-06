<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class CourrierDocumentNotificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_document_notifications' LIMIT 1"
            );

            return (bool) $stmt?->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<int> $recipientIds
     */
    public function createForRecipients(int $tenantId, int $documentId, int $createdByUserId, array $recipientIds): int
    {
        if (!$this->tableExists() || $recipientIds === []) {
            return 0;
        }
        $ins = $this->pdo->prepare(
            'INSERT IGNORE INTO courrier_document_notifications (tenant_id, document_id, recipient_user_id, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $n = 0;
        foreach (array_unique(array_filter($recipientIds)) as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0 || $uid === $createdByUserId) {
                continue;
            }
            $ins->execute([$tenantId, $documentId, $uid, $createdByUserId]);
            $n += $ins->rowCount();
        }

        return $n;
    }

    public function countUnread(int $tenantId, int $userId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM courrier_document_notifications WHERE tenant_id = ? AND recipient_user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$tenantId, $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUnreadForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $lim = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT n.id, n.document_id, n.created_at, d.title, d.reference_number, d.subject
             FROM courrier_document_notifications n
             INNER JOIN courrier_documents d ON d.id = n.document_id
             WHERE n.tenant_id = ? AND n.recipient_user_id = ? AND n.read_at IS NULL
             ORDER BY n.created_at DESC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $lim = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT n.id, n.document_id, n.read_at, n.created_at, d.title, d.reference_number, d.subject
             FROM courrier_document_notifications n
             INNER JOIN courrier_documents d ON d.id = n.document_id
             WHERE n.tenant_id = ? AND n.recipient_user_id = ?
             ORDER BY n.created_at DESC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markReadForDocument(int $tenantId, int $userId, int $documentId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE courrier_document_notifications SET read_at = NOW() WHERE tenant_id = ? AND recipient_user_id = ? AND document_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$tenantId, $userId, $documentId]);
    }

    public function markAllReadForUser(int $tenantId, int $userId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE courrier_document_notifications SET read_at = NOW() WHERE tenant_id = ? AND recipient_user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$tenantId, $userId]);
    }
}
