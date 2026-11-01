<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\TrainingCertificatePdfEngine;
use App\Support\TrainingFormationCustomPageRenderer;

/**
 * Export PDF téléchargeable d'une Documentation HTML (manuels imprimés, fiches de procédure
 * hors-ligne), au gabarit Athena en A4 portrait (Dompdf, repli TCPDF). Réutilise le moteur PDF des
 * attestations (TrainingCertificatePdfEngine) avec le gabarit commun — le rendu navigateur/iframe de
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

        $meta = $this->pdfMeta($row);
        $body = $this->buildBodyHtml($row);

        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $binary = Theme::dompdfRender(Theme::dompdfDocument($body, $meta, $this->printCss()), $meta);
                if ($binary !== null) {
                    return $binary;
                }
                $this->lastFailureReason = 'Le moteur PDF n’a produit aucun document.';
            } catch (\Throwable $e) {
                error_log('[training_doc_pdf] Dompdf : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                $this->lastFailureReason = 'Échec du moteur PDF principal ; tentative de secours.';
            }
        }

        if (TrainingCertificatePdfEngine::ensureTcpdfLoaded()
            && TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()
            && TrainingCertificatePdfEngine::isCacheWritable()
        ) {
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

    /**
     * Métadonnées du gabarit Athena (type, référence, communauté du tenant, bandeau de diffusion).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function pdfMeta(array $row): array
    {
        $title = trim((string) ($row['title'] ?? 'Documentation'));
        $key = trim((string) ($row['slug'] ?? '')) !== '' ? (string) $row['slug'] : (string) ($row['id'] ?? '');
        $key = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $key), '-'));

        return [
            'kind' => 'Documentation de formation',
            'reference' => 'DOC-' . ($key !== '' ? mb_substr($key, 0, 40) : date('Ymd')),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'community' => trim((string) ($row['community'] ?? '')),
            'classification' => 'Diffusion interne · documentation de formation',
            'audience' => 'Personnel formé',
            'title' => $title !== '' ? $title : 'Documentation',
        ];
    }

    /**
     * Corps du document (fragments du gabarit, valides sous Dompdf et TCPDF) : titre, sous-titre,
     * résumé, introduction puis chapitres numérotés.
     *
     * @param array<string, mixed> $row
     */
    private function buildBodyHtml(array $row): string
    {
        $title = trim((string) ($row['title'] ?? 'Documentation'));
        $subtitle = trim((string) ($row['subtitle'] ?? ''));
        $summary = trim((string) ($row['summary'] ?? ''));
        $intro = self::resolveIntroHtml($row);
        $sections = TrainingFormationCustomPageRenderer::decodeSections(
            isset($row['sections_json']) ? (string) $row['sections_json'] : null
        );

        $body = Theme::titleBlock('Documentation', $title !== '' ? $title : 'Documentation', [], $subtitle);
        if ($summary !== '') {
            $body .= Theme::callout(Theme::paragraph($summary, 'muted'), '', 'ok');
        }
        if ($intro !== '') {
            $body .= '<div class="doc-prose doc-intro">' . $intro . '</div>';
        }
        foreach ($sections as $i => $s) {
            $body .= Theme::section(sprintf('%02d', $i + 1), $s['title'])
                . '<div class="doc-prose">' . $s['html'] . '</div>';
        }

        return $body;
    }

    /** @param array<string, mixed> $row */
    private static function resolveIntroHtml(array $row): string
    {
        $introHtml = (string) ($row['intro_html'] ?? '');

        return trim($introHtml !== '' ? $introHtml : (string) ($row['html_body'] ?? ''));
    }

    /** Styles de la prose saisie (Dompdf), complétant la feuille du gabarit. */
    private function printCss(): string
    {
        return '
        .doc-prose { font-size: 9.5pt; line-height: 1.5; }
        .doc-prose p { margin: 0 0 3mm; }
        .doc-prose h1, .doc-prose h2, .doc-prose h3, .doc-prose h4 { font-size: 10pt; margin: 3mm 0 1.5mm; }
        .doc-prose img { max-width: 100%; }
        .doc-prose ul, .doc-prose ol { margin: 0 0 3mm; padding-left: 6mm; }
        .doc-prose table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .doc-prose table th { border-bottom: 0.6pt solid ' . Theme::INK . '; color: ' . Theme::MUTED . '; font-size: 7pt; text-align: left; padding: 1.5mm 2mm; }
        .doc-prose table td { border-bottom: 0.3pt solid ' . Theme::LINE . '; padding: 1.5mm 2mm; }
        .doc-prose a { color: ' . Theme::ACCENT_DARK . '; }
        ';
    }

    /** @param array<string, mixed> $row */
    private function renderWithTcpdf(array $row): ?string
    {
        try {
            return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($row): ?string {
                $pdf = Theme::tcpdf($this->pdfMeta($row));
                if ($pdf === null) {
                    return null;
                }
                $pdf->AddPage();
                $pdf->writeAthenaHtml($this->buildBodyHtml($row));
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
