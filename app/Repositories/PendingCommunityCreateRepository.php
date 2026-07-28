<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PendingCommunityCreateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(string $token, int $userId, string $payloadJson, string $planSlug, string $stripePriceId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pending_community_creates (token, user_id, payload_json, plan_slug, stripe_price_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$token, $userId, $payloadJson, $planSlug, $stripePriceId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pending_community_creates WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByStripeCheckoutSessionId(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pending_community_creates WHERE stripe_checkout_session_id = ? LIMIT 1'
        );
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateStripeSessionId(string $token, string $stripeCheckoutSessionId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pending_community_creates SET stripe_checkout_session_id = ? WHERE token = ?'
        );
        $stmt->execute([$stripeCheckoutSessionId, $token]);
    }

    public function setTenantIdForToken(string $token, int $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pending_community_creates SET tenant_id = ?, creation_error = NULL WHERE token = ?'
        );
        $stmt->execute([$tenantId, $token]);
    }

    public function setCreationError(string $token, string $message): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE pending_community_creates SET creation_error = ? WHERE token = ?'
            );
            $stmt->execute([mb_substr(trim($message), 0, 2000), $token]);
        } catch (\PDOException $e) {
            error_log('[pending_community] setCreationError: ' . $e->getMessage());
        }
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM pending_community_creates WHERE id = ?');
        $stmt->execute([$id]);
    }
}
