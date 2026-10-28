<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\PlatformSettingsRepository;

/**
 * Compte PayPal du site, partagé par la page de paiement des formules (/paiement/…) et la page de dons
 * (/soutenir-atak). Il n’y a qu’un seul réglage : Admin > Paiements (billing_paypal_me_url), avec repli sur
 * ManualBilling::DEFAULT_PAYPAL_URL. Le don se fait chez PayPal : Athena ne voit ni le montant ni le donateur.
 */
final class PayPalDonation
{
    /** Hôtes acceptés pour le lien PayPal. */
    private const HOSTS = ['paypal.me', 'www.paypal.me', 'paypal.com', 'www.paypal.com'];

    /**
     * Lien nettoyé (https, hôte PayPal), ou null s’il n’est pas valable.
     * Accepte « paypal.me/nom » sans https:// et les liens de bouton de don (donate/?hosted_button_id=…).
     */
    public static function normalizeUrl(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        $parts = parse_url($raw);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        if (!in_array($host, self::HOSTS, true)) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/') {
            return null;
        }
        if (preg_match('/[\s<>"\']/', $raw) === 1) {
            return null;
        }
        $url = 'https://' . $host . $path;
        if (!empty($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        return mb_substr($url, 0, 500);
    }

    /** Lien du compte PayPal du site (réglé dans Admin > Paiements). */
    public static function accountUrl(?PlatformSettingsRepository $settings = null): string
    {
        try {
            $raw = ($settings ?? new PlatformSettingsRepository())->get(ManualBilling::SETTING_PAYPAL_URL, '');
        } catch (\Throwable) {
            $raw = '';
        }

        return self::normalizeUrl($raw) ?? ManualBilling::DEFAULT_PAYPAL_URL;
    }

    /** Un lien paypal.me accepte un montant pré-rempli (…/10.00EUR) ; un bouton de don PayPal, non. */
    public static function acceptsAmount(string $base): bool
    {
        $parts = parse_url($base);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        return empty($parts['query'])
            && (in_array($host, ['paypal.me', 'www.paypal.me'], true) || str_starts_with($path, '/paypalme/'));
    }

    /** Lien de don avec le montant pré-rempli quand le compte le permet. */
    public static function amountUrl(string $base, int $amountCents): string
    {
        return self::acceptsAmount($base) && $amountCents > 0 ? ManualBilling::paypalUrl($base, $amountCents) : $base;
    }
}
