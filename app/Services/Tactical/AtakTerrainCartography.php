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
    public function ensure(int $tenantId, int $mapId, bool $force = false, string $product = 'all'): ?array
    {
        // Le blob DEM peut être volumineux. Ne le chargeons pas à chaque GET lorsque
        // les produits correspondant au même relevé existent déjà sur disque.
        $meta = $this->terrain->getGrid($tenantId, $mapId, false);
        if (!is_array($meta)) {
            return null;
        }
        $filled = (int) ($meta['filled_cells'] ?? 0);
        if ($filled < 9) {
            return $meta;
        }
        $stamp = (string) ($meta['sampled_at'] ?? $meta['updated_at'] ?? '');
        $dir = $this->dir($tenantId, $mapId);
        $products = $product === 'all' ? ['hillshade', 'slope', 'contours'] : [$product];
        $paths = [
            'hillshade' => $dir . '/hillshade.png',
            'slope' => $dir . '/slope.png',
            'contours' => $dir . '/contours.json',
        ];
        if (!isset($paths[$product]) && $product !== 'all') {
            return $meta;
        }
        $cacheComplete = true;
        foreach ($products as $name) {
            $stampFile = $dir . '/stamp-' . $name . '.txt';
            if (!is_file($paths[$name]) || !is_file($stampFile)
                || trim((string) @file_get_contents($stampFile)) !== $stamp || $stamp === '') {
                $cacheComplete = false;
                break;
            }
        }
        if (!$force && $cacheComplete) {
            return $meta;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $meta;
        }

        // Un seul processus régénère un relief donné. Sous rafale, les autres
        // requêtes servent immédiatement l'ancienne image au lieu d'empiler des
        // calculs jusqu'à la limite d'exécution PHP.
        $lock = @fopen($dir . '/generation.lock', 'c');
        if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return $meta;
        }
        try {
            // Un autre processus a pu terminer entre le contrôle et le verrou.
            if (!$force && $cacheComplete) {
                return $meta;
            }
            $grid = $this->terrain->getGrid($tenantId, $mapId, true);
            if (!is_array($grid) || !is_string($grid['heights'] ?? null) || $grid['heights'] === '') {
                return $meta;
            }
            foreach ($products as $name) {
                try {
                    if ($name === 'hillshade') {
                        $this->writeHillshade($paths[$name], $grid);
                    } elseif ($name === 'slope') {
                        $this->writeSlope($paths[$name], $grid);
                    } else {
                        $geo = AtakTerrainIsolines::geoJson($grid, 10, 50);
                        $json = json_encode($geo, JSON_UNESCAPED_UNICODE);
                        if (is_string($json)) {
                            @file_put_contents($paths[$name], $json, LOCK_EX);
                        }
                    }
                    if (is_file($paths[$name])) {
                        @file_put_contents($dir . '/stamp-' . $name . '.txt', $stamp, LOCK_EX);
                    }
                } catch (Throwable) {
                }
            }

            return $grid;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function hillshadePath(int $tenantId, int $mapId): ?string
    {
        try {
            $this->ensure($tenantId, $mapId, false, 'hillshade');
        } catch (Throwable) {
        }
        $path = $this->dir($tenantId, $mapId) . '/hillshade.png';

        return is_file($path) ? $path : null;
    }

    public function slopePath(int $tenantId, int $mapId): ?string
    {
        try {
            $this->ensure($tenantId, $mapId, false, 'slope');
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
            $this->ensure($tenantId, $mapId, false, 'contours');
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
