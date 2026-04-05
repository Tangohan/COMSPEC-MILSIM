<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantMessageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<int> */
    public function findStaffUserIdsForTenant(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT u.id FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                WHERE u.tenant_id = ? AND r.slug IN (\'tenant_admin\', \'community_owner\')
                LIMIT 20'
            );
            $stmt->execute([$tenantId]);
            $ids = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) $row['id'];
            }

            return $ids;
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listThreadsForUser(int $tenantId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.id, t.subject, t.created_at, t.updated_at,
                (SELECT body FROM tenant_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_preview
                FROM tenant_message_threads t
                INNER JOIN tenant_message_thread_users tu ON tu.thread_id = t.id AND tu.user_id = ?
                WHERE t.tenant_id = ?
                ORDER BY t.updated_at DESC, t.id DESC
                LIMIT 100'
            );
            $stmt->execute([$userId, $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    public function findThread(int $threadId, int $tenantId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tenant_message_threads WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$threadId, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return null;
            }
            throw $e;
        }
    }

    public function userInThread(int $threadId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_message_thread_users WHERE thread_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$threadId, $userId]);

            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param list<int> $participantIds
     */
    public function createThread(int $tenantId, int $createdByUserId, string $subject, array $participantIds): int
    {
        $subject = trim($subject) === '' ? 'Conversation' : trim($subject);
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_message_threads (tenant_id, subject, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$tenantId, $subject, $createdByUserId]);
        $threadId = (int) $this->pdo->lastInsertId();

        $ids = array_values(array_unique(array_merge([$createdByUserId], $participantIds)));
        $ins = $this->pdo->prepare('INSERT IGNORE INTO tenant_message_thread_users (thread_id, user_id) VALUES (?, ?)');
        foreach ($ids as $uid) {
            if ($uid > 0) {
                $ins->execute([$threadId, $uid]);
            }
        }

        return $threadId;
    }

    public function addMessage(int $threadId, int $senderUserId, string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_messages (thread_id, sender_user_id, body, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$threadId, $senderUserId, $body]);
        $upd = $this->pdo->prepare('UPDATE tenant_message_threads SET updated_at = NOW() WHERE id = ?');
        $upd->execute([$threadId]);
    }

    /** @return list<array<string, mixed>> */
    public function listMessages(int $threadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, u.display_name, u.email FROM tenant_messages m
            INNER JOIN users u ON u.id = m.sender_user_id
            WHERE m.thread_id = ?
            ORDER BY m.id ASC'
        );
        $stmt->execute([$threadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function unreadCountForUser(int $tenantId, int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tenant_message_threads t
                INNER JOIN tenant_message_thread_users tu ON tu.thread_id = t.id AND tu.user_id = ?
                WHERE t.tenant_id = ?
                AND t.updated_at > COALESCE(tu.last_read_at, \'1970-01-01\')'
            );
            $stmt->execute([$userId, $tenantId]);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    public function markThreadRead(int $threadId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_message_thread_users SET last_read_at = NOW() WHERE thread_id = ? AND user_id = ?'
        );
        $stmt->execute([$threadId, $userId]);
    }
}
