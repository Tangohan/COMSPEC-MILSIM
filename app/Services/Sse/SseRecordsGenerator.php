<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseRecordsCountryPacks;
use App\Support\SseRecordsFormat;

/**
 * Passage aux fichiers — génération de personnes FICTIVES cohérentes avec un pack pays (noms courants du pays,
 * adresse, téléphone, véhicule, papiers au format national).
 *
 * Déterministe : une même graine donne la même personne. Le hasard vient d'un xorshift32 local (aucun appel à
 * mt_srand / random_int : l'état global de PHP n'est pas touché).
 *
 * Toutes les valeurs passent SseRecordsFormat::validate pour leur type ; elles restent fabriquées (SSN en 9xx,
 * téléphones US en 555-01xx, FR en 06 39 98, DE en 0151 2xxxxxx, NINO en QQ…).
 */
final class SseRecordsGenerator
{
    public const MAX_BATCH = 200;

    /** Date de référence pour l'âge (18 à 65 ans). */
    public const REFERENCE_DATE = '2026-01-01';

    public const BUILDS = ['slim', 'medium', 'athletic', 'heavy', 'muscular'];

    public const HAIR = ['black', 'brown', 'blond', 'red', 'gray', 'bald', 'white'];

    public const EYES = ['brown', 'blue', 'green', 'hazel', 'gray', 'black'];

    /** Consonnes valables dans tous les motifs (pas de voyelles, ni I, O, Q, U). */
    private const SAFE_LETTERS = 'CFGHJKLMNPRTVWXYZ';

    /** Pays où la génération privilégie cheveux et yeux foncés. */
    private const DARK_FEATURES = ['IRQ', 'AFG', 'MLI'];

    /** Indicatif régional US par ville (le numéro reste en 555-01xx, réservé à la fiction). */
    private const US_AREA_CODES = [
        'New York' => '212', 'Albany' => '518', 'Los Angeles' => '213', 'San Diego' => '619', 'Sacramento' => '916',
        'Houston' => '713', 'Dallas' => '214', 'Austin' => '512', 'San Antonio' => '210', 'Chicago' => '312',
        'Phoenix' => '602', 'Denver' => '303', 'Seattle' => '206', 'Boston' => '617', 'Atlanta' => '404', 'Miami' => '305',
    ];

    /** Préfixe de plaque par ville (DEU : Unterscheidungszeichen ; UKR : code de région ; IRQ/AFG : translittération). */
    private const PLATE_PREFIX = [
        'DEU' => ['Berlin' => 'B', 'Hamburg' => 'HH', 'München' => 'M', 'Köln' => 'K', 'Frankfurt am Main' => 'F', 'Stuttgart' => 'S',
            'Düsseldorf' => 'D', 'Leipzig' => 'L', 'Dortmund' => 'DO', 'Bremen' => 'HB', 'Dresden' => 'DD', 'Hannover' => 'H',
            'Nürnberg' => 'N', 'Essen' => 'E'],
        'UKR' => ['Kyiv' => 'KA', 'Kharkiv' => 'AX', 'Odesa' => 'BH', 'Dnipro' => 'AE', 'Lviv' => 'BC', 'Poltava' => 'BI'],
        'IRQ' => ['Baghdad' => 'BGD', 'Basra' => 'BSR', 'Mosul' => 'NIN', 'Erbil' => 'ERB', 'Kirkuk' => 'KRK', 'Najaf' => 'NJF'],
        'AFG' => ['Kabul' => 'KBL', 'Kandahar' => 'KDR', 'Herat' => 'HRT', 'Mazar-i-Sharif' => 'BLK', 'Jalalabad' => 'NGR', 'Kunduz' => 'KDZ'],
    ];

    /** Combinaisons évitées (homonymes de personnalités connues). */
    private const BLOCKED_NAMES = [
        'James Brown', 'Michael Jackson', 'John Adams', 'John Williams', 'George Lopez', 'Jennifer Lopez', 'Paul Walker',
        'Robert Johnson', 'Steven Wright', 'Thomas Müller', 'Martin Schulz', 'Lena Meyer', 'Pierre Richard', 'Michel Simon',
        'Laurent Blanc', 'Philippe Petit', 'Paolo Rossi', 'Amadou Touré', 'Modibo Keïta', 'Ibrahim Keïta', 'Seydou Keïta',
        'Oumou Sangaré', 'Aminata Traoré', 'Moussa Traoré', 'Rokia Traoré',
    ];

    /**
     * Personne fictive déterministe pour un pays et une graine.
     *
     * @return array{country: string, last_name: string, first_name: string, sex: string, birth_date: string,
     *     birth_place: string, nationality: string, height_cm: int, weight_kg: int, build: string, hair: string, eyes: string,
     *     address: array{line: string, street: string, number: string, postal: string, city: string, region: string, country: string},
     *     phone: string, email: string,
     *     vehicle: array{make: string, model: string, color: string, plate: string, country: string},
     *     ids: array<string, string>}
     */
    public static function person(string $iso3, int $seed, ?string $sex = null): array
    {
        $code = SseRecordsCountryPacks::normalize($iso3);
        $pack = SseRecordsCountryPacks::get($code);
        $s = self::seedState($code . ':' . $seed);

        $sex = strtoupper(trim((string) $sex));
        if ($sex !== 'M' && $sex !== 'F') {
            $sex = self::roll($s, 0, 1) === 0 ? 'M' : 'F';
        }

        /** @var list<string> $firsts */
        $firsts = $pack['names'][$sex === 'M' ? 'male' : 'female'];
        /** @var list<string> $lasts */
        $lasts = $pack['names']['last'];
        $first = self::pick($s, $firsts);
        $last = self::pick($s, $lasts);
        for ($i = 0; $i < 10 && in_array($first . ' ' . $last, self::BLOCKED_NAMES, true); $i++) {
            $last = self::pick($s, $lasts);
        }

        // Naissance : entre 18 et 65 ans au 2026-01-01.
        $ref = new \DateTimeImmutable(self::REFERENCE_DATE);
        $oldest = $ref->modify('-65 years')->modify('+1 day');
        $youngest = $ref->modify('-18 years');
        $span = (int) $oldest->diff($youngest)->days;
        $birth = $oldest->modify('+' . self::roll($s, 0, $span) . ' days');
        $birthDate = $birth->format('Y-m-d');
        $age = (int) $birth->diff($ref)->y;

        /** @var list<array{name: string, region: string, postal: string}> $cities */
        $cities = $pack['cities'];
        $birthCity = self::pick($s, $cities);
        $city = self::pick($s, $cities);

        // Morphologie plausible selon le sexe.
        $height = $sex === 'M'
            ? 162 + self::roll($s, 0, 16) + self::roll($s, 0, 16)
            : 150 + self::roll($s, 0, 15) + self::roll($s, 0, 14);
        $bmi = ($sex === 'M' ? 19.0 : 18.0) + (self::roll($s, 0, 50) + self::roll($s, 0, 50)) / 10;
        $weight = max(30, min(250, (int) round($bmi * ($height / 100) ** 2)));
        $build = match (true) {
            $bmi < 21 => 'slim',
            $bmi < 24.5 => self::pick($s, ['medium', 'athletic']),
            $bmi < 27 => $sex === 'M' ? self::pick($s, ['athletic', 'muscular', 'medium']) : 'medium',
            default => self::pick($s, ['heavy', 'heavy', 'muscular']),
        };
        [$hair, $eyes] = self::features($s, $code, $sex, $age);

        $street = self::pick($s, $pack['streets']);
        $number = (string) ($code === 'USA' ? self::roll($s, 100, 9899) : self::roll($s, 1, 150));
        $line = strtr((string) $pack['address_format'], [
            '{number}' => $number, '{street}' => $street, '{postal}' => $city['postal'], '{city}' => $city['name'], '{region}' => $city['region'],
        ]);

        $vehicle = self::pick($s, $pack['vehicles']);
        $ids = [];
        foreach (array_keys($pack['ids']) as $kind) {
            $ids[$kind] = self::ensure($s, $code, $kind, self::idValue($s, $code, $kind, $sex, $birth, $birthCity, $first, $last, $city, $ids));
        }

        return [
            'country' => $code,
            'last_name' => $last,
            'first_name' => $first,
            'sex' => $sex,
            'birth_date' => $birthDate,
            'birth_place' => $birthCity['name'],
            'nationality' => $code,
            'height_cm' => $height,
            'weight_kg' => $weight,
            'build' => $build,
            'hair' => $hair,
            'eyes' => $eyes,
            'address' => [
                'line' => $line,
                'street' => $street,
                'number' => $number,
                'postal' => $city['postal'],
                'city' => $city['name'],
                'region' => $city['region'],
                'country' => $code,
            ],
            'phone' => self::ensure($s, $code, 'phone', self::phone($s, $code, (string) $pack['phone']['example'], $city['name'])),
            'email' => self::slug($first) . '.' . self::slug($last) . '@' . self::pick($s, $pack['mail_domains']),
            'vehicle' => [
                'make' => $vehicle['make'],
                'model' => $vehicle['model'],
                'color' => self::pick($s, $pack['colors']),
                'plate' => self::ensure($s, $code, 'plate', self::plate($s, $code, (string) $pack['plate']['example'], $city)),
                'country' => $code,
            ],
            'ids' => $ids,
        ];
    }

    /**
     * Lot de personnes (200 au plus), déterministe pour une graine.
     *
     * @return list<array<string, mixed>>
     */
    public static function batch(string $iso3, int $count, int $seed): array
    {
        $out = [];
        $count = max(0, min(self::MAX_BATCH, $count));
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::person($iso3, (int) crc32($seed . '#' . $i));
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────── Papiers

    /**
     * @param array{name: string, region: string, postal: string} $birthCity
     * @param array{name: string, region: string, postal: string} $city
     * @param array<string, string> $done papiers déjà générés (le permis espagnol reprend le DNI)
     */
    private static function idValue(int &$s, string $code, string $kind, string $sex, \DateTimeImmutable $birth, array $birthCity,
        string $first, string $last, array $city, array $done): string
    {
        $example = SseRecordsFormat::example($kind, $code);
        $y = $birth->format('y');
        $mo = $birth->format('m');
        $d = $birth->format('d');

        switch ($code . ':' . $kind) {
            case 'USA:national_id':
                // 9xx : jamais attribué comme SSN.
                return '9' . self::digits($s, 2) . '-' . sprintf('%02d', self::roll($s, 1, 99)) . '-' . sprintf('%04d', self::roll($s, 1, 9999));

            case 'USA:driving_license':
                return match ($city['region']) {
                    'CA' => self::letters($s, 1) . self::digits($s, 7),
                    'TX' => (string) self::roll($s, 1, 9) . self::digits($s, 7),
                    'NY' => (string) self::roll($s, 1, 9) . self::digits($s, 8),
                    'IL' => self::letters($s, 1) . self::digits($s, 11),
                    'FL' => self::letters($s, 1) . self::digits($s, 12),
                    default => self::letters($s, 1) . self::digits($s, 7),
                };

            case 'FRA:national_id':
                $dep = substr($birthCity['postal'], 0, 2);
                if ($dep === '20') {
                    $dep = (int) $birthCity['postal'] < 20200 ? '2A' : '2B';
                }
                $body = ($sex === 'M' ? '1' : '2') . $y . $mo . $dep . sprintf('%03d', self::roll($s, 1, 990)) . sprintf('%03d', self::roll($s, 1, 999));

                return implode(' ', [$body[0], substr($body, 1, 2), substr($body, 3, 2), substr($body, 5, 2), substr($body, 7, 3), substr($body, 10, 3)])
                    . ' ' . SseRecordsFormat::nirKey($body);

            case 'DEU:passport':
                // Reisepass : première lettre C, F, G, H, J ou K.
                return 'CFGHJK'[self::roll($s, 0, 5)] . self::mutate($s, substr($example, 1));

            case 'GBR:national_id':
                // Préfixe QQ réservé : jamais attribué.
                return 'QQ ' . self::digits($s, 2) . ' ' . self::digits($s, 2) . ' ' . self::digits($s, 2) . ' ' . 'ABCD'[self::roll($s, 0, 3)];

            case 'GBR:driving_license':
                $sur = substr(str_pad(strtoupper((string) preg_replace('/[^a-z]/', '', self::slug($last))), 5, '9'), 0, 5);
                $month = (int) $mo + ($sex === 'F' ? 50 : 0);
                $init = strtoupper(substr(self::slug($first), 0, 1) ?: '9') . '9';

                return $sur . $birth->format('Y')[2] . sprintf('%02d', $month) . $d . $birth->format('Y')[3] . $init . '9' . self::letters($s, 2);

            case 'BEL:national_id':
                $order = sprintf('%03d', $sex === 'M' ? 2 * self::roll($s, 0, 498) + 1 : 2 * self::roll($s, 1, 499));
                $base = $y . $mo . $d . $order;
                $num = (int) ((int) $birth->format('Y') >= 2000 ? '2' . $base : $base);

                return $y . '.' . $mo . '.' . $d . '-' . $order . '.' . sprintf('%02d', 97 - ($num % 97));

            case 'POL:national_id':
                $pm = (int) $mo + ((int) $birth->format('Y') >= 2000 ? 20 : 0);
                $body = $y . sprintf('%02d', $pm) . $d . self::digits($s, 3) . (string) (($sex === 'M' ? 1 : 0) + 2 * self::roll($s, 0, 4));
                $w = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
                $sum = 0;
                for ($i = 0; $i < 10; $i++) {
                    $sum += $w[$i] * (int) $body[$i];
                }

                return $body . (string) ((10 - $sum % 10) % 10);

            case 'ESP:national_id':
                $n = sprintf('%08d', self::roll($s, 1000000, 79999999));

                return $n . SseRecordsFormat::dniLetter($n);

            case 'ESP:driving_license':
                if (isset($done['national_id'])) {
                    return $done['national_id'];
                }
                $n = sprintf('%08d', self::roll($s, 1000000, 79999999));

                return $n . SseRecordsFormat::dniLetter($n);
        }

        return self::mutate($s, $example);
    }

    // ─────────────────────────────────────────────────────────────── Téléphone et plaque

    private static function phone(int &$s, string $code, string $example, string $cityName): string
    {
        return match ($code) {
            'USA' => '(' . (self::US_AREA_CODES[$cityName] ?? '555') . ') 555-01' . self::digits($s, 2),
            'FRA' => '06 39 98 ' . self::digits($s, 2) . ' ' . self::digits($s, 2),
            'DEU' => '0151 2' . self::digits($s, 6),
            'GBR' => '07700 900' . self::digits($s, 3),
            default => self::randomTail($s, $example, 3),
        };
    }

    /** @param array{name: string, region: string, postal: string} $city */
    private static function plate(int &$s, string $code, string $example, array $city): string
    {
        $prefix = self::PLATE_PREFIX[$code][$city['name']] ?? null;

        switch ($code) {
            case 'USA':
                return $city['region'] === 'CA'
                    ? self::roll($s, 1, 9) . self::letters($s, 3) . self::digits($s, 3)
                    : self::letters($s, 3) . '-' . self::digits($s, 4);

            case 'DEU':
                // Huit signes au plus (hors séparateurs), première série de chiffres sans zéro initial.
                $prefix ??= 'B';
                $nl = self::roll($s, 1, 2);
                $nd = self::roll($s, 1, min(4, 8 - strlen($prefix) - $nl));

                return $prefix . '-' . self::letters($s, $nl) . ' ' . self::roll($s, 1, 9) . self::digits($s, $nd - 1);

            case 'UKR':
                $ua = 'ABCEHIKMOPTX';

                return ($prefix ?? 'KA') . ' ' . self::digits($s, 4) . ' ' . $ua[self::roll($s, 0, 11)] . $ua[self::roll($s, 0, 11)];

            case 'IRQ':
                return ($prefix ?? 'BGD') . ' ' . chr(65 + self::roll($s, 0, 25)) . ' ' . self::roll($s, 1, 9) . self::digits($s, 4);

            case 'AFG':
                return ($prefix ?? 'KBL') . ' ' . self::roll($s, 1, 9) . self::digits($s, 4);

            case 'ALT':
                return ($city['region'] === 'Stratis' ? 'ST' : 'AL') . ' ' . self::roll($s, 1, 9) . self::digits($s, 4);
        }

        return self::mutate($s, $example);
    }

    // ─────────────────────────────────────────────────────────────── Apparence

    /** @return array{0: string, 1: string} */
    private static function features(int &$s, string $code, string $sex, int $age): array
    {
        if (in_array($code, self::DARK_FEATURES, true)) {
            $hair = self::pick($s, ['black', 'black', 'black', 'brown']);
            $eyes = self::pick($s, $code === 'AFG' ? ['brown', 'brown', 'black', 'hazel', 'green'] : ['brown', 'brown', 'black']);
        } else {
            $hair = self::pick($s, in_array($code, ['DEU', 'NLD', 'POL', 'UKR', 'GBR'], true)
                ? ['brown', 'brown', 'brown', 'blond', 'blond', 'blond', 'black', 'red']
                : ['brown', 'brown', 'brown', 'brown', 'black', 'black', 'blond', 'blond', 'red']);
            $eyes = self::pick($s, ['brown', 'brown', 'blue', 'blue', 'green', 'hazel', 'gray']);
        }
        $roll = self::roll($s, 0, 99);
        if ($sex === 'M' && $age >= 35 && $roll < 12) {
            $hair = 'bald';
        } elseif ($age >= 50 && $roll < 45) {
            $hair = $age >= 60 && $roll < 20 ? 'white' : 'gray';
        }

        return [$hair, $eyes];
    }

    // ─────────────────────────────────────────────────────────────── Outils

    /** Valeur valide pour ce type : celle proposée, sinon une variante de l'exemple, sinon l'exemple. */
    private static function ensure(int &$s, string $code, string $kind, string $value): string
    {
        if (SseRecordsFormat::validate($kind, $value, $code)) {
            return $value;
        }
        $example = SseRecordsFormat::example($kind, $code);
        for ($i = 0; $i < 300; $i++) {
            $v = self::mutate($s, $example);
            if (SseRecordsFormat::validate($kind, $v, $code)) {
                return $v;
            }
        }

        return $example;
    }

    /** Exemple dont chaque chiffre et chaque lettre majuscule est tiré au hasard (séparateurs gardés). */
    private static function mutate(int &$s, string $example): string
    {
        $out = '';
        foreach (mb_str_split($example) as $ch) {
            if (ctype_digit($ch)) {
                $out .= (string) self::roll($s, 0, 9);
            } elseif (preg_match('/^[A-Z]$/', $ch) === 1) {
                $out .= self::SAFE_LETTERS[self::roll($s, 0, strlen(self::SAFE_LETTERS) - 1)];
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }

    /** Exemple dont les $n derniers chiffres sont tirés au hasard (le début, propre à une plage fictive, est gardé). */
    private static function randomTail(int &$s, string $example, int $n): string
    {
        $chars = mb_str_split($example);
        for ($i = count($chars) - 1; $i >= 0 && $n > 0; $i--) {
            if (ctype_digit($chars[$i])) {
                $chars[$i] = (string) self::roll($s, 0, 9);
                $n--;
            }
        }

        return implode('', $chars);
    }

    /** Partie locale de courriel : minuscules ASCII (ä → ae, é → e…). */
    private static function slug(string $s): string
    {
        $s = strtr(mb_strtolower($s, 'UTF-8'), [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'à' => 'a', 'â' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ę' => 'e', 'í' => 'i', 'ì' => 'i',
            'î' => 'i', 'ï' => 'i', 'ł' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ś' => 's',
            'š' => 's', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ý' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'æ' => 'ae', 'œ' => 'oe',
        ]);

        return (string) preg_replace('/[^a-z0-9-]/', '', $s);
    }

    private static function digits(int &$s, int $n): string
    {
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $out .= (string) self::roll($s, 0, 9);
        }

        return $out;
    }

    private static function letters(int &$s, int $n): string
    {
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $out .= self::SAFE_LETTERS[self::roll($s, 0, strlen(self::SAFE_LETTERS) - 1)];
        }

        return $out;
    }

    /**
     * @template T
     * @param array<int, T> $list
     * @return T
     */
    private static function pick(int &$s, array $list): mixed
    {
        $list = array_values($list);

        return $list[self::roll($s, 0, count($list) - 1)];
    }

    /** Entier pseudo-aléatoire entre $min et $max inclus. */
    private static function roll(int &$s, int $min, int $max): int
    {
        return $max <= $min ? $min : $min + self::next($s) % ($max - $min + 1);
    }

    /** xorshift32 (état sur 32 bits non nul). */
    private static function next(int &$s): int
    {
        $s ^= ($s << 13) & 0xFFFFFFFF;
        $s ^= $s >> 17;
        $s ^= ($s << 5) & 0xFFFFFFFF;

        return $s;
    }

    private static function seedState(string $key): int
    {
        $s = (int) crc32($key);
        if ($s === 0) {
            $s = 0x9E3779B9;
        }
        for ($i = 0; $i < 8; $i++) {
            self::next($s);
        }

        return $s;
    }
}
