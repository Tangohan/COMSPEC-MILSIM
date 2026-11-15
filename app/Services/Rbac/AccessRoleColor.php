<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Couleur d'un niveau d'accès : pastille prédéfinie (mêmes noms que la présentation des rôles) ou couleur libre #RRGGBB.
 * La couleur ne sert qu'à une pastille et un liseré : jamais de fond plein, le contraste du texte ne dépend pas d'elle.
 */
final class AccessRoleColor
{
    /** @return array<string, string> nom => #RRGGBB (teintes sobres, lisibles sur fond clair et sombre) */
    public static function presets(): array
    {
        return [
            'slate' => '#64748b',
            'blue' => '#2563eb',
            'indigo' => '#4f46e5',
            'emerald' => '#059669',
            'amber' => '#b45309',
            'red' => '#b91c1c',
            'purple' => '#7e22ce',
        ];
    }

    public static function isHex(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Valeur saisie (nom prédéfini, #RRGGBB ou vide) vers le style stocké ; null si la valeur est invalide.
     *
     * @return array{color?: string, hex?: string}|null [] = retirer la couleur
     */
    public static function normalize(string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }
        if (isset(self::presets()[strtolower($input)])) {
            return ['color' => strtolower($input)];
        }
        if (self::isHex($input)) {
            return ['hex' => strtolower($input)];
        }

        return null;
    }

    /**
     * Couleur d'affichage d'un niveau, ou null.
     *
     * @param array<string, mixed>|string|null $badgeStyle ligne roles.badge_style (JSON ou tableau)
     */
    public static function hexFor(array|string|null $badgeStyle): ?string
    {
        if (is_string($badgeStyle)) {
            $decoded = json_decode($badgeStyle, true);
            $badgeStyle = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($badgeStyle)) {
            return null;
        }
        $hex = (string) ($badgeStyle['hex'] ?? '');
        if (self::isHex($hex)) {
            return strtolower($hex);
        }

        return self::presets()[(string) ($badgeStyle['color'] ?? '')] ?? null;
    }

    /** Valeur à remettre dans le sélecteur : nom prédéfini, #RRGGBB ou vide. */
    public static function inputFor(array|string|null $badgeStyle): string
    {
        if (is_string($badgeStyle)) {
            $decoded = json_decode($badgeStyle, true);
            $badgeStyle = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($badgeStyle)) {
            return '';
        }
        $hex = (string) ($badgeStyle['hex'] ?? '');
        if (self::isHex($hex)) {
            return strtolower($hex);
        }
        $name = (string) ($badgeStyle['color'] ?? '');

        return isset(self::presets()[$name]) ? $name : '';
    }
}
