<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Support\TrainingCertificatePdfEngine;
use App\Support\TrainingFormationCustomPageRenderer;

/**
 * Export PDF téléchargeable d'une Documentation HTML (manuels imprimés, fiches de procédure
 * hors-ligne). Réutilise le moteur PDF déjà en place pour les attestations
 * (TrainingCertificatePdfEngine) mais avec un gabarit dédié — le rendu navigateur/iframe de
 * TrainingFormationCustomPageRenderer::render() (CSS externe, JS de progression) n'est pas
 * directement exploitable par Dompdf/TCPDF, donc on ne réutilise que decodeSections().
 */
final class TrainingFormationCustomPageExportPdfService
{
    private ?string $lastFailureReason = null;

    public function getLastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    /** @param array<string, mixed> $row ligne training_formation_custom_pages */
    public function generateBinary(array $row): ?string
    {
        $this->lastFailureReason = null;

        if (!TrainingCertificatePdfEngine::isAvailable()) {
            $this->lastFailureReason = TrainingCertificatePdfEngine::staffUnavailabilityHint()
                ?? 'Aucun moteur PDF utilisable sur ce serveur.';

            return null;
        }

        $html = $this->buildPrintHtml($row);

        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $binary = $dompdf->output();
                if ($binary !== false && $binary !== '') {
                    return $binary;
                }
                $this->lastFailureReason = 'Le moteur PDF n’a produit aucun document.';
            } catch (\Throwable $e) {
                error_log('[training_doc_pdf] Dompdf : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                $this->lastFailureReason = 'Échec du moteur PDF principal ; tentative de secours.';
            }
        }

        if (TrainingCertificatePdfEngine::ensureTcpdfLoaded() && TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            $binary = $this->renderWithTcpdf($row);
            if ($binary !== null && $binary !== '') {
                $this->lastFailureReason = null;

                return $binary;
            }
        }

        if ($this->lastFailureReason === null) {
            $this->lastFailureReason = 'Aucun moteur PDF n’a pu produire le document.';
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function buildPrintHtml(array $row): string
    {
        $title = trim((string) ($row['title'] ?? 'Documentation'));
        $subtitle = trim((string) ($row['subtitle'] ?? ''));
        $summary = trim((string) ($row['summary'] ?? ''));
        $intro = trim((string) (($row['intro_html'] ?? '') ?: ($row['html_body'] ?? '')));
        $sections = TrainingFormationCustomPageRenderer::decodeSections(
            isset($row['sections_json']) ? (string) $row['sections_json'] : null
        );

        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $subtitleHtml = $subtitle !== '' ? '<p class="doc-subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $summaryHtml = $summary !== '' ? '<p class="doc-summary">' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p>' : '';

        $body = '<div class="doc-header"><h1>' . $titleEsc . '</h1>' . $subtitleHtml . $summaryHtml . '</div>';
        if ($intro !== '') {
            $body .= '<div class="doc-prose doc-intro">' . $intro . '</div>';
        }
        foreach ($sections as $i => $s) {
            $body .= '<div class="doc-chapter">'
                . '<h2>' . ($i + 1) . '. ' . htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') . '</h2>'
                . '<div class="doc-prose">' . $s['html'] . '</div>'
                . '</div>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $this->printCss() . '</style></head>'
            . '<body>' . $body . '</body></html>';
    }

    private function printCss(): string
    {
        return '
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10.5pt; line-height: 1.5; }
        .doc-header { margin-bottom: 8mm; border-bottom: 1pt solid #cbd5e1; padding-bottom: 4mm; }
        .doc-header h1 { font-size: 20pt; margin: 0 0 2mm; color: #0f172a; }
        .doc-subtitle { font-size: 11pt; color: #475569; font-style: italic; margin: 0 0 1mm; }
        .doc-summary { font-size: 10pt; color: #64748b; margin: 0; }
        .doc-chapter { margin-top: 8mm; page-break-inside: avoid; }
        .doc-chapter h2 { font-size: 13pt; color: #0f766e; border-bottom: 0.5pt solid #cbd5e1; padding-bottom: 1.5mm; margin: 0 0 3mm; }
        .doc-prose { font-size: 10.5pt; }
        .doc-prose p { margin: 0 0 3mm; }
        .doc-prose img { max-width: 100%; }
        .doc-prose table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .doc-prose table td, .doc-prose table th { border: 0.5pt solid #cbd5e1; padding: 1.5mm 2mm; }
        ';
    }

    /** @param array<string, mixed> $row */
    private function renderWithTcpdf(array $row): ?string
    {
        $font = TrainingCertificatePdfEngine::resolveCertificateFontFamily();
        if ($font === null) {
            return null;
        }

        try {
            return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($row, $font): ?string {
                $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(16, 16, 16);
                $pdf->SetAutoPageBreak(true, 16);
                $pdf->SetFont($font, '', 10);
                $pdf->AddPage();

                $title = trim((string) ($row['title'] ?? 'Documentation'));
                $sections = TrainingFormationCustomPageRenderer::decodeSections(
                    isset($row['sections_json']) ? (string) $row['sections_json'] : null
                );
                $intro = trim((string) (($row['intro_html'] ?? '') ?: ($row['html_body'] ?? '')));

                $pdf->SetFont($font, 'B', 18);
                $pdf->MultiCell(0, 9, $title, 0, 'L', false, 1);
                $pdf->Ln(2);
                $pdf->SetFont($font, '', 10);
                if ($intro !== '') {
                    $pdf->writeHTML($intro, true, false, true, false, '');
                }
                foreach ($sections as $i => $s) {
                    $pdf->Ln(4);
                    $pdf->SetFont($font, 'B', 13);
                    $pdf->MultiCell(0, 7, ($i + 1) . '. ' . $s['title'], 0, 'L', false, 1);
                    $pdf->SetFont($font, '', 10);
                    $pdf->writeHTML($s['html'], true, false, true, false, '');
                }

                $out = $pdf->Output('', 'S');
                unset($pdf);

                return is_string($out) && $out !== '' ? $out : null;
            });
        } catch (\Throwable $e) {
            error_log('[training_doc_pdf] TCPDF : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->lastFailureReason = 'Erreur lors de la composition du document PDF.';

            return null;
        }
    }
}
