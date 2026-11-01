<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

/**
 * Comparaison « dotation type » (kit par rôle) ↔ équipement réellement porté (dernier loadout remonté par le mod).
 *
 * On compare la présence des objets, pas les quantités : un chargeur tiré ou un pansement utilisé ne fait pas
 * apparaître d’écart tant qu’il en reste au moins un. Un objet du kit rangé ailleurs (radio dans le sac au lieu
 * de l’emplacement radio) compte comme présent.
 */
final class RoleKitConformity
{
    public const OK_THRESHOLD = 90;

    public const PARTIAL_THRESHOLD = 60;

    /**
     * Classes des emplacements principaux d’un loadout getUnitLoadout (armes et accessoires, tenue, gilet, sac,
     * casque, lunettes, jumelles, objets assignés) — sans le contenu des conteneurs.
     *
     * @param list<mixed> $loadout
     * @return list<string>
     */
    public static function wornMainClasses(array $loadout): array
    {
        $out = [];
        $add = static function (mixed $c) use (&$out): void {
            if (is_string($c) && ($c = trim($c)) !== '') {
                $out[strtolower($c)] = $c;
            }
        };
        foreach ([0, 1, 2] as $i) {
            $w = is_array($loadout[$i] ?? null) ? $loadout[$i] : [];
            foreach ([0, 1, 2, 3, 6] as $j) {
                $add($w[$j] ?? null);
            }
        }
        foreach ([3, 4, 5] as $i) {
            $add(is_array($loadout[$i] ?? null) ? ($loadout[$i][0] ?? null) : null);
        }
        $add($loadout[6] ?? null);
        $add($loadout[7] ?? null);
        $add(is_array($loadout[8] ?? null) ? ($loadout[8][0] ?? null) : null);
        foreach (is_array($loadout[9] ?? null) ? $loadout[9] : [] as $item) {
            $add($item);
        }

        return array_values($out);
    }

    /**
     * @param array{weapons: array<string, array<string, string>>, simple: array<string, string>, cargo: array<string, list<array{class: string, count: int}>>} $kit kit normalisé (RoleKitLoadout::normalize)
     * @param list<string> $wornAll  toutes les classes portées (emplacements + contenu)
     * @param list<string> $wornMain classes des emplacements principaux
     * @return array{expected: int, matched: int, coverage: int, missing: list<string>, extra: list<string>, level: string}
     */
    public static function compare(array $kit, array $wornAll, array $wornMain): array
    {
        $expected = [];
        foreach (RoleKitLoadout::classes($kit) as $c) {
            $expected[strtolower($c)] = $c;
        }
        $worn = [];
        foreach (array_merge($wornAll, $wornMain) as $c) {
            $worn[strtolower($c)] = true;
        }
        $missing = [];
        $matched = 0;
        foreach ($expected as $key => $class) {
            if (isset($worn[$key])) {
                $matched++;
            } else {
                $missing[] = $class;
            }
        }
        $extra = [];
        foreach ($wornMain as $c) {
            if (!isset($expected[strtolower($c)])) {
                $extra[strtolower($c)] = $c;
            }
        }
        $total = count($expected);
        $coverage = $total > 0 ? (int) floor($matched * 100 / $total) : 0;

        return [
            'expected' => $total,
            'matched' => $matched,
            'coverage' => $coverage,
            'missing' => $missing,
            'extra' => array_values($extra),
            'level' => self::level($coverage, $total),
        ];
    }

    /** conforme | partiel | ecart | vide */
    public static function level(int $coverage, int $expected = 1): string
    {
        if ($expected < 1) {
            return 'vide';
        }
        if ($coverage >= self::OK_THRESHOLD) {
            return 'conforme';
        }

        return $coverage >= self::PARTIAL_THRESHOLD ? 'partiel' : 'ecart';
    }

    /**
     * Meilleur kit parmi les candidats (couverture la plus haute ; à égalité, le kit propre à une fonction).
     *
     * @param list<array<string, mixed>> $kits kits (slots normalisés sous 'kit')
     * @param list<string> $wornAll
     * @param list<string> $wornMain
     * @return array{kit: array<string, mixed>, result: array<string, mixed>}|null
     */
    public static function best(array $kits, array $wornAll, array $wornMain): ?array
    {
        $best = null;
        foreach ($kits as $kit) {
            $res = self::compare($kit['kit'], $wornAll, $wornMain);
            if ($res['expected'] < 1) {
                continue;
            }
            $specific = ($kit['job_role_id'] ?? null) !== null;
            if (
                $best === null
                || $res['coverage'] > $best['result']['coverage']
                || ($res['coverage'] === $best['result']['coverage'] && $specific && ($best['kit']['job_role_id'] ?? null) === null)
            ) {
                $best = ['kit' => $kit, 'result' => $res];
            }
        }

        return $best;
    }
}
