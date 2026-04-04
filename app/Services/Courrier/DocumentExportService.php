<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\DocumentPresetRepository;

/**
 * Export HTML propre pour impression et génération PDF (wrapper pour lib PDF à venir).
 */
class DocumentExportService
{
    public function __construct(
        private DocumentBuilderService $builderService,
        private DocumentPresetRepository $presetRepository
    ) {
    }

    /**
     * Génère le HTML final pour impression (une page, styles print).
     */
    public function buildPrintHtml(array $document, array $context = []): string
    {
        $html = $this->builderService->buildPreviewHtml($document, $context);
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Document</title>
<style>
body { font-family: "Source Serif 4", Georgia, serif; margin: 0; padding: 0; font-size: 11pt; color: #1e293b; }
.courrier-preview { max-width: 21cm; margin: 0 auto; min-height: 29.7cm; padding: 2.5rem; box-sizing: border-box; border: 1px solid #e5e7eb; background: #fff; }
.courrier-preview .text-\\[10px\\] { font-size: 10px; }
.courrier-preview .text-\\[11px\\] { font-size: 11px; }
.courrier-preview .text-xs { font-size: 0.75rem; line-height: 1.5; text-align: justify; }
.courrier-preview .mb-12 { margin-bottom: 3rem; }
.courrier-preview .mb-10 { margin-bottom: 2.5rem; }
.courrier-preview .mb-8 { margin-bottom: 2rem; }
.courrier-preview .mb-2 { margin-bottom: 0.5rem; }
.courrier-preview .mt-4 { margin-top: 1rem; }
.courrier-preview .mt-24 { margin-top: 6rem; }
.courrier-preview .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.courrier-preview .space-y-1 > * + * { margin-top: 0.25rem; }
.courrier-preview .space-y-2 > * + * { margin-top: 0.5rem; }
.courrier-preview .space-y-4 > * + * { margin-top: 1rem; }
.courrier-preview .text-right { text-align: right; }
.courrier-preview .ml-auto { margin-left: auto; }
.courrier-preview .w-1\\/2 { width: 50%; }
.courrier-preview .w-1\\/3 { width: 33.333333%; }
.courrier-preview .text-center { text-align: center; }
.courrier-preview .font-bold { font-weight: 700; }
.courrier-preview .uppercase { text-transform: uppercase; }
.courrier-preview .underline { text-decoration: underline; }
.courrier-preview .italic { font-style: italic; }
.courrier-preview .leading-tight { line-height: 1.25; }
.courrier-preview .border-b-2 { border-bottom-width: 2px; }
.courrier-preview .border-black { border-color: #000; }
.courrier-preview .w-fit { width: fit-content; }
.courrier-preview .mb-1 { margin-bottom: 0.25rem; }
.courrier-preview .text-blue-600 { color: #2563eb; }
.courrier-preview .h-12 { height: 3rem; }
.courrier-preview .border-dashed { border-style: dashed; }
.courrier-preview .border-gray-300 { border-color: #d1d5db; }
.courrier-preview .flex { display: flex; }
.courrier-preview .items-center { align-items: center; }
.courrier-preview .justify-center { justify-content: center; }
.courrier-preview .text-gray-400 { color: #9ca3af; }
.courrier-body p { margin-bottom: 0.75rem; }
@media print { .no-print { display: none !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style></head><body class="no-print">' . $html . '</body></html>';
    }

    /**
     * Génération PDF : retourne le chemin du fichier généré ou null (à implémenter avec Dompdf/Snappy).
     */
    public function generatePdf(array $document, array $context = [], ?string $outputPath = null): ?string
    {
        $html = $this->buildPrintHtml($document, $context);
        // TODO: intégrer Dompdf ou Snappy pour HTML -> PDF
        // $dompdf = new Dompdf(); $dompdf->loadHtml($html); $dompdf->render(); file_put_contents($outputPath, $dompdf->output());
        return null;
    }
}
