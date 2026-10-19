<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contrôle d'une traduction de l'ATAK natif : mêmes règles que check() de public/assets/js/atak-translate.js
 * (l'atelier vérifie à la frappe, le serveur revérifie chaque version publiée contre le modèle du mod).
 *
 * Erreurs (la phrase est écrite vide dans l'addon, donc affichée en anglais) : marqueurs %1, %2… différents du
 * français, retour à la ligne. Avertissements : espaces en bord, balises, %%, % isolé, texte nettement plus long.
 */
final class AtakTranslationCheck
{
    /**
     * @return array{ids: list<string>, lone: int, pct: int}
     */
    public static function placeholders(string $s): array
    {
        $ids = [];
        $lone = 0;
        $pct = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            if ($s[$i] !== '%') {
                continue;
            }
            $d = $s[$i + 1] ?? '';
            if ($d === '%') {
                $pct++;
                $i++;
            } elseif ($d !== '' && ctype_digit($d)) {
                $j = $i + 1;
                while ($j < $n && ctype_digit($s[$j])) {
                    $j++;
                }
                $ids[] = '%' . substr($s, $i + 1, $j - $i - 1);
                $i = $j - 1;
            } else {
                $lone++;
            }
        }
        sort($ids, SORT_STRING);

        return ['ids' => $ids, 'lone' => $lone, 'pct' => $pct];
    }

    public static function tags(string $s): string
    {
        preg_match_all('/<\/?[a-zA-Z][^>]*>/', $s, $m);
        $out = array_map(
            static fn (string $x): string => strtolower((string) preg_replace('/=(["\'])[^"\']*\1/', '=', (string) preg_replace('/\s+/', ' ', $x))),
            $m[0]
        );
        sort($out, SORT_STRING);

        return implode('|', $out);
    }

    /**
     * @return array{errors: list<array{0: string, 1: array<string, string|int>}>, warnings: list<array{0: string, 1: array<string, string|int>}>}
     */
    public static function check(string $fr, string $tr, string $en = ''): array
    {
        $res = ['errors' => [], 'warnings' => []];
        if ($tr === '') {
            return $res;
        }
        $a = self::placeholders($fr);
        $b = self::placeholders($tr);
        if (implode(',', $a['ids']) !== implode(',', $b['ids'])) {
            $res['errors'][] = ['err_placeholders', ['expected' => implode(' ', $a['ids']) ?: '-', 'found' => implode(' ', $b['ids']) ?: '-']];
        }
        if (preg_match('/[\r\n]/', $tr) === 1) {
            $res['errors'][] = ['err_newline', []];
        }
        if (self::edges($fr) !== self::edges($tr)) {
            $res['warnings'][] = ['warn_space', []];
        }
        if (self::tags($fr) !== self::tags($tr)) {
            $res['warnings'][] = ['warn_tags', []];
        }
        if ($a['pct'] !== $b['pct']) {
            $res['warnings'][] = ['warn_percent', []];
        }
        if ($a['ids'] !== [] && $b['lone'] > $a['lone']) {
            $res['warnings'][] = ['warn_lone_percent', []];
        }
        $ref = $en !== '' ? $en : $fr;
        $rl = mb_strlen($ref);
        $tl = mb_strlen($tr);
        if ($rl >= 8 && $tl > $rl * 1.6 + 4) {
            $res['warnings'][] = ['warn_long', ['pct' => (int) round(($tl / $rl - 1) * 100)]];
        }

        return $res;
    }

    /** @return array{0: bool, 1: bool} espace au début, espace à la fin */
    private static function edges(string $s): array
    {
        return [preg_match('/^\s/u', $s) === 1, preg_match('/\s$/u', $s) === 1];
    }

    /**
     * Contrôle d'un paquet entier contre le modèle actuel du mod.
     *
     * @param list<array{0: string, 1: string, 2: string, 3?: string, 4?: string}> $entries [clé, français, anglais, …]
     * @param array<string, string> $map clé => traduction
     * @return array{total: int, translated: int, missing: int, errors: int, warnings: int, obsolete: int, pct: int, samples: list<array{h: string, fr: string, text: string, issue: string, vars: array<string, string|int>}>}
     */
    public static function audit(array $entries, array $map, int $maxSamples = 12): array
    {
        $total = count($entries);
        $ok = 0;
        $errors = 0;
        $warnings = 0;
        $samples = [];
        $known = [];
        foreach ($entries as $e) {
            $h = $e[0];
            $known[$h] = true;
            $t = $map[$h] ?? '';
            if ($t === '') {
                continue;
            }
            $r = self::check($e[1], $t, $e[2]);
            if ($r['errors'] !== []) {
                $errors++;
                if (count($samples) < $maxSamples) {
                    $samples[] = ['h' => $h, 'fr' => $e[1], 'text' => $t, 'issue' => $r['errors'][0][0], 'vars' => $r['errors'][0][1]];
                }
                continue;
            }
            $ok++;
            if ($r['warnings'] !== []) {
                $warnings++;
            }
        }
        $obsolete = 0;
        foreach ($map as $h => $t) {
            if ($t !== '' && !isset($known[(string) $h])) {
                $obsolete++;
            }
        }

        return [
            'total' => $total,
            'translated' => $ok,
            'missing' => $total - $ok - $errors,
            'errors' => $errors,
            'warnings' => $warnings,
            'obsolete' => $obsolete,
            'pct' => $total > 0 ? (int) floor($ok * 100 / $total) : 0,
            'samples' => $samples,
        ];
    }
}
