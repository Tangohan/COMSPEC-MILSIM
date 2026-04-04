<?php

declare(strict_types=1);

namespace App\Support\Stripe;

/**
 * Vérification des webhooks Stripe sans SDK (équivalent de \Stripe\Webhook::constructEvent).
 * Algorithme : https://stripe.com/docs/webhooks/signatures
 */
final class StripeWebhookSignature
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * @throws WebhookSignatureException
     */
    public static function verify(
        string $payload,
        string $stripeSignatureHeader,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS
    ): void {
        if ($secret === '') {
            throw new WebhookSignatureException('Secret vide');
        }
        $secretKey = self::decodeSigningSecret($secret);
        $parsed = self::parseStripeSignatureHeader($stripeSignatureHeader);
        if ($parsed['timestamp'] === null) {
            throw new WebhookSignatureException('Horodatage manquant dans Stripe-Signature');
        }
        $timestamp = $parsed['timestamp'];
        if (abs(time() - $timestamp) > $toleranceSeconds) {
            throw new WebhookSignatureException('Horodatage hors fenêtre de tolérance');
        }
        $v1Signatures = $parsed['v1'];
        if ($v1Signatures === []) {
            throw new WebhookSignatureException('Aucune signature v1');
        }
        $signedPayload = $timestamp . '.' . $payload;
        $expectedHex = hash_hmac('sha256', $signedPayload, $secretKey, false);
        $ok = false;
        foreach ($v1Signatures as $sig) {
            if (hash_equals($expectedHex, $sig)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            throw new WebhookSignatureException('Signature ne correspond pas');
        }
    }

    /**
     * Secret dashboard / CLI : préfixe whsec_ + base64 (bytes utilisés comme clé HMAC).
     */
    private static function decodeSigningSecret(string $secret): string
    {
        if (str_starts_with($secret, 'whsec_')) {
            $decoded = base64_decode(substr($secret, 5), true);
            if ($decoded === false || $decoded === '') {
                throw new WebhookSignatureException('Secret webhook whsec_ invalide');
            }

            return $decoded;
        }

        return $secret;
    }

    /**
     * @return array{timestamp: ?int, v1: list<string>}
     */
    private static function parseStripeSignatureHeader(string $header): array
    {
        $timestamp = null;
        $v1 = [];
        foreach (explode(',', $header) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $eq = strpos($chunk, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($chunk, 0, $eq));
            $value = trim(substr($chunk, $eq + 1));
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $v1[] = $value;
            }
        }

        return ['timestamp' => $timestamp, 'v1' => $v1];
    }
}
