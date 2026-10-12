<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Correction automatique légère des photos reçues du jeu (téléphone, tablette, caméra casque) :
 * les captures d'Arma sortent délavées, grises et peu contrastées.
 * Niveaux étirés entre les percentiles 0,5 % et 99,5 % de la luminance (limité pour ne pas brûler l'image),
 * tons moyens un peu plus denses, saturation légèrement relevée (GD, table par pixel : moins d'une seconde pour 1920 × 1080).
 * L'original est gardé hors de la racine web (dossier privé « originals », suffixe _orig) : une photo devenue
 * confidentielle ne laisse pas de copie publique. Sans GD ni Imagick, rien ne change.
 */
final class PhotoAutoLevel
{
    /** Étirement maximal (au-delà, une image très sombre ou très claire serait dénaturée). */
    public const MAX_STRETCH = 1.6;

    /** Gain d'étirement en dessous duquel les niveaux ne bougent pas (image déjà étalée). */
    public const MIN_GAIN = 1.05;

    /** Tons moyens : exposant > 1 = plus denses. */
    public const GAMMA = 1.2;

    /** Saturation : écart à la luminance multiplié par ce facteur. */
    public const SATURATION = 1.22;

    /**
     * Profils d'effet où la couleur fait partie du rendu (vision nocturne, thermique) : pas de correction.
     */
    public static function skipsProfile(?string $fxProfile, ?string $device = null): bool
    {
        $p = strtolower(trim((string) $fxProfile));
        if ($p !== '' && preg_match('/(nv|night|nuit|thermal|thermique|flir|ir\b|bw|mono)/', $p) === 1) {
            return true;
        }

        return strtoupper(trim((string) $device)) === 'UAV';
    }

    /**
     * Corrige le fichier en place. Retourne true si une correction a été écrite.
     */
    public static function applyFile(string $absPath, ?string $originalDir = null): bool
    {
        if ($absPath === '' || !is_file($absPath) || !is_writable($absPath)) {
            return false;
        }
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $raw = @file_get_contents($absPath);
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $im = @imagecreatefromstring($raw);
        if ($im === false) {
            return false;
        }
        [$lo, $hi] = self::percentiles($im);
        $span = max(1, $hi - $lo);
        $gain = min(self::MAX_STRETCH, 255 / $span);
        // Image déjà étalée : pas d'étirement, mais tons moyens et saturation corrigés quand même.
        if ($gain < self::MIN_GAIN) {
            $gain = 1.0;
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        // Table de correspondance : niveaux [lo, hi] → [0, 255] puis tons moyens plus denses (gamma).
        $lut = [];
        for ($v = 0; $v < 256; $v++) {
            $t = max(0.0, min(1.0, (($v - $lo) * $gain) / 255));
            $lut[$v] = (int) round(255 * ($t ** self::GAMMA));
        }
        self::applyLut($im, $lut, self::SATURATION);

        if ($originalDir !== null && (is_dir($originalDir) || @mkdir($originalDir, 0750, true))) {
            @copy($absPath, self::originalPath($absPath, $originalDir));
        }
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $tmp = $absPath . '.tmp';
        $ok = $ext === 'png' ? @imagepng($im, $tmp, 6) : @imagejpeg($im, $tmp, 88);
        imagedestroy($im);
        if (!$ok || !is_file($tmp) || !@rename($tmp, $absPath)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    /** Chemin de l'original gardé : nom_orig.ext dans le dossier donné (sinon celui de la photo). */
    public static function originalPath(string $absPath, ?string $dir = null): string
    {
        $info = pathinfo($absPath);
        $dir = rtrim($dir ?? ($info['dirname'] ?? '.'), '/\\') . DIRECTORY_SEPARATOR;
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        return $dir . ($info['filename'] ?? 'photo') . '_orig' . $ext;
    }

    /**
     * Percentiles 0,5 % et 99,5 % de la luminance, sur un échantillon de pixels (grille de 4 096 cases au plus).
     *
     * @param \GdImage $im
     *
     * @return array{0: int, 1: int}
     */
    public static function percentiles($im): array
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $step = max(1, (int) floor(sqrt(($w * $h) / 65536)));
        $hist = array_fill(0, 256, 0);
        $n = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgb = imagecolorat($im, $x, $y);
                if (!imageistruecolor($im)) {
                    $c = imagecolorsforindex($im, $rgb);
                    $r = $c['red'];
                    $g = $c['green'];
                    $b = $c['blue'];
                } else {
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                }
                $hist[(int) round(0.299 * $r + 0.587 * $g + 0.114 * $b)]++;
                $n++;
            }
        }
        $cut = max(1, (int) floor($n * 0.005));
        $acc = 0;
        $lo = 0;
        for ($i = 0; $i < 256; $i++) {
            $acc += $hist[$i];
            if ($acc >= $cut) {
                $lo = $i;
                break;
            }
        }
        $acc = 0;
        $hi = 255;
        for ($i = 255; $i >= 0; $i--) {
            $acc += $hist[$i];
            if ($acc >= $cut) {
                $hi = $i;
                break;
            }
        }

        return [$lo, max($lo + 1, $hi)];
    }

    /**
     * Applique la table à chaque pixel et relève la saturation (écart de chaque canal à la luminance).
     *
     * @param \GdImage $im
     * @param array<int, int> $lut
     */
    private static function applyLut($im, array $lut, float $sat): void
    {
        $w = imagesx($im);
        $h = imagesy($im);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $r = $lut[($rgb >> 16) & 0xFF];
                $g = $lut[($rgb >> 8) & 0xFF];
                $b = $lut[$rgb & 0xFF];
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $r = (int) ($l + ($r - $l) * $sat);
                $g = (int) ($l + ($g - $l) * $sat);
                $b = (int) ($l + ($b - $l) * $sat);
                $r = $r < 0 ? 0 : ($r > 255 ? 255 : $r);
                $g = $g < 0 ? 0 : ($g > 255 ? 255 : $g);
                $b = $b < 0 ? 0 : ($b > 255 ? 255 : $b);
                imagesetpixel($im, $x, $y, ($rgb & 0x7F000000) | ($r << 16) | ($g << 8) | $b);
            }
        }
    }
}
