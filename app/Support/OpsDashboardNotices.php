<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Annonces opérationnelles injectées sur les tableaux de bord (hors table platform_alerts).
 * Identifiants stables ≥ 900000 pour les dismissals serveur (pas de FK vers platform_alerts).
 */
final class OpsDashboardNotices
{
    /** Demande de rechargement des images après perte au sync FTP. */
    public const MEDIA_REUPLOAD_ID = 900001;

    /**
     * @return list<array<string, mixed>> lignes au format platform_alerts (pour normalizeRow)
     */
    public static function platformRowsForAuthenticatedUser(): array
    {
        $accountUrl = function_exists('url') ? url('account') : '/account';
        $orgUrl = function_exists('url') ? url('back-office/organisation/parametres') : '/back-office/organisation/parametres';

        return [
            [
                'id' => self::MEDIA_REUPLOAD_ID,
                'kind' => 'info',
                'display_style' => AlertDisplayStyle::CLASSIC,
                'title' => 'Images à recharger',
                'body' => "Certaines images déposées sur le site (photo de profil, bannière, logos, illustrations) "
                    . "ont pu disparaître lors d’une mise à jour technique. "
                    . "Merci de les déposer à nouveau depuis votre compte ou les réglages de la communauté.",
                'cta_label' => 'Ouvrir mon compte',
                'cta_url' => $accountUrl,
                'coupon_code' => null,
                'starts_at' => null,
                'ends_at' => null,
                'sort_order' => -100,
                'is_active' => 1,
                'dismissible' => 1,
                'accent_color' => '#b45309',
                'icon_key' => 'alert',
                // Métadonnée locale (non persistée) : second lien pour les admins côté UI dédiée.
                '_ops_secondary_cta_label' => 'Logos de la communauté',
                '_ops_secondary_cta_url' => $orgUrl,
            ],
            [
                'id' => self::MEDIA_REUPLOAD_ID + 1,
                'kind' => 'info',
                'display_style' => AlertDisplayStyle::MINI_WARNING,
                'title' => 'Images à recharger',
                'body' => 'Des visuels ont pu être perdus lors d’une mise à jour. Merci de les déposer à nouveau depuis votre compte.',
                'cta_label' => 'Mon compte',
                'cta_url' => $accountUrl,
                'coupon_code' => null,
                'starts_at' => null,
                'ends_at' => null,
                'sort_order' => -100,
                'is_active' => 1,
                'dismissible' => 1,
                'accent_color' => '#b45309',
                'icon_key' => 'alert',
            ],
        ];
    }

    public static function isSyntheticPlatformId(int $alertId): bool
    {
        return $alertId === self::MEDIA_REUPLOAD_ID || $alertId === self::MEDIA_REUPLOAD_ID + 1;
    }
}
