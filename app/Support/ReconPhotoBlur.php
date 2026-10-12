<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flou fort d'un cliché SSE avant publication sur Discord (salon ouvert à la communauté) :
 * réduction très forte puis agrandissement lissé et passes de flou gaussien, de sorte que ni visage,
 * ni texte, ni plaque ne restent lisibles. GD d'abord (déjà utilisé pour la copie Discord), Imagick en repli.
 * Sans l'un ni l'autre : null, l'appelant publie alors le message sans image.
 */
final class ReconPhotoBlur
{
    /** Largeur de la vignette intermédiaire (le plus grand côté) : plus petit = plus flou. */
    public const SAMPLE_PX = 24;

    public const GAUSSIAN_PASSES = 6;

    public static function available(): bool
    {
        return self::gdAvailable() || class_exists(\Imagick::class);
    }

    public static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled') && function_exists('imagefilter');
    }

    /**
     * Image GD floutée (nouvelle image, même taille ; l'originale n'est pas détruite).
     */
    public static function blurGd(\GdImage $im): ?\GdImage
    {
        if (!self::gdAvailable()) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            return null;
        }
        $ratio = min(1.0, self::SAMPLE_PX / max($w, $h));
        $sw = max(1, (int) round($w * $ratio));
        $sh = max(1, (int) round($h * $ratio));
        $small = imagecreatetruecolor($sw, $sh);
        $out = imagecreatetruecolor($w, $h);
        if ($small === false || $out === false) {
            return null;
        }
        imagecopyresampled($small, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);
        for ($i = 0; $i < 2; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagecopyresampled($out, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($small);
        // Agrandissement : passes de flou pour effacer les blocs de la vignette.
        for ($i = 0; $i < self::GAUSSIAN_PASSES; $i++) {
            imagefilter($out, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $out;
    }

    /**
     * Repli Imagick : fichier source → JPEG flouté. true si $dest est écrit.
     */
    public static function blurFileImagick(string $source, string $dest, int $maxPx = 1920): bool
    {
        if (!class_exists(\Imagick::class) || !is_file($source)) {
            return false;
        }
        try {
            $img = new \Imagick($source);
            $img->setImageBackgroundColor('black');
            if (method_exists($img, 'mergeImageLayers')) {
                $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            }
            $w = $img->getImageWidth();
            $h = $img->getImageHeight();
            if ($w < 1 || $h < 1) {
                return false;
            }
            $ratio = min(1.0, $maxPx / max($w, $h));
            $tw = max(1, (int) round($w * $ratio));
            $th = max(1, (int) round($h * $ratio));
            $sr = min(1.0, self::SAMPLE_PX / max($w, $h));
            $img->resizeImage(max(1, (int) round($w * $sr)), max(1, (int) round($h * $sr)), \Imagick::FILTER_BOX, 1);
            $img->resizeImage($tw, $th, \Imagick::FILTER_GAUSSIAN, 1);
            $img->blurImage(0, max(8.0, $tw / 40));
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality(80);
            $ok = $img->writeImage($dest);
            $img->clear();

            return $ok && is_file($dest) && (int) filesize($dest) >= 32;
        } catch (\Throwable) {
            return false;
        }
    }
}
