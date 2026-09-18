<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakSceneObjectRepository;
use App\Repositories\AtakTerrainRepository;
use Throwable;

/**
 * Géométrie statique du théâtre, versionnée par carte.
 * Altis ne bouge pas : on cuit footprints + LOD une fois, on ne recalcule
 * que si le relevé change. Unités / tracés / tags restent dynamiques.
 */
final class AtakSceneMeshBake
{
    public const SCHEMA = 3;

    public function __construct(
        private ?AtakSceneObjectRepository $objects = null,
        private ?AtakTerrainRepository $terrain = null,
    ) {
        $this->objects ??= new AtakSceneObjectRepository();
        $this->terrain ??= new AtakTerrainRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function mesh(int $tenantId, int $mapId, float $minX, float $minY, float $maxX, float $maxY, string $lod = '2'): array
    {
        $bake = $this->ensure($tenantId, $mapId);
        $lod = AtakSceneKind::normalizeLod($lod);
        $area = max(1.0, abs($maxX - $minX) * abs($maxY - $minY));
        if ($lod === AtakSceneKind::LOD3 && $area > 2500000) {
            $lod = AtakSceneKind::LOD2;
        }
        $key = match ($lod) {
            AtakSceneKind::LOD0 => 'lod0',
            AtakSceneKind::LOD1 => 'lod1',
            AtakSceneKind::LOD3 => 'buildings',
            default => 'lod2',
        };
        $rows = is_array($bake[$key] ?? null) ? $bake[$key] : [];
        if ($rows === [] && $key !== 'buildings') {
            $rows = is_array($bake['buildings'] ?? null) ? $bake['buildings'] : [];
        }
        $out = $this->clipRows($rows, $minX, $minY, $maxX, $maxY, $lod === AtakSceneKind::LOD0 ? 2500 : 8000);
        $obstacles = [];
        if ($lod === AtakSceneKind::LOD2 || $lod === AtakSceneKind::LOD3) {
            $obstacles = $this->clipRows(
                is_array($bake['obstacles'] ?? null) ? $bake['obstacles'] : [],
                $minX,
                $minY,
                $maxX,
                $maxY,
                $lod === AtakSceneKind::LOD3 ? 4000 : 2500
            );
        }

        return [
            'ok' => true,
            'lod' => $lod,
            'layer' => 'scene',
            'stamp' => (string) ($bake['stamp'] ?? ''),
            'schema' => self::SCHEMA,
            'anomalies' => (int) ($bake['anomalies'] ?? 0),
            'objects' => $out,
            'obstacles' => $obstacles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ensure(int $tenantId, int $mapId, bool $force = false): array
    {
        $stamp = (string) ($this->objects->lastUpdatedAt($tenantId, $mapId) ?? '');
        $path = $this->path($mapId);
        $stampFile = $this->stampPath($mapId);
        $want = $stamp . '|v' . self::SCHEMA;
        if (!$force && is_file($path) && is_file($stampFile) && trim((string) @file_get_contents($stampFile)) === $want) {
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && (int) ($decoded['schema'] ?? 0) === self::SCHEMA) {
                return $decoded;
            }
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $this->emptyBake($stamp);
        }
        $lock = @fopen($dir . '/mesh.lock', 'c');
        if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            return $this->emptyBake($stamp);
        }
        try {
            $bake = $this->build($tenantId, $mapId, $stamp);
            $json = json_encode($bake, JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                @file_put_contents($path, $json, LOCK_EX);
                @file_put_contents($stampFile, $want, LOCK_EX);
            }

            return $bake;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function invalidate(int $mapId): void
    {
        $stamp = $this->stampPath($mapId);
        if (is_file($stamp)) {
            @unlink($stamp);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId, int $mapId, string $stamp): array
    {
        $rows = [];
        try {
            $rows = $this->objects->allForBake($mapId, 'building');
        } catch (Throwable) {
            $rows = [];
        }
        $grid = null;
        try {
            $grid = $this->terrain->getGrid($tenantId, $mapId, true);
        } catch (Throwable) {
            $grid = null;
        }
        $buildings = [];
        $anomalies = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $box = AtakSceneBounds::sanitize($row);
            $x = (float) ($row['x'] ?? 0);
            $y = (float) ($row['y'] ?? 0);
            $base = is_array($grid) ? AtakTerrainMath::heightAt($grid, $x, $y) : null;
            $quality = AtakSceneKind::quality($row, $box['clipped']);
            $item = [
                'id' => (string) ($row['id'] ?? ''),
                'kind' => 'building',
                'x' => $x,
                'y' => $y,
                'bearing' => (float) ($row['bearing'] ?? 0),
                'width' => $box['width'],
                'depth' => $box['depth'],
                'height' => $box['height'],
                'base_z' => $base !== null ? round($base, 2) : 0.0,
                'floors' => AtakSceneKind::floors($box['height']),
                'quality' => $quality,
            ];
            $buildings[] = $item;
            if ($box['clipped']) {
                $anomalies[] = [
                    'id' => $item['id'],
                    'x' => $x,
                    'y' => $y,
                    'reasons' => $box['reasons'],
                ];
            }
        }
        $obstacles = [];
        try {
            $obsRows = $this->objects->allForBake($mapId, 'obstacle');
        } catch (Throwable) {
            $obsRows = [];
        }
        foreach ($obsRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $kind = AtakSceneKind::normalize((string) ($row['kind'] ?? 'wall'));
            $box = AtakSceneBounds::sanitizeObstacle($row);
            $x = (float) ($row['x'] ?? 0);
            $y = (float) ($row['y'] ?? 0);
            $base = is_array($grid) ? AtakTerrainMath::heightAt($grid, $x, $y) : null;
            $obstacles[] = [
                'id' => (string) ($row['id'] ?? ''),
                'kind' => $kind,
                'x' => $x,
                'y' => $y,
                'bearing' => (float) ($row['bearing'] ?? 0),
                'width' => $box['width'],
                'depth' => $box['depth'],
                'height' => $box['height'],
                'base_z' => $base !== null ? round($base, 2) : 0.0,
                'quality' => AtakSceneKind::quality($row, $box['clipped']),
            ];
            if ($box['clipped']) {
                $anomalies[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'x' => $x,
                    'y' => $y,
                    'reasons' => $box['reasons'],
                ];
            }
        }
        $this->writeAnomalies($mapId, $anomalies);

        return [
            'schema' => self::SCHEMA,
            'stamp' => $stamp,
            'map_id' => $mapId,
            'anomalies' => count($anomalies),
            'buildings' => $buildings,
            'obstacles' => $obstacles,
            'lod0' => self::lodSettlements($buildings, 400.0),
            'lod1' => self::lodClusters($buildings, 140.0, 400.0),
            'lod2' => self::lodClusters($buildings, 55.0, 140.0),
            'clusters_far' => self::lodClusters($buildings, 120.0, 360.0),
            'clusters_mid' => self::lodClusters($buildings, 55.0, 140.0),
        ];
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<array<string, mixed>>
     */
    public static function lodClusters(array $buildings, float $cell, float $maxAreaToMerge, bool $keepTall = true): array
    {
        $kept = [];
        $bins = [];
        foreach ($buildings as $item) {
            $area = (float) $item['width'] * (float) $item['depth'];
            $tall = (float) $item['height'] >= 12.0;
            if ($area >= $maxAreaToMerge || ($keepTall && $tall)) {
                $kept[] = $item;
                continue;
            }
            $gx = (int) floor((float) $item['x'] / $cell);
            $gy = (int) floor((float) $item['y'] / $cell);
            $key = $gx . ':' . $gy;
            if (!isset($bins[$key])) {
                $bins[$key] = [];
            }
            $bins[$key][] = $item;
        }
        foreach ($bins as $members) {
            if (count($members) === 1) {
                $kept[] = $members[0];
                continue;
            }
            $minX = $maxX = (float) $members[0]['x'];
            $minY = $maxY = (float) $members[0]['y'];
            $h = 0.0;
            $z = 0.0;
            $ids = [];
            foreach ($members as $m) {
                $hw = (float) $m['width'] / 2;
                $hd = (float) $m['depth'] / 2;
                $minX = min($minX, (float) $m['x'] - $hw);
                $maxX = max($maxX, (float) $m['x'] + $hw);
                $minY = min($minY, (float) $m['y'] - $hd);
                $maxY = max($maxY, (float) $m['y'] + $hd);
                $h = max($h, (float) $m['height']);
                $z += (float) $m['base_z'];
                if ((string) ($m['id'] ?? '') !== '') {
                    $ids[] = (string) $m['id'];
                }
            }
            $cx = ($minX + $maxX) / 2;
            $cy = ($minY + $maxY) / 2;
            $kept[] = [
                'id' => $ids[0] ?? '',
                'ids' => $ids,
                'cluster' => true,
                'x' => $cx,
                'y' => $cy,
                'bearing' => 0.0,
                'width' => max(AtakSceneBounds::MIN_EDGE, min(AtakSceneBounds::MAX_WIDTH, $maxX - $minX)),
                'depth' => max(AtakSceneBounds::MIN_EDGE, min(AtakSceneBounds::MAX_DEPTH, $maxY - $minY)),
                'height' => max(AtakSceneBounds::MIN_HEIGHT, min(AtakSceneBounds::MAX_HEIGHT, $h)),
                'base_z' => $z / max(1, count($members)),
            ];
        }

        return $kept;
    }

    /**
     * LOD 0 : agglomérations (tout fusionné par cellule).
     *
     * @param list<array<string, mixed>> $buildings
     * @return list<array<string, mixed>>
     */
    public static function lodSettlements(array $buildings, float $cell = 400.0): array
    {
        return self::lodClusters($buildings, $cell, 999999.0, false);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function clipRows(array $rows, float $minX, float $minY, float $maxX, float $maxY, int $limit): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $x = (float) ($row['x'] ?? 0);
            $y = (float) ($row['y'] ?? 0);
            $hw = max(2.0, (float) ($row['width'] ?? 4)) / 2;
            $hd = max(2.0, (float) ($row['depth'] ?? 4)) / 2;
            if ($x + $hw < $minX || $x - $hw > $maxX || $y + $hd < $minY || $y - $hd > $maxY) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $anomalies
     */
    private function writeAnomalies(int $mapId, array $anomalies): void
    {
        $path = $this->dir($mapId) . '/scene-anomalies.log';
        if ($anomalies === []) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }
        $lines = [];
        foreach (array_slice($anomalies, 0, 200) as $row) {
            $lines[] = ($row['id'] ?? '') . ' @ ' . round((float) ($row['x'] ?? 0), 1) . '/' . round((float) ($row['y'] ?? 0), 1)
                . ' ' . implode(',', $row['reasons'] ?? []);
        }
        @file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBake(string $stamp): array
    {
        return [
            'schema' => self::SCHEMA,
            'stamp' => $stamp,
            'anomalies' => 0,
            'buildings' => [],
            'obstacles' => [],
            'lod0' => [],
            'lod1' => [],
            'lod2' => [],
            'clusters_far' => [],
            'clusters_mid' => [],
        ];
    }

    private function dir(int $mapId): string
    {
        return base_path('storage/atak_terrain/shared/' . $mapId);
    }

    private function path(int $mapId): string
    {
        return $this->dir($mapId) . '/scene-mesh.json';
    }

    private function stampPath(int $mapId): string
    {
        return $this->dir($mapId) . '/stamp-mesh.txt';
    }
}
