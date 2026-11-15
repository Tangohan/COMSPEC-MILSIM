<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Éléments graphiques communs des documents d'alerte du portail SSE (fiche de vigilance, notice de recherche) : pictogramme
 * d'avertissement, bande hachurée, barres de niveau, cadre plein sur tout le contour, emblème générique d'un service, QR code de
 * l'adresse de signalement. Tout est dessiné en vectoriel par TCPDF : aucune image externe, aucun logo, sceau ni emblème réel.
 *
 * La classe hôte fournit `$this->pdf` (\TCPDF) et `$this->sans` (police embarquée).
 */
trait SsePdfAlertArt
{
    /**
     * Pictogramme d'avertissement : forme pleine (triangle, losange, octogone) et point d'exclamation.
     *
     * @param array{0: int, 1: int, 2: int} $fill couleur de la forme
     * @param array{0: int, 1: int, 2: int} $mark couleur du point d'exclamation
     */
    private function artPictogram(float $x, float $y, float $size, string $shape, array $fill, array $mark): void
    {
        $pdf = $this->pdf;
        $cx = $x + $size / 2;
        $cy = $y + $size / 2;
        $r = $size / 2;
        $points = match ($shape) {
            'diamond' => [$cx, $y, $x + $size, $cy, $cx, $y + $size, $x, $cy],
            'octagon' => self::artRegular($cx, $cy, $r, 8, 22.5),
            default => [$cx, $y + $size * 0.04, $x + $size, $y + $size * 0.92, $x, $y + $size * 0.92],
        };
        $pdf->SetFillColor(...$fill);
        $pdf->SetDrawColor(...$fill);
        $pdf->SetLineStyle(['width' => $size * 0.05, 'join' => 'round', 'cap' => 'round', 'dash' => 0, 'color' => $fill]);
        $pdf->Polygon($points, 'DF');
        // Point d'exclamation : barre effilée et point, centrés sur le centre visuel de la forme.
        $my = $shape === 'triangle' ? $y + $size * 0.60 : $cy;
        $h = $size * ($shape === 'triangle' ? 0.50 : 0.56);
        $top = $my - $h / 2;
        $w = $size * 0.085;
        $pdf->SetFillColor(...$mark);
        $pdf->Polygon([$cx - $w, $top, $cx + $w, $top, $cx + $w * 0.62, $top + $h * 0.62, $cx - $w * 0.62, $top + $h * 0.62], 'F');
        $pdf->Circle($cx, $top + $h * 0.86, $w * 0.95, 0, 360, 'F');
        $pdf->SetLineStyle(['width' => 0.25, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => [24, 30, 38]]);
    }

    /** @return list<float> sommets d'un polygone régulier */
    private static function artRegular(float $cx, float $cy, float $r, int $n, float $startDeg): array
    {
        $out = [];
        for ($i = 0; $i < $n; ++$i) {
            $a = deg2rad($startDeg + $i * 360 / $n);
            $out[] = $cx + $r * cos($a);
            $out[] = $cy + $r * sin($a);
        }

        return $out;
    }

    /**
     * Bande hachurée d'avertissement (rayures obliques à 45°), rognée au rectangle.
     *
     * @param array{0: int, 1: int, 2: int} $a couleur du fond
     * @param array{0: int, 1: int, 2: int} $b couleur des rayures
     */
    private function artHatch(float $x, float $y, float $w, float $h, array $a, array $b, float $stripe = 0.0): void
    {
        $pdf = $this->pdf;
        $stripe = $stripe > 0 ? $stripe : $h * 1.1;
        $pdf->SetFillColor(...$a);
        $pdf->Rect($x, $y, $w, $h, 'F');
        $pdf->StartTransform();
        $pdf->Rect($x, $y, $w, $h, 'CNZ');
        $pdf->SetFillColor(...$b);
        for ($sx = $x - $h; $sx < $x + $w + $h; $sx += $stripe * 2) {
            $pdf->Polygon([$sx, $y + $h, $sx + $stripe, $y + $h, $sx + $stripe + $h, $y, $sx + $h, $y], 'F');
        }
        $pdf->StopTransform();
    }

    /**
     * Barres de niveau (1 à 4) : pleines jusqu'au rang, contour seul au-delà (lisible sans couleur).
     *
     * @param array{0: int, 1: int, 2: int} $ink
     */
    private function artBars(float $x, float $y, int $rank, array $ink, float $barW = 4.2, float $gap = 1.5, float $maxH = 11.0): void
    {
        $pdf = $this->pdf;
        $pdf->SetLineStyle(['width' => 0.45, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => $ink]);
        $pdf->SetFillColor(...$ink);
        for ($i = 1; $i <= 4; ++$i) {
            $h = $maxH * (0.4 + 0.2 * $i);
            $pdf->Rect($x + ($i - 1) * ($barW + $gap), $y + $maxH - $h, $barW, $h, $i <= $rank ? 'DF' : 'D');
        }
        $pdf->SetLineStyle(['width' => 0.25, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => [24, 30, 38]]);
    }

    /**
     * Cadre plein de couleur sur tout le contour de la page (notice de couleur), avec un filet intérieur.
     *
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function artFrame(array $rgb, float $t, float $pageW = 210.0, float $pageH = 297.0): void
    {
        $pdf = $this->pdf;
        $pdf->SetFillColor(...$rgb);
        $pdf->Rect(0, 0, $pageW, $t, 'F');
        $pdf->Rect(0, $pageH - $t, $pageW, $t, 'F');
        $pdf->Rect(0, 0, $t, $pageH, 'F');
        $pdf->Rect($pageW - $t, 0, $t, $pageH, 'F');
        $pdf->SetLineStyle(['width' => 0.35, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => $rgb]);
        $pdf->Rect($t + 1.3, $t + 1.3, $pageW - 2 * $t - 2.6, $pageH - 2 * $t - 2.6, 'D');
        $pdf->SetLineStyle(['width' => 0.25, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => [24, 30, 38]]);
    }

    /**
     * Emblème d'un service : image téléversée par la communauté (octets) ou, à défaut, cartouche générique (forme, couleur, sigle).
     *
     * @param array<string, mixed> $service color (#RRGGBB), emblem (forme), ac (sigle), emblem_bytes (?string)
     */
    private function artServiceEmblem(array $service, float $x, float $y, float $size): void
    {
        $pdf = $this->pdf;
        $bytes = $service['emblem_bytes'] ?? null;
        if (is_string($bytes) && $bytes !== '') {
            try {
                $pdf->Image('@' . $bytes, $x, $y, $size, $size, '', '', '', true, 300, '', false, false, 0, 'CM');

                return;
            } catch (\Throwable) {
                // Image illisible : le cartouche générique prend le relais.
            }
        }
        $hex = (string) ($service['color'] ?? '');
        $rgb = preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $hex, $m) === 1 ? [(int) hexdec($m[1]), (int) hexdec($m[2]), (int) hexdec($m[3])] : [31, 58, 95];
        $cx = $x + $size / 2;
        $cy = $y + $size / 2;
        $r = $size / 2;
        $pdf->SetFillColor(...$rgb);
        $pdf->SetLineStyle(['width' => $size * 0.045, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => $rgb]);
        switch ((string) ($service['emblem'] ?? 'hex')) {
            case 'circle':
                $pdf->Circle($cx, $cy, $r, 0, 360, 'F');
                break;
            case 'ring':
                $pdf->Circle($cx, $cy, $r, 0, 360, 'F');
                $pdf->SetLineStyle(['width' => $size * 0.03, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => [255, 255, 255]]);
                $pdf->Circle($cx, $cy, $r * 0.82, 0, 360, 'D');
                break;
            case 'diamond':
                $pdf->Polygon([$cx, $y, $x + $size, $cy, $cx, $y + $size, $x, $cy], 'F');
                break;
            case 'chevron':
                $pdf->Polygon([$x, $y, $x + $size, $y, $x + $size, $y + $size * 0.62, $cx, $y + $size, $x, $y + $size * 0.62], 'F');
                break;
            case 'bars':
                $pdf->Rect($x, $y, $size, $size, 'F');
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($x, $y + $size * 0.78, $size, $size * 0.05, 'F');
                $pdf->Rect($x, $y + $size * 0.88, $size, $size * 0.05, 'F');
                break;
            case 'cross':
                $pdf->Polygon(self::artRegular($cx, $cy, $r, 8, 22.5), 'F');
                break;
            default:
                $pdf->Polygon(self::artRegular($cx, $cy, $r, 6, 30.0), 'F');
        }
        $ac = mb_strtoupper(trim((string) ($service['ac'] ?? '')), 'UTF-8');
        $ac = $ac !== '' ? mb_substr($ac, 0, 6, 'UTF-8') : '';
        if ($ac !== '') {
            $fs = $size * 1.15;
            $pdf->SetFont($this->sans, 'B', $fs);
            while ($fs > 3.6 && $pdf->GetStringWidth($ac) > $size * 0.74) {
                $fs -= 0.3;
                $pdf->SetFont($this->sans, 'B', $fs);
            }
            $pdf->SetTextColor(255, 255, 255);
            $lh = $fs * 0.3528 * 1.22;
            $pdf->SetXY($x, $cy - $lh / 2 - ((string) ($service['emblem'] ?? '') === 'chevron' ? $size * 0.07 : 0));
            $pdf->Cell($size, $lh, $ac, 0, 0, 'C');
        }
        $pdf->SetLineStyle(['width' => 0.25, 'join' => 'miter', 'cap' => 'butt', 'dash' => 0, 'color' => [24, 30, 38]]);
    }

    /** QR code d'une adresse (généré par TCPDF, aucun service externe). Faux si l'adresse est vide ou le code impossible. */
    private function artQr(string $url, float $x, float $y, float $size): bool
    {
        if ($url === '') {
            return false;
        }
        try {
            $style = ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255], 'module_width' => 1, 'module_height' => 1];
            $this->pdf->write2DBarcode($url, 'QRCODE,M', $x, $y, $size, $size, $style, 'N');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Plus grande taille (de $size à $min) à laquelle le texte tient sur une ligne de largeur $w ; à $min, coupé avec « … ».
     *
     * @return array{0: string, 1: float}
     */
    private function artFit(string $text, float $w, float $size, float $min, string $style = 'B', ?string $font = null): array
    {
        $pdf = $this->pdf;
        $font ??= $this->sans;
        for ($s = $size; $s >= $min; $s -= 0.5) {
            $pdf->SetFont($font, $style, $s);
            if ($pdf->GetStringWidth($text) <= $w) {
                return [$text, $s];
            }
        }
        $pdf->SetFont($font, $style, $min);
        $t = $text;
        while (mb_strlen($t, 'UTF-8') > 3 && $pdf->GetStringWidth($t . '…') > $w) {
            $t = mb_substr($t, 0, mb_strlen($t, 'UTF-8') - 1, 'UTF-8');
        }

        return [rtrim($t) . '…', $min];
    }
}
