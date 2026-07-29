<?php

declare(strict_types=1);

namespace App\Support\Profile;

/**
 * Fuseaux proposés sur le formulaire d’enrôlement : libellés pays/ville + drapeau,
 * valeur stockée = identifiant IANA (colonne enlistments.timezone).
 */
final class EnlistmentTimezoneCatalog
{
    /**
     * @var list<array{iana: string, country: string, label: string}>
     */
    private const ENTRIES = [
        ['iana' => 'Europe/Paris', 'country' => 'FR', 'label' => 'France — Paris'],
        ['iana' => 'Europe/Brussels', 'country' => 'BE', 'label' => 'Belgique — Bruxelles'],
        ['iana' => 'Europe/Zurich', 'country' => 'CH', 'label' => 'Suisse — Zurich'],
        ['iana' => 'Europe/Luxembourg', 'country' => 'LU', 'label' => 'Luxembourg'],
        ['iana' => 'Europe/Monaco', 'country' => 'MC', 'label' => 'Monaco'],
        ['iana' => 'Europe/Berlin', 'country' => 'DE', 'label' => 'Allemagne — Berlin'],
        ['iana' => 'Europe/Vienna', 'country' => 'AT', 'label' => 'Autriche — Vienne'],
        ['iana' => 'Europe/Amsterdam', 'country' => 'NL', 'label' => 'Pays-Bas — Amsterdam'],
        ['iana' => 'Europe/Madrid', 'country' => 'ES', 'label' => 'Espagne — Madrid'],
        ['iana' => 'Atlantic/Canary', 'country' => 'ES', 'label' => 'Espagne — Canaries'],
        ['iana' => 'Europe/Lisbon', 'country' => 'PT', 'label' => 'Portugal — Lisbonne'],
        ['iana' => 'Europe/Rome', 'country' => 'IT', 'label' => 'Italie — Rome'],
        ['iana' => 'Europe/London', 'country' => 'GB', 'label' => 'Royaume-Uni — Londres'],
        ['iana' => 'Europe/Dublin', 'country' => 'IE', 'label' => 'Irlande — Dublin'],
        ['iana' => 'Europe/Copenhagen', 'country' => 'DK', 'label' => 'Danemark — Copenhague'],
        ['iana' => 'Europe/Stockholm', 'country' => 'SE', 'label' => 'Suède — Stockholm'],
        ['iana' => 'Europe/Oslo', 'country' => 'NO', 'label' => 'Norvège — Oslo'],
        ['iana' => 'Europe/Helsinki', 'country' => 'FI', 'label' => 'Finlande — Helsinki'],
        ['iana' => 'Europe/Warsaw', 'country' => 'PL', 'label' => 'Pologne — Varsovie'],
        ['iana' => 'Europe/Prague', 'country' => 'CZ', 'label' => 'Tchéquie — Prague'],
        ['iana' => 'Europe/Budapest', 'country' => 'HU', 'label' => 'Hongrie — Budapest'],
        ['iana' => 'Europe/Bucharest', 'country' => 'RO', 'label' => 'Roumanie — Bucarest'],
        ['iana' => 'Europe/Sofia', 'country' => 'BG', 'label' => 'Bulgarie — Sofia'],
        ['iana' => 'Europe/Athens', 'country' => 'GR', 'label' => 'Grèce — Athènes'],
        ['iana' => 'Europe/Zagreb', 'country' => 'HR', 'label' => 'Croatie — Zagreb'],
        ['iana' => 'Europe/Belgrade', 'country' => 'RS', 'label' => 'Serbie — Belgrade'],
        ['iana' => 'Europe/Ljubljana', 'country' => 'SI', 'label' => 'Slovénie — Ljubljana'],
        ['iana' => 'Europe/Bratislava', 'country' => 'SK', 'label' => 'Slovaquie — Bratislava'],
        ['iana' => 'Europe/Sarajevo', 'country' => 'BA', 'label' => 'Bosnie-Herzégovine — Sarajevo'],
        ['iana' => 'Europe/Podgorica', 'country' => 'ME', 'label' => 'Monténégro — Podgorica'],
        ['iana' => 'Europe/Skopje', 'country' => 'MK', 'label' => 'Macédoine du Nord — Skopje'],
        ['iana' => 'Europe/Tirane', 'country' => 'AL', 'label' => 'Albanie — Tirana'],
        ['iana' => 'Europe/Vilnius', 'country' => 'LT', 'label' => 'Lituanie — Vilnius'],
        ['iana' => 'Europe/Riga', 'country' => 'LV', 'label' => 'Lettonie — Riga'],
        ['iana' => 'Europe/Tallinn', 'country' => 'EE', 'label' => 'Estonie — Tallinn'],
        ['iana' => 'Europe/Kyiv', 'country' => 'UA', 'label' => 'Ukraine — Kiev'],
        ['iana' => 'Europe/Moscow', 'country' => 'RU', 'label' => 'Russie — Moscou'],
        ['iana' => 'Europe/Minsk', 'country' => 'BY', 'label' => 'Biélorussie — Minsk'],
        ['iana' => 'Europe/Chisinau', 'country' => 'MD', 'label' => 'Moldavie — Chișinău'],
        ['iana' => 'Europe/Istanbul', 'country' => 'TR', 'label' => 'Turquie — Istanbul'],
        ['iana' => 'Asia/Tbilisi', 'country' => 'GE', 'label' => 'Géorgie — Tbilissi'],
        ['iana' => 'Asia/Yerevan', 'country' => 'AM', 'label' => 'Arménie — Erevan'],
        ['iana' => 'Asia/Nicosia', 'country' => 'CY', 'label' => 'Chypre — Nicosie'],
        ['iana' => 'Atlantic/Reykjavik', 'country' => 'IS', 'label' => 'Islande — Reykjavik'],
        ['iana' => 'Europe/Andorra', 'country' => 'AD', 'label' => 'Andorre'],
        ['iana' => 'Europe/Zurich', 'country' => 'LI', 'label' => 'Liechtenstein'],
        ['iana' => 'Africa/Casablanca', 'country' => 'MA', 'label' => 'Maroc — Casablanca'],
        ['iana' => 'Africa/Algiers', 'country' => 'DZ', 'label' => 'Algérie — Alger'],
        ['iana' => 'Africa/Tunis', 'country' => 'TN', 'label' => 'Tunisie — Tunis'],
        ['iana' => 'Africa/Cairo', 'country' => 'EG', 'label' => 'Égypte — Le Caire'],
        ['iana' => 'Africa/Johannesburg', 'country' => 'ZA', 'label' => 'Afrique du Sud — Johannesburg'],
        ['iana' => 'Asia/Dubai', 'country' => 'AE', 'label' => 'Émirats arabes unis — Dubaï'],
        ['iana' => 'Asia/Riyadh', 'country' => 'SA', 'label' => 'Arabie saoudite — Riyad'],
        ['iana' => 'Asia/Jerusalem', 'country' => 'IL', 'label' => 'Israël — Jérusalem'],
        ['iana' => 'Asia/Beirut', 'country' => 'LB', 'label' => 'Liban — Beyrouth'],
        ['iana' => 'Asia/Baghdad', 'country' => 'IQ', 'label' => 'Irak — Bagdad'],
        ['iana' => 'Asia/Tehran', 'country' => 'IR', 'label' => 'Iran — Téhéran'],
        ['iana' => 'Asia/Kabul', 'country' => 'AF', 'label' => 'Afghanistan — Kaboul'],
        ['iana' => 'Asia/Kolkata', 'country' => 'IN', 'label' => 'Inde — New Delhi'],
        ['iana' => 'Asia/Almaty', 'country' => 'KZ', 'label' => 'Kazakhstan — Almaty'],
        ['iana' => 'Asia/Shanghai', 'country' => 'CN', 'label' => 'Chine — Pékin'],
        ['iana' => 'Asia/Tokyo', 'country' => 'JP', 'label' => 'Japon — Tokyo'],
        ['iana' => 'Asia/Seoul', 'country' => 'KR', 'label' => 'Corée du Sud — Séoul'],
        ['iana' => 'Australia/Sydney', 'country' => 'AU', 'label' => 'Australie — Sydney'],
        ['iana' => 'Australia/Perth', 'country' => 'AU', 'label' => 'Australie — Perth'],
        ['iana' => 'Pacific/Auckland', 'country' => 'NZ', 'label' => 'Nouvelle-Zélande — Auckland'],
        ['iana' => 'America/Montreal', 'country' => 'CA', 'label' => 'Canada — Montréal'],
        ['iana' => 'America/Toronto', 'country' => 'CA', 'label' => 'Canada — Toronto'],
        ['iana' => 'America/Vancouver', 'country' => 'CA', 'label' => 'Canada — Vancouver'],
        ['iana' => 'America/New_York', 'country' => 'US', 'label' => 'États-Unis — New York'],
        ['iana' => 'America/Chicago', 'country' => 'US', 'label' => 'États-Unis — Chicago'],
        ['iana' => 'America/Denver', 'country' => 'US', 'label' => 'États-Unis — Denver'],
        ['iana' => 'America/Los_Angeles', 'country' => 'US', 'label' => 'États-Unis — Los Angeles'],
        ['iana' => 'America/Mexico_City', 'country' => 'MX', 'label' => 'Mexique — Mexico'],
        ['iana' => 'America/Sao_Paulo', 'country' => 'BR', 'label' => 'Brésil — São Paulo'],
        ['iana' => 'America/Argentina/Buenos_Aires', 'country' => 'AR', 'label' => 'Argentine — Buenos Aires'],
        ['iana' => 'America/Santiago', 'country' => 'CL', 'label' => 'Chili — Santiago'],
        ['iana' => 'America/Bogota', 'country' => 'CO', 'label' => 'Colombie — Bogotá'],
        ['iana' => 'America/Caracas', 'country' => 'VE', 'label' => 'Venezuela — Caracas'],
        ['iana' => 'America/Montevideo', 'country' => 'UY', 'label' => 'Uruguay — Montevideo'],
    ];

    /**
     * @return list<array{iana: string, country: string, label: string, flag: string, option_label: string}>
     */
    public static function optionsForSelect(): array
    {
        $out = [];
        foreach (self::ENTRIES as $row) {
            $flag = PublicFlagCountryCatalog::flagEmoji($row['country']);
            $optionLabel = trim(($flag !== '' ? $flag . ' ' : '') . $row['label']);
            $out[] = [
                'iana' => $row['iana'],
                'country' => $row['country'],
                'label' => $row['label'],
                'flag' => $flag,
                'option_label' => $optionLabel,
            ];
        }

        return $out;
    }

    public static function isAllowed(?string $iana): bool
    {
        $v = trim((string) $iana);
        if ($v === '') {
            return true;
        }
        foreach (self::ENTRIES as $row) {
            if ($row['iana'] === $v) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne l’IANA autorisé, ou null si vide / inconnu.
     */
    public static function normalize(?string $raw): ?string
    {
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }

        return self::isAllowed($v) ? $v : null;
    }

    /**
     * Options de niveau matériel PC (valeurs stockées telles quelles).
     *
     * @return list<string>
     */
    public static function pcPerformanceOptions(): array
    {
        return ['Excellent', 'Bon', 'Correct', 'Limité', 'Insuffisant'];
    }

    public static function normalizePcPerformance(?string $raw): ?string
    {
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }

        return in_array($v, self::pcPerformanceOptions(), true) ? $v : null;
    }
}
