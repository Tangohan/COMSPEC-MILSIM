<?php

declare(strict_types=1);

namespace App\Services\Atak;

/**
 * Dédoublonnage défensif de la liste des unités envoyée aux téléphones (GET /api/units) : une seule ligne par indicatif.
 * Le téléphone clé ses pistes Athena par indicatif ; une ligne en double (même indicatif, autre identifiant) ne doit
 * jamais partir plus loin que le serveur. Ligne gardée : la meilleure liaison (linked > delayed > offline), puis la plus
 * récente (age_seconds le plus petit). Les lignes sans indicatif sont toutes gardées (rien à comparer).
 */
final class AtakUnitRowDedupe
{
    private const RANK = ['linked' => 3, 'delayed' => 2, 'offline' => 1];

    /**
     * @param list<mixed> $rows
     * @return list<mixed>
     */
    public static function byCallSign(array $rows): array
    {
        $best = [];
        $order = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                $order[] = ['raw', $i];
                continue;
            }
            $cs = strtolower(trim((string) ($row['call_sign'] ?? '')));
            if ($cs === '') {
                $order[] = ['raw', $i];
                continue;
            }
            if (!isset($best[$cs])) {
                $best[$cs] = $i;
                $order[] = ['cs', $cs];
                continue;
            }
            if (self::better($row, $rows[$best[$cs]])) {
                $best[$cs] = $i;
            }
        }
        $out = [];
        foreach ($order as [$kind, $ref]) {
            $out[] = $kind === 'raw' ? $rows[$ref] : $rows[$best[$ref]];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function better(array $a, array $b): bool
    {
        $ra = self::RANK[(string) ($a['status'] ?? '')] ?? 0;
        $rb = self::RANK[(string) ($b['status'] ?? '')] ?? 0;
        if ($ra !== $rb) {
            return $ra > $rb;
        }
        $aa = is_numeric($a['age_seconds'] ?? null) ? (float) $a['age_seconds'] : PHP_FLOAT_MAX;
        $ab = is_numeric($b['age_seconds'] ?? null) ? (float) $b['age_seconds'] : PHP_FLOAT_MAX;

        return $aa < $ab;
    }
}
