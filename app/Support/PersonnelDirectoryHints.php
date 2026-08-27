<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Repositories\UnitRepository;

/**
 * Libellés et info-bulles pour l’annuaire / tableau effectifs.
 */
final class PersonnelDirectoryHints
{
    /**
     * Nom de personnage à afficher sous le membre — vide s’il est absent ou identique au nom de compte.
     */
    public static function distinctCharacterLabel(?string $displayName, ?string $characterName): string
    {
        $character = trim((string) $characterName);
        if ($character === '') {
            return '';
        }
        $display = trim((string) $displayName);
        if ($display === '') {
            return $character;
        }
        if (function_exists('mb_strtolower')) {
            if (mb_strtolower($display, 'UTF-8') === mb_strtolower($character, 'UTF-8')) {
                return '';
            }
        } elseif (strcasecmp($display, $character) === 0) {
            return '';
        }

        return $character;
    }

    /**
     * Enrichit les lignes d’annuaire avec chemin d’organigramme et texte d’info-bulle d’affectation.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function enrichUnitHints(int $tenantId, array $rows): array
    {
        if ($tenantId < 1 || $rows === []) {
            return $rows;
        }

        try {
            /** @var UnitRepository $unitRepo */
            $unitRepo = Container::get(UnitRepository::class);
            $meta = $unitRepo->hierarchyMetaByUnitId($tenantId);
        } catch (\Throwable) {
            $meta = [];
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $unitId = (int) ($row['primary_unit_id'] ?? $row['unit_id'] ?? 0);
            $name = trim((string) ($row['unit_name'] ?? ''));
            $code = trim((string) ($row['unit_code'] ?? ''));
            $blurb = trim((string) ($row['unit_blurb'] ?? ''));
            $path = $unitId > 0 ? trim((string) ($meta[$unitId]['path'] ?? '')) : '';
            $row['unit_path'] = $path;

            $parts = [];
            if ($path !== '') {
                $parts[] = 'Dans l’organigramme : ' . $path;
            } elseif ($name !== '') {
                $parts[] = 'Unité : ' . $name;
            }
            if ($code !== '' && ($name === '' || strcasecmp($code, $name) !== 0)) {
                $parts[] = 'Code d’unité : ' . $code;
            }
            if ($blurb !== '') {
                $parts[] = $blurb;
            }
            $row['unit_tooltip'] = $parts !== [] ? implode(' — ', $parts) : '';
        }
        unset($row);

        return $rows;
    }
}
