<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Toute page HTML du site (public, back-office, portail, ATAK web…) :
 *  - reçoit l'identifiant du déploiement (<meta name="athena-build">) et la fenêtre « Mise à jour disponible »
 *    (assets/js/app-version-check.js) si sa vue ne l'inclut pas déjà ;
 *  - voit ses CSS / JS locaux sans ?v= suffixés par ce même identifiant : nginx les sert « immutable » 7 jours,
 *    sans ce suffixe un navigateur garde l'ancien fichier après une mise à jour.
 */
final class SiteUpdateInjector
{
    public static function apply(string $html): string
    {
        $head = stripos($html, '</head>');
        if ($head === false || stripos($html, '<html') === false) {
            return $html;
        }
        $build = BuildVersion::id();
        $html = self::versionAssets($html, $build);
        if (str_contains($html, 'name="athena-build"')) {
            return $html;
        }
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $base = function_exists('url') ? rtrim((string) url(''), '/') : '';
        $locale = function_exists('locale') ? (locale() === 'en' ? 'en' : 'fr') : 'fr';
        $tags = '<meta name="athena-build" content="' . $h($build) . '"'
            . ' data-label="' . $h(BuildVersion::label()) . '"'
            . ' data-locale="' . $h($locale) . '"'
            . ' data-base="' . $h($base) . '"'
            . ' data-endpoint="' . $h($base . '/api/system/version') . '">';
        if (!str_contains($html, 'app-update-modal.css') && is_file(base_path('public/assets/css/app-update-modal.css'))) {
            $tags .= '<link rel="stylesheet" href="' . $h($base . '/assets/css/app-update-modal.css?v=' . rawurlencode($build)) . '">';
        }
        if (!str_contains($html, 'app-version-check.js') && is_file(base_path('public/assets/js/app-version-check.js'))) {
            $tags .= '<script defer src="' . $h($base . '/assets/js/app-version-check.js?v=' . rawurlencode($build)) . '"></script>';
        }
        $head = stripos($html, '</head>');

        return substr($html, 0, (int) $head) . $tags . substr($html, (int) $head);
    }

    /** Ajoute ?v=<build> aux <script src> / <link href> locaux .css/.js qui n'ont aucun paramètre. */
    public static function versionAssets(string $html, string $build): string
    {
        $base = function_exists('url') ? rtrim((string) url(''), '/') : '';
        $suffix = '?v=' . rawurlencode($build);

        return (string) preg_replace_callback(
            '~(\s(?:src|href)=)(["\'])([^"\'<>\s?#]*/assets/[^"\'<>\s?#]+\.(?:css|js))\2~i',
            static function (array $m) use ($base, $suffix): string {
                $url = $m[3];
                if (str_contains($url, '://') || str_starts_with($url, '//')) {
                    if ($base === '' || !str_starts_with($url, $base . '/')) {
                        return $m[0];
                    }
                }

                return $m[1] . $m[2] . $url . $suffix . $m[2];
            },
            $html
        );
    }
}
