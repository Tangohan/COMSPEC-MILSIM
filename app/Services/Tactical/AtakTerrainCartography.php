<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakTerrainRepository;

/**
 * Produits cartographiques (hillshade PNG, pentes PNG, isolignes GeoJSON) mis en cache.
 */
final class AtakTerrainCartography
{
    public function __construct(private ?AtakTerrainRepository $terrain = null)
    {
        $this->terrain ??= new AtakTerrainRepository();
    }

    public function dir(int $tenantId, int $mapId): string
    {
        return base_path('storage/atak_terrain/' . $tenantId . '/' . $mapId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ensure(int $tenantId, int $mapId, bool $force = false): ?array
    {
        $grid = $this->terrain->getGrid($tenantId, $mapId, true);
        if (!is_array($grid) || !is_string($grid['heights'] ?? null) || $grid['heights'] === '') {
            return null;
        }
        $filled = (int) ($grid['filled_cells'] ?? 0);
        if ($filled < 9) {
            return $grid;
        }
        $stamp = (string) ($grid['sampled_at'] ?? $grid['updated_at'] ?? '');
        $dir = $this->dir($tenantId, $mapId);
        $stampFile = $dir . '/stamp.txt';
        if (!$force && is_file($stampFile) && is_file($dir . '/hillshade.png') && is_file($dir . '/contours.json')) {
            $prev = trim((string) @file_get_contents($stampFile));
            if ($prev === $stamp && $stamp !== '') {
                return $grid;
            }
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $grid;
        }
        $this->writeHillshade($dir . '/hillshade.png', $grid);
        $this->writeSlope($dir . '/slope.png', $grid);
        $geo = AtakTerrainIsolines::geoJson($grid, 10, 50);
        @file_put_contents($dir . '/contours.json', json_encode($geo, JSON_UNESCAPED_UNICODE));
        @file_put_contents($stampFile, $stamp);

        return $grid;
    }

    public function hillshadePath(int $tenantId, int $mapId): ?string
    {
        $this->ensure($tenantId, $mapId);
        $path = $this->dir($tenantId, $mapId) . '/hillshade.png';

        return is_file($path) ? $path : null;
    }

    public function slopePath(int $tenantId, int $mapId): ?string
    {
        $this->ensure($tenantId, $mapId);
        $path = $this->dir($tenantId, $mapId) . '/slope.png';

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    public function contours(int $tenantId, int $mapId): array
    {
        $this->ensure($tenantId, $mapId);
        $path = $this->dir($tenantId, $mapId) . '/contours.json';
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && ($decoded['type'] ?? '') === 'FeatureCollection') {
                return $decoded;
            }
        }

        return ['type' => 'FeatureCollection', 'features' => []];
    }

    /**
     * @param array<string, mixed> $grid
     */
    private function writeHillshade(string $path, array $grid): void
    {
        $this->writeRaster($path, $grid, 'hillshade');
    }

    /**
     * @param array<string, mixed> $grid
     */
    private function writeSlope(string $path, array $grid): void
    {
        $this->writeRaster($path, $grid, 'slope');
    }

    /**
     * @param array<string, mixed> $grid
     */
    private function writeRaster(string $path, array $grid, string $kind): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }
        $blob = (string) $grid['heights'];
        $cols = (int) $grid['cols'];
        $rows = (int) ($grid['rows'] ?? $grid['grid_rows'] ?? 0);
        $cell = (float) ($grid['cell_m'] ?? 50);
        if ($cols < 3 || $rows < 3) {
            return;
        }
        $im = imagecreatetruecolor($cols, $rows);
        if ($im === false) {
            return;
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $cols - 1, $rows - 1, $transparent);

        $slopeRgb = [
            'praticable' => [46, 120, 62],
            'moderee' => [140, 160, 55],
            'forte' => [196, 150, 48],
            'tres_forte' => [196, 92, 36],
            'critique' => [168, 36, 36],
        ];

        for ($r = 0; $r < $rows; $r++) {
            $pr = $rows - 1 - $r;
            for ($c = 0; $c < $cols; $c++) {
                $z = AtakTerrainMath::cellZ($blob, $cols, $c, $r);
                if ($z === null) {
                    continue;
                }
                $hs = AtakTerrainMath::hornShade($blob, $cols, $c, $r, $cell);
                if ($hs === null) {
                    continue;
                }
                if ($kind === 'slope') {
                    $cls = AtakTerrainMath::slopeClass($hs['slope_deg']);
                    $rgb = $slopeRgb[$cls] ?? [80, 80, 80];
                    $col = imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], 20);
                } else {
                    $v = (int) round(18 + $hs['shade'] * 210);
                    $col = imagecolorallocatealpha($im, $v, $v, $v, 0);
                }
                if ($col !== false) {
                    imagesetpixel($im, $c, $pr, $col);
                }
            }
        }
        imagepng($im, $path, 6);
        imagedestroy($im);
    }
}
