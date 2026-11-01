<?php

declare(strict_types=1);

namespace App\Services\Aar;

use App\Core\Response;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF d'un compte rendu post-opération (fiche complète, tableau des actions), A4 portrait au gabarit Athena.
 */
final class AarReportPdfService
{

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
            $binary = $this->buildPdfBinary($report);

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
        $e = fn (mixed $v): string => Theme::e($v);
        $status = (string) ($r['status'] ?? 'pending');
        $tone = match (AarReportAdminService::tone($status)) {
            'ok' => 'ok',
            'bad' => 'bad',
            'info' => 'muted',
            default => 'warn',
        };
        $operation = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) ($r['operation_label'] ?? '') : (string) ($r['title'] ?? '');
        $window = $this->window($r['mission_started_at'] ?? null, $r['mission_ended_at'] ?? null);
        $kicker = 'Retour d’expérience' . ($window !== '' ? ' · ' . $window : '');

        $html = Theme::titleBlock($kicker, $operation, [[AarReportAdminService::statusLabel($status), $tone]]);
        $meta = [
            'Opération' => $operation,
            'Titre' => (string) ($r['title'] ?? ''),
            'Auteur' => (string) ($r['author_name'] ?? ''),
            'Déposé le' => $this->date($r['reported_at'] ?? null, 'd/m/Y à H:i'),
            'Fenêtre de mission' => $window,
            'Relecteur' => (string) ($r['validator_name'] ?? ''),
            'Validé le' => $this->date($r['validated_at'] ?? null, 'd/m/Y'),
        ];
        $meta = array_filter($meta, static fn (string $v): bool => trim($v) !== '' && $v !== '—');
        $half = (int) ceil(count($meta) / 2);
        $html .= Theme::twoColumns(
            Theme::keyValues(array_slice($meta, 0, $half, true), '42%'),
            Theme::keyValues(array_slice($meta, $half, null, true), '42%')
        );

        $n = 0;
        $num = static function () use (&$n): string {
            return sprintf('%02d', ++$n);
        };

        $customRows = is_array($r['custom_rows'] ?? null) ? $r['custom_rows'] : [];
        if (!empty($r['is_custom']) && $customRows !== []) {
            $rows = [];
            foreach ($customRows as $row) {
                $rows[] = [
                    $e($row['label'] ?? ''),
                    !empty($row['empty']) ? '<span style="color:' . Theme::MUTED . ';">Non renseigné</span>' : nl2br($e(trim((string) ($row['display'] ?? '')))),
                ];
            }
            $html .= Theme::section($num(), 'Réponses au questionnaire')
                . Theme::table([['label' => 'Question', 'width' => 38], ['label' => 'Réponse', 'width' => 62]], $rows, false);
        }

        $summary = trim((string) ($r['summary_text'] ?? ''));
        $heading = trim((string) ($r['summary_heading'] ?? ''));
        if ($summary !== '' || $heading !== '') {
            $html .= Theme::section($num(), 'Synthèse')
                . Theme::callout(
                    '<b>' . $e($heading !== '' ? $heading : 'Synthèse opérationnelle') . '</b>'
                    . ($summary !== '' ? '<br>' . nl2br($e($summary)) : '')
                );
        }
        foreach ([['strengths', 'Points forts', 'ok'], ['weaknesses', 'À améliorer', 'warn']] as [$key, $title, $bulletTone]) {
            $items = is_array($r[$key] ?? null) ? array_map('strval', $r[$key]) : [];
            if ($items === []) {
                continue;
            }
            $html .= Theme::section($num(), $title) . Theme::bullets(array_values($items), $bulletTone);
        }
        $lessons = trim((string) ($r['lessons_learned'] ?? ''));
        if ($lessons !== '') {
            $ctx = trim((string) ($r['lessons_context'] ?? ''));
            $html .= Theme::section($num(), $ctx !== '' ? 'Enseignements · à retenir pour ' . $ctx : 'Enseignements')
                . Theme::paragraph($lessons);
        }
        $conclusion = trim((string) ($r['conclusion_text'] ?? ''));
        if ($conclusion !== '') {
            $html .= Theme::section($num(), 'Avis du commandement')
                . Theme::callout(nl2br($e($conclusion)), '', 'ok');
        }

        $actions = AarReportAdminService::actions([$r]);
        if ($actions !== []) {
            $rows = [];
            foreach ($actions as $a) {
                $state = $a['state'] === 'closed' ? 'Close' : ($a['overdue'] ? 'En retard' : 'En cours');
                $rows[] = [
                    'cells' => [(string) $a['label'], $a['owner'] !== '' ? (string) $a['owner'] : '—', $this->date($a['due_at'], 'd/m/Y'), $state],
                    'tones' => [3 => $a['state'] === 'closed' ? 'ok' : ($a['overdue'] ? 'bad' : 'warn')],
                ];
            }
            $html .= Theme::section($num(), 'Actions décidées')
                . Theme::table([
                    ['label' => 'Action', 'width' => 50],
                    ['label' => 'Responsable', 'width' => 20],
                    ['label' => 'Échéance', 'width' => 15, 'mono' => true],
                    ['label' => 'État', 'width' => 15, 'bold' => true],
                ], $rows);
        }

        if ($n === 0) {
            $html .= Theme::spacer(3) . '<p style="color:' . Theme::MUTED . ';">Ce compte rendu n’a pas encore de contenu détaillé.</p>';
        }

        $author = trim((string) ($r['author_name'] ?? ''));
        $validator = trim((string) ($r['validator_name'] ?? ''));
        $reported = $this->date($r['reported_at'] ?? null, 'd/m/Y');
        $validated = $this->date($r['validated_at'] ?? null, 'd/m/Y');

        return $html . Theme::signatures([
            ['Rédigé par', $author !== '' ? $author . ($reported !== '—' ? ' · ' . $reported : '') : 'Nom, date, signature'],
            ['Validé par', $validator !== '' ? $validator . ($validated !== '—' ? ' · ' . $validated : '') : 'Nom, date, signature'],
        ]);
    }

    /**
     * Binaire PDF (A4 portrait, gabarit Athena).
     *
     * @param array<string, mixed> $report
     */
    public function buildPdfBinary(array $report): string
    {
        $ref = (string) ($report['reference_code'] ?? 'AAR');
        $pdf = Theme::tcpdf([
            'kind' => 'Compte rendu d’opération',
            'reference' => $ref,
            'tenant_id' => (int) ($report['tenant_id'] ?? 0),
            'classification' => 'Diffusion interne',
            'audience' => 'Retour d’expérience',
            'title' => 'Compte rendu ' . $ref,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->buildHtml($report));

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
