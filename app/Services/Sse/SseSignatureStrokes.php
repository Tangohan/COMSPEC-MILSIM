<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Services\Media\SignatureImageService;

/**
 * Signature tracée dans le terminal SEEK II (Arma 3) : le mod envoie des traits (points normalisés de 0 à 1000),
 * jamais une image. Le serveur valide strictement les traits puis dessine lui-même le PNG (sans GD), qui passe
 * ensuite par SignatureImageService (mêmes contrôles et même rangement que le pad du site).
 *
 * Entrée : ['strokes' => [[[x, y], ...], ...], 'aspect' => largeur / hauteur du pad]
 */
final class SseSignatureStrokes
{
    public const MAX_STROKES = 12;
    public const MAX_POINTS_PER_STROKE = 400;
    public const MAX_POINTS = 800;
    public const COORD_MAX = 1000;
    /** Au moins deux points au total et un trait réellement tracé. */
    public const MIN_POINTS = 6;
    public const MIN_ASPECT = 1.0;
    public const MAX_ASPECT = 6.0;
    public const WIDTH = 600;
    public const PEN_RADIUS = 2;

    /**
     * @param mixed $raw valeur du champ « signature » reçu de l'API
     * @return array{ok: bool, strokes?: list<list<array{0: int, 1: int}>>, aspect?: float, error?: string}
     */
    public static function validate(mixed $raw): array
    {
        if (!is_array($raw) || !isset($raw['strokes']) || !is_array($raw['strokes'])) {
            return ['ok' => false, 'error' => 'Aucun trait de signature.'];
        }
        $strokesIn = $raw['strokes'];
        if ($strokesIn === [] || count($strokesIn) > self::MAX_STROKES || !array_is_list($strokesIn)) {
            return ['ok' => false, 'error' => 'Nombre de traits de signature invalide.'];
        }
        $aspect = $raw['aspect'] ?? 2.5;
        if ((!is_int($aspect) && !is_float($aspect)) || !is_finite((float) $aspect)
            || $aspect < self::MIN_ASPECT || $aspect > self::MAX_ASPECT) {
            return ['ok' => false, 'error' => 'Proportions de signature invalides.'];
        }
        $strokes = [];
        $total = 0;
        foreach ($strokesIn as $stroke) {
            if (!is_array($stroke) || !array_is_list($stroke) || $stroke === [] || count($stroke) > self::MAX_POINTS_PER_STROKE) {
                return ['ok' => false, 'error' => 'Trait de signature invalide.'];
            }
            $pts = [];
            foreach ($stroke as $pt) {
                if (!is_array($pt) || count($pt) !== 2 || !array_key_exists(0, $pt) || !array_key_exists(1, $pt)) {
                    return ['ok' => false, 'error' => 'Point de signature invalide.'];
                }
                foreach ([0, 1] as $i) {
                    $v = $pt[$i];
                    if ((!is_int($v) && !is_float($v)) || !is_finite((float) $v) || $v < 0 || $v > self::COORD_MAX) {
                        return ['ok' => false, 'error' => 'Point de signature hors limites.'];
                    }
                }
                $pts[] = [(int) round((float) $pt[0]), (int) round((float) $pt[1])];
            }
            $total += count($pts);
            if ($total > self::MAX_POINTS) {
                return ['ok' => false, 'error' => 'Signature trop longue.'];
            }
            $strokes[] = $pts;
        }
        if ($total < self::MIN_POINTS) {
            return ['ok' => false, 'error' => 'Signature trop courte.'];
        }
        $xs = [];
        $ys = [];
        foreach ($strokes as $s) {
            foreach ($s as [$x, $y]) {
                $xs[] = $x;
                $ys[] = $y;
            }
        }
        if (max($xs) - min($xs) < 20 && max($ys) - min($ys) < 20) {
            return ['ok' => false, 'error' => 'Signature trop courte.'];
        }

        return ['ok' => true, 'strokes' => $strokes, 'aspect' => round((float) $aspect, 3)];
    }

    /**
     * Dessine les traits (encre bleu nuit, fond transparent) et rend le PNG en data URL.
     *
     * @param list<list<array{0: int, 1: int}>> $strokes déjà validés par validate()
     */
    public static function renderDataUrl(array $strokes, float $aspect): string
    {
        $w = self::WIDTH;
        $h = max(80, (int) round($w / $aspect));
        $buf = array_fill(0, $w * $h, false);
        $r = self::PEN_RADIUS;
        $stamp = static function (int $cx, int $cy) use (&$buf, $w, $h, $r): void {
            for ($dy = -$r; $dy <= $r; $dy++) {
                for ($dx = -$r; $dx <= $r; $dx++) {
                    if ($dx * $dx + $dy * $dy > $r * $r + 1) {
                        continue;
                    }
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $y >= 0 && $x < $w && $y < $h) {
                        $buf[$y * $w + $x] = true;
                    }
                }
            }
        };
        $margin = $r + 2;
        $mx = static fn (int $v): int => (int) round($margin + ($v / self::COORD_MAX) * ($w - 2 * $margin - 1));
        $my = static fn (int $v): int => (int) round($margin + ($v / self::COORD_MAX) * ($h - 2 * $margin - 1));
        foreach ($strokes as $stroke) {
            $prev = null;
            foreach ($stroke as [$px, $py]) {
                $x = $mx($px);
                $y = $my($py);
                if ($prev === null) {
                    $stamp($x, $y);
                } else {
                    $steps = max(abs($x - $prev[0]), abs($y - $prev[1]), 1);
                    for ($i = 1; $i <= $steps; $i++) {
                        $stamp((int) round($prev[0] + ($x - $prev[0]) * $i / $steps), (int) round($prev[1] + ($y - $prev[1]) * $i / $steps));
                    }
                }
                $prev = [$x, $y];
            }
        }
        $ink = chr(18) . chr(32) . chr(64) . chr(255);
        $none = "\0\0\0\0";
        $raw = '';
        for ($y = 0; $y < $h; $y++) {
            $row = "\0";
            $base = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $row .= $buf[$base + $x] ? $ink : $none;
            }
            $raw .= $row;
        }
        $chunk = static fn (string $t, string $d): string => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
        $png = "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0))
            . $chunk('IDAT', (string) gzcompress($raw, 9))
            . $chunk('IEND', '');

        return SignatureImageService::PREFIX . base64_encode($png);
    }

    /**
     * Valide, dessine et range la signature.
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public static function store(mixed $rawSignature, int $tenantId, ?SignatureImageService $images = null): array
    {
        $v = self::validate($rawSignature);
        if (!$v['ok']) {
            return ['ok' => false, 'error' => $v['error'] ?? 'Signature refusée.'];
        }
        $dataUrl = self::renderDataUrl($v['strokes'], $v['aspect']);

        return ($images ?? new SignatureImageService())->fromDataUrl($dataUrl, $tenantId, 'sse_identity');
    }
}
