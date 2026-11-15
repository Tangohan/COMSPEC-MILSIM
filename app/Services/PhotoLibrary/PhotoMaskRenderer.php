<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

/**
 * Applique les zones masquées dans les pixels de l'image (GD). Le résultat est un nouveau fichier : ce qui est sous le
 * masque n'y est plus, jamais un simple calque à superposer. Trois façons :
 *  - fill : aplat noir ;
 *  - pixel : mosaïque grossière (au plus une dizaine de cases sur le plus petit côté de la zone) ;
 *  - blur : mosaïque très grossière puis flou, irréversible.
 * Sans GD, available() est faux et rien n'est masqué : l'appelant refuse alors de poser un masque et ne sert jamais une
 * photo masquée dont il n'a pas la version masquée.
 */
final class PhotoMaskRenderer
{
    /** Au-delà (pixels), l'image n'est pas traitée : la mémoire de GD (4 octets par pixel) deviendrait déraisonnable. */
    public const MAX_PIXELS = 36_000_000;

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagecopyresampled') && function_exists('imagejpeg');
    }

    /**
     * @param list<array{x: float|string, y: float|string, w: float|string, h: float|string, mode: string}> $zones
     * @param 'jpeg'|'png'|'webp' $format
     * @return string|null octets de l'image masquée, null si GD manque, si l'image est illisible ou trop grande
     */
    public static function apply(string $bytes, array $zones, string $format = 'jpeg', int $quality = 90, ?int $maxSide = null): ?string
    {
        if (!self::available() || $bytes === '') {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }
        $im = @imagecreatefromstring($bytes);
        if ($im === false) {
            return null;
        }
        $width = imagesx($im);
        $height = imagesy($im);
        foreach ($zones as $z) {
            $rect = PhotoGeometry::rect($z['x'], $z['y'], $z['w'], $z['h']);
            $mode = PhotoGeometry::mode($z['mode']);
            if ($rect === null || $mode === null) {
                continue;
            }
            [$x0, $y0, $x1, $y1] = PhotoGeometry::pixels($rect, $width, $height);
            self::maskBox($im, $x0, $y0, $x1 - $x0, $y1 - $y0, $mode);
        }
        if ($maxSide !== null && $maxSide > 0 && max($width, $height) > $maxSide) {
            $scaled = $width >= $height ? @imagescale($im, $maxSide, -1, IMG_BICUBIC) : @imagescale($im, (int) max(1, round($width * $maxSide / $height)), $maxSide, IMG_BICUBIC);
            if ($scaled !== false) {
                $im = $scaled;
            }
        }
        ob_start();
        $ok = match (true) {
            $format === 'png' => imagepng($im, null, 6),
            $format === 'webp' && function_exists('imagewebp') => imagewebp($im, null, $quality),
            default => imagejpeg($im, null, $quality),
        };
        $out = (string) ob_get_clean();

        return $ok && $out !== '' ? $out : null;
    }

    /** Un rectangle de pixels, remplacé selon le mode. */
    private static function maskBox(\GdImage $im, int $x, int $y, int $w, int $h, string $mode): void
    {
        if ($w < 1 || $h < 1) {
            return;
        }
        if ($mode === 'fill') {
            imagefilledrectangle($im, $x, $y, $x + $w - 1, $y + $h - 1, (int) imagecolorallocate($im, 0, 0, 0));

            return;
        }
        $short = min($w, $h);
        $cells = $mode === 'pixel' ? 10 : 5;
        $cell = max(2, (int) ceil($short / $cells));
        $sw = max(1, (int) ceil($w / $cell));
        $sh = max(1, (int) ceil($h / $cell));
        $small = imagecreatetruecolor($sw, $sh);
        if ($small === false) {
            return;
        }
        imagecopyresampled($small, $im, 0, 0, $x, $y, $sw, $sh, $w, $h);
        $big = imagecreatetruecolor($w, $h);
        if ($big === false) {
            return;
        }
        // Agrandissement sans lissage : de vrais blocs ; le flou vient ensuite.
        imagecopyresized($big, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        if ($mode === 'blur') {
            for ($i = 0; $i < 4; $i++) {
                imagefilter($big, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
        imagecopy($im, $big, $x, $y, 0, 0, $w, $h);
    }

    /** Format de sortie qui garde celui du fichier (extension). @return 'jpeg'|'png'|'webp' */
    public static function formatFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'png',
            'webp' => function_exists('imagewebp') ? 'webp' : 'jpeg',
            default => 'jpeg',
        };
    }
}
