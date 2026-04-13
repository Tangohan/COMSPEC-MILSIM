<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Types d’affichage sur l’organigramme ORBAT (prédéfinis + slugs personnalisés par communauté).
 */
final class OrbatChartDisplay
{
    /** @var list<string> */
    public const BUILTIN_SLUGS = ['command', 'alpha', 'bravo', 'support', 'special'];

    public static function sanitizeSlug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9_-]+/', '-', $s) ?? '';
        $s = trim((string) $s, '-');

        return mb_substr($s, 0, 64);
    }

    public static function slugFromLabel(string $label): string
    {
        $base = self::sanitizeSlug(str_replace(['é', 'è', 'ê', 'à', 'ù', 'ô', 'î', 'ç'], ['e', 'e', 'e', 'a', 'u', 'o', 'i', 'c'], mb_strtolower($label)));
        if ($base === '') {
            $base = 'type-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        return $base;
    }

    /** @return list<array{id: string, label: string, builtin: bool}> */
    public static function builtinOptionsForUi(): array
    {
        return [
            ['id' => 'command', 'label' => 'Commandement', 'builtin' => true],
            ['id' => 'alpha', 'label' => 'Alpha', 'builtin' => true],
            ['id' => 'bravo', 'label' => 'Bravo', 'builtin' => true],
            ['id' => 'support', 'label' => 'Soutien', 'builtin' => true],
            ['id' => 'special', 'label' => 'Spécial', 'builtin' => true],
        ];
    }
}
