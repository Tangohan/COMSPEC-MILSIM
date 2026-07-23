<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Création de sessions Stripe Checkout (sans SDK, HTTP POST).
 */
final class StripeCheckoutService
{
    /**
     * @param array<string, string> $metadata
     * @return array{url: string, id: string}
     */
    public function createSubscriptionCheckoutSession(
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        ?string $customerEmail,
        array $metadata
    ): array {
        $secret = getenv('STRIPE_SECRET_KEY') ?: '';
        if ($secret === '') {
            throw new \RuntimeException('Paiement indisponible : STRIPE_SECRET_KEY n’est pas configuré.');
        }

        $params = [
            'mode' => 'subscription',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];
        if ($customerEmail !== null && $customerEmail !== '') {
            $params['customer_email'] = $customerEmail;
        }
        foreach ($metadata as $k => $v) {
            $params['metadata[' . $k . ']'] = $v;
        }

        $data = $this->post('checkout/sessions', $params);

        $url = $data['url'] ?? null;
        $id = $data['id'] ?? null;
        if (!is_string($url) || $url === '' || !is_string($id) || $id === '') {
            throw new \RuntimeException('Réponse Stripe invalide (session Checkout).');
        }

        return ['url' => $url, 'id' => $id];
    }

    /**
     * Paiement one-shot (dons / financement ATAK) via price_data dynamique.
     *
     * @param array<string, string> $metadata
     * @return array{url: string, id: string}
     */
    public function createPaymentCheckoutSession(
        int $amountCents,
        string $currency,
        string $productName,
        string $productDescription,
        string $successUrl,
        string $cancelUrl,
        ?string $customerEmail,
        array $metadata
    ): array {
        $secret = getenv('STRIPE_SECRET_KEY') ?: '';
        if ($secret === '') {
            throw new \RuntimeException('Paiement indisponible : STRIPE_SECRET_KEY n’est pas configuré.');
        }
        $amountCents = max(100, $amountCents);
        $currency = strtolower(trim($currency) !== '' ? $currency : 'eur');

        $params = [
            'mode' => 'payment',
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => $amountCents,
            'line_items[0][price_data][product_data][name]' => $productName,
            'line_items[0][price_data][product_data][description]' => $productDescription,
            'line_items[0][quantity]' => 1,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'submit_type' => 'donate',
        ];
        if ($customerEmail !== null && $customerEmail !== '') {
            $params['customer_email'] = $customerEmail;
        }
        foreach ($metadata as $k => $v) {
            $params['metadata[' . $k . ']'] = (string) $v;
        }

        $data = $this->post('checkout/sessions', $params);

        $url = $data['url'] ?? null;
        $id = $data['id'] ?? null;
        if (!is_string($url) || $url === '' || !is_string($id) || $id === '') {
            throw new \RuntimeException('Réponse Stripe invalide (session Checkout).');
        }

        return ['url' => $url, 'id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new \InvalidArgumentException('Identifiant de session manquant.');
        }

        return $this->get('checkout/sessions/' . rawurlencode($sessionId));
    }

    /**
     * @param array<string, string|int> $params
     * @return array<string, mixed>
     */
    private function post(string $path, array $params): array
    {
        return $this->request('POST', $path, $params);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        return $this->request('GET', $path, null);
    }

    /**
     * @param array<string, string|int>|null $params
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $params): array
    {
        $secret = getenv('STRIPE_SECRET_KEY') ?: '';
        $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $secret . ':',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 45,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params ?? []);
        } else {
            $opts[CURLOPT_HTTPGET] = true;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno !== 0 || !is_string($body)) {
            throw new \RuntimeException('Impossible de joindre Stripe.');
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Réponse Stripe illisible.');
        }
        if (isset($data['error']) && is_array($data['error'])) {
            $msg = (string) ($data['error']['message'] ?? 'Erreur Stripe');
            throw new \RuntimeException($msg);
        }

        return $data;
    }
}
