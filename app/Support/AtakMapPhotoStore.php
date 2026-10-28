<?php

declare(strict_types=1);

namespace App\Support;

use ZipArchive;

/**
 * Fonds de carte « Photo COMSPEC » : pyramides de tuiles faites par la capture de la carte du mod
 * (fn_mapCapture puis tools/map_stitch.py --pyramid), une par théâtre et par rendu (satellite, plan).
 *
 * storage/map-photo/{carte}/{rendu}/meta.json et {z}/{x}/{y}.webp ; mètres Arma, côté « extent », tuile y = 0 au nord.
 * Une tuile absente (zone capturée moins finement) est découpée dans l'ancêtre le plus proche puis gardée en cache
 * (_fill), pour que le zoom reste photo partout.
 * Même pyramide en JPEG ({z}/{x}/{y}.jpg, envoyée à côté par map_stitch.py --upload) pour le téléphone ATAK : Arma ne
 * lit pas le WebP, la DLL télécharge ces tuiles en jeu (fond PHOTO sans mod). Pas de découpe d'ancêtre en JPEG : le
 * téléphone dessine lui-même l'ancêtre qu'il a. À la publication : _stats.json (nombre et poids des tuiles par format)
 * et index-jpg.txt (une tuile JPEG « z/x/y » par ligne, pour que la DLL ne demande que les tuiles qui existent).
 * L'envoi se fait par paquets ZIP : le premier (reset) ouvre un dossier d'arrivée, le dernier (avec meta.json)
 * remplace d'un coup l'ancien fond.
 */
final class AtakMapPhotoStore
{
    public const MODES = ['sat', 'plan'];
    public const EXTS = ['webp', 'jpg'];
    private const TILE = 256;
    private const MAX_ENTRIES = 20000;
    private const MAX_ENTRY_BYTES = 2 * 1024 * 1024;
    private const MAX_FALLBACK_LEVELS = 6;

    public function __construct(private ?string $root = null)
    {
        $this->root ??= dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'map-photo';
    }

    public static function world(?string $raw): ?string
    {
        $w = strtolower(trim((string) $raw));
        if ($w === '' || preg_match('/^[a-z0-9_-]{1,64}$/', $w) !== 1) {
            return null;
        }

        return $w;
    }

    public static function mode(?string $raw): ?string
    {
        $m = strtolower(trim((string) $raw));

        return in_array($m, self::MODES, true) ? $m : null;
    }

    public function dir(string $world, string $mode): string
    {
        return $this->root . DIRECTORY_SEPARATOR . $world . DIRECTORY_SEPARATOR . $mode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(string $world, string $mode): ?array
    {
        $file = $this->dir($world, $mode) . DIRECTORY_SEPARATOR . 'meta.json';
        if (!is_file($file)) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($file), true);
        if (!is_array($meta) || (float) ($meta['extent'] ?? 0) <= 0) {
            return null;
        }

        return $meta;
    }

    /**
     * Calque Leaflet (même forme que les calques Atlas de atak-aerial.js), ou null sans fond envoyé.
     *
     * @return array{tilePattern:string,factorX:float,factorY:float,tileSize:int,minZoom:int,maxZoom:int,attribution:string}|null
     */
    public function overlaySpec(string $world, string $mode, string $baseUrl): ?array
    {
        $meta = $this->meta($world, $mode);
        if ($meta === null) {
            return null;
        }
        $ts = (int) ($meta['tileSize'] ?? self::TILE);
        $factor = $ts / (float) $meta['extent'];

        return [
            'tilePattern' => rtrim($baseUrl, '/') . '/map-photo/' . $world . '/' . $mode . '/{z}/{x}/{y}.webp',
            'factorX' => $factor,
            'factorY' => $factor,
            'tileSize' => $ts,
            'minZoom' => (int) ($meta['minZoom'] ?? 0),
            'maxZoom' => (int) ($meta['maxZoom'] ?? 0),
            'attribution' => '&copy; Bohemia Interactive · capture COMSPEC',
        ];
    }

    /**
     * Fichier de la tuile, découpée dans un ancêtre si elle manque (WebP seulement) ; null hors du fond.
     */
    public function tileFile(string $world, string $mode, int $z, int $x, int $y, string $ext = 'webp'): ?string
    {
        if (!in_array($ext, self::EXTS, true)) {
            return null;
        }
        $meta = $this->meta($world, $mode);
        if ($meta === null || $z < 0 || $z > (int) ($meta['maxZoom'] ?? 0) || $z > 22) {
            return null;
        }
        $n = 1 << $z;
        if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
            return null;
        }
        $dir = $this->dir($world, $mode);
        $own = $dir . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x . DIRECTORY_SEPARATOR . $y . '.' . $ext;
        if (is_file($own)) {
            return $own;
        }
        if ($ext === 'jpg') {
            return null;
        }
        $fill = $dir . DIRECTORY_SEPARATOR . '_fill' . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x . DIRECTORY_SEPARATOR . $y . '.webp';
        if (is_file($fill)) {
            return $fill;
        }
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return null;
        }
        for ($k = 1; $k <= min($z, self::MAX_FALLBACK_LEVELS); $k++) {
            $az = $z - $k;
            $ax = $x >> $k;
            $ay = $y >> $k;
            $parent = $dir . DIRECTORY_SEPARATOR . $az . DIRECTORY_SEPARATOR . $ax . DIRECTORY_SEPARATOR . $ay . '.webp';
            if (!is_file($parent)) {
                continue;
            }

            return $this->cropAncestor($parent, $fill, $k, $x - ($ax << $k), $y - ($ay << $k));
        }

        return null;
    }

    private function cropAncestor(string $parent, string $target, int $k, int $dx, int $dy): ?string
    {
        $src = @imagecreatefromwebp($parent);
        if ($src === false) {
            return null;
        }
        $ts = imagesx($src);
        $part = max(1, intdiv($ts, 1 << $k));
        $dst = imagecreatetruecolor($ts, $ts);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, $dx * $part, $dy * $part, $ts, $ts, $part, $part);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = imagewebp($dst, $tmp, 82);
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok || !@rename($tmp, $target)) {
            @unlink($tmp);

            return null;
        }

        return $target;
    }

    /**
     * Range un paquet ZIP envoyé par map_stitch.py --upload.
     *
     * @return array{ok:bool,files?:int,published?:bool,error?:string}
     */
    public function importZip(string $world, string $mode, string $zipFile, bool $reset, bool $done): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'zip_unavailable'];
        }
        $incoming = $this->dir($world, $mode) . '.incoming';
        if ($reset) {
            self::removeTree($incoming);
        }
        if (!is_dir($incoming) && !@mkdir($incoming, 0775, true) && !is_dir($incoming)) {
            return ['ok' => false, 'error' => 'storage_not_writable'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['ok' => false, 'error' => 'bad_zip'];
        }
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();

            return ['ok' => false, 'error' => 'too_many_files'];
        }
        $written = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            $isMeta = $name === 'meta.json';
            if (!$isMeta && preg_match('#^(\d{1,2})/(\d{1,7})/(\d{1,7})\.(webp|jpg)$#', $name, $em) !== 1) {
                continue;
            }
            if ((int) ($stat['size'] ?? 0) > self::MAX_ENTRY_BYTES) {
                continue;
            }
            $data = $zip->getFromIndex($i);
            if (!is_string($data) || $data === '') {
                continue;
            }
            if ($isMeta) {
                $meta = json_decode($data, true);
                if (!is_array($meta) || (float) ($meta['extent'] ?? 0) <= 0) {
                    continue;
                }
            } elseif ($em[4] === 'webp' && (!str_starts_with($data, 'RIFF') || substr($data, 8, 4) !== 'WEBP')) {
                continue;
            } elseif ($em[4] === 'jpg' && !str_starts_with($data, "\xFF\xD8\xFF")) {
                continue;
            }
            $target = $incoming . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $dir = dirname($target);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($target, $data) !== false) {
                $written++;
            }
        }
        $zip->close();
        $published = false;
        if ($done) {
            if (!is_file($incoming . DIRECTORY_SEPARATOR . 'meta.json')) {
                return ['ok' => false, 'files' => $written, 'error' => 'meta_missing'];
            }
            $live = $this->dir($world, $mode);
            $old = $live . '.old';
            self::removeTree($old);
            if (is_dir($live) && !@rename($live, $old)) {
                return ['ok' => false, 'files' => $written, 'error' => 'swap_failed'];
            }
            $this->writeIndex($incoming);
            if (!@rename($incoming, $live)) {
                if (is_dir($old)) {
                    @rename($old, $live);
                }

                return ['ok' => false, 'files' => $written, 'error' => 'swap_failed'];
            }
            self::removeTree($old);
            $published = true;
        }

        return ['ok' => true, 'files' => $written, 'published' => $published];
    }

    /**
     * Nombre et poids des tuiles par format (_stats.json, écrit à la publication ; recalculé pour un fond plus ancien).
     *
     * @return array{webpTiles:int,webpBytes:int,jpgTiles:int,jpgBytes:int}
     */
    public function stats(string $world, string $mode): array
    {
        $dir = $this->dir($world, $mode);
        $file = $dir . DIRECTORY_SEPARATOR . '_stats.json';
        $st = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (is_array($st) && isset($st['webpTiles'], $st['jpgTiles'])) {
            return [
                'webpTiles' => (int) $st['webpTiles'], 'webpBytes' => (int) ($st['webpBytes'] ?? 0),
                'jpgTiles' => (int) $st['jpgTiles'], 'jpgBytes' => (int) ($st['jpgBytes'] ?? 0),
            ];
        }
        if (!is_dir($dir)) {
            return ['webpTiles' => 0, 'webpBytes' => 0, 'jpgTiles' => 0, 'jpgBytes' => 0];
        }

        return $this->writeIndex($dir);
    }

    /**
     * Tuiles JPEG du fond (« z/x/y » par ligne), ou null sans fond ou sans JPEG.
     */
    public function jpgIndex(string $world, string $mode): ?string
    {
        if ($this->meta($world, $mode) === null) {
            return null;
        }
        $file = $this->dir($world, $mode) . DIRECTORY_SEPARATOR . 'index-jpg.txt';
        if (!is_file($file)) {
            $this->writeIndex($this->dir($world, $mode));
        }
        $txt = is_file($file) ? (string) file_get_contents($file) : '';

        return $txt === '' ? null : $txt;
    }

    /**
     * Fonds publiés : carte, rendu, niveau maximal, tuiles et poids (tous formats), date, et la part JPEG (jeu).
     *
     * @return list<array{world:string,mode:string,maxZoom:int,tiles:int,bytes:int,created:string,jpgTiles:int,jpgBytes:int}>
     */
    public function listAll(): array
    {
        $out = [];
        if (!is_dir((string) $this->root)) {
            return $out;
        }
        foreach (scandir((string) $this->root) ?: [] as $w) {
            // Dossiers d'arrivée et anciens fonds (altis/sat.incoming…) : sous la carte, jamais à ce niveau.
            if (self::world($w) !== $w) {
                continue;
            }
            foreach (self::MODES as $mode) {
                $meta = $this->meta($w, $mode);
                if ($meta === null) {
                    continue;
                }
                $st = $this->stats($w, $mode);
                $out[] = [
                    'world' => $w,
                    'mode' => $mode,
                    'maxZoom' => (int) ($meta['maxZoom'] ?? 0),
                    'tiles' => $st['webpTiles'] + $st['jpgTiles'],
                    'bytes' => $st['webpBytes'] + $st['jpgBytes'],
                    'created' => (string) ($meta['created'] ?? ''),
                    'jpgTiles' => $st['jpgTiles'],
                    'jpgBytes' => $st['jpgBytes'],
                ];
            }
        }

        return $out;
    }

    /**
     * Relève les tuiles d'un dossier de fond (_fill exclu) : écrit _stats.json et index-jpg.txt.
     *
     * @return array{webpTiles:int,webpBytes:int,jpgTiles:int,jpgBytes:int}
     */
    private function writeIndex(string $dir): array
    {
        $st = ['webpTiles' => 0, 'webpBytes' => 0, 'jpgTiles' => 0, 'jpgBytes' => 0];
        $jpg = [];
        foreach (scandir($dir) ?: [] as $z) {
            if (preg_match('/^\d{1,2}$/', $z) !== 1 || !is_dir($dir . DIRECTORY_SEPARATOR . $z)) {
                continue;
            }
            foreach (scandir($dir . DIRECTORY_SEPARATOR . $z) ?: [] as $x) {
                $xd = $dir . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x;
                if (preg_match('/^\d{1,7}$/', $x) !== 1 || !is_dir($xd)) {
                    continue;
                }
                foreach (scandir($xd) ?: [] as $f) {
                    if (preg_match('/^(\d{1,7})\.(webp|jpg)$/', $f, $m) !== 1) {
                        continue;
                    }
                    $st[$m[2] . 'Tiles']++;
                    $st[$m[2] . 'Bytes'] += (int) @filesize($xd . DIRECTORY_SEPARATOR . $f);
                    if ($m[2] === 'jpg') {
                        $jpg[] = $z . '/' . $x . '/' . $m[1];
                    }
                }
            }
        }
        @file_put_contents($dir . DIRECTORY_SEPARATOR . '_stats.json', (string) json_encode($st));
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index-jpg.txt', $jpg === [] ? '' : implode("\n", $jpg) . "\n");

        return $st;
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
