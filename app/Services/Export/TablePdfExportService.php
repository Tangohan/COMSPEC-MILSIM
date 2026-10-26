<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Core\Response;
use App\Support\HtmlTranslator;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF générique d'un tableau du back-office : en-tête communauté, titre, date d'édition, filtres
 * appliqués, puis le tableau (en-tête répété à chaque page). Réutilisable par tout écran qui sait
 * produire ses colonnes et ses lignes en texte.
 *
 * Document attendu :
 *  - title       : titre du tableau (« Effectifs · Médical »)
 *  - community   : nom de la communauté
 *  - filters     : liste de [libellé, valeur] (vide = « aucun filtre »)
 *  - columns     : liste de ['label' => …, 'weight' => largeur relative, 'align' => 'L'|'R'|'C',
 *                  'bold' => true (cellules en gras), 'alert' => true (cellules en rouge),
 *                  'muted' => valeurs affichées en gris (« Aucune connue »), 'translate' => true (valeurs repères)]
 *  - rows        : liste de lignes, chaque ligne = liste de cellules texte (même ordre que columns)
 *  - note        : mention de bas de page facultative (ex. confidentialité)
 *  - filename    : nom du fichier téléchargé, sans extension
 *  - orientation : 'L' (paysage, défaut) ou 'P'
 *
 * Visiteur en anglais : titres, libellés, filtres et valeurs repères (« Aucune connue », colonnes 'translate')
 * passent par le dictionnaire du site ; les données saisies (noms, allergies…) restent telles quelles.
 */
final class TablePdfExportService
{
    public const MAX_ROWS = 2000;

    private const INK = '#0b1220';
    private const MUTED = '#64748b';
    private const LINE = '#e2e8f0';
    private const SOFT = '#f8fafc';
    private const ACCENT = '#1e3a5f';

    /**
     * @param array<string, mixed> $doc
     */
    public function export(array $doc): Response
    {
        $filename = self::safeFilename((string) ($doc['filename'] ?? 'export'));

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($doc, $filename): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $binary = $this->buildPdf($doc);

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * Corps HTML (TCPDF) : bloc titre, filtres, tableau.
     *
     * @param array<string, mixed> $doc
     */
    public function buildHtml(array $doc): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = self::translator();
        $columns = self::columns($doc);
        $rows = is_array($doc['rows'] ?? null) ? array_slice(array_values($doc['rows']), 0, self::MAX_ROWS) : [];
        $filters = is_array($doc['filters'] ?? null) ? $doc['filters'] : [];
        $total = (int) ($doc['total'] ?? count($rows));

        $html = '<style>
            h1 { font-size: 15pt; color: ' . self::INK . '; }
            .k { font-size: 7.5pt; color: ' . self::MUTED . '; letter-spacing: 1px; }
            .f { font-size: 8pt; color: ' . self::INK . '; }
            .m { font-size: 8pt; color: ' . self::MUTED . '; }
            td { font-size: 7.5pt; color: ' . self::INK . '; }
        </style>';
        $html .= '<p class="k">' . $e(mb_strtoupper(trim((string) ($doc['community'] ?? 'Communauté')), 'UTF-8')) . ' · ' . $e(mb_strtoupper($t('Édité le') . ' ' . date('d/m/Y H:i'), 'UTF-8')) . '</p>';
        $html .= '<h1>' . $e($t((string) ($doc['title'] ?? 'Tableau'))) . '</h1>';

        $filterBits = [];
        foreach ($filters as $f) {
            if (!is_array($f) || count($f) < 2 || trim((string) $f[1]) === '') {
                continue;
            }
            $filterBits[] = '<b>' . $e($t((string) $f[0])) . '</b> : ' . $e($t((string) $f[1]));
        }
        $count = $t($total . ($total > 1 ? ' lignes' : ' ligne'));
        if (count($rows) < $total) {
            $count .= ' (' . $t(count($rows) . ' imprimées') . ')';
        }
        $html .= '<table cellpadding="4" cellspacing="0" style="border:0.3px solid ' . self::LINE . ';background-color:' . self::SOFT . ';"><tr>'
            . '<td width="80%" class="f">' . ($filterBits !== [] ? $e($t('Filtres')) . ' : ' . implode(' · ', $filterBits) : '<span class="m">' . $e($t('Aucun filtre : tout le tableau.')) . '</span>') . '</td>'
            . '<td width="20%" class="f" align="right"><b>' . $e($count) . '</b></td></tr></table><br>';

        if ($columns === []) {
            return $html;
        }
        if ($rows === []) {
            return $html . '<p class="m">' . $e($t('Aucune ligne ne correspond aux filtres.')) . '</p>';
        }

        $weights = array_sum(array_map(static fn (array $c): float => $c['weight'], $columns));
        $widths = array_map(static fn (array $c): string => number_format($c['weight'] * 100 / $weights, 2, '.', '') . '%', $columns);
        $th = 'background-color:' . self::ACCENT . ';color:#ffffff;font-weight:bold;font-size:7pt;';
        $html .= '<table cellpadding="3" cellspacing="0"><thead><tr>';
        foreach ($columns as $i => $c) {
            $html .= '<th width="' . $widths[$i] . '" align="' . self::align($c['align']) . '" style="' . $th . '">' . $e(mb_strtoupper($t($c['label']), 'UTF-8')) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $n => $row) {
            $cells = is_array($row) ? array_values($row) : [];
            $bg = $n % 2 === 1 ? 'background-color:' . self::SOFT . ';' : '';
            $html .= '<tr nobr="true">';
            foreach ($columns as $i => $c) {
                $raw = trim((string) ($cells[$i] ?? ''));
                if ($raw === '' || in_array($raw, $c['muted'], true)) {
                    $cell = '<span style="color:' . self::MUTED . ';">' . ($raw !== '' ? $e($t($raw)) : '—') . '</span>';
                } else {
                    $cell = nl2br($e($c['translate'] ? $t($raw) : $raw));
                    if ($c['bold']) {
                        $cell = '<b>' . $cell . '</b>';
                    }
                    if ($c['alert']) {
                        $cell = '<span style="color:#b91c1c;">' . $cell . '</span>';
                    }
                }
                $html .= '<td width="' . $widths[$i] . '" align="' . self::align($c['align']) . '" style="border-bottom:0.3px solid ' . self::LINE . ';' . $bg . '">' . $cell . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $doc
     * @return list<array{label: string, weight: float, align: string, bold: bool, alert: bool, muted: list<string>, translate: bool}>
     */
    private static function columns(array $doc): array
    {
        $out = [];
        foreach (is_array($doc['columns'] ?? null) ? $doc['columns'] : [] as $c) {
            if (is_string($c)) {
                $c = ['label' => $c];
            }
            if (!is_array($c)) {
                continue;
            }
            $out[] = [
                'label' => trim((string) ($c['label'] ?? '')),
                'weight' => max(0.5, (float) ($c['weight'] ?? 1)),
                'align' => (string) ($c['align'] ?? 'L'),
                'bold' => !empty($c['bold']),
                'alert' => !empty($c['alert']),
                'translate' => !empty($c['translate']),
                'muted' => array_values(array_map('strval', is_array($c['muted'] ?? null) ? $c['muted'] : [])),
            ];
        }

        return $out;
    }

    /**
     * Traduction d'un libellé pour un visiteur anglophone (« Effectifs · Médical » : chaque morceau séparément).
     *
     * @return \Closure(string): string
     */
    private static function translator(): \Closure
    {
        if (!function_exists('site_lang') || site_lang() === 'fr') {
            return static fn (string $s): string => $s;
        }

        return static fn (string $s): string => implode(' · ', array_map(
            static fn (string $part): string => HtmlTranslator::translateText($part),
            explode(' · ', $s)
        ));
    }

    private static function align(string $a): string
    {
        return match (strtoupper($a)) {
            'R' => 'right',
            'C' => 'center',
            default => 'left',
        };
    }

    public static function safeFilename(string $name): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii), '-'));

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'export';
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function buildPdf(array $doc): string
    {
        $orientation = strtoupper((string) ($doc['orientation'] ?? 'L')) === 'P' ? 'P' : 'L';
        $pdf = new class ($orientation, 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $headLine = '';
            public string $footLine = '';
            public string $noteLine = '';

            public function Header(): void
            {
                $this->SetFillColor(30, 58, 95);
                $this->Rect(0, 0, $this->w, 2.2, 'F');
                $this->SetY(5);
                $this->SetX($this->lMargin);
                $this->SetFont('dejavusans', 'B', 7);
                $this->SetTextColor(30, 58, 95);
                $this->Cell(0, 4, $this->headLine, 0, 0, 'L');
            }

            public function Footer(): void
            {
                $l = $this->lMargin;
                $r = $this->w - $this->rMargin;
                $this->SetY(-12);
                $this->SetDrawColor(226, 232, 240);
                $this->SetLineWidth(0.3);
                $this->Line($l, $this->GetY(), $r, $this->GetY());
                $this->SetY(-10);
                $this->SetX($l);
                $this->SetFont('dejavusans', '', 7);
                $this->SetTextColor(100, 116, 139);
                $w = ($r - $l) / 3;
                $this->Cell($w, 5, $this->footLine, 0, 0, 'L');
                $this->SetTextColor(185, 28, 28);
                $this->Cell($w, 5, $this->noteLine, 0, 0, 'C');
                $this->SetTextColor(100, 116, 139);
                $this->Cell($w, 5, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $t = self::translator();
        $title = $t((string) ($doc['title'] ?? 'Tableau'));
        $community = trim((string) ($doc['community'] ?? 'Communauté'));
        $pdf->headLine = mb_strtoupper($community . ' · ' . $title, 'UTF-8');
        $pdf->footLine = 'COMSPEC · ' . $t('Édité le') . ' ' . date('d/m/Y H:i');
        $note = trim((string) ($doc['note'] ?? ''));
        $pdf->noteLine = $note !== '' ? $t($note) : '';
        $pdf->SetCreator('COMSPEC');
        $pdf->SetAuthor($community);
        $pdf->SetTitle($community . ' · ' . $title);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetHeaderMargin(0);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->setFooterMargin(12);
        $pdf->setCellHeightRatio(1.3);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->AddPage();
        $pdf->writeHTML($this->buildHtml($doc), true, false, true, false, '');

        return $pdf->Output('', 'S');
    }
}
