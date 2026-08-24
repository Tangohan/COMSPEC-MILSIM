<?php

declare(strict_types=1);

namespace App\Services\MissionPlanning;

use App\Core\Response;
use App\Support\MissionPlanningLabels;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Paquet de planification (couverture, organisation, effectifs, ordre de mission).
 */
final class MissionPlanningPdfService
{
    public function __construct(private MissionPlanningService $planning)
    {
    }

    public function export(int $tenantId, int $planId, bool $inline = false): Response
    {
        $board = $this->planning->board($tenantId, $planId);
        if ($board === null) {
            return (new Response())->setStatusCode(404)->setBody('<p>Plan introuvable.</p>');
        }
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
        }

        /** @var array<string,mixed> $plan */
        $plan = $board['plan'];
        $code = (string) ($plan['mission_code'] ?? '');
        $title = (string) ($plan['operation_name'] ?: $plan['title'] ?? 'Mission');

        $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
            public string $footerLeft = 'ATHENA';
            public string $footerCenter = 'EXERCISE / MILSIM';

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('helvetica', '', 7);
                $this->SetTextColor(80, 90, 95);
                $this->Cell(60, 6, $this->footerLeft, 0, 0, 'L');
                $this->Cell(70, 6, $this->footerCenter, 0, 0, 'C');
                $this->Cell(0, 6, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
            }
        };
        $pdf->footerLeft = $code !== '' ? $code : 'ATHENA';
        $pdf->footerCenter = (string) ($plan['classification'] ?? 'EXERCISE / MILSIM');
        $pdf->SetCreator('Athena');
        $pdf->SetAuthor('Athena — Planification');
        $pdf->SetTitle('Paquet mission ' . $title);
        $pdf->SetMargins(12, 14, 12);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterMargin(10);

        $pdf->AddPage();
        $pdf->writeHTML($this->coverHtml($board), true, false, true, false, '');
        $pdf->AddPage();
        $pdf->writeHTML($this->taskOrgHtml($board), true, false, true, false, '');
        $pdf->AddPage();
        $pdf->writeHTML($this->rosterHtml($board), true, false, true, false, '');
        $pdf->AddPage();
        $pdf->writeHTML($this->opordHtml($board), true, false, true, false, '');

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $code !== '' ? $code : 'mission') ?: 'mission';
        $filename = 'ATHENA_' . $safe . '_Planning_Package.pdf';
        $binary = $pdf->Output($filename, 'S');

        $disposition = $inline ? 'inline' : 'attachment';
        $response = new Response();
        $response->header('Content-Type', 'application/pdf');
        $response->header('Content-Disposition', $disposition . '; filename="' . $filename . '"');

        return $response->setBody(is_string($binary) ? $binary : '');
    }

    /**
     * @param array<string,mixed> $board
     */
    private function coverHtml(array $board): string
    {
        $plan = $board['plan'];
        $counts = $board['counts'];
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        return '<div style="border:2px solid #1e293b;padding:18px;">'
            . '<div style="font-size:9px;letter-spacing:2px;color:#64748b;">MISSION PLANNING PACKAGE</div>'
            . '<div style="font-size:22px;font-weight:bold;margin:8px 0 4px;">' . $h($plan['operation_name'] ?: $plan['title']) . '</div>'
            . '<div style="font-size:11px;color:#334155;">Identifiant mission : ' . $h($plan['mission_code']) . ' · Version ordre : ' . $h($plan['opord_version']) . '</div>'
            . '<div style="margin-top:14px;font-size:10px;line-height:1.6;">'
            . 'Force : ' . $h($plan['task_force_name']) . '<br>'
            . 'Horodatage : ' . $h($plan['dtg']) . '<br>'
            . 'Classification : ' . $h($plan['classification']) . '<br>'
            . 'État : ' . $h(MissionPlanningLabels::status((string) ($plan['status'] ?? 'draft'))) . '<br>'
            . 'Effectifs : ' . (int) $counts['assigned'] . ' / ' . (int) $counts['auth'] . ' postes affectés'
            . '</div>'
            . '<div style="margin-top:18px;font-size:9px;color:#64748b;line-height:1.5;">'
            . '1. Couverture · 2. Organisation de combat · 3. Tableau des effectifs · 4. Ordre de mission<br>'
            . 'Annexes : A Organisation · B Renseignement · C Opérations · D Feux · E Protection · F Soutien · H Transmissions'
            . '</div></div>';
    }

    /**
     * @param array<string,mixed> $board
     */
    private function taskOrgHtml(array $board): string
    {
        $plan = $board['plan'];
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-size:9px;letter-spacing:2px;color:#64748b;">ANNEX A — TASK ORGANIZATION</div>'
            . '<h2 style="font-size:14px;margin:4px 0 10px;">' . $h($plan['task_force_name']) . '</h2>';
        $html .= $this->treeAsPre($board['tree'] ?? []);
        $html .= $this->matrixTable($board['matrix'] ?? []);

        return $html;
    }

    /**
     * @param array<string,mixed> $board
     */
    private function rosterHtml(array $board): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-size:9px;letter-spacing:2px;color:#64748b;">PERSONNEL ROSTER</div>'
            . '<h2 style="font-size:14px;margin:4px 0 10px;">Tableau des effectifs</h2>'
            . '<table border="1" cellpadding="3" cellspacing="0" width="100%" style="font-size:8px;border-color:#cbd5e1;">'
            . '<tr style="background-color:#f1f5f9;font-weight:bold;">'
            . '<th>Unité</th><th>Indicatif</th><th>Fonction</th><th>Personnel</th><th>État</th></tr>';
        foreach ($board['roster'] as $row) {
            $html .= '<tr>'
                . '<td>' . $h($row['element_label'] ?? '') . '</td>'
                . '<td>' . $h($row['callsign'] ?? '') . '</td>'
                . '<td>' . $h($row['function_label'] ?? '') . '</td>'
                . '<td>' . $h($row['assigned_label'] ?? 'Vacant') . '</td>'
                . '<td>' . $h($row['presence_label'] ?? '') . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param array<string,mixed> $board
     */
    private function opordHtml(array $board): string
    {
        $plan = $board['plan'];
        $doc = is_array($board['document'] ?? null) ? $board['document'] : [];
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $p = static function (string $label, string $body) use ($h): string {
            $text = trim($body) !== '' ? nl2br($h($body)) : '<em>À compléter par le rédacteur.</em>';

            return '<p style="font-size:10px;margin:6px 0 2px;"><strong>' . $h($label) . '</strong></p>'
                . '<p style="font-size:9px;margin:0 0 8px;line-height:1.45;">' . $text . '</p>';
        };

        $html = '<div style="font-size:9px;letter-spacing:2px;color:#64748b;">OPERATION ORDER</div>'
            . '<h2 style="font-size:14px;margin:4px 0 8px;">' . $h($plan['operation_name'] ?: $plan['title']) . '</h2>'
            . '<p style="font-size:10px;"><strong>MISSION.</strong> ' . $h($board['mission_sentence'] ?? '') . '</p>'
            . $p('1. SITUATION — a. Enemy Forces', (string) ($doc['situation_enemy'] ?? ''))
            . $p('b. Friendly Forces', (string) ($doc['situation_friendly'] ?? ''))
            . $p('c. Attachments / Detachments', (string) ($doc['situation_attachments'] ?? ''))
            . $p('d. Civil Considerations', (string) ($doc['situation_civil'] ?? ''))
            . $p('2. MISSION', (string) ($board['mission_sentence'] ?? ''))
            . $p('3. EXECUTION — a. Commander\'s Intent', (string) ($doc['execution_intent'] ?? ''))
            . $p('b. Concept of Operations', (string) ($doc['execution_concept'] ?? ''))
            . $p('c. Tasks to Subordinate Units', (string) ($doc['execution_tasks'] ?? ''))
            . $p('d. Coordinating Instructions', (string) ($doc['execution_coordinating'] ?? ''))
            . $p('4. SUSTAINMENT — a. Logistics', (string) ($doc['sustainment_logistics'] ?? ''))
            . $p('b. Medical', (string) ($doc['sustainment_medical'] ?? ''))
            . $p('c. Resupply', (string) ($doc['sustainment_resupply'] ?? ''))
            . $p('5. COMMAND AND SIGNAL — a. Command', (string) ($doc['command_command'] ?? ''))
            . $p('b. Signal', (string) ($doc['command_signal'] ?? ''));

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $tree
     */
    private function treeAsPre(array $tree, string $prefix = ''): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $lines = '';
        $n = count($tree);
        foreach ($tree as $i => $node) {
            $el = $node['element'] ?? [];
            $label = (string) ($el['label'] ?? '');
            $isLast = $i === $n - 1;
            $branch = $prefix . ($isLast ? '└── ' : '├── ');
            $lines .= $h($branch . $label) . "\n";
            $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
            foreach ($node['slots'] ?? [] as $slot) {
                $cs = (string) ($slot['callsign'] ?? '');
                $who = (string) ($slot['assigned_label'] ?? 'Vacant');
                $lines .= $h($childPrefix . '├── ' . $cs . '  ' . $who) . "\n";
            }
            $lines .= $this->treeAsPre($node['children'] ?? [], $childPrefix);
        }
        if ($prefix === '') {
            return '<pre style="font-size:9px;line-height:1.35;font-family:courier;">' . $lines . '</pre>';
        }

        return $lines;
    }

    /**
     * @param list<array<string,mixed>> $matrix
     */
    private function matrixTable(array $matrix): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<h3 style="font-size:11px;margin:12px 0 6px;">Personnel Strength Matrix</h3>'
            . '<table border="1" cellpadding="3" cellspacing="0" width="100%" style="font-size:8px;border-color:#cbd5e1;">'
            . '<tr style="background-color:#f1f5f9;font-weight:bold;"><th></th><th>AUTH</th><th>ASSIGNED</th><th>PRESENT</th><th>ABSENT</th><th>ATTACHED</th></tr>';
        foreach ($matrix as $row) {
            $html .= '<tr>'
                . '<td>' . $h($row['label'] ?? '') . '</td>'
                . '<td>' . (int) ($row['auth'] ?? 0) . '</td>'
                . '<td>' . (int) ($row['assigned'] ?? 0) . '</td>'
                . '<td>' . (int) ($row['present'] ?? 0) . '</td>'
                . '<td>' . (int) ($row['absent'] ?? 0) . '</td>'
                . '<td>' . (int) ($row['attached'] ?? 0) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
