<?php

declare(strict_types=1);

namespace App\Services\MissionPlanning;

use App\Core\Response;
use App\Support\MissionPlanningLabels;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Paquet de planification (couverture, organisation, effectifs, ordre de mission), A4 portrait au gabarit Athena.
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

        $code = (string) ($board['plan']['mission_code'] ?? '');
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $code !== '' ? $code : 'mission') ?: 'mission';
        $filename = 'ATHENA_' . $safe . '_Planning_Package.pdf';
        $binary = $this->buildPdfBinary($tenantId, $board);

        $disposition = $inline ? 'inline' : 'attachment';
        $response = new Response();
        $response->header('Content-Type', 'application/pdf');
        $response->header('Content-Disposition', $disposition . '; filename="' . $filename . '"');

        return $response->setBody($binary);
    }

    /**
     * @param array<string,mixed> $board MissionPlanningService::board()
     */
    public function buildPdfBinary(int $tenantId, array $board): string
    {
        /** @var array<string,mixed> $plan */
        $plan = $board['plan'];
        $code = (string) ($plan['mission_code'] ?? '');
        $title = (string) (($plan['operation_name'] ?? '') ?: ($plan['title'] ?? 'Mission'));
        $classification = trim((string) ($plan['classification'] ?? ''));

        $pdf = Theme::tcpdf([
            'kind' => 'Ordre d’opération',
            'reference' => $code,
            'tenant_id' => $tenantId,
            'classification' => $classification !== '' ? $classification : 'EXERCISE / MILSIM',
            'audience' => 'Participants inscrits',
            'title' => 'Paquet mission ' . $title,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->coverHtml($board));
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->taskOrgHtml($board));
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->rosterHtml($board));
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->opordHtml($board));

        return (string) $pdf->Output('', 'S');
    }

    /**
     * @param array<string,mixed> $board
     */
    private function coverHtml(array $board): string
    {
        $plan = $board['plan'];
        $counts = $board['counts'];

        return Theme::titleBlock('Paquet de planification · version ' . (string) ($plan['opord_version'] ?? ''), (string) (($plan['operation_name'] ?? '') ?: ($plan['title'] ?? '')))
            . Theme::stats([
                ['label' => 'Force', 'value' => (string) ($plan['task_force_name'] ?? '') ?: '—'],
                ['label' => 'Horodatage', 'value' => (string) ($plan['dtg'] ?? '') ?: '—'],
                ['label' => 'État', 'value' => MissionPlanningLabels::status((string) ($plan['status'] ?? 'draft'))],
                ['label' => 'Effectifs', 'value' => (int) $counts['assigned'] . ' / ' . (int) $counts['auth'], 'sub' => 'postes affectés'],
            ])
            . Theme::keyValues([
                'Identifiant mission' => (string) ($plan['mission_code'] ?? ''),
                'Version de l’ordre' => (string) ($plan['opord_version'] ?? ''),
                'Classification' => (string) ($plan['classification'] ?? ''),
            ], '40%')
            . Theme::section('', 'Contenu du paquet')
            . Theme::table([
                ['label' => 'Partie', 'width' => 12, 'mono' => true],
                ['label' => 'Intitulé', 'width' => 88],
            ], [
                ['1', 'Couverture'],
                ['2', 'Organisation de combat'],
                ['3', 'Tableau des effectifs'],
                ['4', 'Ordre de mission'],
            ])
            . Theme::paragraph('Annexes : A Organisation · B Renseignement · C Opérations · D Feux · E Protection · F Soutien · H Transmissions', 'muted');
    }

    /**
     * @param array<string,mixed> $board
     */
    private function taskOrgHtml(array $board): string
    {
        $plan = $board['plan'];
        $rows = [];
        $this->treeRows($board['tree'] ?? [], 0, $rows);

        return Theme::titleBlock('Annexe A · organisation de combat', (string) ($plan['task_force_name'] ?? ''))
            . Theme::section('01', 'Organisation de combat')
            . Theme::table([
                ['label' => 'Élément / indicatif', 'width' => 55],
                ['label' => 'Personnel', 'width' => 45],
            ], $rows, false, 'Aucun élément.')
            . $this->matrixTable($board['matrix'] ?? []);
    }

    /**
     * Arbre de la force en lignes indentées (élément en gras, postes en dessous).
     *
     * @param list<array<string,mixed>> $tree
     * @param list<list<string>> $rows
     */
    private function treeRows(array $tree, int $depth, array &$rows): void
    {
        $indent = str_repeat('&nbsp;', 10 * $depth);
        foreach ($tree as $node) {
            $el = $node['element'] ?? [];
            $rows[] = [$indent . '<b>' . Theme::e((string) ($el['label'] ?? '')) . '</b>', ''];
            foreach ($node['slots'] ?? [] as $slot) {
                $who = (string) ($slot['assigned_label'] ?? 'Vacant');
                $rows[] = [
                    $indent . str_repeat('&nbsp;', 10) . '<span style="font-family:' . Theme::mono() . ';">' . Theme::e((string) ($slot['callsign'] ?? '')) . '</span>',
                    $who === 'Vacant' || $who === '' ? '<span style="color:' . Theme::MUTED . ';">Vacant</span>' : Theme::e($who),
                ];
            }
            $this->treeRows($node['children'] ?? [], $depth + 1, $rows);
        }
    }

    /**
     * @param array<string,mixed> $board
     */
    private function rosterHtml(array $board): string
    {
        $rows = [];
        $current = null;
        foreach ($board['roster'] as $row) {
            $unit = (string) ($row['element_label'] ?? '');
            if ($unit !== $current) {
                $rows[] = ['group' => $unit !== '' ? $unit : '—'];
                $current = $unit;
            }
            $assigned = (string) ($row['assigned_label'] ?? 'Vacant');
            $rows[] = [
                'cells' => [(string) ($row['callsign'] ?? ''), (string) ($row['function_label'] ?? ''), $assigned, (string) ($row['presence_label'] ?? '')],
                'tones' => [2 => $assigned === 'Vacant' || $assigned === '' ? 'muted' : ''],
            ];
        }

        return Theme::titleBlock('Effectifs', 'Tableau des effectifs')
            . Theme::table([
                ['label' => 'Indicatif', 'width' => 16, 'mono' => true],
                ['label' => 'Fonction', 'width' => 30],
                ['label' => 'Personnel', 'width' => 34, 'bold' => true],
                ['label' => 'État', 'width' => 20],
            ], $rows, true, 'Aucun poste.');
    }

    /**
     * @param array<string,mixed> $board
     */
    private function opordHtml(array $board): string
    {
        $plan = $board['plan'];
        $doc = is_array($board['document'] ?? null) ? $board['document'] : [];
        $p = static function (string $label, string $body): string {
            $text = trim($body) !== '' ? nl2br(Theme::e($body)) : '<i style="color:' . Theme::MUTED . ';">À compléter par le rédacteur.</i>';

            return '<p style="font-size:9pt;"><b>' . Theme::e($label) . '</b></p>'
                . '<p style="font-size:9pt;line-height:1.45;">' . $text . '</p>' . Theme::spacer(1.5);
        };

        return Theme::titleBlock('Ordre d’opération', (string) (($plan['operation_name'] ?? '') ?: ($plan['title'] ?? '')))
            . Theme::callout('<b>MISSION.</b> ' . Theme::e((string) ($board['mission_sentence'] ?? '')), '', 'ok')
            . Theme::section('1', 'Situation')
            . $p('a. Forces ennemies', (string) ($doc['situation_enemy'] ?? ''))
            . $p('b. Forces amies', (string) ($doc['situation_friendly'] ?? ''))
            . $p('c. Renforcements / détachements', (string) ($doc['situation_attachments'] ?? ''))
            . $p('d. Considérations civiles', (string) ($doc['situation_civil'] ?? ''))
            . Theme::section('2', 'Mission')
            . $p('Énoncé de mission', (string) ($board['mission_sentence'] ?? ''))
            . Theme::section('3', 'Exécution')
            . $p('a. Intention du chef', (string) ($doc['execution_intent'] ?? ''))
            . $p('b. Concept des opérations', (string) ($doc['execution_concept'] ?? ''))
            . $p('c. Missions des unités subordonnées', (string) ($doc['execution_tasks'] ?? ''))
            . $p('d. Instructions de coordination', (string) ($doc['execution_coordinating'] ?? ''))
            . Theme::section('4', 'Soutien')
            . $p('a. Logistique', (string) ($doc['sustainment_logistics'] ?? ''))
            . $p('b. Santé', (string) ($doc['sustainment_medical'] ?? ''))
            . $p('c. Ravitaillement', (string) ($doc['sustainment_resupply'] ?? ''))
            . Theme::section('5', 'Commandement et transmissions')
            . $p('a. Commandement', (string) ($doc['command_command'] ?? ''))
            . $p('b. Transmissions', (string) ($doc['command_signal'] ?? ''));
    }

    /**
     * @param list<array<string,mixed>> $matrix
     */
    private function matrixTable(array $matrix): string
    {
        $rows = [];
        foreach ($matrix as $row) {
            $rows[] = [
                (string) ($row['label'] ?? ''),
                (string) (int) ($row['auth'] ?? 0),
                (string) (int) ($row['assigned'] ?? 0),
                (string) (int) ($row['present'] ?? 0),
                (string) (int) ($row['absent'] ?? 0),
                (string) (int) ($row['attached'] ?? 0),
            ];
        }

        return Theme::section('02', 'Matrice des effectifs')
            . Theme::table([
                ['label' => '', 'width' => 30, 'bold' => true],
                ['label' => 'Autorisés', 'width' => 14, 'align' => 'R'],
                ['label' => 'Affectés', 'width' => 14, 'align' => 'R'],
                ['label' => 'Présents', 'width' => 14, 'align' => 'R'],
                ['label' => 'Absents', 'width' => 14, 'align' => 'R'],
                ['label' => 'Rattachés', 'width' => 14, 'align' => 'R'],
            ], $rows, true, 'Aucune donnée.');
    }
}
