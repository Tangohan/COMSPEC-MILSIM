<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

final class NewsletterSubscriberRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getPdo();
    }

    public function schemaReady(): bool
    {
        $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'newsletter_subscribers' LIMIT 1");

        return (bool) ($st && $st->fetchColumn());
    }

    public function findByEmail(string $email): ?array
    {
        $emailEq = SqlText::normalizedEquals($this->pdo, 'email');
        $st = $this->pdo->prepare('SELECT * FROM newsletter_subscribers WHERE ' . $emailEq . ' LIMIT 1');
        $st->execute([mb_strtolower(trim($email))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createPending(string $email, string $confirmTokenHash, string $unsubscribeTokenHash, string $ip, string $userAgent): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO newsletter_subscribers (email, status, confirm_token_hash, confirm_expires_at, unsubscribe_token_hash, source, locale, subscribed_at, unsubscribed_at, last_event_at, ip_address, user_agent, created_at, updated_at)
             VALUES (?, "pending", ?, DATE_ADD(NOW(), INTERVAL 48 HOUR), ?, "website_home", "fr", NULL, NULL, NOW(), ?, ?, NOW(), NOW())'
        );
        $st->execute([mb_strtolower(trim($email)), $confirmTokenHash, $unsubscribeTokenHash, $ip, $userAgent]);

        return (int) $this->pdo->lastInsertId();
    }

    public function refreshPending(int $id, string $confirmTokenHash, string $unsubscribeTokenHash, string $ip, string $userAgent): void
    {
        $st = $this->pdo->prepare(
            'UPDATE newsletter_subscribers
             SET status = "pending", confirm_token_hash = ?, confirm_expires_at = DATE_ADD(NOW(), INTERVAL 48 HOUR),
                 unsubscribe_token_hash = ?, unsubscribed_at = NULL, last_event_at = NOW(), ip_address = ?, user_agent = ?, updated_at = NOW()
             WHERE id = ? LIMIT 1'
        );
        $st->execute([$confirmTokenHash, $unsubscribeTokenHash, $ip, $userAgent, $id]);
    }

    public function rotateUnsubscribeToken(int $id, string $unsubscribeTokenHash): void
    {
        $st = $this->pdo->prepare(
            'UPDATE newsletter_subscribers
             SET unsubscribe_token_hash = ?, updated_at = NOW()
             WHERE id = ? LIMIT 1'
        );
        $st->execute([$unsubscribeTokenHash, $id]);
    }

    public function markSubscribedByConfirmToken(string $confirmTokenHash): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM newsletter_subscribers
             WHERE confirm_token_hash = ? AND status = "pending" AND (confirm_expires_at IS NULL OR confirm_expires_at >= NOW())
             LIMIT 1'
        );
        $st->execute([$confirmTokenHash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $up = $this->pdo->prepare(
            'UPDATE newsletter_subscribers
             SET status = "subscribed", subscribed_at = COALESCE(subscribed_at, NOW()), confirm_token_hash = NULL, confirm_expires_at = NULL,
                 last_event_at = NOW(), updated_at = NOW()
             WHERE id = ? LIMIT 1'
        );
        $up->execute([(int) $row['id']]);

        return $this->findById((int) $row['id']);
    }

    public function markUnsubscribedByToken(string $unsubscribeTokenHash): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM newsletter_subscribers WHERE unsubscribe_token_hash = ? LIMIT 1');
        $st->execute([$unsubscribeTokenHash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $up = $this->pdo->prepare(
            'UPDATE newsletter_subscribers
             SET status = "unsubscribed", unsubscribed_at = NOW(), last_event_at = NOW(), updated_at = NOW()
             WHERE id = ? LIMIT 1'
        );
        $up->execute([(int) $row['id']]);

        return $this->findById((int) $row['id']);
    }

    private function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM newsletter_subscribers WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{pending: int, subscribed: int, unsubscribed: int, total: int}
     */
    public function adminCountsByStatus(): array
    {
        $base = ['pending' => 0, 'subscribed' => 0, 'unsubscribed' => 0, 'total' => 0];
        if (!$this->schemaReady()) {
            return $base;
        }
        $st = $this->pdo->query('SELECT status, COUNT(*) AS c FROM newsletter_subscribers GROUP BY status');
        if (!$st) {
            return $base;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $s = (string) ($row['status'] ?? '');
            $c = (int) ($row['c'] ?? 0);
            if (isset($base[$s])) {
                $base[$s] = $c;
            }
            $base['total'] += $c;
        }

        return $base;
    }

    /**
     * @param 'all'|'pending'|'subscribed'|'unsubscribed' $statusFilter
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, total_pages: int}
     */
    public function adminListSubscribers(string $statusFilter, string $emailNeedle, int $page, int $perPage): array
    {
        if (!$this->schemaReady()) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }
        $allowed = ['all', 'pending', 'subscribed', 'unsubscribed'];
        if (!in_array($statusFilter, $allowed, true)) {
            $statusFilter = 'all';
        }
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        $where = [];
        $params = [];
        if ($statusFilter !== 'all') {
            $where[] = 'status = ?';
            $params[] = $statusFilter;
        }
        $needle = trim($emailNeedle);
        if ($needle !== '') {
            $where[] = 'email LIKE ? ESCAPE \'\\\\\'';
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($needle));
            $params[] = '%' . $escaped . '%';
        }
        $sqlWhere = $where === [] ? '1=1' : implode(' AND ', $where);

        $countSt = $this->pdo->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE {$sqlWhere}");
        $countSt->execute($params);
        $total = (int) $countSt->fetchColumn();

        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $cols = 'id, email, status, subscribed_at, unsubscribed_at, last_event_at, created_at, source, locale, ip_address, user_agent';
        $listSt = $this->pdo->prepare(
            "SELECT {$cols} FROM newsletter_subscribers WHERE {$sqlWhere} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?"
        );
        $listParams = $params;
        $listParams[] = $perPage;
        $listParams[] = $offset;
        $listSt->execute($listParams);
        $rows = $listSt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
        ];
    }
}
