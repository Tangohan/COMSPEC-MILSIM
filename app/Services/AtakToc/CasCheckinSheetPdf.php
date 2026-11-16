<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Fiche imprimable « 9 LINE CLOSE AIR SUPPORT BRIEF » (doctrine publique JP 3-09.3) : vierge, ou remplie depuis le check-in
 * versé au compte rendu par l'app JTAC du téléphone (CasCheckinSheet). Même grille que la fiche blanche : en-tête du FAC,
 * bloc de check-in, situation et game plan, 9 lignes numérotées (décalage gauche / droite et code laser), remarques, axe
 * final d'attaque, ligne laser-cible, menaces, ligne canon-cible active, TOT / TTT ou « PUSH ASAP » en case à cocher, et les
 * phrases de séquence du FAC.
 *
 * Aucun nom, logo ni sceau d'organisation réelle, aucune mention de jeu ; la classification n'est imprimée que si le
 * rapport en porte une (bandeau en haut et en bas), jamais sur la fiche vierge.
 */
final class CasCheckinSheetPdf
{
    private const L = 14.0;

    private const W = 182.0;

    private string $sans = 'freesans';

    private bool $en = false;

    /**
     * PDF de la fiche, ou null si le moteur PDF ou ses polices manquent.
     *
     * @param array<string, string> $v valeurs (CasCheckinSheet::fromStructured) ; [] : fiche vierge
     * @param array{lang?: string, banner?: string, rgb?: array{0: int, 1: int, 2: int}, ref?: string, dtg?: string} $opts
     */
    public function render(array $v = [], array $opts = []): ?string
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            return null;
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(fn (): string => $this->draw($v, $opts));
    }

    /** @param array<string, string> $v @param array<string, mixed> $opts */
    private function draw(array $v, array $opts): string
    {
        $this->sans = AthenaPdfTheme::sans();
        $this->en = ($opts['lang'] ?? 'fr') === 'en';
        $banner = trim((string) ($opts['banner'] ?? ''));
        $rgb = is_array($opts['rgb'] ?? null) ? array_values($opts['rgb']) : [0, 0, 0];

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Athena');
        $pdf->SetTitle('9 LINE CLOSE AIR SUPPORT BRIEF');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::L, 12.0, self::L);
        $pdf->SetAutoPageBreak(false);
        $pdf->setFontSubsetting(true);
        $pdf->SetCellPadding(0);
        $pdf->setCellHeightRatio(1.15);
        $pdf->AddPage();

        if ($banner !== '') {
            $this->band($pdf, 0.0, $banner, $rgb);
            $this->band($pdf, 297.0 - 6.4, $banner, $rgb);
        }
        $y = $banner !== '' ? 11.0 : 9.0;
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont($this->sans, 'B', 15.0);
        $pdf->SetXY(self::L, $y);
        $pdf->Cell(self::W, 7.0, '9 LINE CLOSE AIR SUPPORT BRIEF', 0, 0, 'C');
        $y += 7.6;
        $ref = trim((string) ($opts['ref'] ?? '') . ((string) ($opts['dtg'] ?? '') !== '' ? '  ·  ' . $opts['dtg'] : ''));
        if ($ref !== '') {
            $pdf->SetFont($this->sans, '', 7.4);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetXY(self::L, $y);
            $pdf->Cell(self::W, 3.6, $ref, 0, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $y += 4.4;
        }
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.35);

        // En-tête du FAC : « [appareil], this is [FAC], standing by for aircraft check-in ».
        $y = $this->sentence($pdf, $y, [
            ['FAC: “', false], [$v['ac'] ?? '', true, 44.0], [', this is ', false], [$v['fac'] ?? '', true, 40.0], [', standing by for aircraft check-in”', false],
        ]);

        // Bloc de check-in : sept lignes, libellé grisé à gauche.
        foreach (CasCheckinSheet::CHECK_IN as $k) {
            $y = $this->pair($pdf, $y, CasCheckinSheet::FIELDS[$k][0], $v[$k] ?? '', 62.0);
        }
        $y = $this->band2($pdf, $y, 'Provide friendly, enemy situation and game plan to pilot', 'BI');
        if (($v['sit'] ?? '') !== '') {
            $y = $this->free($pdf, $y, $v['sit']);
        }
        $y = $this->band2($pdf, $y, 'FAC: “Stand by for 9 line, I am not a JTAC”', 'I');

        // Les 9 lignes.
        foreach (CasCheckinSheet::NINE as $i => $k) {
            $n = $i + 1;
            if ($n === 2) {
                $y = $this->line2($pdf, $y, $v);
                continue;
            }
            if ($n === 7) {
                $y = $this->line7($pdf, $y, $v);
                continue;
            }
            $y = $this->nine($pdf, $y, $n, $k, $v[$k] ?? '', $n === 9 ? '“EGRESS”:' : '');
        }
        $y = $this->band2($pdf, $y, 'FAC: “Say when ready to copy remarks”', 'I');

        // Remarques.
        $y = $this->labelled($pdf, $y, 'rem', $v['rem'] ?? '', 34.0);
        $y = $this->duo($pdf, $y, ['fah', $v['fah'] ?? '', 40.0, 51.0], ['ltl', $v['ltl'] ?? '', 34.0, 57.0]);
        $y = $this->labelled($pdf, $y, 'thr', $v['thr'] ?? '', 34.0);
        $y = $this->gunTot($pdf, $y, $v);
        $y = $this->band2($pdf, $y, 'FAC: “Say when ready for amplifying information”.  Give pilot talk-on to TGT, big to small', 'I');
        if (($v['amp'] ?? '') !== '') {
            $this->free($pdf, $y, $v['amp']);
        }

        return (string) $pdf->Output('cas-checkin.pdf', 'S');
    }

    /** @param list<int> $rgb */
    private function band(\TCPDF $pdf, float $y, string $text, array $rgb): void
    {
        $luma = 0.299 * ($rgb[0] ?? 0) + 0.587 * ($rgb[1] ?? 0) + 0.114 * ($rgb[2] ?? 0);
        $pdf->SetFillColor((int) ($rgb[0] ?? 0), (int) ($rgb[1] ?? 0), (int) ($rgb[2] ?? 0));
        $pdf->Rect(0.0, $y, 210.0, 6.4, 'F');
        $pdf->SetTextColor(...($luma > 150 ? [0, 0, 0] : [255, 255, 255]));
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY(0.0, $y + 0.9);
        $pdf->Cell(210.0, 4.6, $text, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function hint(string $key): string
    {
        $f = CasCheckinSheet::FIELDS[$key];

        return $this->en ? $f[2] : $f[1];
    }

    /** Hauteur d'une valeur sur une largeur donnée (au moins une ligne de fiche). */
    private function height(\TCPDF $pdf, string $text, float $w, float $min = 7.2): float
    {
        if ($text === '') {
            return $min;
        }
        $pdf->SetFont($this->sans, '', 9.6);

        return max($min, $pdf->getStringHeight($w - 2.4, $text) + 2.0);
    }

    private function value(\TCPDF $pdf, float $x, float $y, float $w, float $h, string $text): void
    {
        if ($text === '') {
            return;
        }
        $pdf->SetFont($this->sans, '', 9.6);
        $pdf->SetTextColor(10, 40, 110);
        $pdf->MultiCell($w - 2.4, $h - 1.0, $text, 0, 'L', false, 0, $x + 1.2, $y + 1.0, true, 0, false, true, $h - 1.0, 'M');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function grey(\TCPDF $pdf, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetFillColor(214, 214, 214);
        $pdf->Rect($x, $y, $w, $h, 'DF');
    }

    /** Phrase de l'en-tête : textes fixes et blancs soulignés (valeur écrite dessus). @param list<array{0: string, 1: bool, 2?: float}> $parts */
    private function sentence(\TCPDF $pdf, float $y, array $parts): float
    {
        $h = 8.0;
        $pdf->Rect(self::L, $y, self::W, $h, 'D');
        $x = self::L + 3.0;
        foreach ($parts as $p) {
            if (!$p[1]) {
                $pdf->SetFont($this->sans, 'BI', 9.6);
                $w = $pdf->GetStringWidth($p[0]);
                $pdf->SetXY($x, $y + 1.8);
                $pdf->Cell($w, 4.6, $p[0], 0, 0, 'L');
                $x += $w;
                continue;
            }
            $w = $p[2] ?? 40.0;
            $pdf->SetLineWidth(0.2);
            $pdf->Line($x + 0.5, $y + 6.2, $x + $w - 0.5, $y + 6.2);
            $pdf->SetLineWidth(0.35);
            if ($p[0] !== '') {
                $size = 9.6;
                $pdf->SetFont($this->sans, '', $size);
                while ($size > 6.0 && $pdf->GetStringWidth($p[0]) > $w - 1.0) {
                    $size -= 0.3;
                    $pdf->SetFont($this->sans, '', $size);
                }
                $pdf->SetTextColor(10, 40, 110);
                $pdf->SetXY($x, $y + 1.6);
                $pdf->Cell($w, 4.6, $p[0], 0, 0, 'C');
                $pdf->SetTextColor(0, 0, 0);
            }
            $x += $w;
        }

        return $y + $h;
    }

    /** Ligne pleine largeur centrée (consigne ou phrase du FAC). */
    private function band2(\TCPDF $pdf, float $y, string $text, string $style): float
    {
        $h = 7.2;
        $pdf->Rect(self::L, $y, self::W, $h, 'D');
        $pdf->SetFont($this->sans, $style, 9.8);
        $pdf->SetXY(self::L, $y + 1.4);
        $pdf->Cell(self::W, 4.6, $text, 0, 0, 'C');

        return $y + $h;
    }

    /** Texte libre sur toute la largeur (situation, informations complémentaires). */
    private function free(\TCPDF $pdf, float $y, string $text): float
    {
        $h = $this->height($pdf, $text, self::W);
        $pdf->Rect(self::L, $y, self::W, $h, 'D');
        $this->value($pdf, self::L, $y, self::W, $h, $text);

        return $y + $h;
    }

    /** Libellé grisé et valeur (bloc de check-in). */
    private function pair(\TCPDF $pdf, float $y, string $label, string $text, float $lw): float
    {
        $h = $this->height($pdf, $text, self::W - $lw);
        $this->grey($pdf, self::L, $y, $lw, $h);
        $pdf->Rect(self::L + $lw, $y, self::W - $lw, $h, 'D');
        $pdf->SetFont($this->sans, 'B', 9.0);
        $pdf->SetXY(self::L + 1.4, $y + ($h - 4.4) / 2);
        $pdf->Cell($lw - 2.0, 4.4, $label, 0, 0, 'L');
        $this->value($pdf, self::L + $lw, $y, self::W - $lw, $h, $text);

        return $y + $h;
    }

    /** Cellule de libellé d'une ligne numérotée : numéro, libellé en gras, aide en petit dessous. */
    private function head(\TCPDF $pdf, float $y, float $h, string $num, string $label, string $hint, float $lw): void
    {
        $pdf->Rect(self::L, $y, 8.0, $h, 'D');
        $this->grey($pdf, self::L + 8.0, $y, $lw, $h);
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY(self::L, $y + ($h - 4.6) / 2);
        $pdf->Cell(8.0, 4.6, $num, 0, 0, 'C');
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY(self::L + 9.4, $y + 1.0);
        $pdf->Cell($lw - 2.0, 4.6, $label, 0, 0, 'L');
        if ($hint !== '') {
            $pdf->SetFont($this->sans, '', 5.6);
            $pdf->SetXY(self::L + 9.4, $y + $h - 3.0);
            $pdf->Cell($lw - 2.0, 2.6, $hint, 0, 0, 'L');
        }
    }

    /** Ligne numérotée simple (1, 3 à 6, 8, 9) ; aide écrite sous la valeur quand elle est plus longue que le libellé. */
    private function nine(\TCPDF $pdf, float $y, int $n, string $key, string $text, string $prefix): float
    {
        $lw = 34.0;
        $vx = self::L + 8.0 + $lw;
        $vw = self::W - 8.0 - $lw;
        $h = max(9.0, $this->height($pdf, $text, $vw, 9.0) + 2.6);
        $this->head($pdf, $y, $h, (string) $n, CasCheckinSheet::FIELDS[$key][0], '', $lw);
        $pdf->Rect($vx, $y, $vw, $h, 'D');
        $pdf->SetFont($this->sans, '', 5.6);
        $pdf->SetXY($vx + 1.2, $y + $h - 3.0);
        $pdf->Cell($vw - 2.4, 2.6, $this->hint($key), 0, 0, 'L');
        if ($prefix !== '') {
            $pdf->SetFont($this->sans, '', 9.6);
            $pdf->SetXY($vx + 1.2, $y + 1.2);
            $pdf->Cell(18.0, 4.6, $prefix, 0, 0, 'L');
            $this->value($pdf, $vx + 18.0, $y, $vw - 18.0, $h - 2.6, $text);
        } else {
            $this->value($pdf, $vx, $y, $vw, $h - 2.6, $text);
        }

        return $y + $h;
    }

    private function box(\TCPDF $pdf, float $x, float $y, bool $on): void
    {
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x, $y, 3.4, 3.4, 'D');
        if ($on) {
            $pdf->Line($x + 0.6, $y + 0.6, $x + 2.8, $y + 2.8);
            $pdf->Line($x + 2.8, $y + 0.6, $x + 0.6, $y + 2.8);
        }
        $pdf->SetLineWidth(0.35);
    }

    /** Ligne 2 : cap, puis « Offset » avec cases Left / Right. @param array<string, string> $v */
    private function line2(\TCPDF $pdf, float $y, array $v): float
    {
        $lw = 34.0;
        $h = 9.0;
        $this->head($pdf, $y, $h, '2', 'Heading', '', $lw);
        $vx = self::L + 8.0 + $lw;
        $offX = self::L + 104.0;
        $pdf->Rect($vx, $y, $offX - $vx, $h, 'D');
        $pdf->SetFont($this->sans, '', 5.6);
        $pdf->SetXY($vx + 1.2, $y + $h - 3.0);
        $pdf->Cell($offX - $vx - 2.4, 2.6, $this->hint('l2'), 0, 0, 'L');
        $this->value($pdf, $vx, $y, $offX - $vx, $h - 2.6, $v['l2'] ?? '');
        $this->grey($pdf, $offX, $y, 24.0, $h);
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY($offX, $y + 2.2);
        $pdf->Cell(24.0, 4.6, 'Offset', 0, 0, 'C');
        $off = CasCheckinSheet::offset($v['l2o'] ?? '');
        $rest = self::L + self::W - $offX - 24.0;
        foreach (['LEFT' => 'Left', 'RIGHT' => 'Right'] as $code => $label) {
            $x = $offX + 24.0 + ($code === 'LEFT' ? 0.0 : $rest / 2);
            $pdf->Rect($x, $y, $rest / 2, $h, 'D');
            $this->box($pdf, $x + 3.0, $y + 2.8, $off === $code);
            $pdf->SetFont($this->sans, '', 10.0);
            $pdf->SetXY($x + 8.0, $y + 2.2);
            $pdf->Cell($rest / 2 - 9.0, 4.6, $label, 0, 0, 'L');
        }

        return $y + $h;
    }

    /** Ligne 7 : type de marquage, puis code laser. @param array<string, string> $v */
    private function line7(\TCPDF $pdf, float $y, array $v): float
    {
        $lw = 34.0;
        $h = 9.0;
        $this->head($pdf, $y, $h, '7', 'Type Mark', '', $lw);
        $vx = self::L + 8.0 + $lw;
        $cx = self::L + 118.0;
        $pdf->Rect($vx, $y, $cx - $vx, $h, 'D');
        $pdf->SetFont($this->sans, '', 5.6);
        $pdf->SetXY($vx + 1.2, $y + $h - 3.0);
        $pdf->Cell($cx - $vx - 2.4, 2.6, $this->hint('l7'), 0, 0, 'L');
        $this->value($pdf, $vx, $y, $cx - $vx, $h - 2.6, $v['l7'] ?? '');
        $this->grey($pdf, $cx, $y, 30.0, $h);
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY($cx, $y + 2.2);
        $pdf->Cell(30.0, 4.6, 'Laser Code', 0, 0, 'C');
        $rx = $cx + 30.0;
        $rw = self::L + self::W - $rx;
        $pdf->Rect($rx, $y, $rw, $h, 'D');
        $pdf->SetFont($this->sans, '', 5.6);
        $pdf->SetXY($rx + 1.2, $y + $h - 3.0);
        $pdf->Cell($rw - 2.4, 2.6, '1111, 1688', 0, 0, 'L');
        $this->value($pdf, $rx, $y, $rw, $h - 2.6, $v['l7c'] ?? '');

        return $y + $h;
    }

    /** Libellé grisé centré (avec son aide) et valeur sur le reste de la ligne (Remarks, Threats). */
    private function labelled(\TCPDF $pdf, float $y, string $key, string $text, float $lw): float
    {
        $vw = self::W - $lw;
        $h = max(9.0, $this->height($pdf, $text, $vw, 9.0) + 2.6);
        $this->grey($pdf, self::L, $y, $lw, $h);
        $pdf->SetFont($this->sans, 'B', 10.0);
        $pdf->SetXY(self::L, $y + 1.4);
        $pdf->Cell($lw, 4.6, CasCheckinSheet::FIELDS[$key][0], 0, 0, 'C');
        $pdf->Rect(self::L + $lw, $y, $vw, $h, 'D');
        $pdf->SetFont($this->sans, '', 5.6);
        $pdf->SetXY(self::L + $lw + 1.2, $y + $h - 3.0);
        $pdf->Cell($vw - 2.4, 2.6, $this->hint($key), 0, 0, 'L');
        $this->value($pdf, self::L + $lw, $y, $vw, $h - 2.6, $text);

        return $y + $h;
    }

    /**
     * Deux paires libellé / valeur sur une ligne (Final Attack Heading, Laser Target Line).
     *
     * @param array{0: string, 1: string, 2: float, 3: float} $a
     * @param array{0: string, 1: string, 2: float, 3: float} $b
     */
    private function duo(\TCPDF $pdf, float $y, array $a, array $b): float
    {
        $h = 9.0;
        $x = self::L;
        foreach ([$a, $b] as [$key, $text, $lw, $vw]) {
            $this->grey($pdf, $x, $y, $lw, $h);
            $pdf->SetFont($this->sans, 'B', 9.6);
            $pdf->SetXY($x, $y + 2.2);
            $pdf->Cell($lw, 4.6, CasCheckinSheet::FIELDS[$key][0], 0, 0, 'C');
            $pdf->Rect($x + $lw, $y, $vw, $h, 'D');
            $pdf->SetFont($this->sans, '', 5.6);
            $pdf->SetXY($x + $lw + 1.2, $y + $h - 3.0);
            $pdf->Cell($vw - 2.4, 2.6, $this->hint($key), 0, 0, 'R');
            $this->value($pdf, $x + $lw, $y, $vw, $h - 2.6, $text);
            $x += $lw + $vw;
        }

        return $y + $h;
    }

    /** Ligne canon-cible active, puis TOT / TTT ou « PUSH ASAP » (case à cocher). @param array<string, string> $v */
    private function gunTot(\TCPDF $pdf, float $y, array $v): float
    {
        $h = 9.0;
        $this->grey($pdf, self::L, $y, 48.0, $h);
        $pdf->SetFont($this->sans, 'B', 9.6);
        $pdf->SetXY(self::L, $y + 2.2);
        $pdf->Cell(48.0, 4.6, 'Active Gun Target Line', 0, 0, 'C');
        $pdf->Rect(self::L + 48.0, $y, 42.0, $h, 'D');
        $this->value($pdf, self::L + 48.0, $y, 42.0, $h, $v['gtl'] ?? '');
        $x = self::L + 90.0;
        $this->grey($pdf, $x, $y, 22.0, $h);
        $pdf->SetFont($this->sans, 'B', 9.6);
        $pdf->SetXY($x, $y + 2.2);
        $pdf->Cell(22.0, 4.6, 'TOT / TTT', 0, 0, 'C');
        $pdf->Rect($x + 22.0, $y, 34.0, $h, 'D');
        $this->value($pdf, $x + 22.0, $y, 34.0, $h, $v['tot'] ?? '');
        $rx = $x + 56.0;
        $pdf->Rect($rx, $y, self::L + self::W - $rx, $h, 'D');
        $pdf->SetFont($this->sans, '', 9.6);
        $pdf->SetXY($rx + 1.5, $y + 2.2);
        $pdf->Cell(6.0, 4.6, 'or', 0, 0, 'L');
        $this->box($pdf, $rx + 8.0, $y + 2.8, ($v['push'] ?? '') !== '');
        $pdf->SetFont($this->sans, 'B', 9.6);
        $pdf->SetXY($rx + 12.6, $y + 2.2);
        $pdf->Cell(self::L + self::W - $rx - 13.0, 4.6, '“PUSH ASAP”', 0, 0, 'L');

        return $y + $h;
    }
}
