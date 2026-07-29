<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * PayPal REST : abonnements (Billing Subscriptions) et paiements one-shot (Orders).
 * Sans SDK — HTTP + OAuth client credentials.
 */
final class PayPalCheckoutService
{
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    /**
     * @param array<string, string> $customFields cles libres stockees en custom_id (JSON compact, max ~127)
     * @return array{url: string, id: string}
     */
    public function createSubscription(
        string $planId,
        string $returnUrl,
        string $cancelUrl,
        ?string $subscriberEmail,
        array $customFields
    ): array {
        $planId = trim($planId);
        if ($planId === '') {
            throw new \InvalidArgumentException('Identifiant de plan PayPal manquant.');
        }

        $body = [
            'plan_id' => $planId,
            'application_context' => [
                'brand_name' => trim((string) (getenv('PAYPAL_BRAND_NAME') ?: 'Athena')),
                'locale' => 'fr-FR',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];
        $customId = $this->encodeCustomId($customFields);
        if ($customId !== '') {
            $body['custom_id'] = $customId;
        }
        if ($subscriberEmail !== null && $subscriberEmail !== '') {
            $body['subscriber'] = ['email_address' => $subscriberEmail];
        }

        $data = $this->request('POST', '/v1/billing/subscriptions', $body);
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : '';
        $url = $this->extractApprovalUrl($data);
        if ($id === '' || $url === '') {
            throw new \RuntimeException('Réponse PayPal invalide (abonnement).');
        }

        return ['url' => $url, 'id' => $id];
    }

    /**
     * Paiement unique (ex. Support du cœur).
     *
     * @param array<string, string> $customFields
     * @return array{url: string, id: string}
     */
    public function createOrder(
        int $amountCents,
        string $currency,
        string $description,
        string $returnUrl,
        string $cancelUrl,
        array $customFields
    ): array {
        $amountCents = max(100, $amountCents);
        $currency = strtoupper(trim($currency) !== '' ? $currency : 'EUR');
        $value = number_format($amountCents / 100, 2, '.', '');

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'description' => mb_substr($description, 0, 127),
                'custom_id' => $this->encodeCustomId($customFields),
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $value,
                ],
            ]],
            'application_context' => [
                'brand_name' => trim((string) (getenv('PAYPAL_BRAND_NAME') ?: 'Athena')),
                'locale' => 'fr-FR',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $data = $this->request('POST', '/v1/checkout/orders', $body);
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : '';
        $url = $this->extractApprovalUrl($data);
        if ($id === '' || $url === '') {
            throw new \RuntimeException('Réponse PayPal invalide (commande).');
        }

        return ['url' => $url, 'id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new \InvalidArgumentException('Identifiant de commande manquant.');
        }

        return $this->request('POST', '/v1/checkout/orders/' . rawurlencode($orderId) . '/capture', new \stdClass());
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $subscriptionId): array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            throw new \InvalidArgumentException('Identifiant d’abonnement manquant.');
        }

        return $this->request('GET', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId), null);
    }

    /**
     * Vérifie la signature d’un webhook PayPal.
     *
     * @param array<string, string> $headers
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $webhookId = trim((string) (getenv('PAYPAL_WEBHOOK_ID') ?: ''));
        if ($webhookId === '') {
            return false;
        }

        $body = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($payload, true) ?? new \stdClass(),
        ];

        try {
            $result = $this->request('POST', '/v1/notifications/verify-webhook-signature', $body);
        } catch (\Throwable) {
            return false;
        }

        return strtoupper((string) ($result['verification_status'] ?? '')) === 'SUCCESS';
    }

    /**
     * @param array<string, string> $fields
     */
    public function encodeCustomId(array $fields): string
    {
        if ($fields === []) {
            return '';
        }
        $json = json_encode($fields, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }
        if (strlen($json) <= 127) {
            return $json;
        }
        // Fallback compact : token seul ou tenant|plan
        if (isset($fields['pct'])) {
            return mb_substr((string) $fields['pct'], 0, 127);
        }
        if (isset($fields['tid'], $fields['plan'])) {
            return mb_substr('t' . $fields['tid'] . ':' . $fields['plan'], 0, 127);
        }

        return mb_substr($json, 0, 127);
    }

    /**
     * @return array<string, string>
     */
    public function decodeCustomId(?string $customId): array
    {
        $customId = trim((string) $customId);
        if ($customId === '') {
            return [];
        }
        if ($customId[0] === '{') {
            $decoded = json_decode($customId, true);

            return is_array($decoded) ? array_map('strval', $decoded) : [];
        }
        if (preg_match('/^t(\d+):([a-z0-9_]+)$/i', $customId, $m)) {
            return ['tid' => $m[1], 'plan' => $m[2]];
        }
        if (strlen($customId) === 64 && ctype_xdigit($customId)) {
            return ['pct' => $customId];
        }

        return ['raw' => $customId];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractApprovalUrl(array $data): string
    {
        $links = $data['links'] ?? null;
        if (!is_array($links)) {
            return '';
        }
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $rel = strtolower((string) ($link['rel'] ?? ''));
            if (in_array($rel, ['approve', 'payer-action'], true)) {
                $href = (string) ($link['href'] ?? '');
                if ($href !== '') {
                    return $href;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed>|object|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array|object|null $body): array
    {
        $token = $this->getAccessToken();
        $base = $this->apiBase();
        $url = $base . $path;
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if ($body !== null && strtoupper($method) !== 'GET') {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new \RuntimeException('Impossible de sérialiser la requête PayPal.');
            }
            $opts[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || !is_string($raw)) {
            throw new \RuntimeException('Impossible de joindre PayPal.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Réponse PayPal illisible.');
        }
        if ($http >= 400) {
            $msg = (string) ($data['message'] ?? $data['error_description'] ?? $data['name'] ?? 'Erreur PayPal');
            $details = $data['details'][0]['description'] ?? null;
            if (is_string($details) && $details !== '') {
                $msg .= ' — ' . $details;
            }
            throw new \RuntimeException($msg);
        }

        return $data;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt - 30) {
            return $this->accessToken;
        }
        $clientId = trim((string) (getenv('PAYPAL_CLIENT_ID') ?: ''));
        $secret = trim((string) (getenv('PAYPAL_CLIENT_SECRET') ?: ''));
        if ($clientId === '' || $secret === '') {
            throw new \RuntimeException('Paiement indisponible : identifiants PayPal non configurés.');
        }
        $url = $this->apiBase() . '/v1/oauth2/token';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $clientId . ':' . $secret,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: fr_FR'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno !== 0 || !is_string($raw)) {
            throw new \RuntimeException('Impossible d’obtenir un jeton PayPal.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Jeton PayPal invalide.');
        }
        $this->accessToken = (string) $data['access_token'];
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 300);

        return $this->accessToken;
    }

    private function apiBase(): string
    {
        $mode = strtolower(trim((string) (getenv('PAYPAL_MODE') ?: 'sandbox')));

        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
