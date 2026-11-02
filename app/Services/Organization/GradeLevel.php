<?php

declare(strict_types=1);

namespace App\Services\Organization;

/**
 * Niveau comparable d’un grade, tiré de son code OTAN (STANAG 2116) : OR-1 à OR-9 pour les militaires du rang et
 * sous-officiers, OF-1 à OF-10 pour les officiers. Le référentiel des grades le renseigne (`label_otan`) pour les
 * systèmes français et américain ; un grade sur mesure sans équivalence n’est pas comparé.
 *
 * Échelle : OR-n → n (1 à 9), adjudants et warrant officers (WO-n, W-n) → 9, OF-n → 9 + n (OF-1 = 10, OF-2 = 11…).
 * Les codes américains du référentiel (E-5, O-3) sont ramenés au code OTAN : E-n = OR-n, O-1 et O-2 = OF-1,
 * O-n = OF-(n-1) au-delà.
 */
final class GradeLevel
{
    public const MIN = 1;

    public const MAX = 19;

    public static function fromCode(?string $raw): ?int
    {
        $code = strtoupper(str_replace([' ', '–', '—', '_'], ['', '-', '-', '-'], trim((string) $raw)));
        if ($code === '') {
            return null;
        }
        if (preg_match('/^OR-?([1-9])$/', $code, $m) === 1) {
            return (int) $m[1];
        }
        if (preg_match('/^OF-?(10|[1-9])$/', $code, $m) === 1) {
            return 9 + (int) $m[1];
        }
        if (preg_match('/^W(?:O)?-?[1-5]$/', $code) === 1) {
            return 9;
        }
        if (preg_match('/^E-?([1-9])$/', $code, $m) === 1) {
            return (int) $m[1];
        }
        if (preg_match('/^O-?(10|[1-9])$/', $code, $m) === 1) {
            $n = (int) $m[1];

            return 9 + ($n <= 2 ? 1 : $n - 1);
        }

        return null;
    }

    /** Code OTAN d’un niveau (5 → OR-5, 11 → OF-2). */
    public static function code(int $level): string
    {
        $level = max(self::MIN, min(self::MAX, $level));

        return $level <= 9 ? 'OR-' . $level : 'OF-' . ($level - 9);
    }

    /** @return list<string> OR-1 … OR-9, OF-1 … OF-10 */
    public static function codes(): array
    {
        $out = [];
        for ($i = self::MIN; $i <= self::MAX; $i++) {
            $out[] = self::code($i);
        }

        return $out;
    }

    /**
     * Libellé d’une plage à partir des grades de la communauté (« Sergent / Sergent-chef »), sinon les codes OTAN.
     *
     * @param array<int, list<string>> $labelsByLevel niveau => libellés des grades de la communauté
     */
    public static function rangeLabel(int $min, int $max, array $labelsByLevel = []): string
    {
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }
        $labels = [];
        for ($l = $min; $l <= $max; $l++) {
            foreach ($labelsByLevel[$l] ?? [] as $label) {
                $label = trim((string) $label);
                if ($label !== '' && !in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
            }
        }
        $codes = $min === $max ? self::code($min) : self::code($min) . '–' . self::code($max);
        if ($labels === []) {
            return $codes;
        }
        if (count($labels) > 3) {
            $labels = [$labels[0], '…', $labels[count($labels) - 1]];
        }

        return implode(' / ', $labels) . ' (' . $codes . ')';
    }
}
