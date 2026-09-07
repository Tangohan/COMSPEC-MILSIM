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
    public const USAGE_RECRUITMENT = 'recruitment';
    public const USAGE_INSTRUCTOR = 'instructor';
    public const USAGE_ZEUS = 'zeus';
    public const USAGE_LOGISTICS = 'logistics';
    public const USAGE_ADMIN = 'admin';
    public const USAGE_OTHER = 'other';

    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_RARELY = 'rarely';

    public const FRICTION_NAVIGATION = 'navigation';
    public const FRICTION_PERSONNEL = 'personnel';
    public const FRICTION_TRAINING = 'training';
    public const FRICTION_OPERATIONS = 'operations';
    public const FRICTION_ATAK = 'atak';
    public const FRICTION_ACCOUNT = 'account';
    public const FRICTION_TRANSLATION = 'translation';
    public const FRICTION_OTHER = 'other';

    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_BOTH = 'both';

    public const AREA_NAVIGATION = 'navigation';
    public const AREA_PUBLIC = 'public_pages';
    public const AREA_ACCOUNT = 'account';
    public const AREA_ERRORS = 'errors';
    public const AREA_LEGAL = 'legal';
    public const AREA_TRAINING = 'training';
    public const AREA_PERSONNEL = 'personnel';
    public const AREA_OPERATIONS = 'operations';
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
            self::USAGE_RECRUITMENT,
            self::USAGE_INSTRUCTOR,
            self::USAGE_ZEUS,
            self::USAGE_LOGISTICS,
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
            self::USAGE_RECRUITMENT => 'Recrutement',
            self::USAGE_INSTRUCTOR => 'Formation et instruction',
            self::USAGE_ZEUS => 'Zeus',
            self::USAGE_LOGISTICS => 'Logistique et matériel',
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
    public static function frequencyKinds(): array
    {
        return [
            self::FREQ_DAILY,
            self::FREQ_WEEKLY,
            self::FREQ_MONTHLY,
            self::FREQ_RARELY,
        ];
    }

    public static function frequencyLabel(string $kind): string
    {
        return match ($kind) {
            self::FREQ_DAILY => 'Plusieurs fois par semaine',
            self::FREQ_WEEKLY => 'Chaque semaine',
            self::FREQ_MONTHLY => 'Quelques fois par mois',
            self::FREQ_RARELY => 'Rarement',
            default => 'Non précisé',
        };
    }

    /** @return array<string, string> */
    public static function frequencyOptions(): array
    {
        $out = [];
        foreach (self::frequencyKinds() as $kind) {
            $out[$kind] = self::frequencyLabel($kind);
        }

        return $out;
    }

    /** @return list<string> */
    public static function frictionKinds(): array
    {
        return [
            self::FRICTION_NAVIGATION,
            self::FRICTION_PERSONNEL,
            self::FRICTION_TRAINING,
            self::FRICTION_OPERATIONS,
            self::FRICTION_ATAK,
            self::FRICTION_ACCOUNT,
            self::FRICTION_TRANSLATION,
            self::FRICTION_OTHER,
        ];
    }

    public static function frictionLabel(string $kind): string
    {
        return match ($kind) {
            self::FRICTION_NAVIGATION => 'Menus et orientation',
            self::FRICTION_PERSONNEL => 'Fiche et effectifs',
            self::FRICTION_TRAINING => 'Formations',
            self::FRICTION_OPERATIONS => 'Manœuvres et opérations',
            self::FRICTION_ATAK => 'ATAK et cartographie',
            self::FRICTION_ACCOUNT => 'Compte et connexion',
            self::FRICTION_TRANSLATION => 'Traduction et langue',
            default => 'Autre',
        };
    }

    /** @return array<string, string> */
    public static function frictionOptions(): array
    {
        $out = [];
        foreach (self::frictionKinds() as $kind) {
            $out[$kind] = self::frictionLabel($kind);
        }

        return $out;
    }

    /** @return list<string> */
    public static function deviceKinds(): array
    {
        return [
            self::DEVICE_DESKTOP,
            self::DEVICE_MOBILE,
            self::DEVICE_BOTH,
        ];
    }

    public static function deviceLabel(string $kind): string
    {
        return match ($kind) {
            self::DEVICE_DESKTOP => 'Ordinateur',
            self::DEVICE_MOBILE => 'Téléphone ou tablette',
            self::DEVICE_BOTH => 'Les deux',
            default => 'Non précisé',
        };
    }

    /** @return array<string, string> */
    public static function deviceOptions(): array
    {
        $out = [];
        foreach (self::deviceKinds() as $kind) {
            $out[$kind] = self::deviceLabel($kind);
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
            self::AREA_TRAINING,
            self::AREA_PERSONNEL,
            self::AREA_OPERATIONS,
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
            self::AREA_TRAINING => 'Formations',
            self::AREA_PERSONNEL => 'Fiche et effectifs',
            self::AREA_OPERATIONS => 'Manœuvres et opérations',
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

    public static function normalizeFrequency(string $kind): string
    {
        return in_array($kind, self::frequencyKinds(), true) ? $kind : '';
    }

    public static function normalizeFriction(string $kind): string
    {
        return in_array($kind, self::frictionKinds(), true) ? $kind : '';
    }

    public static function normalizeDevice(string $kind): string
    {
        return in_array($kind, self::deviceKinds(), true) ? $kind : '';
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

    public static function normalizeClarity(?int $score): ?int
    {
        if ($score === null) {
            return null;
        }
        if ($score < 1 || $score > 5) {
            return null;
        }

        return $score;
    }
}
