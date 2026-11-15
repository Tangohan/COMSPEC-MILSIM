<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Repositories\SseInterestCaseRepository as Cases;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\SseInterestCaseCatalog as Catalog;
use App\Support\SseInterestMarkup;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Fiche PDF d'un dossier d'intérêt : synthèse, éléments liés visibles du lecteur, derniers événements de l'historique.
 * Le dossier arrive déjà filtré par SseInterestCaseService::detail (classification, accès nominatifs, masquage des éléments).
 */
final class SseInterestCasePdfService
{
    /**
     * @param array<string, mixed> $detail résultat de SseInterestCaseService::detail
     */
    public function export(array $detail, string $communityLabel = ''): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($detail, $communityLabel): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $binary = $this->binary($detail, $communityLabel);
            $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($detail['case']['reference_code'] ?? 'dossier'));

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="dossier-interet-' . $slug . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /** @param array<string, mixed> $detail */
    public function binary(array $detail, string $communityLabel = ''): string
    {
        $case = $detail['case'];
        $ref = (string) $case['reference_code'];
        $pdf = Theme::tcpdf([
            'kind' => 'Dossier d’intérêt',
            'reference' => $ref,
            'tenant_id' => (int) ($case['tenant_id'] ?? 0),
            'community' => $communityLabel,
            'classification' => (string) $case['classification_label'] . ' · besoin d’en connaître',
            'audience' => 'Édité le ' . date('d/m/Y H:i'),
            'note' => 'Hypothèse de travail : aucune identité n’est confirmée sans validation humaine · ne pas dissocier de ses pièces',
            'title' => 'Dossier d’intérêt ' . $ref,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->html($detail));

        return (string) $pdf->Output('', 'S');
    }

    /** @param array<string, mixed> $detail */
    public function html(array $detail): string
    {
        $case = $detail['case'];
        $muted = Theme::MUTED;
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $row = static fn (string $k, mixed $v): string => '<tr><td width="30%" style="color:' . Theme::MUTED . ';font-size:8pt;">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</td><td width="70%" style="font-size:9pt;">'
            . htmlspecialchars(trim((string) $v) !== '' ? (string) $v : '—', ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $date = static fn (mixed $v): string => ($ts = strtotime((string) $v)) !== false && $v ? date('d/m/Y H:i', $ts) : '';

        $html = Theme::section('01', 'Synthèse')
            . '<h2 style="font-size:13pt;">' . $e($case['temporary_designation']) . '</h2>'
            . '<table cellpadding="3">'
            . $row('Référence', $case['reference_code'])
            . $row('État', $case['status_label'])
            . $row('Priorité', $case['priority_code'] . ' · ' . $case['interest_label'])
            . $row('Classification', $case['classification_label'])
            . $row('Type', $case['type_label'] ?? '')
            . $row('Responsable', $case['assignee_label'] ?? '')
            . $row('Échéance', $date($case['due_at'] ?? ''))
            . $row('Étiquettes', implode(', ', $case['tag_list'] ?? []))
            . $row('Alias supposé', $case['suspected_alias'] ?? '')
            . $row('Ouvert le', $date($case['created_at'] ?? ''))
            . $row('Dernière activité', $date($case['updated_at'] ?? ''))
            . '</table>'
            . Theme::spacer(2)
            . '<p style="font-size:8pt;font-weight:bold;color:' . $muted . ';">MOTIF D’OUVERTURE</p>'
            . '<p style="font-size:9pt;">' . nl2br($e(SseInterestMarkup::plain((string) ($case['opening_reason'] ?? '')))) . '</p>';
        if (trim((string) ($case['description'] ?? '')) !== '') {
            $html .= '<p style="font-size:8pt;font-weight:bold;color:' . $muted . ';">DESCRIPTION</p>'
                . '<p style="font-size:9pt;">' . nl2br($e(SseInterestMarkup::plain((string) $case['description']))) . '</p>';
        }

        $links = array_values(array_filter((array) ($detail['links'] ?? []), static fn (array $l): bool => empty($l['masked']) && empty($l['missing'])));
        $html .= Theme::section('02', 'Éléments liés') ;
        if ($links === []) {
            $html .= '<p style="font-size:9pt;color:' . $muted . ';">Aucun élément lié visible.</p>';
        } else {
            $html .= '<table cellpadding="3" border="0">';
            foreach ($links as $l) {
                $html .= '<tr><td width="24%" style="color:' . $muted . ';font-size:8pt;">' . $e($l['type_label']) . '</td><td width="76%" style="font-size:9pt;">' . $e($l['label']) . ($l['hint'] !== '' ? ' <span style="color:' . $muted . ';">(' . $e($l['hint']) . ')</span>' : '') . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= Theme::section('03', 'Historique récent');
        $hist = array_slice((array) ($detail['history'] ?? []), 0, 25);
        if ($hist === []) {
            $html .= '<p style="font-size:9pt;color:' . $muted . ';">Aucun événement consigné.</p>';
        } else {
            $html .= '<table cellpadding="3">';
            foreach ($hist as $h) {
                $change = $h['field_label'] !== '' ? $h['field_label'] . ' : ' . ($h['old'] ?? '—') . ' → ' . ($h['new'] ?? '—') : $h['action_label'] . ($h['new'] ? ' : ' . $h['new'] : '');
                $html .= '<tr><td width="22%" style="color:' . $muted . ';font-size:8pt;">' . $e($date($h['at'])) . '</td><td width="78%" style="font-size:8.5pt;">'
                    . '<b>' . $e($h['actor']) . '</b> — ' . $e($change) . ($h['reason'] ? ' <span style="color:' . $muted . ';">(' . $e($h['reason']) . ')</span>' : '') . '</td></tr>';
            }
            $html .= '</table>';
        }

        return $html;
    }
}
