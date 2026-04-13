<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modes de confidentialité ORBAT par unité (stockage BDD + logique d’affichage).
 */
final class OrbatMaskMode
{
    public const NONE = 'none';
    public const HIDDEN_ALL = 'hidden_all';
    public const SCOPE_SECTION = 'scope_section';
    public const SCOPE_TEAM = 'scope_team';
    public const SCOPE_ROLE = 'scope_role';
    public const ANONYMIZE = 'anonymize';

    /** @var list<string> */
    public const ALL = [
        self::NONE,
        self::HIDDEN_ALL,
        self::SCOPE_SECTION,
        self::SCOPE_TEAM,
        self::SCOPE_ROLE,
        self::ANONYMIZE,
    ];

    public static function normalize(?string $raw): string
    {
        $t = strtolower(trim((string) $raw));
        if ($t === '' || !in_array($t, self::ALL, true)) {
            return self::NONE;
        }

        return $t;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}
