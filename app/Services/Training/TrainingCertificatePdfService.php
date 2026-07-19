<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingCertificateTemplateRepository;
use App\Repositories\UserRepository;
use App\Support\TrainingCertificatePdfEngine;

class TrainingCertificatePdfService
{
    private ?string $lastFailureReason = null;

    public function __construct(
        private TrainingCertificateRepository $certificateRepository,
        private TrainingCertificateTemplateRepository $templateRepository,
        private UserRepository $userRepository,
        private TrainingCertificateAssetStorageService $assetStorage,
    ) {}

    public function getLastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    /**
     * Génère le PDF, l’enregistre et met à jour pdf_path. Retourne le chemin relatif ou null.
     */
    public function generateAndStore(int $certificateId, int $tenantId): ?string
    {
        $this->lastFailureReason = null;

        if (!TrainingCertificatePdfEngine::isAvailable()) {
            $this->lastFailureReason = TrainingCertificatePdfEngine::staffUnavailabilityHint()
                ?? 'Aucun moteur PDF utilisable sur ce serveur.';

            return null;
        }

        $cert = $this->certificateRepository->findById($certificateId, $tenantId);
        if (!$cert) {
            $this->lastFailureReason = 'Attestation introuvable pour cette communauté.';

            return null;
        }

        $tplRow = $this->templateRepository->findByTenantId($tenantId);
        $tpl = $this->normalizeTemplate($tplRow);
        $payload = $this->buildPayloadFromCertificate($cert, $tenantId);

        $binary = $this->renderPdfBinary($tpl, $payload);
        if ($binary === null || $binary === '') {
            if ($this->lastFailureReason === null) {
                $this->lastFailureReason = 'Le moteur PDF n’a produit aucun fichier.';
            }

            return null;
        }

        $relDir = 'storage/app/training-certificates-generated/' . $tenantId;
        $absDir = base_path($relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            $this->lastFailureReason = 'Impossible de créer le dossier de stockage des attestations (droits d’écriture).';

            return null;
        }
        if (!is_writable($absDir)) {
            @chmod($absDir, 0775);
        }
        if (!is_writable($absDir)) {
            $this->lastFailureReason = 'Le dossier de stockage des attestations n’est pas accessible en écriture.';

            return null;
        }
        $relPath = $relDir . '/' . $certificateId . '.pdf';
        $absPath = base_path($relPath);
        if (@file_put_contents($absPath, $binary) === false) {
            $this->lastFailureReason = 'Impossible d’enregistrer le fichier PDF sur le serveur (espace disque ou droits).';

            return null;
        }
        $this->certificateRepository->updatePdfPath($certificateId, $relPath);

        return $relPath;
    }

    /**
     * PDF d’exemple (données fictives), sans écriture en base.
     */
    public function generatePreviewBinary(int $tenantId): ?string
    {
        if (!TrainingCertificatePdfEngine::isAvailable()) {
            return null;
        }
        $tplRow = $this->templateRepository->findByTenantId($tenantId);
        $tpl = $this->normalizeTemplate($tplRow);

        return $this->renderPdfBinary($tpl, $this->previewPayload());
    }

    /**
     * @param array<string, mixed> $tpl normalisé (normalizeTemplate)
     * @param array<string, mixed> $payload buildPayloadFromCertificate ou previewPayload
     */
    private function renderPdfBinary(array $tpl, array $payload): ?string
    {
        // Dompdf en premier dès qu’il est dispo (évite le crash TCPDF::_destroy / imagekeys).
        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $html = $this->buildDompdfHtml($tpl, $payload);
                $root = realpath(base_path()) ?: base_path();
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'chroot' => $root]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                $binary = $dompdf->output();
                if ($binary !== false && $binary !== '') {
                    $this->lastFailureReason = null;

                    return $binary;
                }
                error_log('[training_certificate_pdf] Dompdf : sortie vide (render sans exception).');
                $this->lastFailureReason = 'Le moteur PDF n’a produit aucun document.';
            } catch (\Throwable $e) {
                error_log(
                    '[training_certificate_pdf] Dompdf : ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine()
                );
                $this->lastFailureReason = 'Échec du moteur PDF principal ; tentative de secours.';
            }
        }

        // Repli TCPDF si Dompdf absent ou en échec.
        if (TrainingCertificatePdfEngine::ensureTcpdfLoaded()
            && TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()
            && TrainingCertificatePdfEngine::isCacheWritable()
        ) {
            $b = $this->renderWithTcpdf($tpl, $payload);
            if ($b !== null && $b !== '') {
                return $b;
            }
        }

        if ($this->lastFailureReason === null) {
            $this->lastFailureReason = 'Aucun moteur PDF n’a pu produire le document.';
        }
        error_log('[training_certificate_pdf] Aucun moteur PDF utilisable (Dompdf et/ou TCPDF).');

        return null;
    }

    /**
     * @param array<string, mixed> $cert ligne training_certificates
     * @return array<string, mixed>
     */
    private function buildPayloadFromCertificate(array $cert, int $tenantId): array
    {
        $learnerId = (int) ($cert['user_id'] ?? 0);
        $learner = $learnerId > 0 ? $this->userRepository->findById($learnerId, $tenantId) : null;
        $learnerName = '';
        if ($learner) {
            $learnerName = trim((string) ($learner['display_name'] ?? ''));
            if ($learnerName === '') {
                $learnerName = trim((string) ($learner['email'] ?? ''));
            }
        }
        if ($learnerName === '') {
            $learnerName = 'Apprenant';
        }

        return [
            'learner_name' => $learnerName,
            'course_title' => (string) ($cert['course_title'] ?? 'Formation'),
            'certificate_number' => (string) ($cert['certificate_number'] ?? ''),
            'issued_date_fr' => !empty($cert['issued_at']) ? date('d/m/Y', strtotime((string) $cert['issued_at'])) : '',
            'expires_date_fr' => !empty($cert['expires_at']) ? date('d/m/Y', strtotime((string) $cert['expires_at'])) : '',
            'final_score' => round((float) ($cert['final_score'] ?? 0), 1),
        ];
    }

    /** @return array<string, mixed> */
    private function previewPayload(): array
    {
        return [
            'learner_name' => 'Exemple de participant',
            'course_title' => 'Exemple de parcours certifiant',
            'certificate_number' => 'DEMO-0001',
            'issued_date_fr' => date('d/m/Y'),
            'expires_date_fr' => date('d/m/Y', strtotime('+1 year')),
            'final_score' => 88.5,
        ];
    }

    /**
     * @param array<string, mixed> $tpl
     * @param array<string, mixed> $payload
     */
    private function buildDompdfHtml(array $tpl, array $payload): string
    {
        $logoAbs = $this->assetStorage->absolutePath($tpl['logo_relative_path']);
        $bgAbs = $this->assetStorage->absolutePath($tpl['background_relative_path']);

        $primary = htmlspecialchars($tpl['primary_hex'], ENT_QUOTES, 'UTF-8');
        $accent = htmlspecialchars($tpl['accent_hex'], ENT_QUOTES, 'UTF-8');
        $headline = htmlspecialchars($tpl['headline'], ENT_QUOTES, 'UTF-8');
        $subtitle = $tpl['subtitle'] !== ''
            ? '<p class="sub">' . htmlspecialchars($tpl['subtitle'], ENT_QUOTES, 'UTF-8') . '</p>'
            : '';
        $footer = $tpl['footer_legal'] !== ''
            ? '<div class="legal">' . nl2br(htmlspecialchars($tpl['footer_legal'], ENT_QUOTES, 'UTF-8')) . '</div>'
            : '';

        $logoHtml = $logoAbs !== null ? $this->buildImageDataUriHtml($logoAbs, 'logo') : '';
        $bgLayer = '';
        if ($bgAbs !== null) {
            $bgLayer = '<div class="diploma-bg" style="' . $this->buildBackgroundDataUriStyle($bgAbs) . '"></div>';
        }

        $learnerName = htmlspecialchars((string) $payload['learner_name'], ENT_QUOTES, 'UTF-8');
        $courseTitle = htmlspecialchars((string) $payload['course_title'], ENT_QUOTES, 'UTF-8');
        $metaBits = $this->buildMetaBitsHtml($tpl, $payload);
        $sigPed = $this->signatureSvgMarkup('pedagogy', $tpl['primary_hex']);
        $sigDir = $this->signatureSvgMarkup('direction', $tpl['accent_hex']);

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>
        @page { margin: 10mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body { font-family: DejaVu Sans, sans-serif; color: ' . $primary . '; }
        .page { width: 100%; height: 100%; padding: 0; }
        .diploma {
            position: relative;
            width: 100%;
            height: 190mm;
            min-height: 190mm;
            border: 2.5pt solid ' . $accent . ';
            overflow: hidden;
            background: #ffffff;
        }
        .diploma-inner-rule {
            position: absolute;
            left: 4mm; top: 4mm; right: 4mm; bottom: 4mm;
            border: 0.6pt solid ' . $accent . ';
            opacity: 0.45;
            z-index: 1;
        }
        .diploma-bg {
            position: absolute;
            left: 0; top: 0; right: 0; bottom: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.30;
            z-index: 0;
        }
        .content {
            position: relative;
            z-index: 2;
            height: 100%;
            padding: 12mm 16mm 10mm;
            text-align: center;
        }
        .brand {
            text-align: left;
            height: 18mm;
            margin: 0 0 2mm;
        }
        .logo { max-height: 16mm; max-width: 48mm; }
        h1 {
            font-size: 22pt;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 0 0 3mm;
            color: ' . $primary . ';
            line-height: 1.15;
        }
        .sub { font-size: 10pt; color: #475569; margin: 0 0 6mm; font-style: italic; }
        .lead { font-size: 11pt; color: #334155; margin: 0 0 3mm; }
        .learner {
            font-size: 22pt;
            font-weight: bold;
            color: ' . $primary . ';
            margin: 0 0 4mm;
            line-height: 1.2;
        }
        .mid { font-size: 11pt; color: #334155; margin: 0 0 3mm; }
        .course {
            font-size: 15pt;
            font-weight: bold;
            color: ' . $accent . ';
            margin: 0 0 3mm;
            line-height: 1.25;
        }
        .close { font-size: 10pt; color: #475569; margin: 0 0 8mm; }
        .signatures {
            width: 100%;
            margin: 2mm auto 6mm;
            border-collapse: collapse;
        }
        .signatures td {
            width: 50%;
            vertical-align: bottom;
            text-align: center;
            padding: 0 10mm;
        }
        .sig-art { height: 14mm; margin: 0 auto 1mm; }
        .sig-art svg { display: block; margin: 0 auto; }
        .sig-line {
            border-top: 0.7pt solid #94a3b8;
            width: 58mm;
            margin: 0 auto 2mm;
        }
        .sig-role {
            font-size: 8.5pt;
            font-weight: bold;
            color: ' . $primary . ';
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .sig-hint { font-size: 7.5pt; color: #64748b; margin-top: 1mm; }
        .meta {
            font-size: 8pt;
            color: #64748b;
            margin: 4mm 0 0;
            letter-spacing: 0.02em;
        }
        .legal {
            font-size: 7pt;
            color: #94a3b8;
            margin-top: 3mm;
            line-height: 1.35;
        }
        </style></head><body><div class="page"><div class="diploma">'
            . $bgLayer
            . '<div class="diploma-inner-rule"></div>'
            . '<div class="content">'
            . '<div class="brand">' . $logoHtml . '</div>'
            . '<h1>' . $headline . '</h1>' . $subtitle
            . '<p class="lead">La direction atteste par la présente que</p>'
            . '<p class="learner">' . $learnerName . '</p>'
            . '<p class="mid">a suivi avec succès le parcours de formation intitulé</p>'
            . '<p class="course">' . $courseTitle . '</p>'
            . '<p class="close">et en a satisfait l’ensemble des exigences pédagogiques.</p>'
            . '<table class="signatures"><tr>'
            . '<td><div class="sig-art">' . $sigPed . '</div><div class="sig-line"></div>'
            . '<div class="sig-role">Responsable pédagogique</div>'
            . '<div class="sig-hint">Signature</div></td>'
            . '<td><div class="sig-art">' . $sigDir . '</div><div class="sig-line"></div>'
            . '<div class="sig-role">Direction</div>'
            . '<div class="sig-hint">Signature</div></td>'
            . '</tr></table>'
            . ($metaBits !== '' ? '<p class="meta">' . $metaBits . '</p>' : '')
            . $footer
            . '</div></div></div></body></html>';
    }

    /**
     * @param array<string, mixed> $tpl
     * @param array<string, mixed> $payload
     */
    private function buildMetaBitsHtml(array $tpl, array $payload): string
    {
        $parts = [];
        $num = trim((string) ($payload['certificate_number'] ?? ''));
        $issued = trim((string) ($payload['issued_date_fr'] ?? ''));
        if ($num !== '') {
            $parts[] = 'Référence&nbsp;: ' . htmlspecialchars($num, ENT_QUOTES, 'UTF-8');
        }
        if ($issued !== '') {
            $parts[] = 'Délivrée le ' . htmlspecialchars($issued, ENT_QUOTES, 'UTF-8');
        }
        $expires = trim((string) ($payload['expires_date_fr'] ?? ''));
        if ($tpl['show_valid_until'] && $expires !== '') {
            $parts[] = 'Valide jusqu’au ' . htmlspecialchars($expires, ENT_QUOTES, 'UTF-8');
        }
        if ($tpl['show_final_score']) {
            $parts[] = 'Résultat&nbsp;: ' . htmlspecialchars($this->formatScoreFr($payload['final_score'] ?? 0), ENT_QUOTES, 'UTF-8') . '&nbsp;%';
        }

        return implode(' · ', $parts);
    }

    private function formatScoreFr(mixed $score): string
    {
        return number_format(round((float) $score, 1), 1, ',', '');
    }

    private function uppercaseFr(string $text): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($text, 'UTF-8');
        }

        return strtoupper($text);
    }

    /** SVG signature stylisée (présentation uniquement). */
    private function signatureSvgMarkup(string $variant, string $hex): string
    {
        $rgb = $this->hexToRgb($this->sanitizeHex($hex, '#0f172a'));
        $stroke = sprintf('rgb(%d,%d,%d)', $rgb[0], $rgb[1], $rgb[2]);
        if ($variant === 'direction') {
            $path = 'M6 28 C 18 6, 34 6, 48 24 S 78 40, 96 16 S 124 4, 148 22';
        } else {
            $path = 'M8 26 C 22 10, 38 8, 52 22 S 82 42, 102 18 S 128 8, 146 24';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="36" viewBox="0 0 150 36">'
            . '<path d="' . $path . '" fill="none" stroke="' . $stroke . '" stroke-width="2.1" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>'
            . '</svg>';
    }

    /**
     * @param array<string, mixed> $tpl
     * @param array<string, mixed> $payload
     */
    private function renderWithTcpdf(array $tpl, array $payload): ?string
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            $this->lastFailureReason = 'Impossible de charger la bibliothèque PDF.';

            return null;
        }
        if (TrainingCertificatePdfEngine::resolveCertificateFontFamily() === null) {
            $this->lastFailureReason = 'Polices du document PDF incomplètes sur le serveur.';

            return null;
        }

        try {
            // Tentative avec images du gabarit ; si TCPDF Error() détruit le doc (image invalide / cache),
            // on recommence sans images pour garantir un PDF exploitable.
            $withImages = TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
                fn (): ?string => $this->renderWithTcpdfInner($tpl, $payload, true)
            );
            if ($withImages !== null && $withImages !== '') {
                $this->lastFailureReason = null;

                return $withImages;
            }

            $withoutImages = TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
                fn (): ?string => $this->renderWithTcpdfInner($tpl, $payload, false)
            );
            if ($withoutImages !== null && $withoutImages !== '') {
                $this->lastFailureReason = null;
                error_log('[training_certificate_pdf] TCPDF : PDF généré sans images du gabarit (logo/fond exclus).');

                return $withoutImages;
            }

            return null;
        } catch (\Throwable $e) {
            error_log(
                '[training_certificate_pdf] TCPDF : ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine()
            );
            $this->lastFailureReason = 'Erreur lors de la composition du document PDF.';

            return null;
        }
    }

    /**
     * @param array<string, mixed> $tpl
     * @param array<string, mixed> $payload
     */
    private function renderWithTcpdfInner(array $tpl, array $payload, bool $withImages): ?string
    {
        $font = TrainingCertificatePdfEngine::resolveCertificateFontFamily();
        if ($font === null) {
            return null;
        }

        $logoAbs = $withImages ? $this->tcpdfSafeImagePath($this->assetStorage->absolutePath($tpl['logo_relative_path'])) : null;
        $bgRaw = $withImages ? $this->tcpdfSafeImagePath($this->assetStorage->absolutePath($tpl['background_relative_path'])) : null;
        $bgAbs = $bgRaw !== null ? ($this->diplomaBackgroundWatermarkPath($bgRaw) ?? $bgRaw) : null;

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $pageH = $pdf->getPageHeight();

        // Page hors cadre : neutre. Le fond n’existe que dans le cadre diplôme.
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Rect(0, 0, $pageW, $pageH, 'F');

        $frameX = 10.0;
        $frameY = 10.0;
        $frameW = $pageW - 20.0;
        $frameH = $pageH - 20.0;

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($frameX, $frameY, $frameW, $frameH, 'F');

        if ($bgAbs !== null) {
            try {
                $pdf->Image($bgAbs, $frameX, $frameY, $frameW, $frameH, '', '', '', false, 300, '', false, false, 0, false, false, false);
            } catch (\Throwable $e) {
                $this->lastFailureReason = 'L’image de fond du gabarit n’a pas pu être intégrée. Utilisez un JPEG ou un PNG.';
                error_log('[training_certificate_pdf] TCPDF fond : ' . $e->getMessage());
                unset($pdf);

                return null;
            }
        }

        $accentRgb = $this->hexToRgb($tpl['accent_hex']);
        $primaryRgb = $this->hexToRgb($tpl['primary_hex']);

        $pdf->SetDrawColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $pdf->SetLineWidth(1.1);
        $pdf->Rect($frameX, $frameY, $frameW, $frameH, 'D');
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($frameX + 3.5, $frameY + 3.5, $frameW - 7.0, $frameH - 7.0, 'D');

        $contentX = $frameX + 14.0;
        $contentW = $frameW - 28.0;
        $y = $frameY + 8.0;

        if ($logoAbs !== null) {
            try {
                $pdf->Image($logoAbs, $contentX, $y, 38, 0);
            } catch (\Throwable $e) {
                $this->lastFailureReason = 'Le logo du gabarit n’a pas pu être intégré. Utilisez un JPEG ou un PNG.';
                error_log('[training_certificate_pdf] TCPDF logo : ' . $e->getMessage());
                unset($pdf);

                return null;
            }
        }

        // Titre centré (sous la bande logo, sans chevauchement).
        $y = $frameY + 26.0;
        $pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        $pdf->SetFont($font, 'B', 20);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 9, $this->uppercaseFr($tpl['headline']), 0, 'C', false, 1);
        $y = $pdf->GetY() + 1.5;

        if ($tpl['subtitle'] !== '') {
            $pdf->SetTextColor(71, 85, 105);
            $pdf->SetFont($font, 'I', 10);
            $pdf->SetXY($contentX, $y);
            $pdf->MultiCell($contentW, 5, $tpl['subtitle'], 0, 'C', false, 1);
            $y = $pdf->GetY() + 3;
        } else {
            $y += 2;
        }

        $pdf->SetTextColor(51, 65, 85);
        $pdf->SetFont($font, '', 11);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 6, 'La direction atteste par la présente que', 0, 'C', false, 1);
        $y = $pdf->GetY() + 1.5;

        $pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        $pdf->SetFont($font, 'B', 20);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 9, (string) $payload['learner_name'], 0, 'C', false, 1);
        $y = $pdf->GetY() + 2;

        $pdf->SetTextColor(51, 65, 85);
        $pdf->SetFont($font, '', 11);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 6, 'a suivi avec succès le parcours de formation intitulé', 0, 'C', false, 1);
        $y = $pdf->GetY() + 1.5;

        $pdf->SetFont($font, 'B', 14);
        $pdf->SetTextColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 7, (string) $payload['course_title'], 0, 'C', false, 1);
        $y = $pdf->GetY() + 1.5;

        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetFont($font, '', 10);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 5, 'et en a satisfait l’ensemble des exigences pédagogiques.', 0, 'C', false, 1);

        // Signatures : bas du cadre, deux colonnes.
        $sigY = $frameY + $frameH - 52.0;
        $colW = $contentW / 2.0;
        $this->drawTcpdfSignatureBlock(
            $pdf,
            $contentX,
            $sigY,
            $colW,
            'Responsable pédagogique',
            $primaryRgb,
            'pedagogy',
            $font
        );
        $this->drawTcpdfSignatureBlock(
            $pdf,
            $contentX + $colW,
            $sigY,
            $colW,
            'Direction',
            $accentRgb,
            'direction',
            $font
        );

        $metaParts = [];
        $num = trim((string) ($payload['certificate_number'] ?? ''));
        $issued = trim((string) ($payload['issued_date_fr'] ?? ''));
        if ($num !== '') {
            $metaParts[] = 'Référence : ' . $num;
        }
        if ($issued !== '') {
            $metaParts[] = 'Délivrée le ' . $issued;
        }
        if ($tpl['show_valid_until'] && trim((string) ($payload['expires_date_fr'] ?? '')) !== '') {
            $metaParts[] = 'Valide jusqu’au ' . (string) $payload['expires_date_fr'];
        }
        if ($tpl['show_final_score']) {
            $metaParts[] = 'Résultat : ' . $this->formatScoreFr($payload['final_score'] ?? 0) . ' %';
        }

        $metaY = $frameY + $frameH - 18.0;
        if ($metaParts !== []) {
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetFont($font, '', 8);
            $pdf->SetXY($contentX, $metaY);
            $pdf->MultiCell($contentW, 4, implode(' · ', $metaParts), 0, 'C', false, 1);
            $metaY = $pdf->GetY() + 0.5;
        }

        if ($tpl['footer_legal'] !== '') {
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetFont($font, '', 7);
            $pdf->SetXY($contentX, min($metaY, $frameY + $frameH - 10.0));
            $pdf->MultiCell($contentW, 3.5, $tpl['footer_legal'], 0, 'C', false, 1);
        }

        $out = $pdf->Output('', 'S');
        unset($pdf);

        return is_string($out) && $out !== '' ? $out : null;
    }

    /**
     * @param \TCPDF $pdf
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function drawTcpdfSignatureBlock(
        \TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        string $role,
        array $rgb,
        string $variant,
        string $font
    ): void {
        $lineW = min(58.0, $w - 16.0);
        $lineX = $x + ($w - $lineW) / 2.0;

        $sigPath = $this->signatureSvgFilePath($variant, $rgb);
        if ($sigPath !== null) {
            try {
                $pdf->ImageSVG($sigPath, $lineX + 4.0, $y, $lineW - 8.0, 12.0, '', '', '', 0, false);
            } catch (\Throwable) {
                $this->drawTcpdfFallbackSignatureStroke($pdf, $lineX, $y + 2.0, $lineW, $rgb, $variant);
            }
        } else {
            $this->drawTcpdfFallbackSignatureStroke($pdf, $lineX, $y + 2.0, $lineW, $rgb, $variant);
        }

        $pdf->SetDrawColor(148, 163, 184);
        $pdf->SetLineWidth(0.35);
        $pdf->Line($lineX, $y + 14.0, $lineX + $lineW, $y + 14.0);

        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->SetFont($font, 'B', 8);
        $pdf->SetXY($x, $y + 16.0);
        $pdf->MultiCell($w, 4, $this->uppercaseFr($role), 0, 'C', false, 1);

        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetFont($font, '', 7);
        $pdf->SetXY($x, $y + 20.5);
        $pdf->MultiCell($w, 3.5, 'Signature', 0, 'C', false, 1);
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function drawTcpdfFallbackSignatureStroke(
        \TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        array $rgb,
        string $variant
    ): void {
        $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->SetLineWidth(0.55);
        $h = 10.0;
        if ($variant === 'direction') {
            $pdf->Curve($x + 2, $y + $h * 0.75, $x + $w * 0.2, $y, $x + $w * 0.45, $y + $h, $x + $w * 0.7, $y + $h * 0.35);
            $pdf->Curve($x + $w * 0.7, $y + $h * 0.35, $x + $w * 0.85, $y, $x + $w * 0.95, $y + $h * 0.55, $x + $w - 2, $y + $h * 0.45);
        } else {
            $pdf->Curve($x + 2, $y + $h * 0.7, $x + $w * 0.25, $y + $h * 0.1, $x + $w * 0.5, $y + $h * 0.95, $x + $w * 0.75, $y + $h * 0.3);
            $pdf->Curve($x + $w * 0.75, $y + $h * 0.3, $x + $w * 0.88, $y, $x + $w * 0.95, $y + $h * 0.6, $x + $w - 2, $y + $h * 0.5);
        }
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function signatureSvgFilePath(string $variant, array $rgb): ?string
    {
        if (!TrainingCertificatePdfEngine::isCacheWritable()) {
            return null;
        }
        $stroke = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
        $path = $variant === 'direction'
            ? 'M6 28 C 18 6, 34 6, 48 24 S 78 40, 96 16 S 124 4, 148 22'
            : 'M8 26 C 22 10, 38 8, 52 22 S 82 42, 102 18 S 128 8, 146 24';
        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="36" viewBox="0 0 150 36">'
            . '<path d="' . $path . '" fill="none" stroke="' . $stroke . '" stroke-width="2.1" '
            . 'stroke-linecap="round" stroke-linejoin="round"/></svg>';

        $cacheDir = rtrim((string) K_PATH_CACHE, "/\\");
        $dest = $cacheDir . DIRECTORY_SEPARATOR . 'cert_sig_' . $variant . '_' . substr(hash('sha256', $svg), 0, 16) . '.svg';
        if (!is_file($dest) || filesize($dest) < 1) {
            if (@file_put_contents($dest, $svg) === false) {
                return null;
            }
        }

        return $dest;
    }

    /**
     * Aplatit le fond en filigrane opaque (lisibilité sans setAlpha TCPDF).
     */
    private function diplomaBackgroundWatermarkPath(string $absolutePath): ?string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            return null;
        }
        if (!TrainingCertificatePdfEngine::isCacheWritable()) {
            return null;
        }

        $cacheDir = rtrim((string) K_PATH_CACHE, "/\\");
        $dest = $cacheDir . DIRECTORY_SEPARATOR . 'cert_bg_wm_' . hash('sha256', $absolutePath . '|' . (string) @filemtime($absolutePath) . '|v2') . '.jpg';
        if (is_file($dest) && filesize($dest) > 0) {
            return $dest;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }
        $type = (int) ($info[2] ?? 0);
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_GIF => @imagecreatefromgif($absolutePath),
            default => false,
        };
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $canvas = imagecreatetruecolor($w, $h);
        if ($canvas === false) {
            imagedestroy($src);

            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
        // ~28 % de l’image d’origine → fond visible dans le cadre, texte lisible.
        if (function_exists('imagecopymerge')) {
            imagecopymerge($canvas, $src, 0, 0, 0, 0, $w, $h, 28);
        } else {
            imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        }
        imagedestroy($src);

        $ok = @imagejpeg($canvas, $dest, 90);
        imagedestroy($canvas);
        if (!$ok || !is_file($dest)) {
            return null;
        }

        return $dest;
    }

    /**
     * Chemins image utilisables par TCPDF (JPEG / PNG / GIF). WebP et formats exotiques exclus.
     * Les PNG avec canal alpha sont aplatis (fond blanc) pour éviter ImagePngAlpha /
     * fichiers temporaires __tcpdf_*_imgmask_* (source du crash unlink en production).
     */
    private function tcpdfSafeImagePath(?string $absolutePath): ?string
    {
        if ($absolutePath === null || $absolutePath === '' || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }
        $type = (int) ($info[2] ?? 0);
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (!in_array($type, $allowed, true)) {
            error_log('[training_certificate_pdf] Image gabarit ignorée (format non supporté par TCPDF) : ' . $absolutePath);

            return null;
        }

        if ($type === IMAGETYPE_PNG) {
            $flattened = $this->flattenPngAlphaForTcpdf($absolutePath);
            if ($flattened !== null) {
                return $flattened;
            }
        }

        return $absolutePath;
    }

    /**
     * Si le PNG a de la transparence, produit un JPEG opaque dans le cache TCPDF.
     * Retourne null si pas d’alpha (ou si GD indisponible) → utiliser le fichier d’origine.
     */
    private function flattenPngAlphaForTcpdf(string $absolutePath): ?string
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
            return null;
        }
        if (!$this->pngLikelyHasAlpha($absolutePath)) {
            return null;
        }
        if (!TrainingCertificatePdfEngine::isCacheWritable()) {
            return null;
        }

        $cacheDir = rtrim((string) K_PATH_CACHE, "/\\");
        $dest = $cacheDir . DIRECTORY_SEPARATOR . 'cert_flat_' . hash('sha256', $absolutePath . '|' . (string) filemtime($absolutePath)) . '.jpg';
        if (is_file($dest) && filesize($dest) > 0) {
            return $dest;
        }

        $src = @imagecreatefrompng($absolutePath);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $canvas = imagecreatetruecolor($w, $h);
        if ($canvas === false) {
            imagedestroy($src);

            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        $ok = @imagejpeg($canvas, $dest, 92);
        imagedestroy($canvas);
        if (!$ok || !is_file($dest)) {
            return null;
        }

        return $dest;
    }

    private function pngLikelyHasAlpha(string $absolutePath): bool
    {
        $fh = @fopen($absolutePath, 'rb');
        if ($fh === false) {
            return false;
        }
        $header = fread($fh, 64);
        fclose($fh);
        if (!is_string($header) || strlen($header) < 26) {
            return false;
        }
        // Signature PNG + IHDR : color type à l’octet 25 (0-indexé depuis début fichier = offset 25)
        // 4 = grayscale+alpha, 6 = RGBA
        $colorType = ord($header[25]);
        if ($colorType === 4 || $colorType === 6) {
            return true;
        }
        // tRNS chunk = transparence sur palette / RGB
        $raw = @file_get_contents($absolutePath, false, null, 0, 512000);
        if (!is_string($raw)) {
            return false;
        }

        return str_contains($raw, 'tRNS');
    }

    private function buildImageDataUriHtml(string $absolutePath, string $cssClass): string
    {
        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return '';
        }
        $mime = 'image/png';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $m = finfo_file($fi, $absolutePath);
                finfo_close($fi);
                if (is_string($m) && str_starts_with($m, 'image/')) {
                    $mime = $m;
                }
            }
        }

        return '<img class="' . $cssClass . '" src="data:' . $mime . ';base64,' . base64_encode($data) . '" alt="" />';
    }

    private function buildBackgroundDataUriStyle(string $absolutePath): string
    {
        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return '';
        }
        $mime = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $m = finfo_file($fi, $absolutePath);
                finfo_close($fi);
                if (is_string($m) && str_starts_with($m, 'image/')) {
                    $mime = $m;
                }
            }
        }

        return 'background-image:url(data:' . $mime . ';base64,' . base64_encode($data) . ');background-size:cover;background-position:center;';
    }

    /**
     * @param ?array<string, mixed> $tplRow
     * @return array{
     *   headline: string,
     *   subtitle: string,
     *   footer_legal: string,
     *   primary_hex: string,
     *   accent_hex: string,
     *   logo_relative_path: ?string,
     *   background_relative_path: ?string,
     *   show_final_score: bool,
     *   show_valid_until: bool
     * }
     */
    private function normalizeTemplate(?array $tplRow): array
    {
        $defaults = [
            'headline' => 'Attestation de formation',
            'subtitle' => '',
            'footer_legal' => '',
            'primary_hex' => '#0f172a',
            'accent_hex' => '#059669',
            'logo_relative_path' => null,
            'background_relative_path' => null,
            'show_final_score' => true,
            'show_valid_until' => true,
        ];
        if ($tplRow === null) {
            return $defaults;
        }

        $layout = $this->parseLayoutJson($tplRow['layout_json'] ?? null);

        return [
            'headline' => trim((string) ($tplRow['headline'] ?? $defaults['headline'])) ?: $defaults['headline'],
            'subtitle' => trim((string) ($tplRow['subtitle'] ?? '')),
            'footer_legal' => trim((string) ($tplRow['footer_legal'] ?? '')),
            'primary_hex' => $this->sanitizeHex((string) ($tplRow['primary_hex'] ?? $defaults['primary_hex']), $defaults['primary_hex']),
            'accent_hex' => $this->sanitizeHex((string) ($tplRow['accent_hex'] ?? $defaults['accent_hex']), $defaults['accent_hex']),
            'logo_relative_path' => isset($tplRow['logo_relative_path']) && $tplRow['logo_relative_path'] !== ''
                ? (string) $tplRow['logo_relative_path']
                : null,
            'background_relative_path' => isset($tplRow['background_relative_path']) && $tplRow['background_relative_path'] !== ''
                ? (string) $tplRow['background_relative_path']
                : null,
            'show_final_score' => $layout['show_final_score'],
            'show_valid_until' => $layout['show_valid_until'],
        ];
    }

    /** @return array{show_final_score: bool, show_valid_until: bool} */
    private function parseLayoutJson(mixed $raw): array
    {
        $out = ['show_final_score' => true, 'show_valid_until' => true];
        if ($raw === null || $raw === '') {
            return $out;
        }
        if (is_string($raw)) {
            $j = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $j = $raw;
        } else {
            return $out;
        }
        if (!is_array($j)) {
            return $out;
        }
        if (array_key_exists('show_final_score', $j)) {
            $out['show_final_score'] = (bool) $j['show_final_score'];
        }
        if (array_key_exists('show_valid_until', $j)) {
            $out['show_valid_until'] = (bool) $j['show_valid_until'];
        }

        return $out;
    }

    private function sanitizeHex(string $v, string $fallback): string
    {
        $v = trim($v);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
            return $v;
        }

        return $fallback;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [15, 23, 42];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
