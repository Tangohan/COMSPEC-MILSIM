<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Copie « jeu » d'une diapositive de briefing pour le téléphone en jeu : Arma n'affiche ni PNG ni WebP
 * (zone noire dans l'app Briefing), et lit mal les JPEG aux côtés qui ne sont pas des puissances de 2.
 * La diapositive est donc recopiée en JPEG 2048 x 1024, centrée sur fond noir sans déformation
 * (le contrôle du téléphone garde le rapport d'image).
 *
 * La copie est écrite à côté de l'envoi (uploads/briefing-slides/<tenant>/atak/<nom>.jpg), dans public/
 * sinon storage/ (servi à la même adresse par le repli de public/index.php), une seule fois : chaque nouvel
 * envoi porte un nouveau nom. Sans GD ou en cas d'échec, le chemin d'origine est rendu tel quel.
 */
final class BriefingSlideGameImage
{
    public const WIDTH = 2048;

    public const HEIGHT = 1024;

    /** Chemin relatif (sous public/) à servir au jeu pour cette diapositive. */
    public static function path(string $imagePath): string
    {
        $rel = ltrim(trim($imagePath), '/');
        if ($rel === '' || str_contains($rel, '..') || !str_starts_with($rel, 'uploads/')) {
            return $imagePath;
        }
        $root = function_exists('base_path') ? base_path() : '';
        if ($root === '') {
            return $imagePath;
        }
        $gameRel = dirname($rel) . '/atak/' . pathinfo($rel, PATHINFO_FILENAME) . '.jpg';
        $bases = [$root . '/public', $root . '/storage'];
        foreach ($bases as $base) {
            // Une copie vide (écriture interrompue) est refaite.
            if (is_file($base . '/' . $gameRel) && (int) @filesize($base . '/' . $gameRel) > 0) {
                return $gameRel;
            }
        }
        $src = null;
        foreach ($bases as $base) {
            if (is_file($base . '/' . $rel)) {
                $src = $base . '/' . $rel;
                break;
            }
        }
        if ($src === null) {
            return $imagePath;
        }
        foreach ($bases as $base) {
            if (self::write($src, $base . '/' . $gameRel)) {
                return $gameRel;
            }
        }

        return $imagePath;
    }

    /** Recopie $src en JPEG WIDTH x HEIGHT, image entière centrée sur fond noir. */
    public static function write(string $src, string $dest): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $bin = @file_get_contents($src);
        $im = $bin !== false ? @imagecreatefromstring($bin) : false;
        if ($im === false) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);

            return false;
        }
        $scale = min(self::WIDTH / $w, self::HEIGHT / $h);
        $dw = max(1, (int) round($w * $scale));
        $dh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($out, 0, 0, imagecolorallocate($out, 0, 0, 0));
        imagecopyresampled($out, $im, (int) ((self::WIDTH - $dw) / 2), (int) ((self::HEIGHT - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
        imagedestroy($im);
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ok = is_dir($dir) && is_writable($dir) && @imagejpeg($out, $dest, 88);
        imagedestroy($out);

        return $ok;
    }
}
