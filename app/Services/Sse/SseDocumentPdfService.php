<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\Pdf\SsePdfNotice;
use App\Support\Pdf\SseThemedTcpdf;
use App\Support\SseDocumentMarkings;
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
    public function export(array $doc, int $tenantId, bool $inline = false, bool $withNotice = true): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($doc, $tenantId, $inline, $withNotice): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $binary = $this->build($doc, $tenantId, $withNotice);
            $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($doc['reference_code'] ?? 'document')) ?: 'document';

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="SSE-' . $slug . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /** @param array<string,mixed> $doc */
    public function build(array $doc, int $tenantId, bool $withNotice = true): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ref = (string) ($doc['reference_code'] ?? '');
        $classLabel = (string) ($doc['classification_label'] ?? 'Confidentiel');
        $holders = [];
        try {
            foreach (SseDocumentMarkings::forDocument($doc, '')['routing'] ?? [] as $row) {
                if (trim((string) ($row['holder'] ?? '')) !== '') {
                    $holders[] = trim((string) $row['holder']);
                }
            }
        } catch (\Throwable) {
            $holders = [];
        }
        $caseRef = trim((string) ($doc['case_reference'] ?? ''));
        $pdf = SseThemedTcpdf::themed([
            'kind' => (string) ($doc['document_type_label'] ?? 'Document SSE'),
            'reference' => $ref !== '' ? $ref : 'Athena SSE',
            'tenant_id' => $tenantId,
            'classification' => $classLabel . ' · besoin d’en connaître',
            'audience' => (string) ($doc['status_label'] ?? ''),
            'note' => 'Reproduction interdite sans accord du chef de bureau',
            'title' => (string) ($doc['title'] ?? $ref),
            'band_bottom' => true,
        ], (string) ($doc['sheet_theme'] ?? ''), $classLabel . ($caseRef !== '' ? ' · ' . $caseRef : ''),
            $holders !== [] ? implode(', ', array_slice($holders, 0, 4)) : Theme::t('Diffusion restreinte'), $classLabel);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $fields = SseDocumentFieldValues::for($doc);
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

        $noticePage = 0;
        if ($withNotice) {
            $pdf->AddPage();
            $noticePage = $pdf->getPage();
        }
        $pdf->AddPage();
        $bodyHtml = SseDocumentMedia::expand(SseRichText::toPdfHtml((string) ($doc['body'] ?? ''), $fields), $tenantId > 0 ? $tenantId : (int) ($doc['tenant_id'] ?? 0), 'pdf');
        // Un document issu d'un modèle porte déjà son en-tête (service, bandeau, référence, dossier, date, rédacteur) :
        // il garde sa mise en page, sans second titre. Le bandeau de classification haut et bas de chaque page vient du gabarit.
        $hasOwnHeader = SseRichText::hasTemplateHeader((string) ($doc['body'] ?? ''));
        $bodyPage = $pdf->getPage();
        $pdf->writeAthenaHtml(
            $pdf->bodyCss()
            . ($hasOwnHeader ? '' : '<h1 style="font-size:16pt">' . $h($doc['title'] ?? '') . '</h1>'
                . '<p style="font-size:8pt;color:#475569">' . implode(' &nbsp;·&nbsp; ', $meta) . '</p><hr>')
            . '<div style="font-size:10pt">' . $bodyHtml . '</div>'
        );

        $this->writeSignatures($pdf, $doc, $tenantId > 0 ? $tenantId : (int) ($doc['tenant_id'] ?? 0));

        if ($noticePage > 0) {
            SsePdfNotice::fillReserved($pdf, $noticePage, [
                'lang' => Theme::isEnglish() ? 'en' : 'fr',
                'type' => 'document',
                'title' => (string) ($doc['title'] ?? $ref),
                'ref' => $ref,
                'level' => (string) ($doc['classification'] ?? ''),
                'generated_at' => date(Theme::isEnglish() ? 'm/d/Y H:i' : 'd/m/Y H:i'),
                'ids' => [[['Dossier', 'Case'], (string) ($doc['case_reference'] ?? '')], [['Rédigé par', 'Written by'], (string) ($doc['author_label'] ?? '')]],
                'period' => (string) ($doc['updated_at'] ?? ''),
                'version' => (string) ($doc['status_label'] ?? ''),
                'fingerprint' => SsePdfNotice::fingerprint([$doc['title'] ?? '', $doc['body'] ?? '', $ref, $doc['updated_at'] ?? '']),
                'redaction' => null,
                'toc' => [
                    ['label' => Theme::t('Notice du document'), 'page' => $noticePage],
                    ['label' => (string) ($doc['title'] ?? $ref), 'page' => $bodyPage],
                ],
            ]);
        }

        return (string) $pdf->Output('', 'S');
    }

    /**
     * Signatures au pad : image, nom, date, début de l'empreinte SHA-256 et concordance avec le contenu actuel.
     *
     * @param array<string,mixed> $doc
     */
    private function writeSignatures(\App\Support\Pdf\SseThemedTcpdf $pdf, array $doc, int $tenantId): void
    {
        if ($tenantId < 1 || (int) ($doc['id'] ?? 0) < 1) {
            return;
        }
        try {
            $svc = new SseDocumentSignatureService();
            $sigs = $svc->forDocument($tenantId, (int) $doc['id']);
        } catch (\Throwable) {
            return;
        }
        if ($sigs === []) {
            return;
        }
        $checks = SseDocumentSignatureService::verify($doc, $sigs);
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pdf->writeAthenaHtml('<br><h3>' . $h(Theme::t('Signatures')) . '</h3>');
        foreach ($sigs as $sig) {
            if ($pdf->GetY() > $pdf->getPageHeight() - 62) {
                $pdf->AddPage();
            }
            $y = $pdf->GetY();
            $file = $svc->imageFile($sig, $tenantId);
            if ($file !== null) {
                try {
                    $pdf->Image($file, 17, $y + 1, 48, 0, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
                } catch (\Throwable) {
                    // image illisible : la mention écrite reste
                }
            }
            $when = date(Theme::isEnglish() ? 'm/d/Y H:i' : 'd/m/Y H:i', strtotime((string) $sig['signed_at']) ?: time());
            $ok = !empty($checks[(int) $sig['id']]);
            $pdf->SetXY(70, $y + 2);
            $pdf->writeHTMLCell(120, 0, 70, $y + 2, '<p style="font-size:8.5pt"><b>' . $h(Theme::t('Signé par')) . ' ' . $h($sig['signer_label']) . '</b> · ' . $h($when)
                . '<br><span style="font-size:7pt">SHA-256 ' . $h(substr((string) $sig['doc_hash'], 0, 24)) . '… · '
                . $h(Theme::t($ok ? 'empreinte concordante' : 'empreinte différente : document modifié depuis')) . '</span></p>', 0, 1, false, true, 'L');
            $pdf->SetY(max($pdf->GetY(), $y + 24));
        }
    }
}
