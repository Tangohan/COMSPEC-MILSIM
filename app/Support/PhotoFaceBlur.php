<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Floutage automatique des visages sur les photos du téléphone ATAK natif (mode photo).
 * Le jeu repère les têtes visibles au moment de la prise et envoie leurs cadres dans les détails de la prise de vue
 * (photo_extra de COMSPEC Link 2.0.81, clés « blur » et « face1 » à « face12 ») ; le floutage est fait ici, à la
 * réception, avant la correction de couleur et le bandeau (l'original gardé par PhotoAutoLevel est donc déjà flouté).
 *
 *   blur  = type;force;opacité;taille;angle   type : motion, pixel, black, gauss (off : rien)
 *                                             force 1-100, opacité 0-100 (%), taille 50-300 (% du cadre), angle 0-179 (°, flou de mouvement)
 *   faceN = x;y;l;h                           cadre du visage en fractions de l'image (coin haut gauche, largeur, hauteur)
 *
 * Sans GD, sans cadre ou avec un type inconnu, rien ne change.
 */
final class PhotoFaceBlur
{
    public const TYPES = ['motion', 'pixel', 'black', 'gauss'];

    public const MAX_FACES = 12;

    /** Clés de photo_extra lues ici (en plus de celles du relais Discord). */
    public static function extraKeys(): array
    {
        $keys = ['blur'];
        for ($i = 1; $i <= self::MAX_FACES; $i++) {
            $keys[] = 'face' . $i;
        }

        return $keys;
    }

    /**
     * Réglages et cadres lus dans photo_extra brut (« clé=valeur|… »). Null quand il n'y a rien à flouter.
     *
     * @return array{type: string, force: int, opacity: int, size: int, angle: int, faces: list<array{0: float, 1: float, 2: float, 3: float}>}|null
     */
    public static function fromExtra(string $raw): ?array
    {
        $pairs = [];
        foreach (array_slice(explode('|', mb_substr($raw, 0, 6000)), 0, 60) as $pair) {
            $eq = strpos($pair, '=');
            if ($eq === false) {
                continue;
            }
            $key = strtolower(trim(substr($pair, 0, $eq)));
            if (!isset($pairs[$key])) {
                $pairs[$key] = trim(substr($pair, $eq + 1));
            }
        }
        $spec = explode(';', (string) ($pairs['blur'] ?? ''));
        $type = strtolower(trim($spec[0] ?? ''));
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }
        $int = static fn (int $i, int $def, int $min, int $max): int => is_numeric($spec[$i] ?? null)
            ? max($min, min($max, (int) round((float) $spec[$i])))
            : $def;
        $faces = [];
        for ($i = 1; $i <= self::MAX_FACES; $i++) {
            $parts = explode(';', (string) ($pairs['face' . $i] ?? ''));
            if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
                continue;
            }
            [$x, $y, $w, $h] = array_map('floatval', $parts);
            // Cadre absurde (plus grand que l'image, hors champ) : ignoré.
            if ($w <= 0 || $h <= 0 || $w > 1 || $h > 1 || $x + $w < 0 || $y + $h < 0 || $x > 1 || $y > 1) {
                continue;
            }
            $faces[] = [$x, $y, $w, $h];
        }
        if ($faces === []) {
            return null;
        }

        return [
            'type' => $type,
            'force' => $int(1, 60, 1, 100),
            'opacity' => $int(2, 100, 0, 100),
            'size' => $int(3, 130, 50, 300),
            'angle' => $int(4, 0, 0, 179),
            'faces' => $faces,
        ];
    }

    /** Floute le fichier en place. Retourne true si l'image a été réécrite. */
    public static function applyFile(string $absPath, array $spec): bool
    {
        if ($absPath === '' || !is_file($absPath) || !is_writable($absPath) || !function_exists('imagecreatefromstring')) {
            return false;
        }
        $raw = @file_get_contents($absPath);
        $im = is_string($raw) && $raw !== '' ? @imagecreatefromstring($raw) : false;
        if ($im === false) {
            return false;
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        if (self::apply($im, $spec) === 0) {
            imagedestroy($im);

            return false;
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

    /**
     * Floute chaque cadre de l'image. Retourne le nombre de zones traitées.
     *
     * @param \GdImage $im
     */
    public static function apply($im, array $spec): int
    {
        $iw = imagesx($im);
        $ih = imagesy($im);
        $scale = ($spec['size'] ?? 130) / 100;
        $done = 0;
        foreach ($spec['faces'] ?? [] as [$fx, $fy, $fw, $fh]) {
            // Cadre agrandi autour de son centre (réglage Taille), un peu plus haut que large comme une tête.
            $cw = $fw * $iw * $scale;
            $ch = $fh * $ih * $scale;
            $cx = ($fx + $fw / 2) * $iw;
            $cy = ($fy + $fh / 2) * $ih;
            $x0 = max(0, (int) floor($cx - $cw / 2));
            $y0 = max(0, (int) floor($cy - $ch / 2));
            $x1 = min($iw, (int) ceil($cx + $cw / 2));
            $y1 = min($ih, (int) ceil($cy + $ch / 2));
            $w = $x1 - $x0;
            $h = $y1 - $y0;
            if ($w < 2 || $h < 2) {
                continue;
            }
            $zone = self::effect($im, $x0, $y0, $w, $h, $spec);
            imagecopymerge($im, $zone, $x0, $y0, 0, 0, $w, $h, (int) ($spec['opacity'] ?? 100));
            imagedestroy($zone);
            $done++;
        }

        return $done;
    }

    /**
     * Zone floutée (même taille que le cadre).
     *
     * @param \GdImage $im
     *
     * @return \GdImage
     */
    private static function effect($im, int $x0, int $y0, int $w, int $h, array $spec)
    {
        $force = (int) ($spec['force'] ?? 60);
        $zone = imagecreatetruecolor($w, $h);
        switch ($spec['type'] ?? 'gauss') {
            case 'black':
                imagefilledrectangle($zone, 0, 0, $w - 1, $h - 1, imagecolorallocate($zone, 0, 0, 0));
                break;
            case 'pixel':
                // Mosaïque : 2 à 26 blocs sur la largeur selon la force (agrandissement au plus proche voisin).
                $blocks = max(2, (int) round(26 - $force * 0.24));
                $bw = $blocks;
                $bh = max(2, (int) round($blocks * $h / $w));
                $small = imagecreatetruecolor($bw, $bh);
                imagecopyresampled($small, $im, 0, 0, $x0, $y0, $bw, $bh, $w, $h);
                imagecopyresized($zone, $small, 0, 0, 0, 0, $w, $h, $bw, $bh);
                imagedestroy($small);
                break;
            case 'motion':
                // Moyenne de copies décalées le long de l'angle (traîne de 4 % à 60 % de la largeur du cadre).
                $src = imagecreatetruecolor($w, $h);
                imagecopy($src, $im, 0, 0, $x0, $y0, $w, $h);
                imagecopy($zone, $src, 0, 0, 0, 0, $w, $h);
                $len = max(2.0, $w * (0.04 + $force * 0.0056));
                $a = deg2rad((float) ($spec['angle'] ?? 0));
                $steps = (int) min(24, max(4, round($len / 2)));
                for ($k = 1; $k <= $steps; $k++) {
                    $t = ($k / $steps - 0.5) * $len;
                    $dx = (int) round(cos($a) * $t);
                    $dy = (int) round(-sin($a) * $t);
                    // Bords : on reprend l'image autour du cadre pour que la traîne ne laisse pas de vide.
                    imagecopy($src, $im, 0, 0, max(0, min(imagesx($im) - $w, $x0 + $dx)), max(0, min(imagesy($im) - $h, $y0 + $dy)), $w, $h);
                    imagecopymerge($zone, $src, 0, 0, 0, 0, $w, $h, (int) round(100 / ($k + 1)));
                }
                imagedestroy($src);
                break;
            default:
                // Flou gaussien : réduction puis agrandissement lissé, et filtre gaussien répété selon la force.
                $f = max(2, (int) round(2 + $force * 0.14));
                $sw = max(2, intdiv($w, $f));
                $sh = max(2, intdiv($h, $f));
                $small = imagecreatetruecolor($sw, $sh);
                imagecopyresampled($small, $im, 0, 0, $x0, $y0, $sw, $sh, $w, $h);
                for ($i = 0, $n = 1 + intdiv($force, 25); $i < $n; $i++) {
                    imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
                }
                imagecopyresampled($zone, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
                imagefilter($zone, IMG_FILTER_GAUSSIAN_BLUR);
                imagedestroy($small);
                break;
        }

        return $zone;
    }
}
