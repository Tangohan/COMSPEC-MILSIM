<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Recompresse un cliché reçu (PNG ou JPEG) en JPEG qualité 75.
 * Sans GD / Imagick, le fichier d’origine est conservé.
 */
final class IntelPhotoRecompress
{
    public const QUALITY = 75;

    /**
     * @return array{path: string, filename: string, compressed: bool}
     */
    public static function recompressFile(string $absPath): array
    {
        $filename = basename($absPath);
        $result = [
            'path' => $absPath,
            'filename' => $filename,
            'compressed' => false,
        ];
        if ($absPath === '' || !is_file($absPath)) {
            return $result;
        }

        $converted = self::tryImagick($absPath) ?? self::tryGd($absPath);
        if ($converted === null) {
            return $result;
        }

        return $converted;
    }

    /**
     * @return array{path: string, filename: string, compressed: bool}|null
     */
    private static function tryImagick(string $absPath): ?array
    {
        if (!class_exists(\Imagick::class)) {
            return null;
        }
        try {
            $im = new \Imagick($absPath);
            $im->setImageFormat('jpeg');
            $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(self::QUALITY);
            $im->stripImage();
            $outPath = self::jpegSibling($absPath);
            $tmp = $outPath . '.tmp';
            if (!$im->writeImage($tmp)) {
                $im->clear();
                @unlink($tmp);

                return null;
            }
            $im->clear();
            if (!@rename($tmp, $outPath)) {
                @unlink($tmp);

                return null;
            }
            if (strcasecmp($outPath, $absPath) !== 0) {
                @unlink($absPath);
            }

            return [
                'path' => $outPath,
                'filename' => basename($outPath),
                'compressed' => true,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{path: string, filename: string, compressed: bool}|null
     */
    private static function tryGd(string $absPath): ?array
    {
        if (!function_exists('imagejpeg') || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $raw = @file_get_contents($absPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $im = @imagecreatefromstring($raw);
        if ($im === false) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $max = 1920;
        if ($w > $max || $h > $max) {
            $scale = min($max / max(1, $w), $max / max(1, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst !== false) {
                imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($im);
                $im = $dst;
            }
        }
        $outPath = self::jpegSibling($absPath);
        $tmp = $outPath . '.tmp';
        $ok = @imagejpeg($im, $tmp, self::QUALITY);
        imagedestroy($im);
        if (!$ok || !is_file($tmp)) {
            @unlink($tmp);

            return null;
        }
        if (!@rename($tmp, $outPath)) {
            @unlink($tmp);

            return null;
        }
        if (strcasecmp($outPath, $absPath) !== 0) {
            @unlink($absPath);
        }

        return [
            'path' => $outPath,
            'filename' => basename($outPath),
            'compressed' => true,
        ];
    }

    private static function jpegSibling(string $absPath): string
    {
        $ext = strtolower((string) pathinfo($absPath, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            return $absPath;
        }

        return preg_replace('/\.[^.]+$/', '.jpg', $absPath) ?: ($absPath . '.jpg');
    }
}
