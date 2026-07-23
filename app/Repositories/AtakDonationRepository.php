<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Contributions au financement ATAK (Checkout Stripe one-shot).
 */
final class AtakDonationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['atak_donations']);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function createPending(
        int $userId,
        ?int $tenantId,
        int $amountCents,
        string $currency,
        string $checkoutSessionId
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO atak_donations (user_id, tenant_id, amount_cents, currency, stripe_checkout_session_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([
            $userId,
            $tenantId !== null && $tenantId > 0 ? $tenantId : null,
            $amountCents,
            strtolower($currency),
            $checkoutSessionId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByCheckoutSessionId(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_donations WHERE stripe_checkout_session_id = ? LIMIT 1');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markPaid(int $id, ?string $paymentIntentId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE atak_donations
             SET status = \'paid\',
                 stripe_payment_intent_id = COALESCE(?, stripe_payment_intent_id),
                 paid_at = COALESCE(paid_at, NOW())
             WHERE id = ? AND status <> \'paid\''
        );
        $stmt->execute([$paymentIntentId, $id]);
    }

    public function markBadgeGranted(int $id): void
    {
        $this->pdo->prepare('UPDATE atak_donations SET badge_granted = 1 WHERE id = ?')->execute([$id]);
    }

    /** @return list<array<string, mixed>> */
    public function listPaidForUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM atak_donations WHERE user_id = ? AND status = \'paid\' ORDER BY paid_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function hasPaidDonationForUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM atak_donations WHERE user_id = ? AND status = \'paid\' LIMIT 1'
        );
        $stmt->execute([$userId]);

        return (bool) $stmt->fetchColumn();
    }
}
