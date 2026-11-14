<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use PDO;

/**
 * Catalogue des cartes d'Arma 3 de https://atlas.plan-ops.fr (289 cartes) : nom, taille, pyramide de tuiles et repère de
 * chacune, versionnés dans database/data/atlas_maps.json (tools/atlas_refresh_maps.py le rafraîchit). Chaque carte devient une
 * ligne atak_maps : le site (Overwatch, carte du téléphone, plans TOC, réglages des communautés) et la résolution
 * « nom du monde du jeu → carte » connaissent ainsi toutes les cartes, pas seulement les huit du départ.
 *
 * Règles : une carte déjà présente avec une autre origine (les huit cartes de départ, ou une carte réglée à la main) n'est
 * JAMAIS touchée ; une carte importée d'ici est remise à jour à chaque passage. Les tuiles viennent du CDN de l'atlas.
 */
final class AtlasMapCatalog
{
    /** Premier rang d'affichage des cartes importées : les cartes de départ restent en tête des listes. */
    private const FIRST_ORDER = 100;

    public static function snapshotPath(): string
    {
        return dirname(__DIR__, 3) . '/database/data/atlas_maps.json';
    }

    /**
     * Cartes prêtes à écrire dans atak_maps.
     *
     * @return list<array{slug:string,label:string,world_name:string,tile_pattern:string,config:array<string,mixed>,display_order:int}>
     */
    public static function rows(?string $json = null): array
    {
        $raw = $json ?? (string) @file_get_contents(self::snapshotPath());
        $data = json_decode($raw, true);
        if (!is_array($data) || !is_array($data['maps'] ?? null)) {
            return [];
        }
        $base = rtrim((string) ($data['tile_base'] ?? 'https://atlas.plan-ops.fr/data/1'), '/');
        $rows = [];
        $seen = [];
        foreach ($data['maps'] as $i => $m) {
            if (!is_array($m)) {
                continue;
            }
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($m['slug'] ?? '')) ?? '');
            $world = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($m['world'] ?? $slug)) ?? '');
            $pattern = (string) ($m['tile_pattern'] ?? '');
            $crs = is_array($m['crs'] ?? null) ? array_values($m['crs']) : [];
            $worldSize = (int) ($m['world_size'] ?? 0);
            $tileSize = (int) ($m['tile_size'] ?? 0);
            if ($slug === '' || $world === '' || isset($seen[$slug]) || count($crs) !== 3 || $worldSize < 1 || $tileSize < 1
                || preg_match('#^/maps/\d+/\d+/\{z\}/\{x\}/\{y\}\.(png|webp|jpg)$#', $pattern) !== 1) {
                continue;
            }
            $seen[$slug] = true;
            $title = trim((string) ($m['title'] ?? '')) !== '' ? trim((string) $m['title']) : $slug;
            $center = is_array($m['center'] ?? null) && count($m['center']) === 2
                ? [(float) $m['center'][0], (float) $m['center'][1]]
                : [$worldSize / 2, $worldSize / 2];
            $rows[] = [
                'slug' => substr($slug, 0, 50),
                'label' => mb_substr($title, 0, 100),
                'world_name' => substr($world, 0, 50),
                'tile_pattern' => $base . $pattern,
                'display_order' => self::FIRST_ORDER + (int) $i,
                'config' => [
                    'center' => $center,
                    'defaultZoom' => (int) ($m['default_zoom'] ?? 2),
                    'minZoom' => (int) ($m['min_zoom'] ?? 0),
                    'maxZoom' => (int) ($m['max_zoom'] ?? 5),
                    'tileSize' => $tileSize,
                    'worldSize' => $worldSize,
                    'bounds' => [[0, 0], [$worldSize, $worldSize]],
                    'crs' => ['factorx' => (float) $crs[0], 'factory' => (float) $crs[1], 'tileWidth' => (int) $crs[2]],
                    'attribution' => (string) ($m['attribution'] ?? '') !== '' ? (string) $m['attribution'] : '&#169; Bohemia Interactive',
                    'title' => $title,
                    'source' => 'atlas',
                    'atlas_id' => (int) ($m['atlas_id'] ?? 0),
                    'dlc' => $m['dlc'] ?? null,
                    'steamWorkshop' => $m['workshop'] ?? null,
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return array{inserted:int,updated:int,kept:int}
     */
    public static function import(PDO $pdo, ?string $json = null): array
    {
        $out = ['inserted' => 0, 'updated' => 0, 'kept' => 0];
        $find = $pdo->prepare('SELECT id, config FROM atak_maps WHERE slug = ? LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO atak_maps (slug, label, world_name, tile_pattern, config, display_order) VALUES (?, ?, ?, ?, ?, ?)');
        $upd = $pdo->prepare('UPDATE atak_maps SET label = ?, world_name = ?, tile_pattern = ?, config = ?, display_order = ? WHERE id = ?');
        foreach (self::rows($json) as $row) {
            $find->execute([$row['slug']]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            $config = json_encode($row['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_array($existing)) {
                $ins->execute([$row['slug'], $row['label'], $row['world_name'], $row['tile_pattern'], $config, $row['display_order']]);
                ++$out['inserted'];
                continue;
            }
            $current = json_decode((string) ($existing['config'] ?? ''), true);
            if (!is_array($current) || ($current['source'] ?? '') !== 'atlas') {
                ++$out['kept'];
                continue;
            }
            $upd->execute([$row['label'], $row['world_name'], $row['tile_pattern'], $config, $row['display_order'], (int) $existing['id']]);
            ++$out['updated'];
        }

        return $out;
    }
}
