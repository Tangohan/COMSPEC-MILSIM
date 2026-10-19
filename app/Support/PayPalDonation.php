<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\PlatformSettingsRepository;

/**
 * Dons PayPal du site : un lien de don PayPal (paypal.me ou bouton « Faire un don ») réglé par
 * l’administration du site, affiché sur /soutenir-atak avec un bouton et un QR code générés par Athena.
 * Le don se fait chez PayPal : Athena ne voit ni le montant ni le donateur (pas de badge automatique).
 *
 * Réglages (platform_settings) : donations_paypal_url, donations_paypal_enabled.
 * Repli : variable d’environnement PAYPAL_DONATE_URL.
 */
final class PayPalDonation
{
    public const SETTING_URL = 'donations_paypal_url';

    public const SETTING_ENABLED = 'donations_paypal_enabled';

    /** Hôtes acceptés pour le lien de don. */
    private const HOSTS = ['paypal.me', 'www.paypal.me', 'paypal.com', 'www.paypal.com'];

    /**
     * Lien de don nettoyé (https, hôte PayPal), ou null s’il n’est pas valable.
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

    public static function configuredUrl(?PlatformSettingsRepository $settings = null): ?string
    {
        $raw = '';
        try {
            $raw = ($settings ?? new PlatformSettingsRepository())->get(self::SETTING_URL, '');
        } catch (\Throwable) {
            $raw = '';
        }
        if (trim($raw) === '') {
            $raw = (string) (getenv('PAYPAL_DONATE_URL') ?: '');
        }

        return self::normalizeUrl($raw);
    }

    /** Lien de don à afficher au public, ou null si les dons PayPal sont coupés ou non réglés. */
    public static function publicUrl(?PlatformSettingsRepository $settings = null): ?string
    {
        $settings ??= new PlatformSettingsRepository();
        try {
            if (!$settings->getBool(self::SETTING_ENABLED, true)) {
                return null;
            }
        } catch (\Throwable) {
        }

        return self::configuredUrl($settings);
    }
}
