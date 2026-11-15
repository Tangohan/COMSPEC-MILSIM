<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Périmètre d'une campagne et d'un lecteur : des unités et toutes leurs sous-unités. Calcul pur sur la table des unités
 * (identifiant => parent). Une liste d'unités vide pour une campagne veut dire « toute la communauté » ; pour un lecteur,
 * null veut dire « tout » (droit de gestion de la communauté) et une liste, « seulement ces unités et leurs sous-unités ».
 */
final class DataCampaignScope
{
    /**
     * Les unités données et leurs descendants.
     *
     * @param list<int> $unitIds
     * @param array<int, array{name?: string, parent_id: int}> $units
     * @return list<int>
     */
    public static function expand(array $unitIds, array $units): array
    {
        $children = [];
        foreach ($units as $id => $u) {
            $children[(int) $u['parent_id']][] = (int) $id;
        }
        $seen = [];
        $stack = array_values(array_filter(array_map('intval', $unitIds), static fn (int $id): bool => $id > 0));
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = $id;
            foreach ($children[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return array_values($seen);
    }

    /**
     * Le membre (par son unité principale) est-il dans le périmètre de la campagne ?
     *
     * @param list<int> $campaignUnitIds vide : toute la communauté
     * @param array<int, array{name?: string, parent_id: int}> $units
     */
    public static function memberInCampaign(int $unitId, array $campaignUnitIds, array $units): bool
    {
        if ($campaignUnitIds === []) {
            return true;
        }

        return $unitId > 0 && in_array($unitId, self::expand($campaignUnitIds, $units), true);
    }

    /**
     * Unités que le lecteur peut suivre : null s'il gère toute la communauté, sinon les unités qu'il commande et leurs
     * sous-unités (liste vide : il ne voit personne).
     *
     * @param list<int> $commanded
     * @param array<int, array{name?: string, parent_id: int}> $units
     * @return list<int>|null
     */
    public static function viewerUnits(bool $communityWide, array $commanded, array $units): ?array
    {
        return $communityWide ? null : self::expand($commanded, $units);
    }

    /**
     * Ne garde que les lignes de membres que le lecteur peut suivre.
     *
     * @param list<array<string, mixed>> $rows lignes avec unit_id
     * @param list<int>|null $viewerUnits résultat de viewerUnits()
     * @return list<array<string, mixed>>
     */
    public static function filterRows(array $rows, ?array $viewerUnits): array
    {
        if ($viewerUnits === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $r): bool => in_array((int) ($r['unit_id'] ?? 0), $viewerUnits, true)));
    }
}
