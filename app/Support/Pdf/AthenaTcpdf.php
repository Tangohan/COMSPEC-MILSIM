<?php

declare(strict_types=1);

namespace App\Support\Pdf;

/**
 * Document TCPDF A4 portrait au gabarit Athena (voir {@see AthenaPdfTheme}).
 * À obtenir par {@see AthenaPdfTheme::tcpdf()}, qui charge TCPDF au préalable.
 */
class AthenaTcpdf extends \TCPDF
{
    /** @var array{kind: string, reference: string, community: string, logo: ?string, classification: string, audience: string, generated_by: string, note: string, title: string, generated_at: string} */
    public array $athenaMeta;

    /**
     * Trace du dernier document créé (en-têtes / pieds dessinés), lue par les tests.
     *
     * @var array{headers: int, footers: int}
     */
    public static array $lastStamp = ['headers' => 0, 'footers' => 0];

    /**
     * @param array{kind: string, reference: string, community: string, logo: ?string, classification: string, audience: string, generated_by: string, note: string, title: string, generated_at: string} $meta
     */
    public static function make(array $meta): self
    {
        $pdf = new self('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->athenaMeta = $meta;
        $pdf->tcpdflink = false;
        self::$lastStamp = ['headers' => 0, 'footers' => 0];
        $pdf->SetCreator('Athena');
        $pdf->SetAuthor($meta['community'] !== '' ? $meta['community'] : 'Athena');
        $pdf->SetTitle($meta['title'] !== '' ? $meta['title'] : 'Athena');
        $pdf->SetMargins(AthenaPdfTheme::MARGIN, $pdf->contentTop(), AthenaPdfTheme::MARGIN);
        $pdf->SetHeaderMargin(0);
        $pdf->setFooterMargin(AthenaPdfTheme::BOTTOM);
        $pdf->SetAutoPageBreak(true, AthenaPdfTheme::BOTTOM + ($meta['note'] !== '' ? 3 : 0));
        $pdf->setCellHeightRatio(1.3);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont(AthenaPdfTheme::sans(), '', 9);
        $pdf->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::INK));

        return $pdf;
    }

    public function contentTop(): float
    {
        return $this->athenaMeta['classification'] !== '' ? AthenaPdfTheme::TOP_WITH_BAND : AthenaPdfTheme::TOP_WITHOUT_BAND;
    }

    /** Ajoute une page A4 portrait (l'orientation demandée est ignorée : portrait uniquement). */
    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false)
    {
        parent::AddPage('P', 'A4', $keepmargins, $tocpage);
    }

    public function writeAthenaHtml(string $html): void
    {
        // Paragraphes et blocs sans marge implicite : les espacements viennent de AthenaPdfTheme::spacer().
        $none = [0 => ['h' => 0, 'n' => 0], 1 => ['h' => 0, 'n' => 0]];
        $this->setHtmlVSpace(['p' => $none, 'div' => $none, 'table' => $none, 'ul' => $none, 'ol' => $none]);
        $this->writeHTML(AthenaPdfTheme::css() . $html, true, false, true, false, '');
    }

    public function Header()
    {
        ++self::$lastStamp['headers'];
        $m = $this->athenaMeta;
        $sans = AthenaPdfTheme::sans();
        $l = AthenaPdfTheme::MARGIN;
        $r = $this->getPageWidth() - AthenaPdfTheme::MARGIN;
        $x = $l;

        if ($m['logo'] !== null) {
            try {
                $this->Image($m['logo'], $l, 11, 0, 11, '', '', '', true, 300, '', false, false, 0, 'LM');
                $x = $this->getImageRBX() + 3;
            } catch (\Throwable) {
                $x = $l;
            }
        }

        $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::INK));
        $this->SetFont($sans, 'BI', 15);
        $this->SetXY($x, 11);
        $this->Cell($this->GetStringWidth('ATHENA'), 7, 'ATHENA', 0, 0, 'L', false, '', 0, false, 'T', 'B');
        $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::ACCENT));
        $this->Cell(4, 7, '.', 0, 0, 'L', false, '', 0, false, 'T', 'B');
        if ($m['community'] !== '') {
            $this->SetFont($sans, '', 7);
            $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::MUTED));
            $this->SetXY($x, 18.6);
            $this->Cell(min(110, $r - $x - 60), 3.5, $m['community'], 0, 0, 'L', false, '', 1);
        }

        if ($m['kind'] !== '') {
            $this->SetFont($sans, 'B', 6.5);
            $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::MUTED));
            $this->setFontSpacing(0.25);
            $this->SetXY($r - 80, 12.2);
            $this->Cell(80, 3.5, mb_strtoupper(AthenaPdfTheme::t($m['kind']), 'UTF-8'), 0, 0, 'R', false, '', 1);
            $this->setFontSpacing(0);
        }
        if ($m['reference'] !== '') {
            $this->SetFont(AthenaPdfTheme::mono(), 'B', 8.5);
            $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::INK));
            $this->SetXY($r - 80, 16.6);
            $this->Cell(80, 4, $m['reference'], 0, 0, 'R', false, '', 1);
        }

        $this->SetDrawColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::INK));
        $this->SetLineWidth(0.55);
        $this->Line($l, 24.2, $r, 24.2);

        if ($m['classification'] !== '') {
            $this->SetFillColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::INK));
            $this->Rect($l, 25.4, $r - $l, 6.2, 'F');
            $this->SetFont($sans, 'B', 6.8);
            $this->setFontSpacing(0.3);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($l + 3, 25.4);
            $this->Cell(($r - $l) * 0.6, 6.2, mb_strtoupper($m['classification'], 'UTF-8'), 0, 0, 'L', false, '', 1);
            if ($m['audience'] !== '') {
                $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::BAND_ACCENT));
                $this->SetXY($l + ($r - $l) * 0.6, 25.4);
                $this->Cell(($r - $l) * 0.4 - 3, 6.2, mb_strtoupper($m['audience'], 'UTF-8'), 0, 0, 'R', false, '', 1);
            }
            $this->setFontSpacing(0);
        }
        $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::INK));
    }

    public function Footer()
    {
        ++self::$lastStamp['footers'];
        $m = $this->athenaMeta;
        $l = AthenaPdfTheme::MARGIN;
        $r = $this->getPageWidth() - AthenaPdfTheme::MARGIN;
        $y = $this->getPageHeight() - 13;

        if ($m['note'] !== '') {
            $this->SetFont(AthenaPdfTheme::sans(), 'B', 6.5);
            $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::DANGER));
            $this->SetXY($l, $y - 5);
            $this->Cell($r - $l, 4, $m['note'], 0, 0, 'C', false, '', 1);
        }
        $this->SetDrawColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::LINE));
        $this->SetLineWidth(0.2);
        $this->Line($l, $y, $r, $y);

        $this->SetFont(AthenaPdfTheme::sans(), '', 6.5);
        $this->SetTextColorArray(AthenaPdfTheme::rgb(AthenaPdfTheme::MUTED));
        $this->SetXY($l, $y + 1.6);
        $this->Cell(($r - $l) * 0.62, 4, AthenaPdfTheme::footerLine($m), 0, 0, 'L', false, '', 1);

        $this->SetFont(AthenaPdfTheme::mono(), '', 6.5);
        $pages = $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages();
        $this->SetXY($l + ($r - $l) * 0.62, $y + 1.6);
        $this->Cell(($r - $l) * 0.38, 4, ($m['reference'] !== '' ? $m['reference'] . ' · ' : '') . $pages, 0, 0, 'R');
    }
}
