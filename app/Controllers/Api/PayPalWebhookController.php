<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PendingCommunityCreateRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\TenantRepository;
use App\Services\Billing\PayPalCheckoutService;
use App\Services\Community\TenantBootstrapService;
use App\Support\UserFacingExceptionMapper;

/**
 * Webhook PayPal — configure PAYPAL_WEBHOOK_ID et l’URL dans le dashboard PayPal.
 */
final class PayPalWebhookController
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private ReferralRepository $referralRepository,
        private PendingCommunityCreateRepository $pendingCommunityRepository,
        private TenantBootstrapService $tenantBootstrapService,
        private PayPalCheckoutService $payPalCheckoutService,
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        if (!\App\Services\Billing\BillingProvider::paypalConfigured()) {
            return Response::json(['error' => 'PayPal non configuré'], 503);
        }

        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            return Response::json(['error' => 'Empty body'], 400);
        }

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_') && is_string($v)) {
                $name = str_replace('_', '-', substr($k, 5));
                $headers[$name] = $v;
                $headers[strtolower($name)] = $v;
            }
        }
        // PayPal envoie PAYPAL-* ; PHP les mappe en HTTP_PAYPAL_*
        foreach (['PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID', 'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME'] as $h) {
            $serverKey = 'HTTP_' . str_replace('-', '_', $h);
            if (!empty($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
                $headers[$h] = $_SERVER[$serverKey];
                $headers[strtolower($h)] = $_SERVER[$serverKey];
            }
        }

        $webhookId = trim((string) (getenv('PAYPAL_WEBHOOK_ID') ?: ''));
        if ($webhookId !== '') {
            if (!$this->payPalCheckoutService->verifyWebhookSignature($payload, $headers)) {
                return Response::json(['error' => 'Invalid signature'], 400);
            }
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Response::json(['error' => 'Invalid payload'], 400);
        }
        if (!is_array($event)) {
            return Response::json(['error' => 'Invalid payload'], 400);
        }

        $type = (string) ($event['event_type'] ?? '');
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        if (in_array($type, [
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.UPDATED',
            'CHECKOUT.ORDER.APPROVED',
        ], true)) {
            $this->handleSubscriptionResource($resource, $type);
        }

        if (in_array($type, [
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.EXPIRED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
        ], true)) {
            $this->handleSubscriptionStatus($resource, $type === 'BILLING.SUBSCRIPTION.SUSPENDED' ? 'past_due' : 'canceled');
        }

        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            // One-shot (Support du cœur) — pas de changement de plan.
        }

        return Response::json(['received' => true]);
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function handleSubscriptionResource(array $resource, string $eventType): void
    {
        $subscriptionId = trim((string) ($resource['id'] ?? ''));
        if ($subscriptionId === '' || !str_starts_with($subscriptionId, 'I-')) {
            // Peut être une commande Orders — ignorer ici
            if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
                return;
            }
            if ($subscriptionId === '') {
                return;
            }
        }

        $custom = $this->payPalCheckoutService->decodeCustomId(
            isset($resource['custom_id']) ? (string) $resource['custom_id'] : null
        );
        $statusRaw = strtoupper((string) ($resource['status'] ?? 'ACTIVE'));
        $subStatus = match ($statusRaw) {
            'ACTIVE', 'APPROVED' => 'active',
            'SUSPENDED' => 'past_due',
            'CANCELLED', 'EXPIRED' => 'canceled',
            default => 'active',
        };

        $payerId = null;
        if (isset($resource['subscriber']) && is_array($resource['subscriber'])) {
            $payer = $resource['subscriber']['payer_id'] ?? null;
            $payerId = is_string($payer) ? $payer : null;
        }

        $periodEnd = null;
        if (isset($resource['billing_info']['next_billing_time'])) {
            $periodEnd = (string) $resource['billing_info']['next_billing_time'];
        }

        if (!empty($custom['pct'])) {
            $this->processPendingCommunity($custom['pct'], $subscriptionId, $payerId, $periodEnd);

            return;
        }

        $tenantId = isset($custom['tid']) ? (int) $custom['tid'] : 0;
        $planSlug = isset($custom['plan']) ? (string) $custom['plan'] : null;
        if ($tenantId < 1 && $subscriptionId !== '') {
            $pending = $this->pendingCommunityRepository->findByPayPalSubscriptionId($subscriptionId);
            if ($pending !== null && empty($pending['tenant_id'])) {
                $this->processPendingCommunity((string) $pending['token'], $subscriptionId, $payerId, $periodEnd);

                return;
            }
            $tenant = $this->tenantRepository->findByPayPalSubscriptionId($subscriptionId);
            if ($tenant !== null) {
                $tenantId = (int) $tenant['id'];
            }
        }
        if ($tenantId > 0) {
            $this->tenantRepository->updateSubscriptionFromPayPal(
                $tenantId,
                $payerId,
                $subscriptionId,
                $subStatus,
                $planSlug,
                $periodEnd
            );
            $referrerId = isset($custom['ref']) ? (int) $custom['ref'] : 0;
            if ($referrerId > 0 && $subStatus === 'active') {
                $this->referralRepository->recordAttribution($referrerId, $tenantId, 'first_payment');
            }
        }
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function handleSubscriptionStatus(array $resource, string $status): void
    {
        $subscriptionId = trim((string) ($resource['id'] ?? ''));
        if ($subscriptionId === '') {
            return;
        }
        $tenant = $this->tenantRepository->findByPayPalSubscriptionId($subscriptionId);
        if ($tenant === null) {
            $custom = $this->payPalCheckoutService->decodeCustomId(
                isset($resource['custom_id']) ? (string) $resource['custom_id'] : null
            );
            $tenantId = isset($custom['tid']) ? (int) $custom['tid'] : 0;
            if ($tenantId < 1) {
                return;
            }
        } else {
            $tenantId = (int) $tenant['id'];
        }
        $this->tenantRepository->updateSubscriptionFromPayPal(
            $tenantId,
            null,
            $subscriptionId,
            $status,
            null,
            null
        );
    }

    private function processPendingCommunity(
        string $token,
        string $subscriptionId,
        ?string $payerId,
        ?string $periodEnd
    ): void {
        $row = $this->pendingCommunityRepository->findByToken($token);
        if ($row === null || !empty($row['tenant_id'])) {
            return;
        }
        $payload = json_decode((string) $row['payload_json'], true);
        if (!is_array($payload)) {
            return;
        }
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $options['plan_slug'] = (string) $row['plan_slug'];
        $options['skip_founder_trial'] = true;

        try {
            $result = $this->tenantBootstrapService->createCommunity((int) $row['user_id'], $name, $slug, $options);
        } catch (\Throwable $e) {
            error_log('[paypal.pending_community] ' . $e->getMessage());
            $this->pendingCommunityRepository->setCreationError(
                $token,
                UserFacingExceptionMapper::communityCreationMessage($e)
            );

            return;
        }

        $this->tenantRepository->updateSubscriptionFromPayPal(
            (int) $result['tenant_id'],
            $payerId,
            $subscriptionId,
            'active',
            (string) $row['plan_slug'],
            $periodEnd
        );
        $this->pendingCommunityRepository->setTenantIdForToken($token, (int) $result['tenant_id']);

        $referrerId = isset($options['referrer_user_id']) ? (int) $options['referrer_user_id'] : 0;
        if ($referrerId > 0 && $referrerId !== (int) $row['user_id']) {
            $this->referralRepository->recordAttribution($referrerId, (int) $result['tenant_id'], 'first_payment');
        }
    }
}
