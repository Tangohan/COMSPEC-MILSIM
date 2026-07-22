<?php

declare(strict_types=1);

namespace App\Services\Qr;

/**
 * Génère un QR code image (PNG de préférence, SVG en dernier recours).
 *
 * Chaîne de secours :
 * 1. Endroid (Composer) — PNG puis SVG
 * 2. phpqrcode + GD
 * 3. phpqrcode (matrice) + PNG pur PHP (zlib, sans GD)
 * 4. phpqrcode (matrice) + SVG
 */
final class QrPngGenerator
{
    /** @var list<string> */
    private array $attempts = [];

    /**
     * @param bool $pngOnly Si true (ex. client Arma), n’accepte que du PNG binaire — pas de SVG.
     * @return array{body: string, mime: string}|null
     */
    public function png(string $payload, int $sizePixels = 400, int $margin = 12, bool $pngOnly = false): ?array
    {
        $this->attempts = [];
        $payload = trim($payload);
        if ($payload === '') {
            $this->attempts[] = 'empty_payload';
            return null;
        }

        $sizePixels = max(120, $sizePixels);
        $margin = max(0, $margin);

        $viaEndroid = $this->viaEndroid($payload, $sizePixels, $margin, $pngOnly);
        if ($viaEndroid !== null) {
            return $viaEndroid;
        }

        $viaPhpQr = $this->viaPhpQrCode($payload, $sizePixels, $margin, $pngOnly);
        if ($viaPhpQr !== null) {
            return $viaPhpQr;
        }

        return null;
    }

    /**
     * Diagnostic des tentatives échouées (pour logs serveur uniquement).
     *
     * @return list<string>
     */
    public function attempts(): array
    {
        return $this->attempts;
    }

    /**
     * @return array{body: string, mime: string}|null
     */
    private function viaEndroid(string $payload, int $sizePixels, int $margin, bool $pngOnly): ?array
    {
        if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
            $this->attempts[] = 'endroid:class_missing';
            return null;
        }

        $pngWriterClass = \Endroid\QrCode\Writer\PngWriter::class;
        $svgWriterClass = \Endroid\QrCode\Writer\SvgWriter::class;

        if (class_exists($pngWriterClass)) {
            $result = $this->buildEndroid($payload, $sizePixels, $margin, new $pngWriterClass());
            if ($result !== null) {
                return $result;
            }
        } else {
            $this->attempts[] = 'endroid:png_writer_missing';
        }

        // SvgWriter : pas de GD requis (utile si Endroid installé mais ext-gd absente).
        // Skip si le consommateur (Arma RscPicture) n’accepte que du PNG.
        if (!$pngOnly) {
            if (class_exists($svgWriterClass)) {
                $result = $this->buildEndroid($payload, $sizePixels, $margin, new $svgWriterClass());
                if ($result !== null) {
                    return $result;
                }
            } else {
                $this->attempts[] = 'endroid:svg_writer_missing';
            }
        }

        return null;
    }

    /**
     * Compatible Endroid 5 (Builder::create()->…) et 6+ (new Builder(…)->build()).
     *
     * @return array{body: string, mime: string}|null
     */
    private function buildEndroid(string $payload, int $sizePixels, int $margin, object $writer): ?array
    {
        $writerLabel = $writer::class;
        try {
            if (method_exists(\Endroid\QrCode\Builder\Builder::class, 'create')) {
                $result = \Endroid\QrCode\Builder\Builder::create()
                    ->writer($writer)
                    ->data($payload)
                    ->size($sizePixels)
                    ->margin($margin)
                    ->build();
            } else {
                $result = (new \Endroid\QrCode\Builder\Builder(
                    writer: $writer,
                    data: $payload,
                    size: $sizePixels,
                    margin: $margin,
                ))->build();
            }

            $body = $result->getString();
            if ($body === '') {
                $this->attempts[] = 'endroid:empty_body:' . $writerLabel;
                return null;
            }

            return [
                'body' => $body,
                'mime' => $result->getMimeType() ?: $this->guessMime($body),
            ];
        } catch (\Throwable $e) {
            $this->attempts[] = 'endroid:exception:' . $writerLabel . ':' . $e->getMessage();
            return null;
        }
    }

    /**
     * @return array{body: string, mime: string}|null
     */
    private function viaPhpQrCode(string $payload, int $sizePixels, int $margin, bool $pngOnly): ?array
    {
        $lib = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'phpqrcode' . DIRECTORY_SEPARATOR . 'qrlib.php';
        if (!is_file($lib)) {
            $this->attempts[] = 'phpqrcode:lib_missing:' . $lib;
            return null;
        }
        if (!class_exists('QRcode', false)) {
            require_once $lib;
        }
        if (!class_exists('QRcode', false)) {
            $this->attempts[] = 'phpqrcode:class_missing_after_require';
            return null;
        }

        $moduleSize = max(4, (int) round($sizePixels / 50));
        $outer = max(1, (int) round($margin / 4));
        $level = defined('QR_ECLEVEL_M') ? QR_ECLEVEL_M : 1;

        // 1) PNG via GD (chemin historique).
        if (extension_loaded('gd')) {
            $tmp = tempnam(sys_get_temp_dir(), 'comspec_qr_');
            if ($tmp !== false) {
                $pngPath = $tmp . '.png';
                @unlink($tmp);
                try {
                    \QRcode::png($payload, $pngPath, $level, $moduleSize, $outer);
                    if (is_file($pngPath)) {
                        $body = (string) file_get_contents($pngPath);
                        if ($body !== '' && strncmp($body, "\x89PNG", 4) === 0) {
                            return [
                                'body' => $body,
                                'mime' => 'image/png',
                            ];
                        }
                        $this->attempts[] = 'phpqrcode:gd_invalid_png';
                    } else {
                        $this->attempts[] = 'phpqrcode:gd_no_file';
                    }
                } catch (\Throwable $e) {
                    $this->attempts[] = 'phpqrcode:gd_exception:' . $e->getMessage();
                } finally {
                    if (isset($pngPath) && is_file($pngPath)) {
                        @unlink($pngPath);
                    }
                }
            } else {
                $this->attempts[] = 'phpqrcode:tempnam_failed';
            }
        } else {
            $this->attempts[] = 'phpqrcode:gd_missing';
        }

        // 2) Matrice seule (sans GD) → PNG zlib ou SVG.
        try {
            $frame = \QRcode::text($payload, false, $level, $moduleSize, $outer);
        } catch (\Throwable $e) {
            $this->attempts[] = 'phpqrcode:text_exception:' . $e->getMessage();
            return null;
        }
        if (!is_array($frame) || $frame === []) {
            $this->attempts[] = 'phpqrcode:empty_frame';
            return null;
        }

        $png = $this->frameToPng($frame, $moduleSize, $outer);
        if ($png !== null) {
            return [
                'body' => $png,
                'mime' => 'image/png',
            ];
        }

        if ($pngOnly) {
            $this->attempts[] = 'phpqrcode:png_only_no_png';
            return null;
        }

        $svg = $this->frameToSvg($frame, $moduleSize, $outer);
        if ($svg !== null) {
            return [
                'body' => $svg,
                'mime' => 'image/svg+xml; charset=UTF-8',
            ];
        }

        return null;
    }

    /**
     * PNG indexé 1-bit à partir d’une matrice phpqrcode (lignes de '0'/'1'), sans GD.
     * Requiert l’extension zlib (gzcompress) — présente sur presque tous les PHP.
     *
     * @param list<string> $frame
     */
    private function frameToPng(array $frame, int $moduleSize, int $outer): ?string
    {
        if (!function_exists('gzcompress')) {
            $this->attempts[] = 'pure_png:zlib_missing';
            return null;
        }

        $modules = count($frame);
        $moduleW = strlen($frame[0] ?? '');
        if ($modules < 1 || $moduleW < 1) {
            $this->attempts[] = 'pure_png:bad_frame';
            return null;
        }

        $pixelPerPoint = max(1, $moduleSize);
        $width = ($moduleW + 2 * $outer) * $pixelPerPoint;
        $height = ($modules + 2 * $outer) * $pixelPerPoint;
        if ($width > 4096 || $height > 4096) {
            $this->attempts[] = 'pure_png:too_large';
            return null;
        }

        // Niveaux de gris 8 bits (color type 0) — compatible navigateurs / décodeurs.
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00"; // filtre None
            for ($x = 0; $x < $width; $x++) {
                $mx = intdiv($x, $pixelPerPoint) - $outer;
                $my = intdiv($y, $pixelPerPoint) - $outer;
                $dark = ($mx >= 0 && $my >= 0 && $mx < $moduleW && $my < $modules
                    && ($frame[$my][$mx] ?? '0') === '1');
                $raw .= $dark ? "\x00" : "\xff";
            }
        }

        $compressed = gzcompress($raw, 9);
        if ($compressed === false) {
            $this->attempts[] = 'pure_png:gzcompress_failed';
            return null;
        }

        // IHDR : width, height, bit depth 8, color type 0 (greyscale), compression/filter/interlace 0
        $ihdr = pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0);

        $png = "\x89PNG\r\n\x1a\n";
        $png .= $this->pngChunk('IHDR', $ihdr);
        $png .= $this->pngChunk('IDAT', $compressed);
        $png .= $this->pngChunk('IEND', '');

        return $png;
    }

    private function pngChunk(string $type, string $data): string
    {
        // crc32 peut être négatif sur 32-bit ; pack('N') attend un unsigned 32-bit.
        $crc = crc32($type . $data) & 0xffffffff;

        return pack('N', strlen($data)) . $type . $data . pack('N', $crc);
    }

    /**
     * @param list<string> $frame
     */
    private function frameToSvg(array $frame, int $moduleSize, int $outer): ?string
    {
        $modules = count($frame);
        $moduleW = strlen($frame[0] ?? '');
        if ($modules < 1 || $moduleW < 1) {
            $this->attempts[] = 'svg:bad_frame';
            return null;
        }

        $pixelPerPoint = max(1, $moduleSize);
        $width = ($moduleW + 2 * $outer) * $pixelPerPoint;
        $height = ($modules + 2 * $outer) * $pixelPerPoint;
        $rects = [];
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $moduleW; $x++) {
                if (($frame[$y][$x] ?? '0') !== '1') {
                    continue;
                }
                $px = ($x + $outer) * $pixelPerPoint;
                $py = ($y + $outer) * $pixelPerPoint;
                $rects[] = '<rect x="' . $px . '" y="' . $py . '" width="' . $pixelPerPoint
                    . '" height="' . $pixelPerPoint . '"/>';
            }
        }

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height
            . '" viewBox="0 0 ' . $width . ' ' . $height . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<g fill="#000000">' . implode('', $rects) . '</g></svg>';

        return $svg;
    }

    private function guessMime(string $body): string
    {
        if (strncmp($body, "\x89PNG", 4) === 0) {
            return 'image/png';
        }
        if (str_contains($body, '<svg') || str_starts_with(ltrim($body), '<?xml')) {
            return 'image/svg+xml; charset=UTF-8';
        }

        return 'application/octet-stream';
    }
}
