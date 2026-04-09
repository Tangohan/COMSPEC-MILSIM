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

    private const MESSAGE_STAFF_ROLE_SLUGS = [
        'tenant_admin',
        'community_owner',
        'officer',
        'forum_moderator',
        'hr',
        'recruiter',
    ];

    /** Permission optionnelle : destinataires au-delà des rôles ci-dessus (si présente pour le tenant). */
    public const PERMISSION_RECEIVE_INTERNAL_MESSAGES = 'comms.tenant_messages.receive';

    /** @return list<int> */
    public function findStaffUserIdsForTenant(int $tenantId): array
    {
        $ids = [];
        $slugPlaceholders = implode(',', array_fill(0, count(self::MESSAGE_STAFF_ROLE_SLUGS), '?'));
        $slugParams = self::MESSAGE_STAFF_ROLE_SLUGS;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT u.id FROM users u
                INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active'
                AND r.slug IN ({$slugPlaceholders})"
            );
            $stmt->execute(array_merge([$tenantId], $slugParams));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) $row['id'];
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }

        try {
            $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");
            if ($chk && $chk->fetchColumn()) {
                $stmt = $this->pdo->prepare(
                    "SELECT DISTINCT u.id FROM users u
                    INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                    INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                    WHERE u.tenant_id = ? AND u.status = 'active'
                    AND r.slug IN ({$slugPlaceholders})"
                );
                $stmt->execute(array_merge([$tenantId], $slugParams));
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) $row['id'];
                }
            }
        } catch (\PDOException) {
        }

        try {
            $permSlug = self::PERMISSION_RECEIVE_INTERNAL_MESSAGES;
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT u.id FROM users u
                INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = \'active\' AND p.slug = ?'
            );
            $stmt->execute([$tenantId, $permSlug]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) $row['id'];
            }
        } catch (\PDOException) {
        }

        try {
            $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");
            if ($chk && $chk->fetchColumn()) {
                $permSlug = self::PERMISSION_RECEIVE_INTERNAL_MESSAGES;
                $stmt = $this->pdo->prepare(
                    'SELECT DISTINCT u.id FROM users u
                    INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                    INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                    INNER JOIN role_permissions rp ON rp.role_id = r.id
                    INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
                    WHERE u.tenant_id = ? AND u.status = \'active\' AND p.slug = ?'
                );
                $stmt->execute([$tenantId, $permSlug]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) $row['id'];
                }
            }
        } catch (\PDOException) {
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        return array_slice($ids, 0, 40);
    }

    /**
     * Fil récent encore « en attente de réponse » : uniquement des messages de l’auteur, pour éviter les doublons de demandes.
     */
    public function findRecentOpenAuthorOnlyThreadId(int $tenantId, int $authorUserId, int $withinMinutes = 120): ?int
    {
        if ($tenantId < 1 || $authorUserId < 1) {
            return null;
        }
        $withinMinutes = max(5, min(24 * 60, $withinMinutes));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.id FROM tenant_message_threads t
                INNER JOIN tenant_message_thread_users tu ON tu.thread_id = t.id AND tu.user_id = ?
                WHERE t.tenant_id = ? AND t.created_by_user_id = ?
                AND t.updated_at > DATE_SUB(NOW(), INTERVAL ' . (int) $withinMinutes . ' MINUTE)
                AND NOT EXISTS (
                    SELECT 1 FROM tenant_messages m
                    WHERE m.thread_id = t.id AND m.sender_user_id <> ?
                )
                ORDER BY t.updated_at DESC
                LIMIT 1'
            );
            $stmt->execute([$authorUserId, $tenantId, $authorUserId, $authorUserId]);
            $id = $stmt->fetchColumn();

            return $id ? (int) $id : null;
        } catch (\PDOException) {
            return null;
        }
    }

    /** @return list<int> */
    public function listParticipantUserIds(int $threadId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT user_id FROM tenant_message_thread_users WHERE thread_id = ?');
            $stmt->execute([$threadId]);
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $out[] = (int) ($row['user_id'] ?? 0);
            }

            return array_values(array_filter($out, static fn (int $id): bool => $id > 0));
        } catch (\PDOException) {
            return [];
        }
    }

    public function markAllThreadsReadForUser(int $tenantId, int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tenant_message_thread_users tu
                INNER JOIN tenant_message_threads t ON t.id = tu.thread_id
                SET tu.last_read_at = NOW()
                WHERE tu.user_id = ? AND t.tenant_id = ?'
            );
            $stmt->execute([$userId, $tenantId]);
        } catch (\PDOException) {
        }
    }

    /**
     * Activité récente (conversations mises à jour) pour le hub.
     *
     * @return list<array<string, mixed>>
     */
    public function listActivityThreadsForUser(int $tenantId, int $userId, int $limit = 40): array
    {
        try {
            $lim = max(1, min(80, $limit));
            $stmt = $this->pdo->prepare(
                "SELECT t.id, t.subject, t.updated_at,
                (SELECT body FROM tenant_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_preview,
                (SELECT sender_user_id FROM tenant_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_sender_id,
                tu.last_read_at,
                (t.updated_at > COALESCE(tu.last_read_at, '1970-01-01')) AS thread_unread
                FROM tenant_message_threads t
                INNER JOIN tenant_message_thread_users tu ON tu.thread_id = t.id AND tu.user_id = ?
                WHERE t.tenant_id = ?
                ORDER BY t.updated_at DESC, t.id DESC
                LIMIT {$lim}"
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

    /** @return list<array<string, mixed>> */
    public function listThreadsForUser(int $tenantId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.id, t.subject, t.created_at, t.updated_at,
                (SELECT body FROM tenant_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_preview,
                (t.updated_at > COALESCE(tu.last_read_at, \'1970-01-01\')) AS has_unread
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
