<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Avancement d'une campagne : trois paniers, sans chevauchement.
 *  - confirmed : fiche confirmée et aucun signalement ouvert ;
 *  - flagged   : au moins un signalement ouvert (fiche confirmée ou non) ;
 *  - none      : le reste (pas encore de réponse complète).
 */
final class DataCampaignProgress
{
    public const CONFIRMED = 'confirmed';
    public const FLAGGED = 'flagged';
    public const NONE = 'none';

    public static function bucket(bool $confirmed, int $openFlags): string
    {
        if ($openFlags > 0) {
            return self::FLAGGED;
        }

        return $confirmed ? self::CONFIRMED : self::NONE;
    }

    /**
     * @param list<array<string, mixed>> $members lignes avec confirmed (bool) et open_flags (int)
     * @return array{total: int, confirmed: int, flagged: int, none: int, percent: int}
     */
    public static function totals(array $members): array
    {
        $out = ['total' => 0, 'confirmed' => 0, 'flagged' => 0, 'none' => 0, 'percent' => 0];
        foreach ($members as $m) {
            $out['total']++;
            $out[self::bucket(!empty($m['confirmed']), (int) ($m['open_flags'] ?? 0))]++;
        }
        $out['percent'] = $out['total'] > 0 ? (int) round($out['confirmed'] * 100 / $out['total']) : 0;

        return $out;
    }

    /**
     * Avancement par unité principale.
     *
     * @param list<array<string, mixed>> $members lignes avec confirmed, open_flags et unit_id
     * @param array<int, string> $unitLabels
     * @return list<array{unit_id: int, label: string, total: int, confirmed: int, flagged: int, none: int, percent: int}>
     */
    public static function byUnit(array $members, array $unitLabels): array
    {
        $groups = [];
        foreach ($members as $m) {
            $groups[(int) ($m['unit_id'] ?? 0)][] = $m;
        }
        $out = [];
        foreach ($groups as $unitId => $rows) {
            $out[] = ['unit_id' => $unitId, 'label' => $unitId > 0 ? ($unitLabels[$unitId] ?? 'Unité ' . $unitId) : 'Sans unité'] + self::totals($rows);
        }
        usort($out, static fn (array $a, array $b): int => $a['unit_id'] === 0 ? 1 : ($b['unit_id'] === 0 ? -1 : strcasecmp($a['label'], $b['label'])));

        return $out;
    }
}
