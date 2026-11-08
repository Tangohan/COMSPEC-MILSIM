<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Services\OrganizationCatalog\RealUnitKitDefinitions as Catalog;

/** Compare les ancêtres effectifs au référentiel, sans modifier les unités personnalisées. */
final class MissingCommandDetector
{
    /**
     * @param list<array<string,mixed>> $units Uniquement les unités de la communauté courante.
     * @return list<array{name:string, expected:string}>
     */
    public static function detect(array $units): array
    {
        $byId = [];
        $nodes = [];
        foreach ($units as $unit) {
            $id = (int) $unit['id'];
            $byId[$id] = $unit;
            $nodes[$id] = Catalog::findNode((string) ($unit['slug'] ?? ''), (string) $unit['name'], (string) ($unit['code'] ?? ''));
        }
        $out = [];
        foreach ($byId as $id => $unit) {
            $node = $nodes[$id];
            if ($node === null) {
                continue;
            }
            $ancestors = [];
            $seen = [$id => true];
            $parentId = (int) ($unit['parent_id'] ?? 0);
            while (isset($byId[$parentId]) && !isset($seen[$parentId])) {
                $seen[$parentId] = true;
                if ($nodes[$parentId] !== null) {
                    $ancestors[] = $nodes[$parentId];
                }
                $parentId = (int) ($byId[$parentId]['parent_id'] ?? 0);
            }
            $missing = [];
            while (($node = Catalog::parentSlug($node)) !== null) {
                if (!in_array($node, $ancestors, true)) {
                    $missing[] = Catalog::officialName($node) ?? $node;
                }
            }
            if ($missing !== []) {
                $out[] = ['name' => (string) $unit['name'], 'expected' => implode(' → ', $missing)];
            }
        }
        return $out;
    }
}
