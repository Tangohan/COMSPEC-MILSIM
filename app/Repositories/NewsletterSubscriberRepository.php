<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
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
        $st = $this->pdo->prepare('SELECT * FROM newsletter_subscribers WHERE email = ? LIMIT 1');
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
}
