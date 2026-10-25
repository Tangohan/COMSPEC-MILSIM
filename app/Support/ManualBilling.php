<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formules payées par PayPal (paypal.me) puis activées à la main par l’administration du site.
 *
 * Il n’y a pas d’abonnement automatique : chaque paiement couvre un mois ou un an. La période en cours
 * est recopiée dans tenants.settings sous la clé manual_billing :
 *   {"reference": "ATH-7KQ2XM", "plan_slug": "pro", "interval": "monthly", "ends_at": "2026-11-25 10:00:00"}
 * Une fois ends_at passé, l’abonnement « active » ne compte plus (sauf abonnement chez un prestataire).
 */
final class ManualBilling
{
    public const SETTINGS_KEY = 'manual_billing';

    public const SETTING_PAYPAL_URL = 'billing_paypal_me_url';

    /** Compte PayPal de la plateforme (lien public, pas un secret) ; modifiable dans Admin > Paiements. */
    public const DEFAULT_PAYPAL_URL = 'https://www.paypal.me/TTRDProIndividual';

    /** Alphabet des références : sans 0/O, 1/I/L pour la saisie à la main. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generateReference(): string
    {
        $out = 'ATH-';
        $max = strlen(self::REFERENCE_ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $out .= self::REFERENCE_ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /** Lien paypal.me avec le montant pré-rempli : https://www.paypal.me/Nom/12.50EUR */
    public static function paypalUrl(string $base, int $amountCents): string
    {
        $base = rtrim(trim($base), '/');
        if ($base === '') {
            $base = self::DEFAULT_PAYPAL_URL;
        }

        return $base . '/' . sprintf('%d.%02d', intdiv($amountCents, 100), $amountCents % 100) . 'EUR';
    }

    public static function formatAmount(int $amountCents): string
    {
        $euros = intdiv($amountCents, 100);
        $cents = $amountCents % 100;

        return ($cents === 0 ? (string) $euros : $euros . ',' . sprintf('%02d', $cents)) . ' €';
    }

    /**
     * Fin de la nouvelle période : prolonge la période en cours si elle n’est pas terminée.
     */
    public static function periodEnd(?string $currentEnd, string $interval, ?int $now = null): string
    {
        $now ??= time();
        $start = $now;
        if ($currentEnd !== null && trim($currentEnd) !== '') {
            $ts = strtotime($currentEnd);
            if ($ts !== false && $ts > $now) {
                $start = $ts;
            }
        }
        $end = strtotime($interval === 'yearly' ? '+1 year' : '+1 month', $start);

        return date('Y-m-d H:i:s', $end !== false ? $end : $start + 30 * 86400);
    }

    /**
     * Période payée en cours d’après les réglages de la communauté.
     *
     * @param array<string, mixed> $tenant
     * @return array{reference: string, plan_slug: string, interval: string, ends_at: string}|null
     */
    public static function current(array $tenant): ?array
    {
        $settings = $tenant['settings'] ?? null;
        if (is_string($settings)) {
            $settings = trim($settings) !== '' ? json_decode($settings, true) : [];
        }
        $row = is_array($settings) ? ($settings[self::SETTINGS_KEY] ?? null) : null;
        if (!is_array($row) || !is_string($row['ends_at'] ?? null) || trim($row['ends_at']) === '') {
            return null;
        }

        return [
            'reference' => (string) ($row['reference'] ?? ''),
            'plan_slug' => (string) ($row['plan_slug'] ?? ''),
            'interval' => (string) ($row['interval'] ?? 'monthly'),
            'ends_at' => trim($row['ends_at']),
        ];
    }

    /**
     * Abonnement « active » qui ne repose que sur un paiement manuel dont la période est terminée.
     *
     * @param array<string, mixed> $tenant
     */
    public static function isExpired(array $tenant, ?int $now = null): bool
    {
        $cur = self::current($tenant);
        if ($cur === null) {
            return false;
        }
        if (trim((string) ($tenant['stripe_subscription_id'] ?? '')) !== ''
            || trim((string) ($tenant['paypal_subscription_id'] ?? '')) !== '') {
            return false;
        }
        $ts = strtotime($cur['ends_at']);

        return $ts !== false && $ts <= ($now ?? time());
    }

    /**
     * Abonnement payant en cours (prestataire ou paiement manuel non échu).
     *
     * @param array<string, mixed> $tenant
     */
    public static function paidActive(array $tenant, ?int $now = null): bool
    {
        return in_array((string) ($tenant['subscription_status'] ?? 'none'), ['active', 'trialing'], true)
            && !self::isExpired($tenant, $now);
    }
}
