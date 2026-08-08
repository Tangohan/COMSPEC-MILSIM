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
        $body = (string) ($document['body_rendered'] ?? '');
        // Résolution tardive : le corps édité peut encore contenir {{variables}}.
        $body = $this->renderService->renderBody($body, array_merge($context, [
            'document' => array_merge(is_array($context['document'] ?? null) ? $context['document'] : [], $document),
        ]));
        $document['body_rendered'] = $body;
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

        $html = '<div class="courrier-preview ' . $paperClass . ' max-w-[21cm] mx-auto min-h-[29.7cm] border border-gray-200 bg-white" data-paper="a4" data-orientation="' . htmlspecialchars($orientation) . '"' . $styleAttr . '>';
        $html .= $this->buildClassificationOverlay($document);
        $html .= '<div class="courrier-preview-inner p-10">';
        $html .= $this->buildEnvelopeHtml($document, $preset);
        $html .= '<div class="courrier-body text-xs leading-relaxed text-justify space-y-4">' . $body . '</div>';
        $html .= '</div></div>';
        return $html;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentMetadata(array $document): array
    {
        $raw = $document['metadata_json'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $d = json_decode($raw, true);

            return is_array($d) ? $d : [];
        }

        return is_array($raw) ? $raw : [];
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

        $meta = $this->documentMetadata($document);
        $hasMetaHeader = (trim((string) ($meta['header_line1'] ?? '')) !== '')
            || (trim((string) ($meta['header_unit'] ?? '')) !== '')
            || (trim((string) ($meta['header_section'] ?? '')) !== '');

        $out .= '<div class="courrier-envelope-header text-[10px] font-bold uppercase leading-tight mb-12">';
        if ($hasMetaHeader) {
            $l1 = trim((string) ($meta['header_line1'] ?? ''));
            if ($l1 === '') {
                $l1 = 'MINISTÈRE DE LA DÉFENSE';
            }
            $u = trim((string) ($meta['header_unit'] ?? ''));
            $s = trim((string) ($meta['header_section'] ?? ''));
            $out .= '<p>' . htmlspecialchars($l1) . '</p>';
            $out .= '<p class="border-b-2 border-black w-fit mb-1">' . htmlspecialchars('UNITÉ : ' . ($u !== '' ? $u : '(à définir)')) . '</p>';
            $out .= '<p>' . htmlspecialchars('SECTION : ' . ($s !== '' ? $s : '(à définir)')) . '</p>';
        } elseif (is_array($lines) && count($lines) >= 3) {
            $out .= '<p>' . htmlspecialchars((string) $lines[0]) . '</p>';
            $out .= '<p class="border-b-2 border-black w-fit mb-1">' . htmlspecialchars((string) $lines[1]) . '</p>';
            $out .= '<p>' . htmlspecialchars((string) $lines[2]) . '</p>';
        } else {
            $out .= '<p>MINISTÈRE DE LA DÉFENSE</p>';
            $out .= '<p class="border-b-2 border-black w-fit mb-1">UNITÉ : (à définir)</p>';
            $out .= '<p>SECTION : (à définir)</p>';
        }
        $refTemplate = is_array($headerConfig) ? ($headerConfig['reference_template'] ?? 'N° {{ref}} / CERBERE / RH') : 'N° {{ref}} / CERBERE / RH';
        $refLine = str_replace('{{ref}}', $refDisplay, $refTemplate);
        $out .= '<p class="mt-4">' . htmlspecialchars($refLine) . '</p>';
        $out .= '</div>';

        $dateStr = $datePlace !== '' ? $datePlace . ', le ' . date('d/m/Y') : 'Le ' . date('d/m/Y');
        $out .= '<div class="text-right text-[11px] mb-10"><p>' . htmlspecialchars($dateStr) . '</p></div>';

        $issuer = trim((string) ($document['issuer_label'] ?? ''));
        $destination = trim((string) ($document['destination_label'] ?? ''));
        $subject = trim((string) ($document['subject'] ?? ''));

        if ($issuer !== '' || $destination !== '') {
            $out .= '<div class="courrier-envelope-recipients ml-auto w-1/2 text-[11px] font-bold mb-12">';
            if ($issuer !== '' && $destination !== '') {
                $issuerLines = preg_split('/\r\n|\r|\n/', $issuer, -1, PREG_SPLIT_NO_EMPTY) ?: [$issuer];
                $destLines = preg_split('/\r\n|\r|\n/', $destination, -1, PREG_SPLIT_NO_EMPTY) ?: [$destination];
                $out .= '<div class="courrier-envelope-recipients-inner space-y-1">';
                foreach ($issuerLines as $line) {
                    $out .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
                }
                $out .= '<p class="courrier-envelope-a"><span class="courrier-envelope-a-sep">à</span></p>';
                foreach ($destLines as $line) {
                    $out .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
                }
                $out .= '</div>';
            } else {
                $out .= '<div class="space-y-1">';
                if ($issuer !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $issuer, -1, PREG_SPLIT_NO_EMPTY) ?: [$issuer] as $line) {
                        $out .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
                    }
                }
                if ($destination !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $destination, -1, PREG_SPLIT_NO_EMPTY) ?: [$destination] as $line) {
                        $out .= '<p>' . htmlspecialchars(trim($line)) . '</p>';
                    }
                }
                $out .= '</div>';
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
     * Résout les {{variables}} encore présentes dans un corps de document.
     *
     * @param array{user_id?: int, tenant_id?: int, unit_id?: int, document?: array} $context
     * @param array<string, string> $overrides
     */
    public function resolveBodyPlaceholders(string $body, array $context, array $overrides = []): string
    {
        return $this->renderService->renderBody($body, $context, $overrides);
    }

    /**
     * @return list<string>
     */
    public function findUnresolvedPlaceholders(string $text): array
    {
        return $this->renderService->findUnresolvedPlaceholders($text);
    }

    /**
     * Remplace {{signature_block}} dans le body par un placeholder (brouillon) ou l'image + tampons (document signé).
     * Si le marqueur est absent, le bloc est ajouté en fin de corps.
     */
    public function injectSignatureBlock(string $body, array $document, array $context = []): string
    {
        $sigData = $document['signature_data_json'] ?? null;
        $data = $sigData !== null ? (is_string($sigData) ? json_decode($sigData, true) : $sigData) : null;

        if (empty($data) || empty($data['signature_image_path'])) {
            $issuerLabel = trim((string) ($document['issuer_label'] ?? ''));
            $placeholder = $this->buildSignaturePlaceholderHtml($issuerLabel);

            return $this->mergeSignatureIntoBody($body, $placeholder);
        }

        $docId = (int) ($document['id'] ?? 0);
        $imgUrl = $docId > 0 ? url('courrier/documents/' . $docId . '/signature-image') : '';
        $stampOrig = htmlspecialchars($data['stamp_original_signed'] ?? '');
        $stampName = htmlspecialchars($data['stamp_name_signature'] ?? '');
        $stampGrade = htmlspecialchars($data['stamp_grade'] ?? '');

        $block = '<div class="courrier-signature-block courrier-signature-block--signed mt-10 text-[11px]">';
        $block .= '<div class="courrier-signature-signed-inner flex flex-col items-center text-center gap-3">';
        if ($imgUrl !== '') {
            $block .= '<img src="' . htmlspecialchars($imgUrl) . '" alt="Signature" class="courrier-signature-img max-h-20 object-contain" />';
        }
        if ($stampOrig !== '') {
            $block .= '<p class="courrier-signature-original-stamp">' . $stampOrig . '</p>';
        } elseif ($imgUrl !== '') {
            $block .= '<p class="courrier-signature-original-stamp">Original signé</p>';
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
        $block .= '</div></div>';

        return $this->mergeSignatureIntoBody($body, $block);
    }

    private function buildSignaturePlaceholderHtml(string $issuerLabel): string
    {
        $html = '<div class="courrier-signature-block courrier-signature-block--draft mt-24 mx-auto max-w-sm text-center text-[10px]">';
        $html .= '<p class="courrier-signature-title">SIGNATURE</p>';
        $html .= '<div class="courrier-signature-placeholder">Signature Numérique</div>';
        if ($issuerLabel !== '') {
            $html .= '<p class="courrier-signature-name">' . htmlspecialchars($issuerLabel) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    private function mergeSignatureIntoBody(string $body, string $blockHtml): string
    {
        $marker = '{{signature_block}}';
        if (str_contains($body, $marker)) {
            return str_replace($marker, $blockHtml, $body);
        }

        return $body . $blockHtml;
    }
}
