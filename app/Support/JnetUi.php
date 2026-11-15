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
     * Logo d'unité, sans écusson ni cadre : l'emblème téléversé seul ; à défaut, le monogramme en lettres de la
     * couleur de l'unité (--u ou --jn-unit, posée par le parent).
     */
    public static function crest(string $monogram, ?string $emblem, string $class = 'ja-crest'): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $emblem = $emblem !== null ? trim($emblem) : '';
        $inner = $emblem !== ''
            ? '<img src="' . $esc($emblem) . '" alt="" loading="lazy">'
            : '<b>' . $esc($monogram !== '' ? $monogram : 'U') . '</b>';

        return '<span class="' . $esc($class) . ($emblem !== '' ? ' has-img' : '') . '" aria-hidden="true">' . $inner . '</span>';
    }

    /** Icônes de l'en-tête (traits de 1,8 px, couleur du texte). */
    public static function icon(string $name): string
    {
        $paths = [
            'switch' => '<path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/>',
            'extranet' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'home' => '<path d="M4 11 12 4l8 7v9h-5v-6H9v6H4z"/>',
            'manage' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>',
            'logout' => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        ];

        return '<svg class="ja-ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
    }

    /**
     * Pastille du compte connecté (portrait ou initiales) : un clic ramène sur « Mon compte ». Le portrait est celui
     * de la fiche ; sans portrait, les initiales du nom affiché. Aucune erreur de lecture ne casse l'en-tête.
     */
    public static function account(string $name): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $photo = '';
        try {
            $uid = (int) \App\Core\Session::get('user_id');
            if ($uid > 0) {
                $profile = \App\Core\Container::get(\App\Repositories\PersonnelProfileRepository::class)->getByUserId($uid);
                $path = is_array($profile) ? trim((string) ($profile['character_portrait_path'] ?? '')) : '';
                if ($path !== '' && function_exists('user_media_public_url')) {
                    $photo = (string) user_media_public_url($path);
                }
            }
        } catch (\Throwable) {
            $photo = '';
        }
        $name = trim($name);
        $parts = preg_split('/[\s._-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        $face = $photo !== '' ? '<img src="' . $esc($photo) . '" alt="">' : '<span>' . $esc($initials !== '' ? $initials : '·') . '</span>';
        $label = $name !== '' ? 'Mon compte (' . $name . ')' : 'Mon compte';

        return '<a class="ja-me" href="' . $esc(function_exists('url') ? url('account') : 'account') . '" title="' . $esc($label) . '" aria-label="' . $esc($label) . '">'
            . '<span class="ja-me__face">' . $face . '</span>'
            . ($name !== '' ? '<span class="ja-me__name" translate="no">' . $esc($name) . '</span>' : '')
            . '</a>';
    }

    /**
     * Fil d'Ariane des pages profondes : « Mon extranet › Unité › Page ». Chaque élément est [libellé, lien] ; le dernier
     * (la page où l'on se trouve) n'a pas de lien. Un élément sans libellé est ignoré.
     *
     * @param list<array{0: string, 1: string|null}> $items
     */
    public static function crumbs(array $items): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $items = array_values(array_filter($items, static fn (array $i): bool => trim((string) ($i[0] ?? '')) !== ''));
        if ($items === []) {
            return '';
        }
        $last = count($items) - 1;
        $out = '<nav class="ja-crumbs" aria-label="Fil d’Ariane"><ol>';
        foreach ($items as $i => $item) {
            $label = trim((string) $item[0]);
            $href = $i < $last ? trim((string) ($item[1] ?? '')) : '';
            $out .= '<li>' . ($href !== '' ? '<a href="' . $esc($href) . '">' . $esc($label) . '</a>' : '<span aria-current="page">' . $esc($label) . '</span>') . '</li>';
        }

        return $out . '</ol></nav>';
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
