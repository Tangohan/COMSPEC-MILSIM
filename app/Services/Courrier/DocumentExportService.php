<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\DocumentPresetRepository;

/**
 * Export HTML / PDF : styles alignés sur {@see CourrierPrintStyles}, QR optionnel (Endroid), caviardage export externe.
 */
class DocumentExportService
{
    public function __construct(
        private DocumentBuilderService $builderService,
        private DocumentPresetRepository $presetRepository,
        private DocumentRedactionService $redactionService = new DocumentRedactionService(),
        private CourrierQrService $qrService = new CourrierQrService()
    ) {
    }

    /**
     * @param array<string, mixed> $options export_mode: internal|external (caviardage irréversible si external)
     */
    public function buildPrintHtml(array $document, array $context = [], array $options = []): string
    {
        $doc = $document;
        $mode = $options['export_mode'] ?? 'internal';
        if ($mode === 'external' && !empty($doc['body_rendered'])) {
            $doc['body_rendered'] = $this->redactionService->applyIrreversibleForExport((string) $doc['body_rendered']);
        }

        $html = $this->builderService->buildPreviewHtml($doc, $context);
        $html = $this->embedVerificationQr($html, $doc);

        $css = CourrierPrintStyles::inlineCss();
        $fontLink = htmlspecialchars(CourrierPrintStyles::interFontLink(), ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Document</title>
<link rel="stylesheet" href="' . $fontLink . '" />
<style>
body { font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; padding: 1rem; font-size: 14px; color: #0b1220; background: #e8edf3; }
' . $css . '
@media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } }
</style></head><body class="no-print">' . $html . '</body></html>';
    }

    /**
     * Remplace le marqueur QR par une image data-URI ou un lien texte si lib indisponible.
     * @param array<string, mixed> $document
     */
    private function embedVerificationQr(string $html, array $document): string
    {
        if (!str_contains($html, '<!--COURRIER_QR-->')) {
            return $html;
        }
        $uuid = (string) ($document['uuid'] ?? '');
        if ($uuid === '' || empty($document['signed_at'])) {
            return str_replace('<!--COURRIER_QR-->', '', $html);
        }
        $verifyUrl = url('courrier/verify?uuid=' . rawurlencode($uuid));
        $dataUri = $this->qrService->dataUriForText($verifyUrl, 110);
        if ($dataUri === null) {
            $fallback = '<p class="text-[10px]" style="margin-top:4px;max-width:200px;word-break:break-all;">Vérification : '
                . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</p>';

            return str_replace('<!--COURRIER_QR-->', $fallback, $html);
        }
        $img = '<img src="' . htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') . '" alt="QR vérification" width="110" height="110" style="display:block;margin-top:4px;" />';

        return str_replace('<!--COURRIER_QR-->', $img, $html);
    }

    /**
     * Génération PDF (Dompdf). Retourne le chemin du fichier ou null si lib absente / erreur.
     * @param array<string, mixed> $options export_mode passé à buildPrintHtml
     */
    public function generatePdf(array $document, array $context = [], ?string $outputPath = null, array $options = []): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        $html = $this->buildPrintHtml($document, $context, $options);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $binary = $dompdf->output();
        if ($outputPath !== null) {
            if (file_put_contents($outputPath, $binary) === false) {
                return null;
            }

            return $outputPath;
        }
        $tmp = sys_get_temp_dir() . '/courrier-' . uniqid('', true) . '.pdf';
        if (file_put_contents($tmp, $binary) === false) {
            return null;
        }

        return $tmp;
    }

    /**
     * Flux PDF brut pour réponse HTTP.
     * @param array<string, mixed> $options
     */
    public function renderPdfBinary(array $document, array $context = [], array $options = []): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        $html = $this->buildPrintHtml($document, $context, $options);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
