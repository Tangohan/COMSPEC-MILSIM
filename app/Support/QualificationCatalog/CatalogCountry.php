<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

/**
 * Pays des catalogues de référence (qualifications et émetteurs) : petit drapeau dessiné (les drapeaux emoji ne
 * s’affichent pas sous Windows), nom du pays dans sa propre langue et nom de cette langue.
 */
final class CatalogCountry
{
    /** Pays => [nom dans la langue du pays, langue officielle (fr), langue officielle (en)]. */
    public const NATIVE = [
        'US' => ['United States', 'anglais', 'English'],
        'FR' => ['France', 'français', 'French'],
        'DE' => ['Deutschland', 'allemand', 'German'],
        'CN' => ['中国', 'chinois', 'Chinese'],
        'RU' => ['Россия', 'russe', 'Russian'],
        'MY' => ['Malaysia', 'malais', 'Malay'],
        'NATO' => ['NATO · OTAN', 'anglais et français', 'English and French'],
    ];

    public static function native(string $country): string
    {
        return self::NATIVE[$country][0] ?? '';
    }

    public static function language(string $country, string $lang): string
    {
        return self::NATIVE[$country][QualificationCatalog::lang($lang) === 'en' ? 2 : 1] ?? '';
    }

    /** Drapeau en SVG (décoratif : le nom du pays est toujours écrit à côté). */
    public static function flag(string $country, string $class = 'qcat-flag'): string
    {
        $body = match ($country) {
            'FR' => '<rect width="10" height="20" fill="#0055a4"/><rect x="10" width="10" height="20" fill="#fff"/><rect x="20" width="10" height="20" fill="#ef4135"/>',
            'DE' => '<rect width="30" height="7" fill="#000"/><rect y="6.67" width="30" height="6.67" fill="#dd0000"/><rect y="13.33" width="30" height="6.67" fill="#ffce00"/>',
            'RU' => '<rect width="30" height="7" fill="#fff"/><rect y="6.67" width="30" height="6.67" fill="#0039a6"/><rect y="13.33" width="30" height="6.67" fill="#d52b1e"/>',
            'MY' => '<rect width="30" height="20" fill="#fff"/><path stroke="#cc0001" stroke-width="1.43" d="M0 .71h30M0 3.57h30M0 6.43h30M0 9.29h30M0 12.14h30M0 15h30M0 17.86h30"/><rect width="15" height="11.43" fill="#010066"/><circle cx="5.6" cy="5.71" r="4" fill="#fc0"/><circle cx="6.7" cy="5.71" r="3.4" fill="#010066"/><path fill="#fc0" d="M10.90 2.51L11.23 4.25L12.29 2.83L11.84 4.54L13.40 3.71L12.25 5.06L14.02 5.00L12.40 5.71L14.02 6.42L12.25 6.36L13.40 7.71L11.84 6.88L12.29 8.59L11.23 7.17L10.90 8.91L10.57 7.17L9.51 8.59L9.96 6.88L8.40 7.71L9.55 6.36L7.78 6.42L9.40 5.71L7.78 5.00L9.55 5.06L8.40 3.71L9.96 4.54L9.51 2.83L10.57 4.25z"/>',
            'CN' => '<rect width="30" height="20" fill="#de2910"/><path fill="#ffde00" d="m5 2.2 1 3h3.1l-2.5 1.8 1 3L5 8.2 2.4 10l1-3L.9 5.2H4z"/><circle cx="10.5" cy="2.5" r=".9" fill="#ffde00"/><circle cx="12.3" cy="4.6" r=".9" fill="#ffde00"/><circle cx="12.3" cy="7.4" r=".9" fill="#ffde00"/><circle cx="10.5" cy="9.4" r=".9" fill="#ffde00"/>',
            'US' => '<rect width="30" height="20" fill="#fff"/><path stroke="#b22234" stroke-width="1.54" d="M0 .77h30M0 3.85h30M0 6.92h30M0 10h30M0 13.08h30M0 16.15h30M0 19.23h30"/><rect width="12" height="10.77" fill="#3c3b6e"/><g fill="#fff"><circle cx="2" cy="2" r=".6"/><circle cx="6" cy="2" r=".6"/><circle cx="10" cy="2" r=".6"/><circle cx="4" cy="4.6" r=".6"/><circle cx="8" cy="4.6" r=".6"/><circle cx="2" cy="7.2" r=".6"/><circle cx="6" cy="7.2" r=".6"/><circle cx="10" cy="7.2" r=".6"/><circle cx="4" cy="9.6" r=".6"/><circle cx="8" cy="9.6" r=".6"/></g>',
            'NATO' => '<rect width="30" height="20" fill="#004990"/><g fill="#fff"><path d="M15 3.2 16 9h-2zM15 16.8 14 11h2zM9.2 10 15 9v2zM20.8 10 15 11V9z"/></g><circle cx="15" cy="10" r="4.2" fill="none" stroke="#fff" stroke-width=".7"/>',
            default => '<rect width="30" height="20" fill="#8a94a6"/>',
        };

        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 30 20" width="21" height="14" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}
