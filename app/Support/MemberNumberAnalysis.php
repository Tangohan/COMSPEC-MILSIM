<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contrôle du registre des matricules d’organisation d’une communauté : doublons, membres sans matricule,
 * valeurs hors format, numéros sautés dans la séquence et séquence en retard sur les numéros déjà attribués.
 *
 * Calcul pur (aucune requête) : la reconnaissance du format reproduit exactement
 * {@see \App\Services\Personnel\TenantMemberNumberService::format()}, nettoyage des séparateurs compris.
 */
final class MemberNumberAnalysis
{
    /** Nombre maximal de numéros sautés listés un par un (au-delà : seulement le total). */
    public const GAP_LIST_LIMIT = 60;

    /**
     * Expressions régulières reconnaissant un matricule produit par le format : une par combinaison
     * unité / grade renseigné ou vide (le nettoyage des séparateurs diffère selon le cas).
     *
     * @return list<string>
     */
    public static function patternRegexes(string $pattern, string $prefix, ?string $tenantSlug = null): array
    {
        $hasUnit = stripos($pattern, '{UNIT}') !== false;
        $hasGrade = stripos($pattern, '{GRADE}') !== false;
        $out = [];
        foreach ($hasUnit ? [false, true] : [false] as $unit) {
            foreach ($hasGrade ? [false, true] : [false] as $grade) {
                $out[] = self::patternRegex($pattern, $prefix, $tenantSlug, $unit, $grade);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Numéro de séquence d’un matricule conforme au format (0 si le format n’a pas de numéro),
     * ou null si la valeur ne suit pas le format.
     *
     * @param list<string> $regexes
     */
    public static function matchSequence(string $value, array $regexes): ?int
    {
        foreach ($regexes as $regex) {
            if (preg_match($regex, $value, $m) === 1) {
                return isset($m['seq']) ? (int) $m['seq'] : 0;
            }
        }

        return null;
    }

    /**
     * Expression régulière reconnaissant un matricule produit par le format, avec le numéro de séquence
     * dans le groupe nommé « seq » (absent si le format n’a pas de {NUMBER}). $unitFilled / $gradeFilled :
     * l’unité et le grade du membre sont-ils renseignés (code alphanumérique) ou vides.
     */
    public static function patternRegex(string $pattern, string $prefix, ?string $tenantSlug = null, bool $unitFilled = false, bool $gradeFilled = false): string
    {
        // Jetons remplacés par des marqueurs neutres (ni tiret ni barre) pour appliquer le même nettoyage que format().
        $marks = [
            'NUM' => "\x01N\x01",
            'Y4' => "\x01Y\x01",
            'Y2' => "\x01y\x01",
            'MO' => "\x01M\x01",
            'UN' => "\x01U\x01",
            'GR' => "\x01G\x01",
        ];
        $out = $pattern;
        $out = str_replace(['{PREFIX}', '{prefix}'], $prefix, $out);
        $out = str_replace(['{YEAR:2}', '{year:2}'], $marks['Y2'], $out);
        $out = str_replace(['{YEAR}', '{year}'], $marks['Y4'], $out);
        $out = str_replace(['{MONTH}', '{month}'], $marks['MO'], $out);
        $out = str_replace(['{TENANT}', '{tenant}'], (string) ($tenantSlug ?? ''), $out);
        $out = str_replace(['{UNIT}', '{unit}'], $unitFilled ? $marks['UN'] : '', $out);
        $out = str_replace(['{GRADE}', '{grade}'], $gradeFilled ? $marks['GR'] : '', $out);
        $pad = null;
        if (preg_match('/\{NUMBER:(\d+)\}/i', $out, $m)) {
            $pad = max(1, min(8, (int) $m[1]));
            $out = preg_replace('/\{NUMBER:\d+\}/i', $marks['NUM'], $out) ?? $out;
        } else {
            $out = str_replace(['{NUMBER}', '{number}'], $marks['NUM'], $out);
        }
        $out = preg_replace('/-{2,}/', '-', $out) ?? $out;
        $out = preg_replace('/\/{2,}/', '/', $out) ?? $out;
        $out = trim($out, '-/');

        $regex = preg_quote($out, '/');
        $numberDone = false;
        $regex = preg_replace_callback('/\x01N\x01/', static function () use (&$numberDone, $pad): string {
            if ($numberDone) {
                // Un deuxième {NUMBER} reprend la même valeur : on ne capture que le premier.
                return '\d+';
            }
            $numberDone = true;

            return $pad !== null ? '(?<seq>\d{' . $pad . ',})' : '(?<seq>\d+)';
        }, $regex) ?? $regex;
        $regex = str_replace(
            ["\x01Y\x01", "\x01y\x01", "\x01M\x01", "\x01U\x01", "\x01G\x01"],
            ['\d{4}', '\d{2}', '(?:0[1-9]|1[0-2])', '[A-Za-z0-9]+', '[A-Za-z0-9]+'],
            $regex
        );

        return '/^' . $regex . '$/i';
    }

    /**
     * @param list<array{id: int, tenant_member_number?: string|null}> $members
     * @param list<string> $extraPatterns formats choisis pour certaines unités (reconnus eux aussi)
     * @return array{
     *   total: int,
     *   assigned: int,
     *   missing: int,
     *   duplicates: array<string, list<int>>,
     *   duplicate_members: int,
     *   off_format: list<int>,
     *   format_checked: bool,
     *   max_sequence: int,
     *   gaps: list<int>,
     *   gap_count: int,
     *   sequence_behind: bool,
     *   suggested_next: int
     * }
     */
    public static function analyze(array $members, string $pattern, string $prefix, ?string $tenantSlug, int $nextSequence, array $extraPatterns = []): array
    {
        $regexes = self::patternRegexes($pattern, $prefix, $tenantSlug);
        foreach ($extraPatterns as $extra) {
            $regexes = array_merge($regexes, self::patternRegexes((string) $extra, $prefix, $tenantSlug));
        }
        $regexes = array_values(array_unique($regexes));
        $hasNumber = preg_match('/\{NUMBER(:\d+)?\}/i', $pattern) === 1;

        $byValue = [];
        $offFormat = [];
        $sequences = [];
        $assigned = 0;
        foreach ($members as $m) {
            $id = (int) ($m['id'] ?? 0);
            $value = trim((string) ($m['tenant_member_number'] ?? ''));
            if ($value === '') {
                continue;
            }
            ++$assigned;
            $byValue[mb_strtolower($value)][] = $id;
            $seq = self::matchSequence($value, $regexes);
            if ($seq !== null) {
                if ($seq > 0) {
                    $sequences[$seq] = true;
                }
            } else {
                $offFormat[] = $id;
            }
        }

        $duplicates = [];
        $dupMembers = 0;
        foreach ($byValue as $value => $ids) {
            if (count($ids) > 1) {
                $duplicates[(string) $value] = $ids;
                $dupMembers += count($ids);
            }
        }
        ksort($duplicates, SORT_NATURAL);

        $max = $sequences === [] ? 0 : max(array_keys($sequences));
        $gaps = [];
        $gapCount = 0;
        if ($max > 0 && $hasNumber) {
            // Total calculé sans parcourir 1..max (un matricule saisi « 99999999 » ne doit pas bloquer la page) ;
            // la liste s’arrête aux premiers trous, donc au plus « membres + limite » tours.
            $gapCount = $max - count($sequences);
            for ($n = 1; $n < $max && count($gaps) < self::GAP_LIST_LIMIT; ++$n) {
                if (!isset($sequences[$n])) {
                    $gaps[] = $n;
                }
            }
        }
        $nextSequence = max(1, $nextSequence);

        return [
            'total' => count($members),
            'assigned' => $assigned,
            'missing' => count($members) - $assigned,
            'duplicates' => $duplicates,
            'duplicate_members' => $dupMembers,
            'off_format' => $offFormat,
            'format_checked' => $hasNumber,
            'max_sequence' => $max,
            'gaps' => $gaps,
            'gap_count' => $gapCount,
            'sequence_behind' => $max > 0 && $nextSequence <= $max,
            'suggested_next' => max($nextSequence, $max + 1),
        ];
    }

    /**
     * Regroupe les numéros sautés en plages lisibles : [3, 4, 5, 9] → « 3 à 5 », « 9 ».
     *
     * @param list<int> $numbers
     * @return list<string>
     */
    public static function ranges(array $numbers): array
    {
        sort($numbers);
        $out = [];
        $start = null;
        $prev = null;
        foreach ($numbers as $n) {
            if ($start === null) {
                $start = $prev = $n;
                continue;
            }
            if ($n === $prev + 1) {
                $prev = $n;
                continue;
            }
            $out[] = $start === $prev ? (string) $start : $start . ' à ' . $prev;
            $start = $prev = $n;
        }
        if ($start !== null) {
            $out[] = $start === $prev ? (string) $start : $start . ' à ' . $prev;
        }

        return $out;
    }
}
