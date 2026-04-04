<?php

declare(strict_types=1);

namespace App\Services\Courrier;

use App\Repositories\Courrier\DocumentPresetRepository;
use App\Repositories\Courrier\DocumentTemplateRepository;

/**
 * Assemble le document final : preset (mise en page) + body rendu pour aperçu HTML.
 */
class DocumentBuilderService
{
    public function __construct(
        private TemplateRenderService $renderService,
        private DocumentPresetRepository $presetRepository,
        private DocumentTemplateRepository $templateRepository,
        private DocumentRedactionService $redactionService = new DocumentRedactionService()
    ) {
    }

    /**
     * Génère le HTML d'aperçu du document (avec styles inline pour l'aperçu).
     * @param array{body_rendered: string, preset_id?: int, template_id?: int, variables_json?: string|array} $document
     * @param array{user_id?: int, tenant_id?: int, unit_id?: int, document?: array} $context
     */
    public function buildPreviewHtml(array $document, array $context = []): string
    {
        $body = $document['body_rendered'] ?? '';
        $body = $this->redactionService->applyVisualMarkers($body);
        $body = $this->injectSignatureBlock($body, $document, $context);
        if (trim(strip_tags($body)) === '') {
            $body = '<p class="text-slate-400 text-sm">Le corps du document est vide. Saisissez le contenu dans la zone « Corps du document ».</p>';
        }
        $presetId = $document['preset_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        $styles = [];
        $preset = null;
        if ($presetId) {
            $preset = $this->presetRepository->findById((int) $presetId, $tenantId);
        }
        if ($preset) {
            $margins = is_string($preset['margins_json'] ?? null) ? json_decode($preset['margins_json'], true) : ($preset['margins_json'] ?? []);
            if (is_array($margins)) {
                $top = $margins['top'] ?? 20;
                $right = $margins['right'] ?? 20;
                $bottom = $margins['bottom'] ?? 20;
                $left = $margins['left'] ?? 20;
                $styles[] = "margin: {$top}mm {$right}mm {$bottom}mm {$left}mm;";
            }
            $typography = is_string($preset['typography_json'] ?? null) ? json_decode($preset['typography_json'], true) : ($preset['typography_json'] ?? []);
            if (is_array($typography)) {
                if (!empty($typography['font_family'])) {
                    $styles[] = 'font-family: ' . $typography['font_family'] . ';';
                }
                if (!empty($typography['font_size'])) {
                    $styles[] = 'font-size: ' . $typography['font_size'] . ';';
                }
                if (isset($typography['line_height'])) {
                    $styles[] = 'line-height: ' . $typography['line_height'] . ';';
                }
            }
        }

        $hasFont = false;
        foreach ($styles as $s) {
            if (str_contains((string) $s, 'font-family')) {
                $hasFont = true;
                break;
            }
        }
        if (!$hasFont) {
            $styles[] = 'font-family: Inter, \"Segoe UI\", Roboto, Arial, sans-serif;';
            $styles[] = 'font-size: 14px;';
            $styles[] = 'line-height: 1.55;';
            $styles[] = 'color: #0b1220;';
        }

        $orientation = $preset !== null ? ($preset['orientation'] ?? 'portrait') : 'portrait';
        $paperClass = $orientation === 'landscape' ? 'a4-landscape' : 'a4-portrait';
        $styleAttr = !empty($styles) ? ' style="' . implode(' ', $styles) . '"' : '';

        $html = '<div class="courrier-preview ' . $paperClass . ' max-w-[21cm] mx-auto min-h-[29.7cm] p-10 border border-gray-200 bg-white" data-paper="a4" data-orientation="' . htmlspecialchars($orientation) . '"' . $styleAttr . '>';
        $html .= $this->buildClassificationOverlay($document);
        $html .= $this->buildEnvelopeHtml($document, $preset);
        $html .= '<div class="courrier-body text-xs leading-relaxed text-justify space-y-4">' . $body . '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Bloc en-tête + expéditeur/destinataire/objet/référence (forme courrier officiel — rendu Ministère Défense).
     * @param array<string, mixed> $document
     * @param array<string, mixed>|null $preset
     */
    private function buildEnvelopeHtml(array $document, ?array $preset): string
    {
        $out = '';
        $headerConfig = null;
        if ($preset !== null && !empty($preset['header_config_json'])) {
            $headerConfig = is_string($preset['header_config_json'])
                ? json_decode($preset['header_config_json'], true)
                : $preset['header_config_json'];
        }
        $reference = trim((string) ($document['reference_number'] ?? ''));
        $refDisplay = $reference !== '' ? $reference : '____';
        $lines = is_array($headerConfig['lines'] ?? null) ? $headerConfig['lines'] : null;
        $datePlace = trim((string) ($headerConfig['date_place'] ?? ''));

        $out .= '<div class="text-[10px] font-bold uppercase leading-tight mb-12">';
        if (is_array($lines) && count($lines) >= 3) {
            $out .= '<p>' . htmlspecialchars((string) $lines[0]) . '</p>';
            $out .= '<p class="border-b-2 border-black w-fit mb-1">' . htmlspecialchars((string) $lines[1]) . '</p>';
            $out .= '<p>' . htmlspecialchars((string) $lines[2]) . '</p>';
        } else {
            $out .= '<p>MINISTÈRE DE LA DÉFENSE</p>';
            $out .= '<p class="border-b-2 border-black w-fit mb-1">UNITÉ : (à définir)</p>';
            $out .= '<p>SECTION : (à définir)</p>';
        }
        $refTemplate = $headerConfig['reference_template'] ?? 'N° {{ref}} / CERBERE / RH';
        $refLine = str_replace('{{ref}}', $refDisplay, $refTemplate);
        $out .= '<p class="mt-4">' . htmlspecialchars($refLine) . '</p>';
        $out .= '</div>';

        $dateStr = $datePlace !== '' ? $datePlace . ', le ' . date('d/m/Y') : 'Le ' . date('d/m/Y');
        $out .= '<div class="text-right text-[11px] mb-10"><p>' . htmlspecialchars($dateStr) . '</p></div>';

        $issuer = trim((string) ($document['issuer_label'] ?? ''));
        $destination = trim((string) ($document['destination_label'] ?? ''));
        $subject = trim((string) ($document['subject'] ?? ''));

        if ($issuer !== '' || $destination !== '') {
            $out .= '<div class="ml-auto w-1/2 text-[11px] font-bold space-y-1 mb-12">';
            if ($issuer !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $issuer, -1, PREG_SPLIT_NO_EMPTY) ?: [$issuer] as $line) {
                    $out .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
                }
            }
            if ($destination !== '') {
                $out .= '<p class="text-blue-600 italic py-2">à</p>';
                foreach (preg_split('/\r\n|\r|\n/', $destination, -1, PREG_SPLIT_NO_EMPTY) ?: [$destination] as $line) {
                    $out .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
                }
            }
            $out .= '</div>';
        }

        if ($subject !== '' || $reference !== '') {
            $out .= '<div class="text-[11px] space-y-2 mb-10">';
            if ($subject !== '') {
                $out .= '<p><span class="underline font-bold">OBJET</span> : ' . htmlspecialchars($subject) . '</p>';
            }
            if ($reference !== '') {
                $out .= '<p><span class="underline font-bold">RÉFÉRENCE</span> : ' . htmlspecialchars($reference) . '</p>';
            }
            $out .= '</div>';
        }

        return $out;
    }

    /**
     * Bandeau + filigrane selon classification.
     * @param array<string, mixed> $document
     */
    private function buildClassificationOverlay(array $document): string
    {
        $level = (string) ($document['classification_level'] ?? 'interne');
        $label = CourrierClassification::label($level);
        $wmClass = CourrierClassification::watermarkClass($level);
        $out = '<div class="courrier-classification-banner">Classification — ' . htmlspecialchars($label) . '</div>';
        $out .= '<div class="courrier-watermark ' . htmlspecialchars($wmClass) . '">' . htmlspecialchars(mb_strtoupper($label, 'UTF-8')) . '</div>';

        return $out;
    }

    /**
     * Assemble le body à partir du template + contexte (pour nouveau document ou régénération).
     */
    public function buildBodyFromTemplate(int $templateId, array $context, array $variablesOverrides = []): string
    {
        $template = $this->templateRepository->findById($templateId, $context['tenant_id'] ?? null);
        if (!$template || empty($template['body_template'])) {
            return '';
        }
        return $this->renderService->renderBody($template['body_template'], $context, $variablesOverrides);
    }

    /**
     * Remplace {{signature_block}} dans le body par un placeholder (brouillon) ou l'image + tampons (document signé).
     */
    public function injectSignatureBlock(string $body, array $document, array $context = []): string
    {
        $sigData = $document['signature_data_json'] ?? null;
        $data = $sigData !== null ? (is_string($sigData) ? json_decode($sigData, true) : $sigData) : null;

        if (empty($data) || empty($data['signature_image_path'])) {
            $issuerLabel = trim((string) ($document['issuer_label'] ?? ''));
            $placeholder = '<div class="mt-24 ml-auto w-1/3 text-center text-[10px]">';
            $placeholder .= '<p class="font-bold uppercase underline mb-8">Signature</p>';
            $placeholder .= '<div class="h-12 border border-dashed border-gray-300 flex items-center justify-center text-gray-400 mb-2">Signature Numérique</div>';
            if ($issuerLabel !== '') {
                $placeholder .= '<p>' . htmlspecialchars($issuerLabel) . '</p>';
            }
            $placeholder .= '</div>';
            return str_replace('{{signature_block}}', $placeholder, $body);
        }

        $docId = (int) ($document['id'] ?? 0);
        $imgUrl = $docId > 0 ? url('courrier/documents/' . $docId . '/signature-image') : '';
        $stampOrig = htmlspecialchars($data['stamp_original_signed'] ?? '');
        $stampName = htmlspecialchars($data['stamp_name_signature'] ?? '');
        $stampGrade = htmlspecialchars($data['stamp_grade'] ?? '');

        $block = '<div class="mt-10 flex flex-col items-end gap-2 text-[11px]">';
        if ($imgUrl !== '') {
            $block .= '<img src="' . htmlspecialchars($imgUrl) . '" alt="Signature" class="max-h-16 object-contain" />';
        }
        if ($stampOrig !== '') {
            $block .= '<p class="font-semibold">' . $stampOrig . '</p>';
        }
        if ($stampName !== '') {
            $block .= '<p>' . $stampName . '</p>';
        }
        if ($stampGrade !== '') {
            $block .= '<p>' . $stampGrade . '</p>';
        }
        if (is_array($data) && !empty($data['verification_code'])) {
            $block .= '<p class="font-semibold">' . htmlspecialchars('Signé numériquement — ' . (string) $data['verification_code']) . '</p>';
        }
        $block .= '<!--COURRIER_QR-->';
        $block .= '</div>';

        return str_replace('{{signature_block}}', $block, $body);
    }
}
