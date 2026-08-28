<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Core\Database;
use App\Repositories\TenantRepository;

/**
 * Bandeau d’identification type caméra-piéton, gravé sur les photos terrain à la réception.
 */
final class ReconPhotoHudService
{
    public const POSITIONS = ['top', 'bottom', 'both'];
    public const STYLES = ['axon', 'discreet'];

    public function defaults(): array
    {
        return [
            'enabled' => true,
            'reviewed' => false,
            'position' => 'top',
            'style' => 'axon',
            'agency' => '',
            'custom_line' => '',
            'show_datetime' => true,
            'show_callsign' => true,
            'show_device' => true,
            'show_grid' => true,
            'show_heading' => false,
            'show_altitude' => false,
            'updated_at' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $tenantId): array
    {
        $merged = $this->defaults();
        $raw = $this->readRaw($tenantId);
        if (is_array($raw)) {
            foreach ($merged as $key => $_) {
                if (array_key_exists($key, $raw)) {
                    $merged[$key] = $raw[$key];
                }
            }
        }
        $merged['enabled'] = !empty($merged['enabled']);
        $merged['reviewed'] = !empty($merged['reviewed']);
        $merged['position'] = $this->normalizePosition((string) $merged['position']);
        $merged['style'] = $this->normalizeStyle((string) $merged['style']);
        $merged['agency'] = $this->clip((string) $merged['agency'], 80);
        $merged['custom_line'] = $this->clip((string) $merged['custom_line'], 80);
        foreach (['show_datetime', 'show_callsign', 'show_device', 'show_grid', 'show_heading', 'show_altitude'] as $flag) {
            $merged[$flag] = !empty($merged[$flag]);
        }
        if (trim((string) $merged['agency']) === '') {
            $merged['agency'] = $this->tenantName($tenantId);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function put(int $tenantId, array $incoming): array
    {
        $merged = $this->defaults();
        $merged['enabled'] = !empty($incoming['enabled']);
        $merged['reviewed'] = true;
        $merged['position'] = $this->normalizePosition((string) ($incoming['position'] ?? 'top'));
        $merged['style'] = $this->normalizeStyle((string) ($incoming['style'] ?? 'axon'));
        $merged['agency'] = $this->clip(trim((string) ($incoming['agency'] ?? '')), 80);
        $merged['custom_line'] = $this->clip(trim((string) ($incoming['custom_line'] ?? '')), 80);
        foreach (['show_datetime', 'show_callsign', 'show_device', 'show_grid', 'show_heading', 'show_altitude'] as $flag) {
            $merged[$flag] = !empty($incoming[$flag]);
        }
        $merged['updated_at'] = gmdate('c');
        $this->writeRaw($tenantId, $merged);

        return $this->get($tenantId);
    }

    public function isReviewed(int $tenantId): bool
    {
        $raw = $this->readRaw($tenantId);

        return is_array($raw) && !empty($raw['reviewed']);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function applyToFile(int $tenantId, string $absolutePath, array $meta): bool
    {
        $cfg = $this->get($tenantId);
        if (empty($cfg['enabled'])) {
            return true;
        }
        if ($absolutePath === '' || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return false;
        }
        $bytes = @filesize($absolutePath);
        if ($bytes === false || $bytes < 32 || $bytes > 8 * 1024 * 1024) {
            return false;
        }
        $info = @getimagesize($absolutePath);
        if (!is_array($info) || ($info[0] ?? 0) < 80 || ($info[1] ?? 0) < 80) {
            return false;
        }
        if (((int) $info[0] * (int) $info[1]) > 3_500_000) {
            return false;
        }
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $bin = @file_get_contents($absolutePath);
        if ($bin === false || $bin === '') {
            return false;
        }
        $magic = substr($bin, 0, 3);
        $im = @imagecreatefromstring($bin);
        unset($bin);
        if ($im === false) {
            return false;
        }

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 80 || $h < 80) {
            imagedestroy($im);

            return false;
        }

        imagealphablending($im, true);
        $this->paint($im, $w, $h, $cfg, $meta);

        $ok = $this->saveSameFormat($im, $absolutePath, $magic);
        imagedestroy($im);

        return $ok;
    }

    /**
     * Aperçu PNG (data URI) pour l’écran de configuration.
     *
     * @param array<string, mixed>|null $cfg
     */
    public function previewDataUri(int $tenantId, ?array $cfg = null): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }
        $cfg = $cfg ?? $this->get($tenantId);
        $w = 960;
        $h = 540;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            return '';
        }
        imagealphablending($im, true);
        $sky = imagecolorallocate($im, 42, 56, 48);
        $ground = imagecolorallocate($im, 88, 92, 70);
        imagefilledrectangle($im, 0, 0, $w, (int) ($h * 0.55), $sky);
        imagefilledrectangle($im, 0, (int) ($h * 0.55), $w, $h, $ground);
        $this->paint($im, $w, $h, $cfg, [
            'author_callsign' => 'N-10',
            'device_type' => 'HELMET',
            'grid_ref' => '200092',
            'heading' => 142,
            'altitude' => 18,
            'captured_at' => time(),
        ]);

        ob_start();
        imagepng($im, null, 6);
        $png = ob_get_clean();
        imagedestroy($im);
        if (!is_string($png) || $png === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * @param \GdImage $im
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $meta
     */
    private function paint($im, int $w, int $h, array $cfg, array $meta): void
    {
        $style = $this->normalizeStyle((string) ($cfg['style'] ?? 'axon'));
        $position = $this->normalizePosition((string) ($cfg['position'] ?? 'top'));
        $barRatio = $style === 'discreet' ? 0.055 : 0.078;
        $barH = max(28, min(110, (int) round($h * $barRatio)));
        $alpha = $style === 'discreet' ? 55 : 32;
        $bar = imagecolorallocatealpha($im, 6, 8, 10, $alpha);
        $white = imagecolorallocate($im, 236, 241, 245);
        $muted = imagecolorallocate($im, 168, 180, 188);
        $rec = imagecolorallocate($im, 220, 38, 38);

        $lines = $this->composeLines($cfg, $meta, $style, $position);
        $drawTop = $position === 'top' || $position === 'both';
        $drawBottom = $position === 'bottom' || $position === 'both';

        if ($drawTop) {
            imagefilledrectangle($im, 0, 0, $w - 1, $barH, $bar);
            $this->drawBarContent($im, 0, $barH, $w, $lines['top'], $white, $muted, $rec, $style === 'axon');
        }
        if ($drawBottom) {
            $y0 = $h - $barH;
            imagefilledrectangle($im, 0, $y0, $w - 1, $h - 1, $bar);
            $this->drawBarContent($im, $y0, $barH, $w, $lines['bottom'], $white, $muted, $rec, $style === 'axon' && !$drawTop);
        }
    }

    /**
     * @param \GdImage $im
     * @param list<string> $partsLeft
     * @param array{left: list<string>, right: string} $pack
     */
    private function drawBarContent($im, int $y0, int $barH, int $w, array $pack, int $white, int $muted, int $rec, bool $showRec): void
    {
        $padX = max(10, (int) round($w * 0.016));
        $fontSize = max(10, min(26, (int) round($barH * 0.38)));
        $font = $this->resolveFont();
        $left = implode('   ·   ', array_values(array_filter($pack['left'], static fn ($s) => $s !== '')));
        $right = (string) ($pack['right'] ?? '');
        $textY = $y0 + (int) round($barH * 0.68);
        $x = $padX;

        if ($showRec) {
            $cy = $y0 + (int) round($barH / 2);
            $r = max(4, (int) round($barH * 0.14));
            imagefilledellipse($im, $x + $r, $cy, $r * 2, $r * 2, $rec);
            $x += $r * 2 + (int) round($barH * 0.18);
            $this->text($im, $font, $fontSize, $x, $textY, 'REC', $white);
            $x += $this->textWidth($font, $fontSize, 'REC') + (int) round($barH * 0.35);
        }

        if ($left !== '') {
            $this->text($im, $font, $fontSize, $x, $textY, $left, $white);
        }
        if ($right !== '') {
            $rw = $this->textWidth($font, $fontSize, $right);
            $this->text($im, $font, $fontSize, max($padX, $w - $padX - $rw), $textY, $right, $muted);
        }
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $meta
     * @return array{top: array{left: list<string>, right: string}, bottom: array{left: list<string>, right: string}}
     */
    private function composeLines(array $cfg, array $meta, string $style, string $position): array
    {
        $device = $this->deviceLabel((string) ($meta['device_type'] ?? 'CTAB'));
        $callsign = strtoupper(trim((string) ($meta['author_callsign'] ?? $meta['author'] ?? '')));
        $grid = trim((string) ($meta['grid_ref'] ?? $meta['grid'] ?? ''));
        $grid = preg_replace('/\s+/', ' ', $grid) ?? $grid;
        $agency = trim((string) ($cfg['agency'] ?? ''));
        $custom = trim((string) ($cfg['custom_line'] ?? ''));
        $when = $this->formatStamp($meta['captured_at'] ?? null);

        $identity = [];
        if (!empty($cfg['show_device']) && $device !== '') {
            $identity[] = $device;
        }
        if (!empty($cfg['show_callsign']) && $callsign !== '') {
            $identity[] = $callsign;
        }

        $geo = [];
        if (!empty($cfg['show_grid']) && $grid !== '') {
            $geo[] = 'GRILLE ' . $grid;
        }
        if (!empty($cfg['show_heading']) && isset($meta['heading']) && is_numeric($meta['heading'])) {
            $geo[] = sprintf('CAP %03d°', ((int) round((float) $meta['heading'])) % 360);
        }
        if (!empty($cfg['show_altitude']) && isset($meta['altitude']) && is_numeric($meta['altitude'])) {
            $geo[] = round((float) $meta['altitude']) . ' m';
        }

        $time = (!empty($cfg['show_datetime']) && $when !== '') ? $when : '';

        $topLeft = $identity;
        if ($style === 'axon' && $agency !== '') {
            array_unshift($topLeft, $agency);
        }
        $topRight = $time;

        $bottomLeft = [];
        if ($agency !== '' && $style !== 'axon') {
            $bottomLeft[] = $agency;
        }
        $bottomLeft = array_merge($bottomLeft, $geo);
        if ($custom !== '') {
            $bottomLeft[] = $custom;
        }

        if ($style === 'axon') {
            $topLeft = array_merge($topLeft, $geo);
            if ($custom !== '') {
                $topLeft[] = $custom;
            }
            $bottomLeft = $agency !== '' ? [$agency] : $geo;
            if ($custom !== '' && $agency !== '') {
                $bottomLeft[] = $custom;
            }
        }

        if ($position === 'bottom') {
            $allLeft = array_values(array_unique(array_merge($topLeft, $bottomLeft)));
            $bottomLeft = $allLeft;
            $topLeft = [];
            $topRight = '';
        }

        return [
            'top' => ['left' => $topLeft, 'right' => $topRight],
            'bottom' => ['left' => $bottomLeft, 'right' => $position === 'bottom' ? $time : ($style === 'axon' ? $time : '')],
        ];
    }

    /**
     * @param \GdImage $im
     */
    private function text($im, ?string $font, int $size, int $x, int $y, string $text, int $color): void
    {
        $text = $this->latin($text);
        if ($font !== null && function_exists('imagettftext')) {
            @imagettftext($im, $size, 0, $x, $y, $color, $font, $text);

            return;
        }
        imagestring($im, 5, $x, max(0, $y - 12), $text, $color);
    }

    private function textWidth(?string $font, int $size, string $text): int
    {
        $text = $this->latin($text);
        if ($font !== null && function_exists('imagettfbbox')) {
            $box = @imagettfbbox($size, 0, $font, $text);
            if (is_array($box)) {
                return abs((int) $box[2] - (int) $box[0]);
            }
        }

        return strlen($text) * 9;
    }

    private function latin(string $text): string
    {
        $text = str_replace(['’', '‘', '–', '—'], ["'", "'", '-', '-'], $text);

        return $text;
    }

    private function formatStamp(mixed $capturedAt): string
    {
        $ts = time();
        if (is_int($capturedAt) && $capturedAt > 1_000_000_000) {
            $ts = $capturedAt;
        } elseif (is_numeric($capturedAt) && (int) $capturedAt > 1_000_000_000) {
            $ts = (int) $capturedAt;
        } elseif (is_string($capturedAt) && $capturedAt !== '') {
            $parsed = strtotime($capturedAt);
            if ($parsed !== false) {
                $ts = $parsed;
            }
        }

        return gmdate('d/m/Y  H:i:s', $ts) . ' Z';
    }

    private function deviceLabel(string $deviceType): string
    {
        return match (strtoupper(trim($deviceType))) {
            'HELMET', 'HCAM' => 'CAMÉRA CASQUE',
            'DRONE' => 'CAMÉRA DRONE',
            'UAV', 'VEHICLE' => 'CAMÉRA AÉRIENNE',
            'CTAB', 'TABLET' => 'PHOTO TABLETTE',
            default => 'PHOTO TERRAIN',
        };
    }

    private function normalizePosition(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::POSITIONS, true) ? $value : 'top';
    }

    private function normalizeStyle(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::STYLES, true) ? $value : 'axon';
    }

    private function clip(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function tenantName(int $tenantId): string
    {
        if ($tenantId < 1) {
            return '';
        }
        try {
            $row = (new TenantRepository())->findById($tenantId);
            $name = trim((string) ($row['name'] ?? ''));

            return $this->clip($name, 80);
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveFont(): ?string
    {
        static $cached = false;
        static $path = null;
        if ($cached) {
            return $path;
        }
        $cached = true;
        $candidates = [
            'C:\\Windows\\Fonts\\consola.ttf',
            'C:\\Windows\\Fonts\\courbd.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeMonoBold.ttf',
        ];
        if (function_exists('base_path')) {
            array_unshift($candidates, base_path('storage/fonts/DejaVuSansMono.ttf'));
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $path = $candidate;

                return $path;
            }
        }

        return null;
    }

    /**
     * @param \GdImage $im
     */
    private function saveSameFormat($im, string $path, string $originalBin): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            return (bool) @imagepng($im, $path, 6);
        }
        if ($ext === 'webp' && function_exists('imagewebp')) {
            return (bool) @imagewebp($im, $path, 88);
        }
        if ($ext === 'gif') {
            return (bool) @imagegif($im, $path);
        }

        $quality = 90;
        if (str_starts_with($originalBin, "\xFF\xD8")) {
            return (bool) @imagejpeg($im, $path, $quality);
        }
        if ($ext === 'jpg' || $ext === 'jpeg') {
            return (bool) @imagejpeg($im, $path, $quality);
        }

        return (bool) @imagepng($im, $path, 6);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRaw(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $this->ensureSchema();
        if (!$this->hasColumn()) {
            return null;
        }
        try {
            $st = Database::getPdo()->prepare(
                'SELECT photo_hud_config FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1'
            );
            $st->execute([$tenantId]);
            $raw = $st->fetchColumn();
            if (!is_string($raw) || trim($raw) === '') {
                return null;
            }
            $data = json_decode($raw, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeRaw(int $tenantId, array $config): void
    {
        if ($tenantId < 1) {
            return;
        }
        $this->ensureSchema();
        if (!$this->hasColumn()) {
            return;
        }
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        $pdo = Database::getPdo();
        try {
            $st = $pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            if ($st->fetchColumn()) {
                $upd = $pdo->prepare(
                    'UPDATE tenant_atak_config SET photo_hud_config = ?, updated_at = NOW() WHERE tenant_id = ?'
                );
                $upd->execute([$json, $tenantId]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO tenant_atak_config (tenant_id, photo_hud_config, default_map_slug, created_at, updated_at)
                     VALUES (?, ?, \'altis\', NOW(), NOW())'
                );
                $ins->execute([$tenantId, $json]);
            }
        } catch (\Throwable) {
        }
    }

    private function ensureSchema(): void
    {
        static $attempted = false;
        if ($attempted) {
            return;
        }
        $attempted = true;
        if ($this->hasColumn()) {
            return;
        }
        try {
            Database::getPdo()->exec(
                'ALTER TABLE tenant_atak_config ADD COLUMN photo_hud_config JSON DEFAULT NULL'
            );
        } catch (\Throwable) {
        }
    }

    private function hasColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = Database::getPdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config'
                   AND COLUMN_NAME = 'photo_hud_config' LIMIT 1"
            );
            $cached = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }
}
