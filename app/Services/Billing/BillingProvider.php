<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Selection du fournisseur de paiement (PayPal prioritaire si configure).
 */
final class BillingProvider
{
    public const PAYPAL = 'paypal';
    public const STRIPE = 'stripe';

    public static function paypalConfigured(): bool
    {
        $id = trim((string) (getenv('PAYPAL_CLIENT_ID') ?: ''));
        $secret = trim((string) (getenv('PAYPAL_CLIENT_SECRET') ?: ''));

        return $id !== '' && $secret !== '';
    }

    public static function stripeConfigured(): bool
    {
        return trim((string) (getenv('STRIPE_SECRET_KEY') ?: '')) !== '';
    }

    public static function anyConfigured(): bool
    {
        return self::paypalConfigured() || self::stripeConfigured();
    }

    /**
     * Fournisseur actif pour les nouveaux paiements.
     * BILLING_PROVIDER=paypal|stripe|auto (defaut auto = PayPal si dispo, sinon Stripe).
     */
    public static function preferred(): ?string
    {
        $forced = strtolower(trim((string) (getenv('BILLING_PROVIDER') ?: 'auto')));
        if ($forced === self::PAYPAL && self::paypalConfigured()) {
            return self::PAYPAL;
        }
        if ($forced === self::STRIPE && self::stripeConfigured()) {
            return self::STRIPE;
        }
        if (self::paypalConfigured()) {
            return self::PAYPAL;
        }
        if (self::stripeConfigured()) {
            return self::STRIPE;
        }

        return null;
    }

    public static function isPayPalPreferred(): bool
    {
        return self::preferred() === self::PAYPAL;
    }
}
