<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fond de carte d'un terrain tel que le lit la carte Overwatch (window.ATAK_MAP_CONFIG) : tuiles, repère, décalage,
 * couche aérienne. Une seule construction pour l'Overwatch et la carte du téléphone (/m/carte, /m/briefs), qui
 * affichent ainsi exactement la même carte.
 */
final class AtakOverwatchMapConfig
{
    /**
     * @param array<string, mixed> $map ligne atak_maps (config décodée)
     * @return array<string, mixed>
     */
    public static function fromMap(array $map): array
    {
        $c = is_array($map['config'] ?? null) ? $map['config'] : [];
        $slug = (string) ($map['slug'] ?? 'altis');
        $tp = (string) ($map['tile_pattern'] ?? '');
        $cfg = [
            'slug' => $slug,
            'tilePattern' => function_exists('atak_resolve_tile_pattern') ? atak_resolve_tile_pattern($tp, $slug) : $tp,
            'center' => $c['center'] ?? [15000, 15000],
            'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
            'minZoom' => (int) ($c['minZoom'] ?? 0),
            'maxZoom' => (int) ($c['maxZoom'] ?? 6),
            'tileSize' => (int) ($c['tileSize'] ?? 212),
            'worldSize' => (int) ($c['worldSize'] ?? 30720),
            'attribution' => $c['attribution'] ?? '&copy; Bohemia Interactive',
            'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
            'offsetX' => isset($c['offset_x']) ? (float) $c['offset_x'] : 0,
            'offsetY' => isset($c['offset_y']) ? (float) $c['offset_y'] : 0,
            'aerial' => isset($c['aerial']) && is_array($c['aerial']) ? $c['aerial'] : null,
        ];

        return function_exists('atak_with_aerial_layer') ? atak_with_aerial_layer($cfg, $slug) : $cfg;
    }
}
