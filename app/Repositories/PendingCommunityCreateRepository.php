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

    public function create(
        string $token,
        int $userId,
        string $payloadJson,
        string $planSlug,
        string $priceOrPlanId,
        string $paymentProvider = 'stripe'
    ): int {
        $paymentProvider = in_array($paymentProvider, ['stripe', 'paypal'], true) ? $paymentProvider : 'stripe';
        if ($this->hasPaymentProviderColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pending_community_creates (token, user_id, payload_json, plan_slug, stripe_price_id, payment_provider, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$token, $userId, $payloadJson, $planSlug, $priceOrPlanId, $paymentProvider]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pending_community_creates (token, user_id, payload_json, plan_slug, stripe_price_id, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$token, $userId, $payloadJson, $planSlug, $priceOrPlanId]);
        }

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

    public function findByPayPalSubscriptionId(string $subscriptionId): ?array
    {
        if (!$this->hasPayPalSubscriptionColumn()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pending_community_creates WHERE paypal_subscription_id = ? LIMIT 1'
        );
        $stmt->execute([$subscriptionId]);
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

    public function updatePayPalSubscriptionId(string $token, string $paypalSubscriptionId): void
    {
        if (!$this->hasPayPalSubscriptionColumn()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE pending_community_creates SET paypal_subscription_id = ? WHERE token = ?'
        );
        $stmt->execute([$paypalSubscriptionId, $token]);
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

    private function hasPaymentProviderColumn(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pending_community_creates' AND COLUMN_NAME = 'payment_provider' LIMIT 1"
            );

            return $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasPayPalSubscriptionColumn(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pending_community_creates' AND COLUMN_NAME = 'paypal_subscription_id' LIMIT 1"
            );

            return $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
