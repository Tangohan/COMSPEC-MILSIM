<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Listes de grades lisibles dans les formulaires : un groupe par système (France, États-Unis)
 * et par catégorie, du plus élevé au plus bas.
 *
 * Le catalogue range les officiers du plus bas au plus haut et les sous-officiers dans l'autre sens :
 * l'ordre suit donc le code OTAN (OF-9 → OF-1, OR-9 → OR-1), puis la catégorie et l'ordre du catalogue.
 */
final class GradeSelectOptions
{
    private const CATEGORY_ORDER = [
        'OFFICIER' => 1,
        'SOUS_OFFICIER' => 2,
        'MDR' => 3,
        'CIVIL' => 4,
        'HORS_GRADE' => 5,
    ];

    private const CATEGORY_LABELS = [
        'OFFICIER' => 'Officiers',
        'SOUS_OFFICIER' => 'Sous-officiers',
        'MDR' => 'Militaires du rang',
        'CIVIL' => 'Civils',
        'HORS_GRADE' => 'Hors grade',
    ];

    private const SYSTEM_LABELS = [
        'FR' => 'France',
        'US' => 'États-Unis',
    ];

    /**
     * @param list<array<string, mixed>> $grades
     * @return list<array<string, mixed>>
     */
    public static function sorted(array $grades): array
    {
        $rows = array_values(array_filter($grades, static fn ($g): bool => is_array($g) && (int) ($g['id'] ?? 0) > 0));
        usort($rows, static function (array $a, array $b): int {
            return self::sortKey($a) <=> self::sortKey($b);
        });

        return $rows;
    }

    /**
     * Groupes prêts pour des <optgroup> : « France · Officiers », « États-Unis · Sous-officiers »…
     * Le système de la communauté passe en premier ; le nom du pays est omis s'il n'y en a qu'un.
     *
     * @param list<array<string, mixed>> $grades
     * @return list<array{label: string, grades: list<array<string, mixed>>}>
     */
    public static function groups(array $grades, string $preferredCountry = 'FR'): array
    {
        $preferredCountry = strtoupper(trim($preferredCountry)) ?: 'FR';
        $rows = self::sorted($grades);
        $countries = [];
        foreach ($rows as $g) {
            $countries[self::country($g)] = true;
        }
        $multi = count($countries) > 1;
        usort($rows, static function (array $a, array $b) use ($preferredCountry): int {
            $ca = self::country($a) === $preferredCountry ? 0 : 1;
            $cb = self::country($b) === $preferredCountry ? 0 : 1;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }
            $c = strcmp(self::country($a), self::country($b));

            return $c !== 0 ? $c : (self::sortKey($a) <=> self::sortKey($b));
        });
        $groups = [];
        foreach ($rows as $g) {
            $cat = self::category($g);
            $label = self::CATEGORY_LABELS[$cat] ?? (trim((string) ($g['category_label'] ?? '')) ?: 'Autres');
            if ($multi) {
                $country = self::country($g);
                $label = (self::SYSTEM_LABELS[$country] ?? $country) . ' · ' . $label;
            }
            if ($groups === [] || $groups[count($groups) - 1]['label'] !== $label) {
                $groups[] = ['label' => $label, 'grades' => []];
            }
            $groups[count($groups) - 1]['grades'][] = $g;
        }

        return $groups;
    }

    /** Libellé d'option : « Sgt — Sergent », ou le seul libellé disponible. */
    public static function label(array $grade): string
    {
        $short = trim((string) ($grade['label_short'] ?? $grade['short_name'] ?? ''));
        $long = trim((string) ($grade['label_long'] ?? $grade['name'] ?? ''));
        if ($short !== '' && $long !== '' && mb_strtolower($short, 'UTF-8') !== mb_strtolower($long, 'UTF-8')) {
            return $long . ' (' . $short . ')';
        }

        return $long !== '' ? $long : ($short !== '' ? $short : 'Grade #' . (int) ($grade['id'] ?? 0));
    }

    /** Rang OTAN (OF-5 → 105, OR-7 → 7, O-10 → 110), 0 si inconnu. */
    public static function natoRank(array $grade): int
    {
        $raw = strtoupper(trim((string) ($grade['label_otan'] ?? $grade['nato_code'] ?? '')));
        // Codes OTAN (OF-5, OR-7, WO-2) ou soldes américaines (O-10, W-3, E-9).
        if (preg_match('/^(OF|OR|WO|O|W|E)-?(\d{1,2})\b/', $raw, $m) === 1) {
            $n = (int) $m[2];

            return match ($m[1]) {
                'OF', 'O' => 100 + $n,
                'WO', 'W' => 50 + $n,
                default => $n,
            };
        }

        return 0;
    }

    /** @return array{int, int, int, int} */
    private static function sortKey(array $g): array
    {
        $cat = self::CATEGORY_ORDER[self::category($g)] ?? 9;
        $nato = self::natoRank($g);
        $order = (int) ($g['sort_order'] ?? 0);
        // Officiers rangés du bas vers le haut dans le catalogue : on inverse (départage aussi
        // deux grades au même code OTAN, comme sous-lieutenant et lieutenant en OF-1).
        if (self::category($g) === 'OFFICIER') {
            $order = -$order;
        }

        return [$cat, -$nato, $order, (int) ($g['id'] ?? 0)];
    }

    private static function category(array $g): string
    {
        $code = strtoupper(trim((string) ($g['category_code'] ?? '')));
        if ($code !== '') {
            return $code;
        }
        $nato = self::natoRank($g);
        if ($nato > 100) {
            return 'OFFICIER';
        }
        if (!empty($g['is_commissioned'])) {
            return 'OFFICIER';
        }

        return $nato >= 5 ? 'SOUS_OFFICIER' : ($nato > 0 ? 'MDR' : 'AUTRE');
    }

    private static function country(array $g): string
    {
        $c = strtoupper(trim((string) ($g['country_code'] ?? '')));
        if ($c === '') {
            $sys = strtoupper((string) ($g['system_code'] ?? ''));
            $c = str_starts_with($sys, 'US') ? 'US' : 'FR';
        }

        return $c;
    }
}
