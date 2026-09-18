<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakTerrainRepository;
use Throwable;

/**
 * Tuiles Terrain-RGB (encodage Mapbox) dérivées de la grille DEM int16.
 * Cache fichier par mapId + z/x/y. Ne pas renvoyer la grille brute à chaque frame.
 */
final class AtakTerrainRgb
{
    public const TILE_SIZE = 256;

    public function __construct(private ?AtakTerrainRepository $terrain = null)
    {
        $this->terrain ??= new AtakTerrainRepository();
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    public static function encodeMeters(float $meters): array
    {
        $n = (int) round(($meters + 10000.0) / 0.1);
        if ($n < 0) {
            $n = 0;
        }
        if ($n > 16777215) {
            $n = 16777215;
        }

        return [
            'r' => ($n >> 16) & 255,
            'g' => ($n >> 8) & 255,
            'b' => $n & 255,
        ];
    }

    public static function decodeMeters(int $r, int $g, int $b): float
    {
        return -10000.0 + ((($r & 255) * 256 * 256 + ($g & 255) * 256 + ($b & 255)) * 0.1);
    }

    public function tilePath(int $tenantId, int $mapId, int $z, int $x, int $y, float $offsetX = 0.0, float $offsetY = 0.0): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $z = max(0, min(16, $z));
        $x = max(0, $x);
        $y = max(0, $y);
        $grid = null;
        try {
            $grid = $this->terrain->getGrid($tenantId, $mapId, false);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($grid) || (int) ($grid['filled_cells'] ?? 0) < 9) {
            return null;
        }
        $stamp = (string) ($grid['sampled_at'] ?? $grid['updated_at'] ?? '');
        $dir = $this->tileDir($mapId, $z);
        $name = $x . '_' . $y . '.png';
        $path = $dir . '/' . $name;
        $stampFile = $dir . '/' . $x . '_' . $y . '.stamp';
        $stampPayload = $stamp . '|' . sprintf('%.3f,%.3f', $offsetX, $offsetY);
        if (is_file($path) && is_file($stampFile) && trim((string) @file_get_contents($stampFile)) === $stampPayload) {
            return $path;
        }
        $overview = $this->ensureOverview($tenantId, $mapId, $grid, $stamp);
        if ($overview === null || !is_file($overview['path'])) {
            return null;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $written = $this->writeTile($path, $overview, $z, $x, $y, $offsetX, $offsetY);
        if (!$written) {
            return is_file($path) ? $path : null;
        }
        @file_put_contents($stampFile, $stampPayload, LOCK_EX);

        return $path;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{path: string, cols: int, rows: int, origin_x: float, origin_y: float, cell_m: float, world_size: float}|null
     */
    public function ensureOverview(int $tenantId, int $mapId, ?array $meta = null, string $stamp = ''): ?array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        if (!is_array($meta)) {
            try {
                $meta = $this->terrain->getGrid($tenantId, $mapId, false);
            } catch (Throwable) {
                return null;
            }
        }
        if (!is_array($meta) || (int) ($meta['filled_cells'] ?? 0) < 9) {
            return null;
        }
        if ($stamp === '') {
            $stamp = (string) ($meta['sampled_at'] ?? $meta['updated_at'] ?? '');
        }
        $dir = $this->dir($mapId);
        $path = $dir . '/terrain-rgb.png';
        $stampFile = $dir . '/stamp-rgb.txt';
        if (is_file($path) && is_file($stampFile) && trim((string) @file_get_contents($stampFile)) === $stamp && $stamp !== '') {
            return $this->overviewMeta($path, $meta);
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        try {
            $grid = $this->terrain->getGrid($tenantId, $mapId, true);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($grid) || !is_string($grid['heights'] ?? null) || $grid['heights'] === '') {
            return null;
        }
        if (!$this->writeOverview($path, $grid)) {
            return is_file($path) ? $this->overviewMeta($path, $grid) : null;
        }
        @file_put_contents($stampFile, $stamp, LOCK_EX);

        return $this->overviewMeta($path, $grid);
    }

    /**
     * @param array<string, mixed> $grid
     */
    private function writeOverview(string $path, array $grid): bool
    {
        $blob = (string) $grid['heights'];
        $cols = (int) ($grid['cols'] ?? 0);
        $rows = (int) ($grid['rows'] ?? 0);
        if ($cols < 2 || $rows < 2) {
            return false;
        }
        $im = @imagecreatetruecolor($cols, $rows);
        if ($im === false) {
            return false;
        }
        $zero = self::encodeMeters(0.0);
        $bg = imagecolorallocate($im, $zero['r'], $zero['g'], $zero['b']);
        imagefilledrectangle($im, 0, 0, $cols - 1, $rows - 1, $bg);
        for ($r = 0; $r < $rows; $r++) {
            $imgRow = $rows - 1 - $r;
            for ($c = 0; $c < $cols; $c++) {
                $z = AtakTerrainMath::cellZ($blob, $cols, $c, $r);
                $rgb = self::encodeMeters($z === null ? 0.0 : $z);
                imagesetpixel($im, $c, $imgRow, imagecolorallocate($im, $rgb['r'], $rgb['g'], $rgb['b']));
            }
        }
        $ok = imagepng($im, $path, 1);
        imagedestroy($im);

        return $ok === true && is_file($path);
    }

    /**
     * @param array{path: string, cols: int, rows: int, origin_x: float, origin_y: float, cell_m: float, world_size: float} $overview
     */
    private function writeTile(string $path, array $overview, int $z, int $x, int $y, float $offsetX, float $offsetY): bool
    {
        $src = @imagecreatefrompng($overview['path']);
        if ($src === false) {
            return false;
        }
        $tile = imagecreatetruecolor(self::TILE_SIZE, self::TILE_SIZE);
        if ($tile === false) {
            imagedestroy($src);

            return false;
        }
        $zero = self::encodeMeters(0.0);
        $bg = imagecolorallocate($tile, $zero['r'], $zero['g'], $zero['b']);
        imagefilledrectangle($tile, 0, 0, self::TILE_SIZE - 1, self::TILE_SIZE - 1, $bg);

        $world = AtakTheaterProjection::mercatorTileWorld($z, $x, $y, $offsetX, $offsetY);
        $originX = $overview['origin_x'];
        $originY = $overview['origin_y'];
        $cell = max(1.0, $overview['cell_m']);
        $cols = $overview['cols'];
        $rows = $overview['rows'];
        $srcX0 = ($world['minX'] - $originX) / $cell;
        $srcX1 = ($world['maxX'] - $originX) / $cell;
        $srcYNorth = ($rows - 1) - (($world['maxY'] - $originY) / $cell);
        $srcYSouth = ($rows - 1) - (($world['minY'] - $originY) / $cell);
        $sx = (int) floor(min($srcX0, $srcX1));
        $ex = (int) ceil(max($srcX0, $srcX1));
        $sy = (int) floor(min($srcYNorth, $srcYSouth));
        $ey = (int) ceil(max($srcYNorth, $srcYSouth));
        if ($ex < 0 || $ey < 0 || $sx >= $cols || $sy >= $rows) {
            $ok = imagepng($tile, $path, 1);
            imagedestroy($tile);
            imagedestroy($src);

            return $ok === true;
        }
        $sx = max(0, $sx);
        $sy = max(0, $sy);
        $ex = min($cols - 1, $ex);
        $ey = min($rows - 1, $ey);
        $sw = max(1, $ex - $sx + 1);
        $sh = max(1, $ey - $sy + 1);
        $worldW = max(1.0, $world['maxX'] - $world['minX']);
        $worldH = max(1.0, $world['maxY'] - $world['minY']);
        $dstX = (int) round((($sx * $cell + $originX) - $world['minX']) / $worldW * self::TILE_SIZE);
        $dstW = (int) round(($sw * $cell) / $worldW * self::TILE_SIZE);
        $northY = $originY + ($rows - 1 - $sy) * $cell;
        $dstY = (int) round(($world['maxY'] - $northY) / $worldH * self::TILE_SIZE);
        $dstH = (int) round(($sh * $cell) / $worldH * self::TILE_SIZE);
        if ($dstW < 1) {
            $dstW = 1;
        }
        if ($dstH < 1) {
            $dstH = 1;
        }
        imagecopyresampled($tile, $src, $dstX, $dstY, $sx, $sy, $dstW, $dstH, $sw, $sh);
        $ok = imagepng($tile, $path, 1);
        imagedestroy($tile);
        imagedestroy($src);

        return $ok === true && is_file($path);
    }

    /**
     * @param array<string, mixed> $grid
     * @return array{path: string, cols: int, rows: int, origin_x: float, origin_y: float, cell_m: float, world_size: float}
     */
    private function overviewMeta(string $path, array $grid): array
    {
        return [
            'path' => $path,
            'cols' => (int) ($grid['cols'] ?? 0),
            'rows' => (int) ($grid['rows'] ?? 0),
            'origin_x' => (float) ($grid['origin_x'] ?? 0),
            'origin_y' => (float) ($grid['origin_y'] ?? 0),
            'cell_m' => (float) ($grid['cell_m'] ?? 50),
            'world_size' => (float) ($grid['world_size'] ?? 0),
        ];
    }

    private function dir(int $mapId): string
    {
        return base_path('storage/atak_terrain/shared/' . $mapId);
    }

    private function tileDir(int $mapId, int $z): string
    {
        return $this->dir($mapId) . '/rgb/' . $z;
    }
}
