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

    /** @var list<string> */
    private const PLATFORM_STYLES = [
        self::CLASSIC,
        self::MINI_INFO,
        self::MINI_SUCCESS,
        self::MINI_WARNING,
        self::MINI_DANGER,
        self::BREAKING,
    ];

    /** @var list<string> */
    private const TENANT_STYLES = [
        self::CLASSIC,
        self::IMPORTANT,
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
            self::BREAKING => 'Bandeau Breaking (défilement — maj / maintenance)',
        ];
    }

    /**
     * @return array<string, string> value => libellé formulaire communauté
     */
    public static function tenantOptions(): array
    {
        return [
            self::CLASSIC => 'Bandeau classique',
            self::IMPORTANT => 'Annonce importante (barre jaune sous le menu)',
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

    public static function isClassicStyle(string $style): bool
    {
        return !self::isNavbarStyle($style);
    }

    public static function label(string $style): string
    {
        $all = self::platformOptions() + self::tenantOptions();

        return $all[self::normalize($style)] ?? 'Bandeau classique';
    }

    /** Libellé court du badge sur la mini-barre. */
    public static function miniTag(string $style): string
    {
        return match (self::normalize($style)) {
            self::MINI_SUCCESS => 'Succès',
            self::MINI_WARNING => 'Alerte',
            self::MINI_DANGER => 'Critique',
            self::BREAKING => 'Breaking',
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
