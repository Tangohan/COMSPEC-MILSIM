<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Repositories\AtakDonationRepository;
use App\Repositories\BadgeRepository;
use App\Repositories\UserRepository;

/**
 * Finalisation d’un don ATAK (paiement one-shot + badge Donateur ATAK).
 */
final class AtakDonationFulfillmentService
{
    public function __construct(
        private AtakDonationRepository $donations,
        private BadgeRepository $badges,
        private UserRepository $users,
        private StripeCheckoutService $stripe
    ) {}

    /**
     * Marque le don comme payé et attribue le badge sur toutes les communautés du compte.
     *
     * @param object|array<string, mixed>|null $sessionObj Objet session Stripe (webhook) ou null
     */
    public function fulfillByCheckoutSessionId(string $sessionId, object|array|null $sessionObj = null): bool
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || !$this->donations->schemaReady()) {
            return false;
        }

        $row = $this->donations->findByCheckoutSessionId($sessionId);
        if ($row === null) {
            return false;
        }

        $paymentIntentId = null;
        $paymentStatus = null;
        if (is_object($sessionObj)) {
            $pi = $sessionObj->payment_intent ?? null;
            $paymentIntentId = is_string($pi) ? $pi : null;
            $paymentStatus = isset($sessionObj->payment_status) ? (string) $sessionObj->payment_status : null;
        } elseif (is_array($sessionObj)) {
            $pi = $sessionObj['payment_intent'] ?? null;
            $paymentIntentId = is_string($pi) ? $pi : null;
            $paymentStatus = isset($sessionObj['payment_status']) ? (string) $sessionObj['payment_status'] : null;
        }

        if ((string) ($row['status'] ?? '') !== 'paid') {
            if ($paymentStatus === null) {
                try {
                    $remote = $this->stripe->retrieveCheckoutSession($sessionId);
                    $paymentStatus = (string) ($remote['payment_status'] ?? '');
                    $pi = $remote['payment_intent'] ?? null;
                    $paymentIntentId = is_string($pi) ? $pi : $paymentIntentId;
                } catch (\Throwable) {
                    return false;
                }
            }
            if ($paymentStatus !== 'paid') {
                return false;
            }
            $this->donations->markPaid((int) $row['id'], $paymentIntentId);
            $row = $this->donations->findByCheckoutSessionId($sessionId) ?? $row;
        }

        if (!empty($row['badge_granted'])) {
            return true;
        }

        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId < 1) {
            return false;
        }

        $user = $this->users->findById($userId);
        $email = is_array($user) ? trim((string) ($user['email'] ?? '')) : '';
        $granted = false;
        if ($email !== '') {
            foreach ($this->users->listTenantsForEmail($email) as $membership) {
                $tid = (int) ($membership['tenant_id'] ?? 0);
                $uid = (int) ($membership['id'] ?? 0);
                if ($tid > 0 && $uid > 0) {
                    if ($this->badges->ensureAndGrantDonorAtak($tid, $uid, $uid)) {
                        $granted = true;
                    }
                }
            }
        } else {
            $tid = (int) ($row['tenant_id'] ?? 0);
            if ($tid > 0) {
                $granted = $this->badges->ensureAndGrantDonorAtak($tid, $userId, $userId);
            }
        }

        if ($granted) {
            $this->donations->markBadgeGranted((int) $row['id']);
        }

        return true;
    }
}
