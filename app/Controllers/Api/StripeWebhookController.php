<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TenantRepository;
use App\Support\Stripe\StripeWebhookSignature;
use App\Support\Stripe\WebhookSignatureException;
use JsonException;

/**
 * Webhook Stripe — configure STRIPE_WEBHOOK_SECRET et l’URL dans le dashboard Stripe.
 * Vérification de signature sans SDK (HMAC-SHA256, voir StripeWebhookSignature).
 * Met à jour plan_slug / subscription sur tenants via metadata.tenant_id.
 */
class StripeWebhookController
{
    public function __construct(
        private TenantRepository $tenantRepository
    ) {}

    public function handle(Request $request, array $params = []): Response
    {
        $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        if ($secret === '') {
            return Response::json(['error' => 'STRIPE_WEBHOOK_SECRET non configuré'], 503);
        }

        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            return Response::json(['error' => 'Empty body'], 400);
        }

        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if ($sigHeader === '') {
            return Response::json(['error' => 'Missing signature'], 400);
        }

        try {
            StripeWebhookSignature::verify($payload, $sigHeader, $secret);
        } catch (WebhookSignatureException) {
            return Response::json(['error' => 'Invalid signature'], 400);
        }

        try {
            $event = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Response::json(['error' => 'Invalid payload'], 400);
        }

        $type = is_object($event) && isset($event->type) ? (string) $event->type : '';
        $obj = (is_object($event) && isset($event->data) && is_object($event->data->object ?? null))
            ? $event->data->object
            : null;

        if ($type === 'checkout.session.completed' && $obj !== null) {
            $meta = (array) ($obj->metadata ?? []);
            $tenantId = isset($meta['tenant_id']) ? (int) $meta['tenant_id'] : 0;
            $planSlug = isset($meta['plan_slug']) ? (string) $meta['plan_slug'] : 'standard';
            if ($tenantId > 0 && isset($obj->customer)) {
                $subId = $obj->subscription ?? null;
                $this->tenantRepository->updateSubscriptionFromStripe(
                    $tenantId,
                    is_string($obj->customer) ? $obj->customer : null,
                    is_string($subId) ? $subId : null,
                    'active',
                    $planSlug,
                    null
                );
            }
        }

        if (($type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted') && $obj !== null) {
            $meta = (array) ($obj->metadata ?? []);
            $tenantId = isset($meta['tenant_id']) ? (int) $meta['tenant_id'] : 0;
            $status = (string) ($obj->status ?? 'active');
            $map = ['active' => 'active', 'trialing' => 'active', 'past_due' => 'past_due', 'canceled' => 'canceled', 'unpaid' => 'past_due'];
            $subStatus = $map[$status] ?? 'active';
            $periodEnd = isset($obj->current_period_end) ? date('c', (int) $obj->current_period_end) : null;
            if ($tenantId > 0) {
                $planSlug = isset($meta['plan_slug']) ? (string) $meta['plan_slug'] : null;
                $this->tenantRepository->updateSubscriptionFromStripe(
                    $tenantId,
                    null,
                    is_string($obj->id ?? null) ? $obj->id : null,
                    $subStatus,
                    $planSlug,
                    $periodEnd
                );
            }
        }

        return Response::json(['received' => true]);
    }
}
