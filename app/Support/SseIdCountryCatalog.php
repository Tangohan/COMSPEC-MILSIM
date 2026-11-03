<?php

declare(strict_types=1);

namespace App\Support;

/**
 * COMSPEC IDENTITY — règles des pays émetteurs créés par une communauté.
 *
 * Un pays de jeu est toujours fictif. Son code à trois lettres ne doit pas être celui d'un pays réel
 * (ISO 3166-1 alpha-3) ni un code réservé par l'OACI pour les documents de voyage, sauf dans un univers
 * imaginaire (SCP, Star Wars…) où le code ne prête pas à confusion avec un vrai papier.
 * Exception : les pays réels STYLISÉS du jeu proposé à l'import (SseIdRealCountryCatalog), réservés à l'univers
 * « Réaliste — simulation » choisi par la communauté ; ils ne se créent pas à la main.
 */
final class SseIdCountryCatalog
{
    /** Univers du pays (les thèmes « réalistes » refusent les codes de pays réels). */
    public const THEMES = [
        'contemporary' => 'Contemporain',
        'historical' => 'Historique',
        'near_future' => 'Futur proche',
        'scifi' => 'Science-fiction',
        'scp' => 'SCP',
        'star_wars' => 'Star Wars',
        'warhammer' => 'Warhammer 40K',
        'fantasy' => 'Fantastique',
        'other' => 'Autre univers',
        'realistic_sim' => 'Réaliste — simulation (pays réels stylisés)',
    ];

    /** Thèmes où un code de pays réel est refusé (risque de confusion avec un vrai papier). */
    public const REALISTIC_THEMES = ['contemporary', 'historical', 'near_future'];

    /** Langues dans lesquelles le papier est imprimé. */
    public const LANGUAGES = [
        'fr' => 'Français',
        'en' => 'Anglais',
        'de' => 'Allemand',
        'es' => 'Espagnol',
        'it' => 'Italien',
        'pt' => 'Portugais',
        'el' => 'Grec',
        'pl' => 'Polonais',
        'ru' => 'Russe',
        'ar' => 'Arabe',
        'fa' => 'Persan',
        'zh' => 'Chinois',
        'nl' => 'Néerlandais',
        'uk' => 'Ukrainien',
        'ps' => 'Pachto',
        'other' => 'Langue imaginaire',
    ];

    /** Réservoirs de noms du bouton « Générer ». */
    public const REGIONS = [
        '' => 'Noms génériques',
        'ALTIS' => 'Méditerranée (Altis)',
        'TANOA' => 'Pacifique (Tanoa)',
        'LIVONIA' => 'Europe de l’Est (Livonie)',
        'MALDEN' => 'Europe de l’Ouest (Malden)',
        'LEVANT' => 'Proche-Orient',
        'AFRICA_SAHEL' => 'Sahel',
        'RUSSIA' => 'Russie',
        'UK' => 'Royaume-Uni (anglais)',
        'USA' => 'États-Unis (anglais)',
        'GREECE' => 'Grèce (grec)',
        'GERMANY' => 'Allemagne (allemand)',
        'AUSTRIA' => 'Autriche (allemand)',
        'COLOMBIA' => 'Colombie (espagnol)',
        'FRANCE' => 'France (français)',
        'BELGIUM' => 'Belgique (français, néerlandais)',
        'SPAIN' => 'Espagne (espagnol)',
        'POLAND' => 'Pologne (polonais)',
        'UKRAINE' => 'Ukraine (ukrainien)',
        'RUSSIA_ID' => 'Russie (russe, noms au féminin)',
        'IRAQ_ID' => 'Irak (arabe)',
        'SYRIA_ID' => 'Syrie (arabe)',
        'AFGHANISTAN' => 'Afghanistan (dari, pachto)',
    ];

    public const VALIDITY_MIN = 1;
    public const VALIDITY_MAX = 50;

    /** Codes réservés par l'OACI (apatride, réfugié, organisations) : toujours refusés. */
    public const ICAO_RESERVED = ['XXA', 'XXB', 'XXC', 'XXX', 'XOM', 'XPO', 'XES', 'XCC', 'XIM', 'XBA', 'UNO', 'UNA', 'UNK', 'EUE', 'GBD', 'GBN', 'GBO', 'GBP', 'GBS', 'RKS'];

    /** ISO 3166-1 alpha-3 : pays et territoires réels. */
    public const ISO_ALPHA3 = [
        'ABW', 'AFG', 'AGO', 'AIA', 'ALA', 'ALB', 'AND', 'ARE', 'ARG', 'ARM', 'ASM', 'ATA', 'ATF', 'ATG', 'AUS', 'AUT', 'AZE',
        'BDI', 'BEL', 'BEN', 'BES', 'BFA', 'BGD', 'BGR', 'BHR', 'BHS', 'BIH', 'BLM', 'BLR', 'BLZ', 'BMU', 'BOL', 'BRA', 'BRB',
        'BRN', 'BTN', 'BVT', 'BWA', 'CAF', 'CAN', 'CCK', 'CHE', 'CHL', 'CHN', 'CIV', 'CMR', 'COD', 'COG', 'COK', 'COL', 'COM',
        'CPV', 'CRI', 'CUB', 'CUW', 'CXR', 'CYM', 'CYP', 'CZE', 'DEU', 'DJI', 'DMA', 'DNK', 'DOM', 'DZA', 'ECU', 'EGY', 'ERI',
        'ESH', 'ESP', 'EST', 'ETH', 'FIN', 'FJI', 'FLK', 'FRA', 'FRO', 'FSM', 'GAB', 'GBR', 'GEO', 'GGY', 'GHA', 'GIB', 'GIN',
        'GLP', 'GMB', 'GNB', 'GNQ', 'GRC', 'GRD', 'GRL', 'GTM', 'GUF', 'GUM', 'GUY', 'HKG', 'HMD', 'HND', 'HRV', 'HTI', 'HUN',
        'IDN', 'IMN', 'IND', 'IOT', 'IRL', 'IRN', 'IRQ', 'ISL', 'ISR', 'ITA', 'JAM', 'JEY', 'JOR', 'JPN', 'KAZ', 'KEN', 'KGZ',
        'KHM', 'KIR', 'KNA', 'KOR', 'KWT', 'LAO', 'LBN', 'LBR', 'LBY', 'LCA', 'LIE', 'LKA', 'LSO', 'LTU', 'LUX', 'LVA', 'MAC',
        'MAF', 'MAR', 'MCO', 'MDA', 'MDG', 'MDV', 'MEX', 'MHL', 'MKD', 'MLI', 'MLT', 'MMR', 'MNE', 'MNG', 'MNP', 'MOZ', 'MRT',
        'MSR', 'MTQ', 'MUS', 'MWI', 'MYS', 'MYT', 'NAM', 'NCL', 'NER', 'NFK', 'NGA', 'NIC', 'NIU', 'NLD', 'NOR', 'NPL', 'NRU',
        'NZL', 'OMN', 'PAK', 'PAN', 'PCN', 'PER', 'PHL', 'PLW', 'PNG', 'POL', 'PRI', 'PRK', 'PRT', 'PRY', 'PSE', 'PYF', 'QAT',
        'REU', 'ROU', 'RUS', 'RWA', 'SAU', 'SDN', 'SEN', 'SGP', 'SGS', 'SHN', 'SJM', 'SLB', 'SLE', 'SLV', 'SMR', 'SOM', 'SPM',
        'SRB', 'SSD', 'STP', 'SUR', 'SVK', 'SVN', 'SWE', 'SWZ', 'SXM', 'SYC', 'SYR', 'TCA', 'TCD', 'TGO', 'THA', 'TJK', 'TKL',
        'TKM', 'TLS', 'TON', 'TTO', 'TUN', 'TUR', 'TUV', 'TWN', 'TZA', 'UGA', 'UKR', 'UMI', 'URY', 'USA', 'UZB', 'VAT', 'VCT',
        'VEN', 'VGB', 'VIR', 'VNM', 'VUT', 'WLF', 'WSM', 'YEM', 'ZAF', 'ZMB', 'ZWE',
    ];

    public static function isRealCode(string $code): bool
    {
        return in_array(strtoupper($code), self::ISO_ALPHA3, true);
    }

    /**
     * Code refusé ? Renvoie le motif lisible, ou '' si le code est accepté.
     */
    public static function codeProblem(string $code, string $theme, bool $fromRealSet = false): string
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            return 'Le code du pays s’écrit en trois lettres, sans accent ni chiffre.';
        }
        if (in_array($code, self::ICAO_RESERVED, true)) {
            return 'Ce code est réservé aux documents de voyage réels (apatrides, réfugiés, organisations) : choisissez-en un autre.';
        }
        if ($theme === SseIdRealCountryCatalog::UNIVERSE) {
            // Univers réaliste-simulation : seulement les pays du jeu stylisé, ajoutés par « Ajouter des pays réels ».
            if ($fromRealSet && SseIdRealCountryCatalog::has($code)) {
                return '';
            }

            return 'L’univers « Réaliste — simulation » est réservé aux pays réels stylisés : ajoutez-les avec le bouton « Ajouter des pays réels (simulation) ».';
        }
        if (self::isRealCode($code) && in_array($theme, self::REALISTIC_THEMES, true)) {
            return 'Ce code est celui d’un pays réel : choisissez un code inventé, placez le pays dans un univers imaginaire, ou ajoutez-le depuis les pays réels stylisés (univers réaliste-simulation).';
        }

        return '';
    }

    /** Code MRZ : une à trois lettres, mêmes réserves que le code du pays. */
    public static function mrzProblem(string $mrz, string $theme, bool $fromRealSet = false): string
    {
        $mrz = strtoupper(trim($mrz));
        if (preg_match('/^[A-Z]{1,3}$/', $mrz) !== 1) {
            return 'Le code de la bande de lecture s’écrit en une à trois lettres.';
        }
        if ($fromRealSet && in_array($mrz, array_column(SseIdRealCountryCatalog::COUNTRIES, 'mrz'), true)) {
            return '';
        }
        if ($mrz === 'D' && !in_array($theme, ['scifi', 'scp', 'star_wars', 'warhammer', 'fantasy', 'other'], true)) {
            return 'Bande de lecture : « D » est le code réel de l’Allemagne, choisissez-en un autre.';
        }
        if (strlen($mrz) === 3) {
            $p = self::codeProblem($mrz, $theme);
            if ($p !== '') {
                return 'Bande de lecture : ' . $p;
            }
        }

        return '';
    }

    /**
     * Format du numéro : « # » chiffre, « @ » lettre, lettres et chiffres fixes recopiés ; 5 à 20 signes dont au
     * moins 4 tirés au sort (assez de numéros différents).
     */
    public static function formatProblem(string $format): string
    {
        $format = strtoupper(trim($format));
        if (preg_match('/^[#@A-Z0-9]{5,20}$/', $format) !== 1) {
            return 'Le format du numéro compte 5 à 20 signes : « # » pour un chiffre, « @ » pour une lettre, et des lettres ou chiffres fixes.';
        }
        if (strlen((string) preg_replace('/[^#@]/', '', $format)) < 4) {
            return 'Le format du numéro doit laisser au moins quatre signes tirés au sort (« # » ou « @ »).';
        }

        return '';
    }

    /**
     * @param mixed $raw liste de clés cochées
     * @param array<string, string> $map
     * @return list<string>
     */
    public static function pickMany(mixed $raw, array $map): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $v) {
            $v = is_scalar($v) ? (string) $v : '';
            if ($v !== '' && isset($map[$v]) && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /** Couleur #RRGGBB ou null. */
    public static function color(mixed $raw): ?string
    {
        $c = strtoupper(trim(is_scalar($raw) ? (string) $raw : ''));

        return preg_match('/^#[0-9A-F]{6}$/', $c) === 1 ? $c : null;
    }
}
