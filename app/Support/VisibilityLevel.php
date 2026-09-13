<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Niveaux de confidentialité métier (personnels, unités, affectations, qualifications).
 * Indépendants du statut administratif.
 */
final class VisibilityLevel
{
    public const NORMAL = 'normal';
    public const ANONYMIZED = 'anonymized';
    public const RESTRICTED = 'restricted';
    public const HIDDEN = 'hidden';

    /** @var list<string> */
    public const ALL = [
        self::NORMAL,
        self::ANONYMIZED,
        self::RESTRICTED,
        self::HIDDEN,
    ];

    public static function normalize(?string $raw): string
    {
        $t = strtolower(trim((string) $raw));
        // Alias FR / legacy
        $aliases = [
            'visible' => self::NORMAL,
            'none' => self::NORMAL,
            'normale' => self::NORMAL,
            'anonymise' => self::ANONYMIZED,
            'anonymisé' => self::ANONYMIZED,
            'anonymize' => self::ANONYMIZED,
            'restreinte' => self::RESTRICTED,
            'restreint' => self::RESTRICTED,
            'commandement' => self::RESTRICTED,
            'command' => self::RESTRICTED,
            'masquee' => self::HIDDEN,
            'masquée' => self::HIDDEN,
            'masque' => self::HIDDEN,
            'masked' => self::HIDDEN,
            'hidden_all' => self::HIDDEN,
            'non_visible' => self::HIDDEN,
            'scope_section' => self::RESTRICTED,
            'scope_team' => self::RESTRICTED,
            'scope_role' => self::RESTRICTED,
        ];
        if (isset($aliases[$t])) {
            return $aliases[$t];
        }
        if (!in_array($t, self::ALL, true)) {
            return self::NORMAL;
        }

        return $t;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }

    public static function label(string $level): string
    {
        return match (self::normalize($level)) {
            self::ANONYMIZED => 'Anonymisée',
            self::RESTRICTED => 'Restreinte',
            self::HIDDEN => 'Masquée',
            default => 'Normale',
        };
    }

    public static function consequence(string $level, string $subject = 'unit'): string
    {
        $level = self::normalize($level);
        if ($subject === 'personnel') {
            return match ($level) {
                self::ANONYMIZED => 'Le personnel apparaît comme occupant un poste, sans identité nominative pour les utilisateurs non autorisés.',
                self::RESTRICTED => 'L’identité peut rester visible, mais l’affectation exacte et certaines données sont masquées.',
                self::HIDDEN => 'Le personnel est totalement absent des annuaires, recherches, listes et exports pour les utilisateurs non autorisés.',
                default => 'Affichage normal dans l’annuaire, l’ORBAT et les recherches.',
            };
        }

        return match ($level) {
            self::ANONYMIZED => 'La structure apparaît sous un libellé générique (« Unité restreinte ») pour les utilisateurs non autorisés.',
            self::RESTRICTED => 'La structure reste visible avec des informations limitées ; le détail et les effectifs nominatifs sont protégés.',
            self::HIDDEN => 'Cette unité et ses sous-structures (sauf dérogation) ne seront pas visibles par les utilisateurs sans autorisation.',
            default => 'Structure affichée normalement dans l’ORBAT, l’annuaire et les sélecteurs.',
        };
    }

    /**
     * Convertit un OrbatMaskMode legacy vers VisibilityLevel.
     */
    public static function fromOrbatMaskMode(?string $mask): string
    {
        $mask = OrbatMaskMode::normalize($mask);

        return match ($mask) {
            OrbatMaskMode::HIDDEN_ALL => self::HIDDEN,
            OrbatMaskMode::ANONYMIZE => self::ANONYMIZED,
            OrbatMaskMode::SCOPE_SECTION,
            OrbatMaskMode::SCOPE_TEAM,
            OrbatMaskMode::SCOPE_ROLE => self::RESTRICTED,
            default => self::NORMAL,
        };
    }

    /**
     * Convertit VisibilityLevel vers OrbatMaskMode pour compatibilité stockage/legacy.
     */
    public static function toOrbatMaskMode(string $level): string
    {
        return match (self::normalize($level)) {
            self::HIDDEN => OrbatMaskMode::HIDDEN_ALL,
            self::ANONYMIZED => OrbatMaskMode::ANONYMIZE,
            self::RESTRICTED => OrbatMaskMode::SCOPE_SECTION,
            default => OrbatMaskMode::NONE,
        };
    }

    /**
     * Sévérité relative (plus élevé = plus secret).
     */
    public static function severity(string $level): int
    {
        return match (self::normalize($level)) {
            self::HIDDEN => 40,
            self::RESTRICTED => 30,
            self::ANONYMIZED => 20,
            default => 10,
        };
    }

    /**
     * Combine deux niveaux : conserve le plus restrictif.
     */
    public static function mostRestrictive(string $a, string $b): string
    {
        return self::severity(self::normalize($a)) >= self::severity(self::normalize($b))
            ? self::normalize($a)
            : self::normalize($b);
    }
}
