<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakTerrainRepository;
use Throwable;

/**
 * Produits cartographiques (hillshade PNG, pentes PNG, isolignes GeoJSON) mis en cache.
 */
final class AtakTerrainCartography
{
    private const MAX_RASTER_EDGE = 512;

    public function __construct(private ?AtakTerrainRepository $terrain = null)
    {
        $this->terrain ??= new AtakTerrainRepository();
    }

    public function dir(int $tenantId, int $mapId): string
    {
        return base_path('storage/atak_terrain/shared/' . $mapId);
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
        try {
            $this->writeHillshade($dir . '/hillshade.png', $grid);
        } catch (Throwable) {
        }
        try {
            $this->writeSlope($dir . '/slope.png', $grid);
        } catch (Throwable) {
        }
        try {
            $geo = AtakTerrainIsolines::geoJson($grid, 10, 50);
            @file_put_contents($dir . '/contours.json', json_encode($geo, JSON_UNESCAPED_UNICODE));
        } catch (Throwable) {
        }
        @file_put_contents($stampFile, $stamp);

        return $grid;
    }

    public function hillshadePath(int $tenantId, int $mapId): ?string
    {
        try {
            $this->ensure($tenantId, $mapId);
        } catch (Throwable) {
        }
        $path = $this->dir($tenantId, $mapId) . '/hillshade.png';

        return is_file($path) ? $path : null;
    }

    public function slopePath(int $tenantId, int $mapId): ?string
    {
        try {
            $this->ensure($tenantId, $mapId);
        } catch (Throwable) {
        }
        $path = $this->dir($tenantId, $mapId) . '/slope.png';

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    public function contours(int $tenantId, int $mapId): array
    {
        try {
            $this->ensure($tenantId, $mapId);
        } catch (Throwable) {
        }
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
        $step = max(1, (int) ceil(max($cols, $rows) / self::MAX_RASTER_EDGE));
        $outW = (int) ceil($cols / $step);
        $outH = (int) ceil($rows / $step);
        if ($outW < 1 || $outH < 1) {
            return;
        }
        $im = @imagecreatetruecolor($outW, $outH);
        if ($im === false) {
            return;
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $outW - 1, $outH - 1, $transparent);

        $grey = [];
        for ($i = 0; $i <= 255; $i++) {
            $grey[$i] = imagecolorallocatealpha($im, $i, $i, $i, 0);
        }
        $slopeColors = [
            'praticable' => imagecolorallocatealpha($im, 46, 120, 62, 20),
            'moderee' => imagecolorallocatealpha($im, 140, 160, 55, 20),
            'forte' => imagecolorallocatealpha($im, 196, 150, 48, 20),
            'tres_forte' => imagecolorallocatealpha($im, 196, 92, 36, 20),
            'critique' => imagecolorallocatealpha($im, 168, 36, 36, 20),
        ];
        $slopeFallback = imagecolorallocatealpha($im, 80, 80, 80, 20);

        for ($or = 0; $or < $outH; $or++) {
            $r = min($rows - 1, $or * $step);
            $pr = $outH - 1 - $or;
            for ($oc = 0; $oc < $outW; $oc++) {
                $c = min($cols - 1, $oc * $step);
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
                    $col = $slopeColors[$cls] ?? $slopeFallback;
                } else {
                    $v = (int) round(18 + $hs['shade'] * 210);
                    $v = max(0, min(255, $v));
                    $col = $grey[$v];
                }
                if ($col !== false) {
                    imagesetpixel($im, $oc, $pr, $col);
                }
            }
        }
        @imagepng($im, $path, 6);
        imagedestroy($im);
    }
}
