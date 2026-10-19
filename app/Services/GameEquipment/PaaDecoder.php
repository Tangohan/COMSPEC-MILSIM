<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

/**
 * Texture Arma 3 (.paa) vers image GD : icônes d’objets (config `picture`) envoyées par COMSPEC Link (« Item.Icons »).
 *
 * Formats lus : DXT1 (0xFF01), DXT5/DXT3 (0xFF05/0xFF03, alpha interpolé ou explicite), ARGB8888 (0x8888),
 * ARGB4444 (0x4444), ARGB1555 (0x1555) et AI88 (0x8080). Mipmaps DXT compressés LZO (largeur avec le bit 0x8000),
 * autres formats compressés LZSS quand la taille stockée est plus petite que l’image brute.
 * Seul le premier mipmap dont les deux côtés tiennent dans $maxSide est décodé (les grands sont sautés sans les lire).
 * Toute donnée incohérente lève une \RuntimeException : le fichier vient du PC d’un joueur, rien n’est supposé valide.
 */
final class PaaDecoder
{
    public const MAX_INPUT_BYTES = 4 * 1024 * 1024;

    private const TYPE_DXT1 = 0xFF01;
    private const TYPE_DXT3 = 0xFF03;
    private const TYPE_DXT5 = 0xFF05;
    private const TYPE_ARGB8888 = 0x8888;
    private const TYPE_ARGB4444 = 0x4444;
    private const TYPE_ARGB1555 = 0x1555;
    private const TYPE_AI88 = 0x8080;

    /**
     * @return \GdImage image truecolor avec alpha (alphablending coupé, savealpha actif)
     */
    public static function decode(string $paa, int $maxSide = 512): \GdImage
    {
        $len = strlen($paa);
        if ($len < 16 || $len > self::MAX_INPUT_BYTES) {
            throw new \RuntimeException('paa_size');
        }
        $type = self::u16($paa, 0);
        if (!in_array($type, [self::TYPE_DXT1, self::TYPE_DXT3, self::TYPE_DXT5, self::TYPE_ARGB8888, self::TYPE_ARGB4444, self::TYPE_ARGB1555, self::TYPE_AI88], true)) {
            throw new \RuntimeException('paa_type');
        }
        $p = 2;
        // Balises TAGG (« GGAT » + nom + longueur) : moyenne, max, offsets… ignorées.
        for ($guard = 0; $guard < 32 && $p + 12 <= $len && substr($paa, $p, 4) === 'GGAT'; $guard++) {
            $tagLen = self::u32($paa, $p + 8);
            if ($tagLen > $len) {
                throw new \RuntimeException('paa_tag');
            }
            $p += 12 + $tagLen;
        }
        if ($p + 2 > $len) {
            throw new \RuntimeException('paa_truncated');
        }
        $p += 2 + self::u16($paa, $p) * 3; // palette (inutilisée par ces formats)

        $chosen = null;
        $smallest = null;
        for ($guard = 0; $guard < 16 && $p + 7 <= $len; $guard++) {
            $w = self::u16($paa, $p);
            $h = self::u16($paa, $p + 2);
            if ($w === 0 || $h === 0) {
                break;
            }
            $size = ord($paa[$p + 4]) | (ord($paa[$p + 5]) << 8) | (ord($paa[$p + 6]) << 16);
            $dataAt = $p + 7;
            if ($dataAt + $size > $len) {
                throw new \RuntimeException('paa_truncated');
            }
            $lzo = ($w & 0x8000) !== 0;
            $w &= 0x7FFF;
            if ($w < 1 || $h < 1 || $w > 8192 || $h > 8192) {
                throw new \RuntimeException('paa_dimensions');
            }
            $mip = ['w' => $w, 'h' => $h, 'at' => $dataAt, 'size' => $size, 'lzo' => $lzo];
            $smallest = $mip;
            if ($w <= $maxSide && $h <= $maxSide) {
                $chosen = $mip;
                break;
            }
            $p = $dataAt + $size;
        }
        $mip = $chosen ?? $smallest;
        if ($mip === null) {
            throw new \RuntimeException('paa_no_mipmap');
        }
        if ($mip['w'] > $maxSide * 4 || $mip['h'] > $maxSide * 4) {
            throw new \RuntimeException('paa_too_large');
        }
        $raw = substr($paa, $mip['at'], $mip['size']);

        return self::toImage($type, $mip['w'], $mip['h'], $raw, $mip['lzo']);
    }

    private static function toImage(int $type, int $w, int $h, string $raw, bool $lzo): \GdImage
    {
        $isDxt = in_array($type, [self::TYPE_DXT1, self::TYPE_DXT3, self::TYPE_DXT5], true);
        if ($isDxt) {
            $bw = max(1, intdiv($w + 3, 4));
            $bh = max(1, intdiv($h + 3, 4));
            $expected = $bw * $bh * ($type === self::TYPE_DXT1 ? 8 : 16);
            $data = $lzo ? self::lzo1xDecompress($raw, $expected) : $raw;
        } else {
            $expected = $w * $h * ($type === self::TYPE_ARGB8888 ? 4 : 2);
            $data = strlen($raw) >= $expected ? $raw : self::lzssDecompress($raw, $expected);
        }
        if (strlen($data) < $expected) {
            throw new \RuntimeException('paa_truncated');
        }

        $img = imagecreatetruecolor($w, $h);
        if ($img === false) {
            throw new \RuntimeException('gd_failed');
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);

        if ($isDxt) {
            self::drawDxt($img, $type, $w, $h, $data);

            return $img;
        }
        $o = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                switch ($type) {
                    case self::TYPE_ARGB8888:
                        $b = ord($data[$o]);
                        $g = ord($data[$o + 1]);
                        $r = ord($data[$o + 2]);
                        $a = ord($data[$o + 3]);
                        $o += 4;
                        break;
                    case self::TYPE_ARGB4444:
                        $v = ord($data[$o]) | (ord($data[$o + 1]) << 8);
                        $o += 2;
                        $b = ($v & 0xF) * 17;
                        $g = (($v >> 4) & 0xF) * 17;
                        $r = (($v >> 8) & 0xF) * 17;
                        $a = (($v >> 12) & 0xF) * 17;
                        break;
                    case self::TYPE_ARGB1555:
                        $v = ord($data[$o]) | (ord($data[$o + 1]) << 8);
                        $o += 2;
                        $b = (($v & 0x1F) * 255) >> 5;
                        $g = ((($v >> 5) & 0x1F) * 255) >> 5;
                        $r = ((($v >> 10) & 0x1F) * 255) >> 5;
                        $a = ($v & 0x8000) !== 0 ? 255 : 0;
                        break;
                    default: // AI88 : gris puis alpha
                        $r = $g = $b = ord($data[$o]);
                        $a = ord($data[$o + 1]);
                        $o += 2;
                }
                imagesetpixel($img, $x, $y, self::gdColor($r, $g, $b, $a));
            }
        }

        return $img;
    }

    private static function drawDxt(\GdImage $img, int $type, int $w, int $h, string $data): void
    {
        $blockSize = $type === self::TYPE_DXT1 ? 8 : 16;
        $bw = max(1, intdiv($w + 3, 4));
        $bh = max(1, intdiv($h + 3, 4));
        $o = 0;
        for ($by = 0; $by < $bh; $by++) {
            for ($bx = 0; $bx < $bw; $bx++) {
                $alpha = array_fill(0, 16, 255);
                $c = $o;
                if ($type === self::TYPE_DXT5) {
                    $a0 = ord($data[$o]);
                    $a1 = ord($data[$o + 1]);
                    $pal = [$a0, $a1];
                    if ($a0 > $a1) {
                        for ($i = 1; $i <= 6; $i++) {
                            $pal[] = intdiv((7 - $i) * $a0 + $i * $a1, 7);
                        }
                    } else {
                        for ($i = 1; $i <= 4; $i++) {
                            $pal[] = intdiv((5 - $i) * $a0 + $i * $a1, 5);
                        }
                        $pal[] = 0;
                        $pal[] = 255;
                    }
                    // 48 bits d’indices (3 bits par pixel), lus en deux moitiés de 24 bits.
                    $lo = ord($data[$o + 2]) | (ord($data[$o + 3]) << 8) | (ord($data[$o + 4]) << 16);
                    $hi = ord($data[$o + 5]) | (ord($data[$o + 6]) << 8) | (ord($data[$o + 7]) << 16);
                    for ($i = 0; $i < 8; $i++) {
                        $alpha[$i] = $pal[($lo >> (3 * $i)) & 7];
                        $alpha[$i + 8] = $pal[($hi >> (3 * $i)) & 7];
                    }
                    $c = $o + 8;
                } elseif ($type === self::TYPE_DXT3) {
                    for ($i = 0; $i < 8; $i++) {
                        $v = ord($data[$o + $i]);
                        $alpha[$i * 2] = ($v & 0xF) * 17;
                        $alpha[$i * 2 + 1] = ($v >> 4) * 17;
                    }
                    $c = $o + 8;
                }
                $c0 = ord($data[$c]) | (ord($data[$c + 1]) << 8);
                $c1 = ord($data[$c + 2]) | (ord($data[$c + 3]) << 8);
                $cols = [self::rgb565($c0), self::rgb565($c1)];
                $oneBitAlpha = $type === self::TYPE_DXT1 && $c0 <= $c1;
                if (!$oneBitAlpha) {
                    $cols[] = self::mix($cols[0], $cols[1], 2, 1, 3);
                    $cols[] = self::mix($cols[0], $cols[1], 1, 2, 3);
                } else {
                    $cols[] = self::mix($cols[0], $cols[1], 1, 1, 2);
                    $cols[] = [0, 0, 0];
                }
                $bits = ord($data[$c + 4]) | (ord($data[$c + 5]) << 8) | (ord($data[$c + 6]) << 16) | (ord($data[$c + 7]) << 24);
                for ($i = 0; $i < 16; $i++) {
                    $x = $bx * 4 + ($i & 3);
                    $y = $by * 4 + ($i >> 2);
                    if ($x >= $w || $y >= $h) {
                        continue;
                    }
                    $idx = ($bits >> (2 * $i)) & 3;
                    $a = ($oneBitAlpha && $idx === 3) ? 0 : $alpha[$i];
                    [$r, $g, $b] = $cols[$idx];
                    imagesetpixel($img, $x, $y, self::gdColor($r, $g, $b, $a));
                }
                $o += $blockSize;
            }
        }
    }

    /**
     * LZO1X (mipmaps DXT d’Arma). Sortie bornée à $outLen : un flux qui déborde ou référence avant le début est refusé.
     */
    public static function lzo1xDecompress(string $in, int $outLen): string
    {
        $inLen = strlen($in);
        $out = '';
        $ip = 0;
        $state = 'loop';
        $t = 0;
        $mPos = 0;
        $need = static function (int $n) use (&$ip, $inLen): void {
            if ($ip + $n > $inLen) {
                throw new \RuntimeException('lzo_input_overrun');
            }
        };
        $literals = static function (int $n) use (&$out, &$ip, $in, $outLen, $need): void {
            $need($n);
            if (strlen($out) + $n > $outLen) {
                throw new \RuntimeException('lzo_output_overrun');
            }
            $out .= substr($in, $ip, $n);
            $ip += $n;
        };
        $copyMatch = static function (int $from, int $n) use (&$out, $outLen): void {
            $op = strlen($out);
            if ($from < 0 || $from >= $op) {
                throw new \RuntimeException('lzo_lookbehind');
            }
            if ($op + $n > $outLen) {
                throw new \RuntimeException('lzo_output_overrun');
            }
            $dist = $op - $from;
            if ($dist >= $n) {
                $out .= substr($out, $from, $n);

                return;
            }
            for ($i = 0; $i < $n; $i++) {
                $out .= $out[$from + $i];
            }
        };
        $need(1);
        if (ord($in[0]) > 17) {
            $t = ord($in[$ip++]) - 17;
            if ($t < 4) {
                $state = 'match_next';
            } else {
                $literals($t);
                $state = 'first_literal_run';
            }
        }
        for ($guard = 0; $guard < 50_000_000; $guard++) {
            switch ($state) {
                case 'loop':
                    $need(1);
                    $t = ord($in[$ip++]);
                    if ($t >= 16) {
                        $state = 'match';
                        break;
                    }
                    if ($t === 0) {
                        $need(1);
                        while (ord($in[$ip]) === 0) {
                            $t += 255;
                            $ip++;
                            $need(1);
                        }
                        $t += 15 + ord($in[$ip++]);
                    }
                    $literals($t + 3);
                    $state = 'first_literal_run';
                    break;
                case 'first_literal_run':
                    $need(2);
                    $t = ord($in[$ip++]);
                    if ($t >= 16) {
                        $state = 'match';
                        break;
                    }
                    $mPos = strlen($out) - (1 + 0x0800) - ($t >> 2) - (ord($in[$ip++]) << 2);
                    $copyMatch($mPos, 3);
                    $state = 'match_done';
                    break;
                case 'match':
                    if ($t >= 64) {
                        $need(1);
                        $mPos = strlen($out) - 1 - (($t >> 2) & 7) - (ord($in[$ip++]) << 3);
                        $t = ($t >> 5) - 1;
                    } elseif ($t >= 32) {
                        $t &= 31;
                        if ($t === 0) {
                            $need(1);
                            while (ord($in[$ip]) === 0) {
                                $t += 255;
                                $ip++;
                                $need(1);
                            }
                            $t += 31 + ord($in[$ip++]);
                        }
                        $need(2);
                        $mPos = strlen($out) - 1 - ((ord($in[$ip]) >> 2) + (ord($in[$ip + 1]) << 6));
                        $ip += 2;
                    } elseif ($t >= 16) {
                        $mPos = strlen($out) - (($t & 8) << 11);
                        $t &= 7;
                        if ($t === 0) {
                            $need(1);
                            while (ord($in[$ip]) === 0) {
                                $t += 255;
                                $ip++;
                                $need(1);
                            }
                            $t += 7 + ord($in[$ip++]);
                        }
                        $need(2);
                        $mPos -= (ord($in[$ip]) >> 2) + (ord($in[$ip + 1]) << 6);
                        $ip += 2;
                        if ($mPos === strlen($out)) {
                            return $out; // fin de flux
                        }
                        $mPos -= 0x4000;
                    } else {
                        $need(1);
                        $mPos = strlen($out) - 1 - ($t >> 2) - (ord($in[$ip++]) << 2);
                        $copyMatch($mPos, 2);
                        $state = 'match_done';
                        break;
                    }
                    $copyMatch($mPos, $t + 2);
                    $state = 'match_done';
                    break;
                case 'match_done':
                    $t = ord($in[$ip - 2]) & 3;
                    if ($t === 0) {
                        $state = 'loop';
                        break;
                    }
                    // no break : quelques littéraux suivent le match
                case 'match_next':
                    $literals($t);
                    $need(1);
                    $t = ord($in[$ip++]);
                    $state = 'match';
                    break;
            }
            if (strlen($out) >= $outLen && $state !== 'match_done') {
                return $out;
            }
        }
        throw new \RuntimeException('lzo_runaway');
    }

    /**
     * LZSS d’Arma (formats non DXT) : octet de drapeaux, bit à 1 = octet littéral, à 0 = renvoi de 2 octets
     * (distance sur 12 bits, longueur sur 4 bits + 3). Les 4 octets de somme de contrôle finaux sont ignorés.
     */
    public static function lzssDecompress(string $in, int $outLen): string
    {
        $out = '';
        $ip = 0;
        $inLen = strlen($in);
        while (strlen($out) < $outLen && $ip < $inLen) {
            $flags = ord($in[$ip++]);
            for ($bit = 0; $bit < 8 && strlen($out) < $outLen; $bit++) {
                if (($flags >> $bit) & 1) {
                    if ($ip >= $inLen) {
                        throw new \RuntimeException('lzss_truncated');
                    }
                    $out .= $in[$ip++];
                    continue;
                }
                if ($ip + 1 >= $inLen) {
                    throw new \RuntimeException('lzss_truncated');
                }
                $b1 = ord($in[$ip++]);
                $b2 = ord($in[$ip++]);
                $rpos = $b1 | (($b2 & 0xF0) << 4);
                $rlen = ($b2 & 0x0F) + 3;
                $from = strlen($out) - $rpos;
                for ($i = 0; $i < $rlen && strlen($out) < $outLen; $i++) {
                    $at = $from + $i;
                    $out .= $at >= 0 && $at < strlen($out) ? $out[$at] : ' ';
                }
            }
        }
        if (strlen($out) < $outLen) {
            throw new \RuntimeException('lzss_truncated');
        }

        return $out;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb565(int $c): array
    {
        $r = ($c >> 11) & 0x1F;
        $g = ($c >> 5) & 0x3F;
        $b = $c & 0x1F;

        return [($r << 3) | ($r >> 2), ($g << 2) | ($g >> 4), ($b << 3) | ($b >> 2)];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $a
     * @param array{0: int, 1: int, 2: int} $b
     * @return array{0: int, 1: int, 2: int}
     */
    private static function mix(array $a, array $b, int $wa, int $wb, int $div): array
    {
        return [
            intdiv($wa * $a[0] + $wb * $b[0], $div),
            intdiv($wa * $a[1] + $wb * $b[1], $div),
            intdiv($wa * $a[2] + $wb * $b[2], $div),
        ];
    }

    private static function gdColor(int $r, int $g, int $b, int $a): int
    {
        // GD : alpha 0 = opaque, 127 = transparent.
        return (127 - ($a >> 1)) << 24 | ($r << 16) | ($g << 8) | $b;
    }

    private static function u16(string $s, int $p): int
    {
        return ord($s[$p]) | (ord($s[$p + 1]) << 8);
    }

    private static function u32(string $s, int $p): int
    {
        return ord($s[$p]) | (ord($s[$p + 1]) << 8) | (ord($s[$p + 2]) << 16) | (ord($s[$p + 3]) << 24);
    }
}
