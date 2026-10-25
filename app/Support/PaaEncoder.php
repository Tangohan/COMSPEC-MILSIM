<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Image GD vers texture Arma 3 (.paa), pour les images du site affichées dans le téléphone en jeu (ATAK natif).
 *
 * Même structure que les .paa du mod écrits par HEMTT (utils paa convert) : DXT1 si l'image est opaque, DXT5 si
 * elle a de la transparence ; balises CGVA (couleur moyenne), CXAM, GALF (transparence) et SFFO (position de chaque
 * mipmap) ; palette vide ; mipmaps jusqu'au côté 4 ; six octets nuls de fin. Les blocs DXT sont écrits sans
 * compression LZO (permis : HEMTT laisse ainsi ses petits mipmaps), le fichier reste petit (½ ou 1 octet par pixel).
 *
 * Les deux côtés doivent être des puissances de 2 (règle du moteur), de 4 à 4096 px : sinon \InvalidArgumentException.
 * Relu par PaaDecoder (tests) et par HEMTT (utils paa inspect / convert).
 */
final class PaaEncoder
{
    public const TYPE_DXT1 = 0xFF01;

    public const TYPE_DXT5 = 0xFF05;

    private const MIN_SIDE = 4;

    private const MAX_SIDE = 4096;

    public static function isPowerOfTwo(int $n): bool
    {
        return $n >= self::MIN_SIDE && $n <= self::MAX_SIDE && ($n & ($n - 1)) === 0;
    }

    /** Puissance de 2 la plus proche de $n (en rapport, pas en écart), bornée entre $min et $max. */
    public static function nearestPowerOfTwo(int $n, int $min = 4, int $max = 4096): int
    {
        $p = $min;
        // On passe au double tant qu'il est plus proche de $n en rapport : p * 2 <= n * √2.
        while ($p < $max && $p * 2 <= max(1, $n) * M_SQRT2) {
            $p *= 2;
        }

        return $p;
    }

    /**
     * @param \GdImage $im image truecolor dont les côtés sont des puissances de 2
     */
    public static function encode(\GdImage $im): string
    {
        $w = imagesx($im);
        $h = imagesy($im);
        if (!self::isPowerOfTwo($w) || !self::isPowerOfTwo($h)) {
            throw new \InvalidArgumentException('paa_dimensions');
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }

        [$avg, $hasAlpha] = self::stats($im, $w, $h);
        $type = $hasAlpha ? self::TYPE_DXT5 : self::TYPE_DXT1;

        // Mipmaps : chacun réduit de moitié depuis le précédent, tant que les deux côtés restent >= 4.
        $mips = [];
        $level = $im;
        $lw = $w;
        $lh = $h;
        for ($i = 0; $i < 16; $i++) {
            $data = self::dxt($level, $lw, $lh, $hasAlpha);
            $mips[$i] = pack('vv', $lw, $lh) . self::u24(strlen($data)) . $data;
            if ($lw / 2 < self::MIN_SIDE || $lh / 2 < self::MIN_SIDE) {
                break;
            }
            $next = self::half($level, $lw, $lh);
            if ($level !== $im) {
                imagedestroy($level);
            }
            $level = $next;
            $lw = intdiv($lw, 2);
            $lh = intdiv($lh, 2);
        }
        if ($level !== $im) {
            imagedestroy($level);
        }

        $tags = self::tag('CGVA', pack('C4', $avg[0], $avg[1], $avg[2], $avg[3]))
            . self::tag('CXAM', "\xFF\xFF\xFF\xFF")
            . ($hasAlpha ? self::tag('GALF', pack('V', 1)) : '');
        // SFFO : position absolue de chaque mipmap dans le fichier (16 emplacements, 0 au-delà).
        $offset = 2 + strlen($tags) + 12 + 64 + 2;
        $offsets = [];
        foreach ($mips as $mip) {
            $offsets[] = $offset;
            $offset += strlen($mip);
        }
        $offsets = array_pad($offsets, 16, 0);

        return pack('v', $type) . $tags . self::tag('SFFO', pack('V*', ...$offsets))
            . pack('v', 0) . implode('', $mips) . str_repeat("\0", 6);
    }

    private static function tag(string $name, string $data): string
    {
        return 'GGAT' . $name . pack('V', strlen($data)) . $data;
    }

    private static function u24(int $n): string
    {
        return chr($n & 0xFF) . chr(($n >> 8) & 0xFF) . chr(($n >> 16) & 0xFF);
    }

    /**
     * Couleur moyenne (R, G, B, A sur 0-255) et présence de transparence.
     *
     * @return array{0: array{0: int, 1: int, 2: int, 3: int}, 1: bool}
     */
    private static function stats(\GdImage $im, int $w, int $h): array
    {
        $r = $g = $b = $a = 0;
        $alpha = false;
        // Échantillon (au plus ~64 x 64 points) : la moyenne sert au moteur de loin, pas au rendu de l'image.
        $step = max(1, intdiv(max($w, $h), 64));
        $n = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($im, $x, $y);
                $r += ($c >> 16) & 0xFF;
                $g += ($c >> 8) & 0xFF;
                $b += $c & 0xFF;
                $a += 255 - self::alpha8(($c >> 24) & 0x7F);
                $n++;
            }
        }
        // La transparence se cherche sur tous les pixels : un seul pixel transparent impose DXT5.
        for ($y = 0; $y < $h && !$alpha; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ((imagecolorat($im, $x, $y) >> 24) & 0x7F) {
                    $alpha = true;
                    break;
                }
            }
        }

        return [[intdiv($r, $n), intdiv($g, $n), intdiv($b, $n), intdiv($a, $n)], $alpha];
    }

    /** Transparence GD (0 opaque … 127 transparent) vers opacité 8 bits inversée (0 opaque … 255 transparent). */
    private static function alpha8(int $gd): int
    {
        return $gd >= 127 ? 255 : intdiv($gd * 255 + 63, 127);
    }

    private static function half(\GdImage $src, int $w, int $h): \GdImage
    {
        $dw = intdiv($w, 2);
        $dh = intdiv($h, 2);
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);

        return $dst;
    }

    /** Blocs DXT1 (8 octets) ou DXT5 (16 octets) de l'image, ligne de blocs par ligne de blocs. */
    private static function dxt(\GdImage $im, int $w, int $h, bool $alpha): string
    {
        $out = '';
        for ($by = 0; $by < $h; $by += 4) {
            for ($bx = 0; $bx < $w; $bx += 4) {
                $px = [];
                for ($y = 0; $y < 4; $y++) {
                    for ($x = 0; $x < 4; $x++) {
                        $px[] = imagecolorat($im, $bx + $x, $by + $y);
                    }
                }
                if ($alpha) {
                    $out .= self::alphaBlock($px);
                }
                $out .= self::colorBlock($px);
            }
        }

        return $out;
    }

    /**
     * Bloc couleur : extrémités prises sur la boîte englobante des 16 pixels (resserrée de 1/16, méthode « real-time
     * DXT »), couleur 0 > couleur 1 en RGB565 (mode 4 couleurs), chaque pixel vers la plus proche des 4 teintes.
     *
     * @param list<int> $px
     */
    private static function colorBlock(array $px): string
    {
        $minR = $minG = $minB = 255;
        $maxR = $maxG = $maxB = 0;
        foreach ($px as $c) {
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;
            $minR = min($minR, $r);
            $minG = min($minG, $g);
            $minB = min($minB, $b);
            $maxR = max($maxR, $r);
            $maxG = max($maxG, $g);
            $maxB = max($maxB, $b);
        }
        $iR = ($maxR - $minR) >> 4;
        $iG = ($maxG - $minG) >> 4;
        $iB = ($maxB - $minB) >> 4;
        $c0 = self::rgb565(min(255, $maxR - $iR), min(255, $maxG - $iG), min(255, $maxB - $iB));
        $c1 = self::rgb565(max(0, $minR + $iR), max(0, $minG + $iG), max(0, $minB + $iB));
        if ($c0 < $c1) {
            [$c0, $c1] = [$c1, $c0];
        }
        if ($c0 === $c1) {
            return pack('vvV', $c0, $c1, 0);
        }
        $p0 = self::expand565($c0);
        $p1 = self::expand565($c1);
        $pal = [
            $p0,
            $p1,
            [intdiv(2 * $p0[0] + $p1[0], 3), intdiv(2 * $p0[1] + $p1[1], 3), intdiv(2 * $p0[2] + $p1[2], 3)],
            [intdiv($p0[0] + 2 * $p1[0], 3), intdiv($p0[1] + 2 * $p1[1], 3), intdiv($p0[2] + 2 * $p1[2], 3)],
        ];
        $bits = 0;
        foreach ($px as $i => $c) {
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;
            $best = 0;
            $bestD = PHP_INT_MAX;
            foreach ($pal as $k => $p) {
                $d = ($r - $p[0]) ** 2 + ($g - $p[1]) ** 2 + ($b - $p[2]) ** 2;
                if ($d < $bestD) {
                    $bestD = $d;
                    $best = $k;
                }
            }
            $bits |= $best << (2 * $i);
        }

        return pack('vvV', $c0, $c1, $bits);
    }

    /**
     * Bloc alpha DXT5 : alpha0 = max > alpha1 = min (mode 8 valeurs), index de 3 bits par pixel sur 48 bits.
     *
     * @param list<int> $px
     */
    private static function alphaBlock(array $px): string
    {
        $vals = [];
        foreach ($px as $c) {
            $vals[] = 255 - self::alpha8(($c >> 24) & 0x7F);
        }
        $a0 = max($vals);
        $a1 = min($vals);
        if ($a0 === $a1) {
            return chr($a0) . chr($a1) . str_repeat("\0", 6);
        }
        $pal = [$a0, $a1];
        for ($i = 1; $i <= 6; $i++) {
            $pal[] = intdiv((7 - $i) * $a0 + $i * $a1, 7);
        }
        $bits = 0;
        foreach ($vals as $i => $v) {
            $best = 0;
            $bestD = 256;
            foreach ($pal as $k => $p) {
                $d = abs($v - $p);
                if ($d < $bestD) {
                    $bestD = $d;
                    $best = $k;
                }
            }
            $bits |= $best << (3 * $i);
        }
        $idx = '';
        for ($i = 0; $i < 6; $i++) {
            $idx .= chr(($bits >> (8 * $i)) & 0xFF);
        }

        return chr($a0) . chr($a1) . $idx;
    }

    private static function rgb565(int $r, int $g, int $b): int
    {
        return (($r >> 3) << 11) | (($g >> 2) << 5) | ($b >> 3);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function expand565(int $c): array
    {
        $r = ($c >> 11) & 0x1F;
        $g = ($c >> 5) & 0x3F;
        $b = $c & 0x1F;

        return [($r << 3) | ($r >> 2), ($g << 2) | ($g >> 4), ($b << 3) | ($b >> 2)];
    }
}
