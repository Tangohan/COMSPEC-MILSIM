<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\SseRichText;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF d'un document rédigé dans le bureau SSE, par la même chaîne que le reste du SSE (gabarit Athena : bandeau de
 * classification, en-tête, pied paginé). Le corps est le HTML assaini de l'éditeur (ou le texte historique converti).
 */
final class SseDocumentPdfService
{
    /**
     * @param array<string,mixed> $doc document hydraté (SseDocumentRepository::findById)
     */
    public function export(array $doc, int $tenantId, bool $inline = false): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($doc, $tenantId, $inline): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $binary = $this->build($doc, $tenantId);
            $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($doc['reference_code'] ?? 'document')) ?: 'document';

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="SSE-' . $slug . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /** @param array<string,mixed> $doc */
    public function build(array $doc, int $tenantId): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ref = (string) ($doc['reference_code'] ?? '');
        $classLabel = (string) ($doc['classification_label'] ?? 'Confidentiel');
        $pdf = Theme::tcpdf([
            'kind' => (string) ($doc['document_type_label'] ?? 'Document SSE'),
            'reference' => $ref !== '' ? $ref : 'Athena SSE',
            'tenant_id' => $tenantId,
            'classification' => $classLabel . ' · besoin d’en connaître',
            'audience' => (string) ($doc['status_label'] ?? ''),
            'note' => 'Reproduction interdite sans accord du chef de bureau',
            'title' => (string) ($doc['title'] ?? $ref),
            'band_bottom' => true,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $fields = [
            'dossier' => (string) ($doc['case_reference'] ?? ''),
            'classification' => $classLabel,
            'reference' => $ref,
            'titre' => (string) ($doc['title'] ?? ''),
        ];
        $meta = [];
        foreach ([
            'Référence' => $ref,
            'Dossier' => (string) ($doc['case_reference'] ?? ''),
            'Rédigé par' => (string) ($doc['author_label'] ?? ''),
            'Mis à jour' => (string) ($doc['updated_at'] ?? ''),
        ] as $label => $value) {
            if (trim($value) !== '') {
                $meta[] = '<b>' . $h($label) . '</b> : ' . $h($value);
            }
        }

        $pdf->AddPage();
        $bodyHtml = SseRichText::toPdfHtml((string) ($doc['body'] ?? ''), $fields);
        // Un document issu d'un modèle porte déjà son en-tête (service, bandeau, référence, dossier, date, rédacteur) :
        // il garde sa mise en page, sans second titre. Le bandeau de classification haut et bas de chaque page vient du gabarit.
        $hasOwnHeader = SseRichText::hasTemplateHeader((string) ($doc['body'] ?? ''));
        $pdf->writeAthenaHtml(
            ($hasOwnHeader ? '' : '<h1 style="font-size:16pt">' . $h($doc['title'] ?? '') . '</h1>'
                . '<p style="font-size:8pt;color:#475569">' . implode(' &nbsp;·&nbsp; ', $meta) . '</p><hr>')
            . '<div style="font-size:10pt">' . $bodyHtml . '</div>'
        );

        return (string) $pdf->Output('', 'S');
    }
}
