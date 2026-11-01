<?php

declare(strict_types=1);

namespace App\Support\Profile;

/**
 * Liste restreinte de codes pays (ISO 3166-1 alpha-2) pour le sélecteur « drapeau fiche »
 * et validation serveur stricte.
 */
final class PublicFlagCountryCatalog
{
    /** @var array<string, string> code => libellé français */
    private const OPTIONS = [
        '' => 'Ne pas afficher de drapeau',
        'AD' => 'Andorre',
        'AE' => 'Émirats arabes unis',
        'AF' => 'Afghanistan',
        'AL' => 'Albanie',
        'AM' => 'Arménie',
        'AR' => 'Argentine',
        'AT' => 'Autriche',
        'AU' => 'Australie',
        'BA' => 'Bosnie-Herzégovine',
        'BE' => 'Belgique',
        'BG' => 'Bulgarie',
        'BR' => 'Brésil',
        'BY' => 'Biélorussie',
        'CA' => 'Canada',
        'CH' => 'Suisse',
        'CL' => 'Chili',
        'CN' => 'Chine',
        'CO' => 'Colombie',
        'CY' => 'Chypre',
        'CZ' => 'Tchéquie',
        'DE' => 'Allemagne',
        'DK' => 'Danemark',
        'DZ' => 'Algérie',
        'EE' => 'Estonie',
        'EG' => 'Égypte',
        'ES' => 'Espagne',
        'FI' => 'Finlande',
        'FR' => 'France',
        'GB' => 'Royaume-Uni',
        'GE' => 'Géorgie',
        'GR' => 'Grèce',
        'HR' => 'Croatie',
        'HU' => 'Hongrie',
        'IE' => 'Irlande',
        'IL' => 'Israël',
        'IN' => 'Inde',
        'IQ' => 'Irak',
        'IR' => 'Iran',
        'IS' => 'Islande',
        'IT' => 'Italie',
        'JP' => 'Japon',
        'KR' => 'Corée du Sud',
        'KZ' => 'Kazakhstan',
        'LB' => 'Liban',
        'LI' => 'Liechtenstein',
        'LT' => 'Lituanie',
        'LU' => 'Luxembourg',
        'LV' => 'Lettonie',
        'MA' => 'Maroc',
        'MC' => 'Monaco',
        'MD' => 'Moldavie',
        'ME' => 'Monténégro',
        'MK' => 'Macédoine du Nord',
        'MX' => 'Mexique',
        'NL' => 'Pays-Bas',
        'NO' => 'Norvège',
        'NZ' => 'Nouvelle-Zélande',
        'PL' => 'Pologne',
        'PT' => 'Portugal',
        'RO' => 'Roumanie',
        'RS' => 'Serbie',
        'RU' => 'Russie',
        'SA' => 'Arabie saoudite',
        'SE' => 'Suède',
        'SI' => 'Slovénie',
        'SK' => 'Slovaquie',
        'TN' => 'Tunisie',
        'TR' => 'Turquie',
        'UA' => 'Ukraine',
        'US' => 'États-Unis',
        'UY' => 'Uruguay',
        'VE' => 'Venezuela',
        'ZA' => 'Afrique du Sud',
    ];

    /** @var array<string, string> code => nom anglais (même liste que OPTIONS) */
    private const OPTIONS_EN = [
        'AD' => 'Andorra', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AL' => 'Albania',
        'AM' => 'Armenia', 'AR' => 'Argentina', 'AT' => 'Austria', 'AU' => 'Australia',
        'BA' => 'Bosnia and Herzegovina', 'BE' => 'Belgium', 'BG' => 'Bulgaria', 'BR' => 'Brazil',
        'BY' => 'Belarus', 'CA' => 'Canada', 'CH' => 'Switzerland', 'CL' => 'Chile', 'CN' => 'China',
        'CO' => 'Colombia', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DE' => 'Germany', 'DK' => 'Denmark',
        'DZ' => 'Algeria', 'EE' => 'Estonia', 'EG' => 'Egypt', 'ES' => 'Spain', 'FI' => 'Finland',
        'FR' => 'France', 'GB' => 'United Kingdom', 'GE' => 'Georgia', 'GR' => 'Greece', 'HR' => 'Croatia',
        'HU' => 'Hungary', 'IE' => 'Ireland', 'IL' => 'Israel', 'IN' => 'India', 'IQ' => 'Iraq', 'IR' => 'Iran',
        'IS' => 'Iceland', 'IT' => 'Italy', 'JP' => 'Japan', 'KR' => 'South Korea', 'KZ' => 'Kazakhstan',
        'LB' => 'Lebanon', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia',
        'MA' => 'Morocco', 'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Montenegro', 'MK' => 'North Macedonia',
        'MX' => 'Mexico', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NZ' => 'New Zealand', 'PL' => 'Poland',
        'PT' => 'Portugal', 'RO' => 'Romania', 'RS' => 'Serbia', 'RU' => 'Russia', 'SA' => 'Saudi Arabia',
        'SE' => 'Sweden', 'SI' => 'Slovenia', 'SK' => 'Slovakia', 'TN' => 'Tunisia', 'TR' => 'Turkey',
        'UA' => 'Ukraine', 'US' => 'United States', 'UY' => 'Uruguay', 'VE' => 'Venezuela', 'ZA' => 'South Africa',
    ];

    /** @return array<string, string> */
    public static function optionsForSelect(): array
    {
        return self::OPTIONS;
    }

    /** Nom anglais d'un pays de la liste, sinon chaîne vide. */
    public static function englishLabel(string $iso2): string
    {
        return self::OPTIONS_EN[strtoupper(trim($iso2))] ?? '';
    }

    public static function isAllowed(?string $code): bool
    {
        if ($code === null || $code === '') {
            return true;
        }

        return array_key_exists(strtoupper($code), self::OPTIONS);
    }

    /**
     * Retourne null si absent ou « ne pas afficher », sinon code en majuscules.
     */
    public static function normalize(?string $raw): ?string
    {
        $v = strtoupper(trim((string) $raw));
        if ($v === '' || $v === '0') {
            return null;
        }

        return self::isAllowed($v) && $v !== '' ? $v : null;
    }

    /**
     * Drapeau Unicode (indicateurs régionaux) pour un code ISO2 autorisé.
     */
    public static function flagEmoji(?string $iso2): string
    {
        if ($iso2 === null || $iso2 === '') {
            return '';
        }
        $code = strtoupper($iso2);
        if (strlen($code) !== 2 || !ctype_alpha($code) || !self::isAllowed($code)) {
            return '';
        }
        $a = ord($code[0]) - 65 + 0x1F1E6;
        $b = ord($code[1]) - 65 + 0x1F1E6;

        return mb_chr($a, 'UTF-8') . mb_chr($b, 'UTF-8');
    }
}
