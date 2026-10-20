<?php

declare(strict_types=1);

namespace App\Services\Aar;

use App\Core\Response;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF d'un compte rendu post-opération (fiche complète, tableau des actions).
 */
final class AarReportPdfService
{
    private const INK = '#0b1220';
    private const MUTED = '#64748b';
    private const LINE = '#e2e8f0';
    private const SOFT = '#f8fafc';
    private const ACCENT = '#1e3a5f';

    /**
     * @param array<string, mixed> $report rapport présenté (AarReportRepository::present)
     */
    public function export(array $report): Response
    {
        $ref = (string) ($report['reference_code'] ?? 'AAR');

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($report, $ref): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $title = 'Compte rendu ' . $ref;
            $binary = $this->buildPdf($this->buildHtml($report), $title, $ref);

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="compte-rendu-' . strtolower($ref) . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * @param array<string, mixed> $r
     */
    public function buildHtml(array $r): string
    {
        $e = fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $nl = fn (mixed $v): string => nl2br($e(trim((string) $v)));
        $status = (string) ($r['status'] ?? 'pending');
        $statusColor = match (AarReportAdminService::tone($status)) {
            'ok' => '#0b6b47',
            'bad' => '#b91c1c',
            'info' => '#1d4ed8',
            default => '#a16207',
        };
        $operation = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? '');

        $meta = [
            'Opération' => $operation,
            'Titre' => (string) ($r['title'] ?? ''),
            'Auteur' => (string) ($r['author_name'] ?? ''),
            'Déposé le' => $this->date($r['reported_at'] ?? null, 'd/m/Y à H:i'),
            'Fenêtre de mission' => $this->window($r['mission_started_at'] ?? null, $r['mission_ended_at'] ?? null),
            'Relecteur' => (string) ($r['validator_name'] ?? ''),
            'Validé le' => $this->date($r['validated_at'] ?? null, 'd/m/Y'),
        ];
        $metaRows = '';
        foreach ($meta as $k => $v) {
            if (trim($v) === '' || $v === '—') {
                continue;
            }
            $metaRows .= '<tr><td width="32%" style="color:' . self::MUTED . ';">' . $e($k) . '</td><td width="68%">' . $e($v) . '</td></tr>';
        }

        $html = '<style>
            h1 { font-size: 16pt; color: ' . self::INK . '; }
            h2 { font-size: 10.5pt; color: ' . self::ACCENT . '; }
            .k { font-size: 7.5pt; color: ' . self::MUTED . '; letter-spacing: 1px; }
            .p { font-size: 9pt; color: ' . self::INK . '; }
            td { font-size: 8.5pt; }
        </style>';
        $html .= '<p class="k">' . $e($r['reference_code'] ?? '') . ' · COMPTE RENDU POST-OPÉRATION</p>';
        $html .= '<h1>' . $e($operation) . '</h1>';
        $html .= '<p><span style="color:#ffffff;background-color:' . $statusColor . ';font-size:8pt;font-weight:bold;">&nbsp;' . $e(mb_strtoupper(AarReportAdminService::statusLabel($status), 'UTF-8')) . '&nbsp;</span></p>';
        $html .= '<table cellpadding="4" cellspacing="0" style="border:0.3px solid ' . self::LINE . ';background-color:' . self::SOFT . ';">' . $metaRows . '</table>';

        $section = function (string $num, string $title, string $body): string {
            return '<br><p class="k">' . htmlspecialchars($num, ENT_QUOTES, 'UTF-8') . '</p><h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>' . $body;
        };

        $customRows = is_array($r['custom_rows'] ?? null) ? $r['custom_rows'] : [];
        if (!empty($r['is_custom']) && $customRows !== []) {
            $rows = '';
            foreach ($customRows as $row) {
                $rows .= '<tr><td width="38%" style="border-bottom:0.3px solid ' . self::LINE . ';color:' . self::MUTED . ';">' . $e($row['label'] ?? '') . '</td>'
                    . '<td width="62%" style="border-bottom:0.3px solid ' . self::LINE . ';">' . (!empty($row['empty']) ? '<span style="color:' . self::MUTED . ';">Non renseigné</span>' : $nl($row['display'] ?? '')) . '</td></tr>';
            }
            $html .= $section('DEBRIEFING', 'Réponses au questionnaire', '<table cellpadding="4">' . $rows . '</table>');
        }

        $n = 0;
        $summary = trim((string) ($r['summary_text'] ?? ''));
        $heading = trim((string) ($r['summary_heading'] ?? ''));
        if ($summary !== '' || $heading !== '') {
            $html .= $section(++$n . ' · SYNTHÈSE', $heading !== '' ? $heading : 'Synthèse opérationnelle', '<p class="p">' . $nl($summary) . '</p>');
        }
        foreach ([['strengths', 'POINTS FORTS', 'Ce qui a fonctionné', '#0b6b47'], ['weaknesses', 'POINTS FAIBLES', 'Ce qui doit progresser', '#a16207']] as [$key, $kicker, $title, $color]) {
            $items = is_array($r[$key] ?? null) ? $r[$key] : [];
            if ($items === []) {
                continue;
            }
            $list = '';
            foreach ($items as $it) {
                $list .= '<tr><td width="3%" style="color:' . $color . ';">■</td><td width="97%" class="p">' . $e($it) . '</td></tr>';
            }
            $html .= $section(++$n . ' · ' . $kicker, $title, '<table cellpadding="3">' . $list . '</table>');
        }
        $lessons = trim((string) ($r['lessons_learned'] ?? ''));
        if ($lessons !== '') {
            $ctx = trim((string) ($r['lessons_context'] ?? ''));
            $html .= $section(++$n . ' · ENSEIGNEMENTS', $ctx !== '' ? 'À retenir pour ' . $ctx : 'À retenir', '<p class="p">' . $nl($lessons) . '</p>');
        }
        $conclusion = trim((string) ($r['conclusion_text'] ?? ''));
        if ($conclusion !== '') {
            $html .= $section(++$n . ' · CONCLUSION', 'Avis du commandement', '<p class="p">' . $nl($conclusion) . '</p>');
        }

        $actions = AarReportAdminService::actions([$r]);
        if ($actions !== []) {
            $th = 'style="background-color:' . self::ACCENT . ';color:#ffffff;font-weight:bold;font-size:7.5pt;"';
            $rows = '<tr><td width="50%" ' . $th . '>ACTION</td><td width="20%" ' . $th . '>RESPONSABLE</td><td width="15%" ' . $th . '>ÉCHÉANCE</td><td width="15%" ' . $th . '>ÉTAT</td></tr>';
            foreach ($actions as $a) {
                $state = $a['state'] === 'closed' ? 'Close' : ($a['overdue'] ? 'En retard' : 'En cours');
                $color = $a['state'] === 'closed' ? '#0b6b47' : ($a['overdue'] ? '#b91c1c' : '#a16207');
                $b = 'border-bottom:0.3px solid ' . self::LINE . ';';
                $rows .= '<tr><td style="' . $b . '">' . $e($a['label']) . '</td><td style="' . $b . '">' . $e($a['owner'] !== '' ? $a['owner'] : '—') . '</td>'
                    . '<td style="' . $b . '">' . $e($this->date($a['due_at'], 'd/m/Y')) . '</td><td style="' . $b . 'color:' . $color . ';font-weight:bold;">' . $e($state) . '</td></tr>';
            }
            $html .= $section(++$n . ' · ACTIONS', 'Actions décidées', '<table cellpadding="4">' . $rows . '</table>');
        }

        if ($n === 0 && !(!empty($r['is_custom']) && $customRows !== [])) {
            $html .= '<br><p class="p" style="color:' . self::MUTED . ';">Ce compte rendu n’a pas encore de contenu détaillé.</p>';
        }

        return $html;
    }

    private function buildPdf(string $html, string $title, string $ref): string
    {
        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $aarRef = '';

            public function Header(): void
            {
                $this->SetFillColor(30, 58, 95);
                $this->Rect(0, 0, $this->w, 2.2, 'F');
                $this->SetY(5);
                $this->SetX($this->lMargin);
                $this->SetFont('helvetica', 'B', 7);
                $this->SetTextColor(30, 58, 95);
                $this->Cell(0, 4, 'ATHENA · RETOUR D’EXPÉRIENCE', 0, 0, 'L');
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
                $this->SetFont('helvetica', '', 7);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(($r - $l) / 2, 5, $this->aarRef, 0, 0, 'L');
                $this->Cell(($r - $l) / 2, 5, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->aarRef = 'Athena · ' . $ref . ' · édité le ' . date('d/m/Y à H:i');
        $pdf->SetCreator('Athena COMSPEC');
        $pdf->SetTitle($title);
        $pdf->SetMargins(16, 14, 16);
        $pdf->SetHeaderMargin(0);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setFooterMargin(12);
        $pdf->setCellHeightRatio(1.35);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    private function date(mixed $raw, string $format): string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return '—';
        }
        $t = strtotime($raw);

        return $t === false ? $raw : date($format, $t);
    }

    private function window(mixed $from, mixed $to): string
    {
        $a = $this->date($from, 'd/m/Y H:i');
        $b = $this->date($to, 'd/m/Y H:i');
        if ($a === '—' && $b === '—') {
            return '';
        }

        if ($a === '—') {
            return 'fin le ' . $b;
        }
        if ($b === '—') {
            return 'depuis le ' . $a;
        }

        return $a . ' → ' . $b;
    }
}
