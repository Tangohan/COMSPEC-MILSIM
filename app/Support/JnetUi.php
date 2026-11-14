<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Petits éléments d'interface communs aux écrans JNET : insigne d'unité, photo de fond générique, libellés d'échelon et
 * de fonction lisibles (jamais le code brut de l'ORBAT).
 */
final class JnetUi
{
    /**
     * Photos de groupe en opération servies au survol d'une unité sans bannière. L'attribution est stable par unité.
     *
     * @var list<string>
     */
    public const GENERIC_PHOTOS = [
        'assets/images/fog-team.jpg',
        'assets/images/night-team.jpg',
        'assets/images/hero-explosion.jpg',
        'assets/images/login-accueil-nvg-forest.jpg',
    ];

    /** Chemin (relatif à public/) de la photo générique d'une unité : toujours la même pour un même identifiant. */
    public static function genericPhoto(int $unitId): string
    {
        $n = count(self::GENERIC_PHOTOS);

        return self::GENERIC_PHOTOS[(($unitId % $n) + $n) % $n];
    }

    /**
     * Insigne d'unité : écusson à la couleur de l'unité ; l'emblème téléversé (ou, à défaut, le monogramme) est cadré
     * dedans. La couleur vient de --u (ou --jn-unit), posée par le parent.
     */
    public static function crest(string $monogram, ?string $emblem, string $class = 'ja-crest'): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $emblem = $emblem !== null ? trim($emblem) : '';
        $inner = $emblem !== ''
            ? '<img src="' . $esc($emblem) . '" alt="" loading="lazy">'
            : '<b>' . $esc($monogram !== '' ? $monogram : 'U') . '</b>';

        return '<span class="' . $esc($class) . ($emblem !== '' ? ' has-img' : '') . '" aria-hidden="true">'
            . '<svg viewBox="0 0 48 56" focusable="false"><path class="ja-crest__body" d="M24 2.5 44.5 9.5V27c0 13.6-8.6 23.4-20.5 26.5C12.1 50.4 3.5 40.6 3.5 27V9.5z"/>'
            . '<path class="ja-crest__line" d="M24 7.5 40 13v14c0 10.8-6.6 18.6-16 21.3C14.6 45.6 8 37.8 8 27V13z"/></svg>'
            . $inner . '</span>';
    }

    /** « Demi-section (section OTAN) » devient « Demi-section », « Équipe / équipage » devient « Équipe ». */
    public static function shortLabel(string $label): string
    {
        $label = trim($label);
        $cut = preg_split('/\s+\/\s+|\s+\(/u', $label, 2);

        return trim((string) ($cut[0] ?? $label));
    }

    /** Échelon lisible (« Compagnie »), vide si la clé est inconnue. */
    public static function echelonLabel(string $key): string
    {
        $meta = UnitMilitarySymbol::ECHELONS[$key] ?? null;

        return $meta === null ? '' : self::shortLabel((string) $meta[2]);
    }

    /** Fonction lisible (« Infanterie légère »), vide si la clé est inconnue. */
    public static function functionLabel(string $key): string
    {
        $meta = UnitMilitarySymbol::FUNCTIONS[$key] ?? null;

        return $meta === null ? '' : self::shortLabel((string) $meta[2]);
    }

    /** Pluriel simple d'un nom français : « 1 membre », « 12 membres ». */
    public static function count(int $n, string $one, ?string $many = null): string
    {
        return $n . ' ' . ($n > 1 ? ($many ?? $one . 's') : $one);
    }
}
