<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Photo du visage prise au terminal SEEK : le jeu ne sait enregistrer qu'une capture
 * plein écran, cadrée sur la tête du sujet. On la recadre ici en portrait 3:4 centré.
 *
 * Le jeu signale le recadrage dans l'angle transmis : « face@portrait,q=88 »
 * (angle « face » | « trois_quarts » | « profil », qualité estimée 0-100).
 */
final class SseFacePortrait
{
    public const MAX_HEIGHT = 1000;

    /**
     * @return array{angle: string, portrait: bool, quality: ?int}
     */
    public static function parseAngle(string $raw): array
    {
        $raw = strtolower(trim($raw));
        $parts = explode('@', $raw, 2);
        $angle = trim($parts[0]);
        $portrait = false;
        $quality = null;
        if (isset($parts[1])) {
            foreach (explode(',', $parts[1]) as $flag) {
                $flag = trim($flag);
                if ($flag === 'portrait') {
                    $portrait = true;
                } elseif (str_starts_with($flag, 'q=') && is_numeric(substr($flag, 2))) {
                    $quality = max(0, min(100, (int) substr($flag, 2)));
                }
            }
        }

        return [
            'angle' => $angle !== '' ? $angle : 'face',
            'portrait' => $portrait,
            'quality' => $quality,
        ];
    }

    /**
     * Rectangle 3:4 centré sur toute la hauteur d'une image paysage.
     * Une image déjà en portrait (ou presque) n'est pas recadrée.
     *
     * @return array{x: int, y: int, w: int, h: int}|null
     */
    public static function cropBox(int $width, int $height): ?array
    {
        if ($width <= 0 || $height <= 0) {
            return null;
        }
        $w = (int) round($height * 3 / 4);
        if ($w >= $width) {
            return null;
        }

        return ['x' => intdiv($width - $w, 2), 'y' => 0, 'w' => $w, 'h' => $height];
    }

    /**
     * Écrit le portrait recadré (JPEG) dans $dest. Retourne false si GD manque,
     * si l'image est illisible ou si elle n'a pas besoin de recadrage.
     */
    public static function writePortrait(string $source, string $dest): bool
    {
        if (!function_exists('imagecreatefromstring') || !is_file($source)) {
            return false;
        }
        $bin = @file_get_contents($source);
        if (!is_string($bin) || $bin === '') {
            return false;
        }
        $im = @imagecreatefromstring($bin);
        if ($im === false) {
            return false;
        }
        $box = self::cropBox(imagesx($im), imagesy($im));
        if ($box === null) {
            imagedestroy($im);

            return false;
        }
        $outH = min($box['h'], self::MAX_HEIGHT);
        $outW = (int) round($outH * 3 / 4);
        $out = imagecreatetruecolor($outW, $outH);
        imagecopyresampled($out, $im, 0, 0, $box['x'], $box['y'], $outW, $outH, $box['w'], $box['h']);
        $ok = imagejpeg($out, $dest, 88);
        imagedestroy($im);
        imagedestroy($out);

        return $ok;
    }
}
