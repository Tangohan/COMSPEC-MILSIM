<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier de l’avis plateforme et des propositions de traduction.
 */
final class PlatformReviewCatalog
{
    public const USAGE_OPERATOR = 'operator';
    public const USAGE_COMMAND = 'command';
    public const USAGE_PERSONNEL = 'personnel';
    public const USAGE_ZEUS = 'zeus';
    public const USAGE_ADMIN = 'admin';
    public const USAGE_OTHER = 'other';

    public const AREA_NAVIGATION = 'navigation';
    public const AREA_PUBLIC = 'public_pages';
    public const AREA_ACCOUNT = 'account';
    public const AREA_ERRORS = 'errors';
    public const AREA_LEGAL = 'legal';
    public const AREA_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    public const SNOOZE_DAYS = 21;
    public const MAX_PENDING_TRANSLATIONS = 15;

    /** @return list<string> */
    public static function usageKinds(): array
    {
        return [
            self::USAGE_OPERATOR,
            self::USAGE_COMMAND,
            self::USAGE_PERSONNEL,
            self::USAGE_ZEUS,
            self::USAGE_ADMIN,
            self::USAGE_OTHER,
        ];
    }

    public static function usageLabel(string $kind): string
    {
        return match ($kind) {
            self::USAGE_OPERATOR => 'Opérateur en jeu',
            self::USAGE_COMMAND => 'Commandement',
            self::USAGE_PERSONNEL => 'Ressources humaines',
            self::USAGE_ZEUS => 'Zeus',
            self::USAGE_ADMIN => 'Administration',
            default => 'Autre',
        };
    }

    /** @return array<string, string> */
    public static function usageOptions(): array
    {
        $out = [];
        foreach (self::usageKinds() as $kind) {
            $out[$kind] = self::usageLabel($kind);
        }

        return $out;
    }

    /** @return list<string> */
    public static function translationAreas(): array
    {
        return [
            self::AREA_NAVIGATION,
            self::AREA_PUBLIC,
            self::AREA_ACCOUNT,
            self::AREA_ERRORS,
            self::AREA_LEGAL,
            self::AREA_OTHER,
        ];
    }

    public static function areaLabel(string $area): string
    {
        return match ($area) {
            self::AREA_NAVIGATION => 'Menus et boutons',
            self::AREA_PUBLIC => 'Pages publiques',
            self::AREA_ACCOUNT => 'Compte et connexion',
            self::AREA_ERRORS => 'Messages d’erreur',
            self::AREA_LEGAL => 'Mentions légales',
            default => 'Autre',
        };
    }

    /** @return array<string, string> */
    public static function areaOptions(): array
    {
        $out = [];
        foreach (self::translationAreas() as $area) {
            $out[$area] = self::areaLabel($area);
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function localeOptions(): array
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
        ];
    }

    public static function localeLabel(string $locale): string
    {
        $opts = self::localeOptions();

        return $opts[$locale] ?? $locale;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACCEPTED => 'Reprise',
            self::STATUS_DECLINED => 'Non retenue',
            default => 'En attente',
        };
    }

    public static function normalizeUsage(string $kind): string
    {
        return in_array($kind, self::usageKinds(), true) ? $kind : self::USAGE_OTHER;
    }

    public static function normalizeArea(string $area): string
    {
        return in_array($area, self::translationAreas(), true) ? $area : self::AREA_OTHER;
    }

    public static function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return array_key_exists($locale, self::localeOptions()) ? $locale : 'en';
    }

    public static function normalizeStatus(string $status): string
    {
        return in_array($status, [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_DECLINED], true)
            ? $status
            : self::STATUS_PENDING;
    }
}
