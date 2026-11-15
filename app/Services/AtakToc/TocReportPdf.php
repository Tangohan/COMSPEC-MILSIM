<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Support\Pdf\AthenaPdfTheme;
use App\Support\SseUsClassification;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Rapport tactique du poste TOC web en PDF : gabarit sobre inspiré des rapports militaires américains (bandeaux de
 * classification en haut et en bas de chaque page, bloc À / DE / OBJET / DIFFUSION, GDH, paragraphes numérotés), même
 * moteur (TCPDF), mêmes niveaux américains (SseUsClassification) et mêmes polices que les fiches SSE (SseSheetPdf).
 *
 * Règle de fond : aucun nom, logo ni sceau d'organisation, de service ou de gouvernement réels ; le service émetteur est
 * le poste TOC de la communauté. Le rapport ne porte aucune mention de jeu (ni en-tête, ni pied, ni diffusion).
 *
 * Le gabarit ne reçoit que la fiche déjà lue avec les droits de l'opérateur (TocReportService::find) : un passage caviardé
 * arrive sous forme de blocs pleins (U+2588) et sort en RECTANGLE noir de largeur fixe, jamais en glyphe (la police n'a pas
 * ce caractère : il sortirait en « ? »).
 */
final class TocReportPdf
{
    private const BLOCK = "\u{2588}";

    /** Libellés fixes : clé => [français, anglais]. */
    private const L = [
        'to' => ['À', 'TO'], 'from' => ['DE', 'FROM'], 'subj' => ['OBJET', 'SUBJ'], 'dist' => ['DIFFUSION', 'DIST'],
        'dtg' => ['GDH', 'DTG'], 'ctrl' => ['N° de contrôle', 'Control no.'], 'prio' => ['Priorité', 'Precedence'],
        'status' => ['Statut', 'Status'], 'pos' => ['Position', 'Position'], 'unit' => ['Unité', 'Unit'], 'who' => ['Rédacteur', 'Originator'],
        'issuer' => ['Poste TOC', 'TOC post'], 'page' => ['Page', 'Page'], 'of' => ['sur', 'of'], 'extra' => ['Autres informations', 'Other information'],
        'lines' => ['Lignes', 'Lines'], 'remarks' => ['Remarques', 'Remarks'], 'details' => ['Détail', 'Detail'], 'location' => ['Lieu', 'Location'],
        'redacted' => ['Passages caviardés : ils ne sont pas lisibles avec votre habilitation.', 'Redacted passages: not readable with your clearance.'],
        'none' => ['Non communiqué', 'Not provided'], 'gen' => ['Édité le', 'Issued'], 'by' => ['par', 'by'],
        'tosrc' => ['Poste TOC — diffusion de l’opération', 'TOC post — operation distribution'],
    ];

    private string $sans = 'freesans';

    private int $lang = 0;

    /**
     * PDF du rapport, ou null si le moteur PDF ou ses polices manquent.
     *
     * @param array<string, mixed> $r fiche de TocReportService::find
     * @param array{lang?: string, operator?: string, community?: string, generated?: string} $opts
     */
    public function render(array $r, array $opts = []): ?string
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            return null;
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(fn (): string => $this->draw($r, $opts));
    }

    /** @return array{0: string, 1: array{0: int, 1: int, 2: int}} bannière et couleur */
    public static function banner(array $r): array
    {
        $b = (string) ($r['classification'] ?? 'UNCLASSIFIED');
        if ($b === 'UNCLASSIFIED') {
            return [$b, [0, 122, 51]];
        }

        return [$b, SseUsClassification::rgb((string) ($r['required_level'] ?? 'interne'))];
    }

    private function tr(string $fr): string
    {
        return $this->lang === 1 ? \App\Support\HtmlTranslator::translateText($fr) : $fr;
    }

    private function l(string $key): string
    {
        return self::L[$key][$this->lang];
    }

    /** Texte en HTML TCPDF : échappé, retours à la ligne conservés, caviardage en rectangle noir de largeur fixe. */
    public static function html(string $text): string
    {
        $h = nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);

        return (string) preg_replace('/' . self::BLOCK . '+/u', '<span style="background-color:#000000;color:#000000;">' . str_repeat('&nbsp;', 9) . '</span>', $h);
    }

    /** @param array<string, mixed> $r @param array<string, mixed> $opts */
    private function draw(array $r, array $opts): string
    {
        $this->sans = AthenaPdfTheme::sans();
        $this->lang = ($opts['lang'] ?? 'fr') === 'en' ? 1 : 0;
        [$banner, $rgb] = self::banner($r);
        $level = (string) ($r['required_level'] ?? 'interne');
        $ctrl = strtoupper((string) ($r['key'] ?? 'TR-0'));
        $sans = $this->sans;
        $generated = (string) ($opts['generated'] ?? gmdate('Y-m-d H:i') . ' UTC');
        $operator = trim((string) ($opts['operator'] ?? ''));

        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            /** @var callable */
            public $drawHeader;

            /** @var callable */
            public $drawFooter;

            public function __construct(string $o, string $u, string $f, bool $unicode, string $enc, bool $disk)
            {
                parent::__construct($o, $u, $f, $unicode, $enc, $disk);
                $this->tcpdflink = false;
            }

            public function Header(): void
            {
                ($this->drawHeader)($this);
            }

            public function Footer(): void
            {
                ($this->drawFooter)($this);
            }
        };
        $pdf->SetCreator('Athena');
        $pdf->SetAuthor($this->l('issuer'));
        $pdf->SetTitle($this->tr((string) ($r['type_label'] ?? 'Rapport')) . ' ' . $ctrl);
        $pdf->SetSubject($banner);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(14.0, 16.0, 14.0);
        $pdf->SetAutoPageBreak(true, 24.0);
        $pdf->setFontSubsetting(true);
        $pdf->SetCellPadding(0);
        $pdf->setCellHeightRatio(1.25);

        $luma = 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];
        $text = $luma > 150 ? [0, 0, 0] : [255, 255, 255];
        $band = static function (\TCPDF $p, float $y) use ($rgb, $text, $banner, $sans): void {
            $p->SetFillColor(...$rgb);
            $p->Rect(0.0, $y, 210.0, 6.4, 'F');
            $p->SetTextColor(...$text);
            $p->SetFont($sans, 'B', 10.0);
            $p->SetXY(0.0, $y + 0.9);
            $p->Cell(210.0, 4.6, $banner, 0, 0, 'C');
        };
        $pdf->drawHeader = static function (\TCPDF $p) use ($band): void {
            $band($p, 0.0);
        };
        $lblPage = $this->l('page');
        $lblOf = $this->l('of');
        $lblGen = $this->l('gen');
        // Un rapport sans restriction ne porte aucune mention de diffusion (le bandeau UNCLASSIFIED suffit).
        $dissem = $banner === 'UNCLASSIFIED' ? '' : implode(' / ', SseUsClassification::dissemination($level));
        $pdf->drawFooter = function (\TCPDF $p) use ($band, $ctrl, $sans, $generated, $lblPage, $lblOf, $lblGen, $dissem): void {
            $p->SetDrawColor(24, 30, 38);
            $p->SetLineWidth(0.3);
            $p->Line(14.0, 272.4, 196.0, 272.4);
            $p->SetFont($sans, '', 7.2);
            $p->SetTextColor(98, 108, 120);
            $p->SetXY(14.0, 274.2);
            $p->Cell(60.0, 3.6, $ctrl, 0, 0, 'L');
            $p->SetXY(150.0, 274.2);
            $p->Cell(46.0, 3.6, $lblPage . ' ' . $p->getAliasNumPage() . ' ' . $lblOf . ' ' . $p->getAliasNbPages(), 0, 0, 'R');
            $p->SetFont($sans, '', 6.6);
            $p->SetXY(14.0, 279.0);
            $p->Cell(110.0, 3.2, $dissem, 0, 0, 'L');
            $p->SetXY(124.0, 279.0);
            $p->Cell(72.0, 3.2, $lblGen . ' ' . $generated, 0, 0, 'R');
            $band($p, 297.0 - 6.4);
        };

        $pdf->AddPage();
        $pdf->SetY(11.0);
        $this->front($pdf, $r, $ctrl, $operator, $dissem, $banner);
        $this->body($pdf, $r);

        return (string) $pdf->Output('rapport.pdf', 'S');
    }

    /** En-tête du document : service émetteur, numéro de contrôle, titre, bloc À / DE / OBJET / DIFFUSION. @param array<string, mixed> $r */
    private function front(\TCPDF $pdf, array $r, string $ctrl, string $operator, string $dissem, string $banner): void
    {
        $sans = $this->sans;
        $l = 14.0;
        // Emblème générique (cercle et méridiens) : aucun sceau réel.
        $pdf->SetDrawColor(24, 30, 38);
        $pdf->SetLineWidth(0.5);
        $pdf->Circle($l + 6.0, 18.0, 5.6, 0, 360, 'D');
        $pdf->SetLineWidth(0.3);
        $pdf->Ellipse($l + 6.0, 18.0, 2.4, 5.6, 0, 0, 360, 'D');
        $pdf->Line($l + 0.6, 18.0, $l + 11.4, 18.0);
        $pdf->SetFont($sans, 'B', 11.0);
        $pdf->SetTextColor(24, 30, 38);
        // Nom du service seul, centré sur l'emblème.
        $pdf->SetXY($l + 15.0, 15.5);
        $pdf->Cell(110.0, 5.0, mb_strtoupper($this->l('issuer') . ' · ATHENA', 'UTF-8'), 0, 1, 'L');
        $pdf->SetTextColor(98, 108, 120);
        // Numéro de contrôle et GDH.
        $pdf->SetLineWidth(0.3);
        $pdf->Rect(150.0, 10.6, 46.0, 14.0, 'D');
        $pdf->SetFont($sans, '', 6.2);
        $pdf->SetXY(152.0, 11.4);
        $pdf->Cell(42.0, 2.8, mb_strtoupper($this->l('ctrl'), 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($sans, 'B', 10.0);
        $pdf->SetTextColor(24, 30, 38);
        $pdf->SetXY(152.0, 14.4);
        $pdf->Cell(42.0, 4.6, $ctrl, 0, 0, 'L');
        $pdf->SetFont($sans, '', 7.2);
        $pdf->SetXY(152.0, 19.6);
        $pdf->Cell(42.0, 3.6, $this->l('dtg') . ' ' . (string) ($r['dtg'] ?? ''), 0, 0, 'L');
        // Titre.
        $pdf->SetLineWidth(0.6);
        $pdf->Line($l, 28.0, 196.0, 28.0);
        $pdf->SetFont($sans, 'B', 15.0);
        $pdf->SetXY($l, 29.4);
        $pdf->Cell(130.0, 6.4, mb_strtoupper($this->tr((string) ($r['type_label'] ?? 'Rapport')), 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont($sans, 'B', 8.6);
        $pdf->SetXY(150.0, 31.0);
        $pdf->Cell(46.0, 4.0, mb_strtoupper($this->l('prio') . ' : ' . $this->tr((string) ($r['priority_label'] ?? '')), 'UTF-8'), 0, 0, 'R');
        // Bloc À / DE / OBJET / DIFFUSION.
        $y = 40.0;
        $from = trim((string) ($r['who'] ?? '') . ((string) ($r['unit'] ?? '') !== '' ? ' — ' . $r['unit'] : ''));
        $rows = [
            ['to', $this->l('tosrc')],
            ['from', $from],
            ['subj', (string) ($r['title'] ?? '')],
            ['dist', implode(' · ', array_filter([$dissem, trim((string) ($r['classification'] ?? ''))], static fn (string $v): bool => $v !== '')) ?: $banner],
        ];
        $pdf->SetDrawColor(200, 205, 211);
        $pdf->SetLineWidth(0.2);
        foreach ($rows as [$key, $value]) {
            $pdf->Line($l, $y, 196.0, $y);
            $pdf->SetFont(AthenaPdfTheme::mono(), 'B', 8.2);
            $pdf->SetTextColor(24, 30, 38);
            $pdf->SetXY($l, $y + 1.1);
            $pdf->Cell(22.0, 4.0, $this->l($key) . ':', 0, 0, 'L');
            $size = 9.0;
            $value = $this->plain($value);
            $pdf->SetFont($sans, '', $size);
            while ($size > 6.6 && $pdf->GetStringWidth($value) > 158.0) {
                $size -= 0.3;
                $pdf->SetFont($sans, '', $size);
            }
            $pdf->SetXY($l + 23.0, $y + 0.9);
            $pdf->Cell(158.0, 4.4, $value, 0, 0, 'L');
            $y += 5.6;
        }
        $pdf->Line($l, $y, 196.0, $y);
        $pdf->SetY($y + 4.0);
        $pdf->SetX($l);
        unset($operator);
    }

    /** Texte d'une cellule sans bloc de caviardage (une seule ligne) : le bloc devient « [CAVIARDÉ] ». */
    public static function line(string $text, bool $english = false): string
    {
        return (string) preg_replace('/' . self::BLOCK . '+/u', $english ? '[REDACTED]' : '[CAVIARDÉ]', $text);
    }

    private function plain(string $text): string
    {
        return self::line($text, $this->lang === 1);
    }

    /** @param array<string, mixed> $r */
    private function body(\TCPDF $pdf, array $r): void
    {
        $sans = $this->sans;
        $n = 0;
        $section = function (string $title) use ($pdf, $sans, &$n): void {
            ++$n;
            $pdf->Ln(1.5);
            $pdf->SetFont($sans, 'B', 9.4);
            $pdf->SetTextColor(24, 30, 38);
            $pdf->SetX(14.0);
            $pdf->Cell(182.0, 5.0, $n . '. ' . mb_strtoupper($title, 'UTF-8'), 0, 1, 'L');
            $pdf->SetDrawColor(24, 30, 38);
            $pdf->SetLineWidth(0.25);
            $pdf->Line(14.0, $pdf->GetY(), 196.0, $pdf->GetY());
            $pdf->Ln(1.6);
        };
        $table = function (array $rows) use ($pdf, $sans): void {
            $html = '<table cellpadding="3" cellspacing="0" border="0" style="font-size:9pt;">';
            foreach ($rows as [$label, $value]) {
                $v = trim((string) $value) === '' ? '<i style="color:#626c78;">' . self::html($this->l('none')) . '</i>' : self::html((string) $value);
                $html .= '<tr><td width="34%" style="border-bottom:0.3px solid #c8cdd3;color:#626c78;">' . self::html($this->tr((string) $label)) . '</td>'
                    . '<td width="66%" style="border-bottom:0.3px solid #c8cdd3;"><b>' . $v . '</b></td></tr>';
            }
            $pdf->SetFont($sans, '', 9.0);
            $pdf->SetTextColor(24, 30, 38);
            $pdf->writeHTMLCell(182.0, 0, 14.0, null, $html . '</table>', 0, 1, false, true, 'L', true);
        };
        $para = function (string $label, string $text) use ($pdf, $sans): void {
            $pdf->SetFont($sans, 'B', 8.6);
            $pdf->SetTextColor(98, 108, 120);
            $pdf->SetX(14.0);
            $pdf->Cell(182.0, 4.2, mb_strtoupper($this->tr($label), 'UTF-8'), 0, 1, 'L');
            $pdf->SetFont($sans, '', 9.4);
            $pdf->SetTextColor(24, 30, 38);
            $pdf->writeHTMLCell(182.0, 0, 14.0, null, '<div style="font-size:9.4pt;line-height:1.3;">' . self::html($text) . '</div>', 0, 1, false, true, 'L', true);
            $pdf->Ln(1.2);
        };

        // 1. Généralités : qui, quand, où, état.
        $status = $this->tr((string) ($r['status_label'] ?? '')) . (!empty($r['acked_at']) ? ' · ' . $this->l('by') . ' ' . $r['acked_at'] : '');
        $pos = (string) ($r['grid'] ?? '');
        if (is_array($r['pos'] ?? null)) {
            $pos = trim($pos . '  (' . (int) $r['pos'][0] . ' / ' . (int) $r['pos'][1] . ')');
        }
        $general = [
            [$this->l('who'), (string) ($r['who'] ?? '')],
            [$this->l('unit'), (string) ($r['unit'] ?? '')],
            [$this->l('dtg'), (string) ($r['dtg'] ?? '')],
            [$this->l('pos'), $pos],
            [$this->l('status'), $status],
        ];
        if ((string) ($r['location'] ?? '') !== '') {
            $general[] = [$this->l('location'), (string) $r['location']];
        }
        $section($this->lang === 1 ? 'General' : 'Généralités');
        $table($general);

        if (($r['lines'] ?? []) !== []) {
            $section($this->l('lines'));
            $table(array_map(fn (array $ln): array => [$ln['n'] . ' · ' . $this->tr((string) $ln['label']), (string) $ln['value']], $r['lines']));
        }
        if (($r['facts'] ?? []) !== []) {
            $section($this->lang === 1 ? 'Report' : 'Compte rendu');
            $table(array_map(static fn (array $f): array => [$f['label'], (string) $f['value']], $r['facts']));
        }
        foreach ($r['blocks'] ?? [] as $b) {
            $para((string) $b['label'], (string) $b['text']);
        }
        if (($r['extra'] ?? []) !== []) {
            $section($this->l('extra'));
            $table(array_map(static fn (array $f): array => [$f['label'], (string) $f['value']], $r['extra']));
        }
        if ((string) ($r['remarks'] ?? '') !== '') {
            $para($this->l('remarks'), (string) $r['remarks']);
        }
        if (empty($r['facts']) && empty($r['blocks']) && empty($r['lines']) && empty($r['extra']) && (string) ($r['summary'] ?? '') !== '') {
            $section($this->l('details'));
            $para($this->l('details'), (string) $r['summary']);
        }
        if (!empty($r['redacted'])) {
            $pdf->Ln(1.0);
            $pdf->SetFont($sans, 'I', 8.2);
            $pdf->SetTextColor(176, 18, 31);
            $pdf->SetX(14.0);
            $pdf->MultiCell(182.0, 4.0, $this->l('redacted'), 0, 'L');
        }
    }
}
