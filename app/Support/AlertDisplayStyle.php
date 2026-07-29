<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Styles d’affichage des annonces (bandeau classique vs barre sous le menu).
 */
final class AlertDisplayStyle
{
    public const CLASSIC = 'classic';
    public const MINI_INFO = 'mini_info';
    public const MINI_SUCCESS = 'mini_success';
    public const MINI_WARNING = 'mini_warning';
    public const MINI_DANGER = 'mini_danger';
    public const BREAKING = 'breaking';
    public const IMPORTANT = 'important';
    public const POPUP = 'popup';
    public const ACTIVITY_FEED = 'activity_feed';
    public const MEMBERS_ONLY = 'members_only';
    public const BACK_OFFICE = 'back_office';

    /** @var list<string> */
    private const PLATFORM_STYLES = [
        self::CLASSIC,
        self::MINI_INFO,
        self::MINI_SUCCESS,
        self::MINI_WARNING,
        self::MINI_DANGER,
        self::BREAKING,
        self::POPUP,
    ];

    /** @var list<string> */
    private const TENANT_STYLES = [
        self::CLASSIC,
        self::IMPORTANT,
        self::POPUP,
        self::ACTIVITY_FEED,
        self::MEMBERS_ONLY,
        self::BACK_OFFICE,
    ];

    /** @var list<string> */
    private const NAVBAR_STYLES = [
        self::MINI_INFO,
        self::MINI_SUCCESS,
        self::MINI_WARNING,
        self::MINI_DANGER,
        self::BREAKING,
        self::IMPORTANT,
    ];

    /**
     * @return array<string, string> value => libellé formulaire admin plateforme
     */
    public static function platformOptions(): array
    {
        return [
            self::CLASSIC => 'Bandeau classique (zone annonces)',
            self::MINI_INFO => 'Barre sous le menu — Information',
            self::MINI_SUCCESS => 'Barre sous le menu — Succès / confirmation',
            self::MINI_WARNING => 'Barre sous le menu — Attention',
            self::MINI_DANGER => 'Barre sous le menu — Critique',
            self::BREAKING => 'Bandeau Attention (défilement — maj / maintenance)',
            self::POPUP => 'Pop-up éphémère (fenêtre à l’arrivée sur le tableau de bord)',
        ];
    }

    /**
     * @return array<string, string> value => libellé formulaire communauté
     */
    public static function tenantOptions(): array
    {
        $meta = self::tenantOptionsWithMeta();

        return array_map(static fn (array $m): string => $m['label'], $meta);
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    public static function tenantOptionsWithMeta(): array
    {
        return [
            self::CLASSIC => [
                'label' => 'Bandeau classique',
                'hint' => 'Bandeau habituel dans la zone d’annonces du portail.',
            ],
            self::IMPORTANT => [
                'label' => 'Annonce importante',
                'hint' => 'Barre jaune pleine largeur sous le menu, pour les messages vraiment prioritaires.',
            ],
            self::POPUP => [
                'label' => 'Pop-up éphémère',
                'hint' => 'Fenêtre à l’arrivée sur le tableau de bord, affichée une fois par membre.',
            ],
            self::ACTIVITY_FEED => [
                'label' => 'Fil d’activité',
                'hint' => 'Message visible dans « Mon activité », avec les alertes et échanges récents.',
            ],
            self::MEMBERS_ONLY => [
                'label' => 'Espace membre uniquement',
                'hint' => 'Réservé aux membres connectés : masqué sur les pages publiques du portail.',
            ],
            self::BACK_OFFICE => [
                'label' => 'Back-office uniquement',
                'hint' => 'Visible des responsables dans le centre de pilotage, pas sur le portail membre.',
            ],
        ];
    }

    public static function sanitizePlatform(?string $raw): string
    {
        $v = trim((string) $raw);

        return in_array($v, self::PLATFORM_STYLES, true) ? $v : self::CLASSIC;
    }

    public static function sanitizeTenant(?string $raw): string
    {
        $v = trim((string) $raw);

        return in_array($v, self::TENANT_STYLES, true) ? $v : self::CLASSIC;
    }

    public static function normalize(?string $raw): string
    {
        $v = trim((string) $raw);
        if ($v === '' || $v === self::CLASSIC) {
            return self::CLASSIC;
        }
        if (in_array($v, self::NAVBAR_STYLES, true) || in_array($v, self::PLATFORM_STYLES, true)) {
            return $v;
        }

        return self::CLASSIC;
    }

    public static function isNavbarStyle(string $style): bool
    {
        return in_array(self::normalize($style), self::NAVBAR_STYLES, true);
    }

    public static function isPopupStyle(string $style): bool
    {
        return self::normalize($style) === self::POPUP;
    }

    public static function isClassicStyle(string $style): bool
    {
        $n = self::normalize($style);

        return !self::isNavbarStyle($n)
            && !self::isPopupStyle($n)
            && !self::isActivityFeedStyle($n)
            && !self::isBackOfficeStyle($n);
    }

    public static function isActivityFeedStyle(string $style): bool
    {
        return self::normalize($style) === self::ACTIVITY_FEED;
    }

    public static function isMembersOnlyStyle(string $style): bool
    {
        return self::normalize($style) === self::MEMBERS_ONLY;
    }

    public static function isBackOfficeStyle(string $style): bool
    {
        return self::normalize($style) === self::BACK_OFFICE;
    }

    /**
     * Emplacements visibles sur le portail membre (hors back-office et fil d’activité dédié).
     */
    public static function isPortalPlacement(string $style): bool
    {
        $n = self::normalize($style);

        return !self::isBackOfficeStyle($n) && !self::isActivityFeedStyle($n);
    }

    public static function label(string $style): string
    {
        $n = self::normalize($style);
        $tenant = self::tenantOptionsWithMeta();
        if (isset($tenant[$n])) {
            return $tenant[$n]['label'];
        }
        $platform = self::platformOptions();

        return $platform[$n] ?? 'Bandeau classique';
    }

    /** Libellé court du badge sur la mini-barre. */
    public static function miniTag(string $style): string
    {
        return match (self::normalize($style)) {
            self::MINI_SUCCESS => 'Succès',
            self::MINI_WARNING => 'Alerte',
            self::MINI_DANGER => 'Critique',
            self::BREAKING => 'Attention',
            self::IMPORTANT => 'Important',
            default => 'Info',
        };
    }

    /** Classe CSS tone pour mini-banner (info|success|warning|danger). */
    public static function miniToneClass(string $style): string
    {
        return match (self::normalize($style)) {
            self::MINI_SUCCESS => 'success',
            self::MINI_WARNING => 'warning',
            self::MINI_DANGER => 'danger',
            default => 'info',
        };
    }
}
