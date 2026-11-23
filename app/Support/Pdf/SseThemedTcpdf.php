<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Support\SseSheetThemes;
use App\Support\TrainingCertificatePdfEngine;

/**
 * PDF d'un document SSE habillé par un thème de feuille (voir {@see SseSheetThemes}) : papier teinté, bloc d'identification,
 * bandeaux de classification, filigrane diagonal très pâle, marge de registre, tampon de classification, pied de page avec
 * référence, destinataires et « page n sur N ». Mêmes couleurs que la feuille de l'éditeur (public/assets/css/sse-doc-themes.css).
 * Aucun logo, sceau ni emblème : seul le texte du portail (ATHENA / SSE).
 */
final class SseThemedTcpdf extends AthenaTcpdf
{
    /** @var array<string,string> */
    public array $theme = [];

    public string $themeId = SseSheetThemes::DEFAULT;

    public string $watermark = '';

    public string $recipients = '';

    public string $stamp = '';

    public static function themed(array $meta, string $themeId, string $watermark, string $recipients, string $stamp): ?self
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            return null;
        }
        $pdf = self::make(AthenaPdfTheme::meta($meta));
        $pdf->themeId = SseSheetThemes::normalize($themeId);
        $pdf->theme = SseSheetThemes::get($themeId);
        $pdf->watermark = $watermark;
        $pdf->recipients = $recipients;
        $pdf->stamp = $stamp;
        $pdf->SetTextColorArray(SseSheetThemes::rgb($pdf->theme['ink']));

        return $pdf;
    }

    /** Famille du corps selon le thème (serif et machine à écrire si les fontes sont embarquées, sinon sans). */
    public function bodyFamily(): string
    {
        $kind = $this->theme['pdf_body'] ?? 'sans';
        if ($kind === 'mono') {
            return AthenaPdfTheme::mono();
        }
        if ($kind === 'serif') {
            $dir = TrainingCertificatePdfEngine::resolveTcpdfFontsDir();
            if ($dir !== null && is_file($dir . DIRECTORY_SEPARATOR . 'dejavuserif.php') && is_file($dir . DIRECTORY_SEPARATOR . 'dejavuserif.z')) {
                return 'dejavuserif';
            }
        }

        return AthenaPdfTheme::sans();
    }

    /** Feuille de style du corps (couleurs et famille du thème). */
    public function bodyCss(): string
    {
        $f = $this->bodyFamily();
        $t = $this->theme;

        return '<style>p, td, th, li, div, span { font-family: ' . $f . '; color: ' . $t['ink'] . '; }'
            . ' h2, h3, h4 { color: ' . $t['accent'] . '; }'
            . ' th { background-color: ' . $t['head_bg'] . '; color: ' . $t['head_fg'] . '; }</style>';
    }

    public function Header()
    {
        ++self::$lastStamp['headers'];
        $m = $this->athenaMeta;
        $t = $this->theme !== [] ? $this->theme : SseSheetThemes::get(null);
        $sans = AthenaPdfTheme::sans();
        $l = AthenaPdfTheme::MARGIN;
        $r = $this->getPageWidth() - AthenaPdfTheme::MARGIN;
        $w = $this->getPageWidth();
        $h = $this->getPageHeight();

        // Papier teinté, puis filigrane diagonal très pâle sous le texte.
        $this->SetFillColorArray(SseSheetThemes::rgb($t['paper']));
        $this->Rect(0, 0, $w, $h, 'F');
        if ($this->watermark !== '') {
            $this->StartTransform();
            $this->Rotate(40, $w / 2, $h / 2);
            $this->SetFont($sans, 'B', 46);
            $this->SetTextColorArray(SseSheetThemes::rgb($t['mark']));
            $this->setFontSpacing(2);
            $this->SetXY(0, $h / 2 - 12);
            $this->Cell($w, 24, mb_strtoupper($this->watermark, 'UTF-8'), 0, 0, 'C', false, '', 1);
            $this->setFontSpacing(0);
            $this->StopTransform();
        }
        // Marge de registre : filet vertical.
        $this->SetDrawColorArray(SseSheetThemes::rgb($t['rule']));
        $this->SetLineWidth(0.25);
        $this->Line($l - 4, 34, $l - 4, $h - 17);

        // Bloc d'identification.
        $this->SetFillColorArray(SseSheetThemes::rgb($t['head_bg']));
        $this->Rect($l, 8.5, $r - $l, 15, 'F');
        // Logo de la communauté (celui déjà enregistré pour elle), sur une pastille claire pour rester lisible sur tous les thèmes.
        $x = $l + 3;
        if ($m['logo'] !== null) {
            try {
                $this->SetFillColor(255, 255, 255);
                $this->RoundedRect($l + 2, 9.5, 13, 13, 1.2, '1111', 'F');
                $this->Image($m['logo'], $l + 2.6, 10.1, 11.8, 11.8, '', '', '', true, 300, '', false, false, 0, 'CM');
                $x = $l + 18;
            } catch (\Throwable) {
                $x = $l + 3;
            }
        }
        $this->SetTextColorArray(SseSheetThemes::rgb($t['head_fg']));
        $this->SetFont($sans, 'B', 13);
        $this->SetXY($x, 10.2);
        $this->Cell(90, 6.5, 'ATHENA · BUREAU SSE', 0, 0, 'L', false, '', 1);
        if ($m['community'] !== '') {
            $this->SetFont($sans, '', 7);
            $this->SetXY($x, 17.2);
            $this->Cell(95 - ($x - $l - 3), 4, $m['community'], 0, 0, 'L', false, '', 1);
        }
        if ($m['kind'] !== '') {
            $this->SetFont($sans, 'B', 6.8);
            $this->setFontSpacing(0.25);
            $this->SetXY($r - 83, 10.2);
            $this->Cell(80, 4, mb_strtoupper(AthenaPdfTheme::t($m['kind']), 'UTF-8'), 0, 0, 'R', false, '', 1);
            $this->setFontSpacing(0);
        }
        if ($m['reference'] !== '') {
            $this->SetFont(AthenaPdfTheme::mono(), 'B', 9);
            $this->SetXY($r - 83, 15.4);
            $this->Cell(80, 5, $m['reference'], 0, 0, 'R', false, '', 1);
        }

        if ($m['classification'] !== '') {
            $this->SetFillColorArray(SseSheetThemes::rgb($t['band_bg']));
            $this->Rect($l, 25.4, $r - $l, 6.2, 'F');
            $this->SetFont($sans, 'B', 6.8);
            $this->setFontSpacing(0.3);
            $this->SetTextColorArray(SseSheetThemes::rgb($t['band_fg']));
            $this->SetXY($l + 3, 25.4);
            $this->Cell(($r - $l) * 0.6, 6.2, mb_strtoupper($m['classification'], 'UTF-8'), 0, 0, 'L', false, '', 1);
            if ($m['audience'] !== '') {
                $this->SetXY($l + ($r - $l) * 0.6, 25.4);
                $this->Cell(($r - $l) * 0.4 - 3, 6.2, mb_strtoupper($m['audience'], 'UTF-8'), 0, 0, 'R', false, '', 1);
            }
            $this->setFontSpacing(0);
        }

        // Tampon de classification : cadre légèrement incliné dans le bloc d'identification, entre le nom du service et la référence.
        if ($this->stamp !== '') {
            $this->StartTransform();
            $this->Rotate(-4, $l + 118, 16);
            $this->SetDrawColorArray(SseSheetThemes::rgb($t['head_fg']));
            $this->SetLineWidth(0.45);
            $this->SetTextColorArray(SseSheetThemes::rgb($t['head_fg']));
            $this->SetFont($sans, 'B', 6.6);
            $this->setFontSpacing(0.35);
            $this->RoundedRect($l + 96, 11.8, 44, 7.4, 1, '1111', 'D');
            $this->SetXY($l + 96, 11.8);
            $this->Cell(44, 7.4, mb_strtoupper($this->stamp, 'UTF-8'), 0, 0, 'C', false, '', 1);
            $this->setFontSpacing(0);
            $this->StopTransform();
        }
        $this->SetTextColorArray(SseSheetThemes::rgb($t['ink']));
    }

    public function Footer()
    {
        ++self::$lastStamp['footers'];
        $m = $this->athenaMeta;
        $t = $this->theme !== [] ? $this->theme : SseSheetThemes::get(null);
        $l = AthenaPdfTheme::MARGIN;
        $r = $this->getPageWidth() - AthenaPdfTheme::MARGIN;
        $y = $this->getPageHeight() - 13;
        $sans = AthenaPdfTheme::sans();

        $this->SetDrawColorArray(SseSheetThemes::rgb($t['rule']));
        $this->SetLineWidth(0.25);
        $this->Line($l, $y, $r, $y);
        $this->SetTextColorArray(SseSheetThemes::rgb($t['muted']));
        $this->SetFont($sans, '', 6.5);
        $this->SetXY($l, $y + 1.4);
        $left = $m['reference'] !== '' ? $m['reference'] : 'ATHENA SSE';
        if ($this->recipients !== '') {
            $left .= ' · ' . AthenaPdfTheme::t('Destinataires') . ' : ' . $this->recipients;
        }
        $this->Cell(($r - $l) * 0.72, 4, $left, 0, 0, 'L', false, '', 1);
        $this->SetFont(AthenaPdfTheme::mono(), 'B', 7);
        $this->SetXY($l + ($r - $l) * 0.72, $y + 1.4);
        $word = AthenaPdfTheme::isEnglish() ? ['page ', ' of '] : ['page ', ' sur '];
        $this->Cell(($r - $l) * 0.28, 4, $word[0] . $this->getAliasNumPage() . $word[1] . $this->getAliasNbPages(), 0, 0, 'R');

        if ($m['band_bottom'] && $m['classification'] !== '') {
            $by = $this->getPageHeight() - 6.4;
            $this->SetFillColorArray(SseSheetThemes::rgb($t['band_bg']));
            $this->Rect($l, $by, $r - $l, 4.6, 'F');
            $this->SetFont($sans, 'B', 6.2);
            $this->setFontSpacing(0.3);
            $this->SetTextColorArray(SseSheetThemes::rgb($t['band_fg']));
            $this->SetXY($l + 3, $by);
            $this->Cell($r - $l - 6, 4.6, mb_strtoupper($m['classification'], 'UTF-8'), 0, 0, 'C', false, '', 1);
            $this->setFontSpacing(0);
        }
        $this->SetTextColorArray(SseSheetThemes::rgb($t['ink']));
    }
}
