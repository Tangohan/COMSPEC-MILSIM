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
        if (TrainingCertificatePdfEngine::prefersTcpdf()) {
            $b = $this->renderWithTcpdf($tpl, $payload);
            if ($b !== null && $b !== '') {
                return $b;
            }
        }
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
                    return $binary;
                }
                error_log('[training_certificate_pdf] Dompdf : sortie vide (render sans exception).');
                $this->lastFailureReason = 'Le moteur de secours n’a produit aucun document.';
            } catch (\Throwable $e) {
                error_log(
                    '[training_certificate_pdf] Dompdf : ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine()
                );
                $this->lastFailureReason = 'Échec du moteur de secours PDF.';
            }

            return null;
        }

        if ($this->lastFailureReason === null) {
            $this->lastFailureReason = 'TCPDF n’a pas pu produire le document et aucun moteur de secours n’est installé.';
        }
        error_log('[training_certificate_pdf] Aucun moteur PDF utilisable après échec TCPDF (Dompdf absent).');

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
        $subtitle = $tpl['subtitle'] !== '' ? '<p class="sub">' . htmlspecialchars($tpl['subtitle'], ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $footer = $tpl['footer_legal'] !== ''
            ? '<div class="footer">' . nl2br(htmlspecialchars($tpl['footer_legal'], ENT_QUOTES, 'UTF-8')) . '</div>'
            : '';

        $logoHtml = '';
        if ($logoAbs !== null) {
            $logoHtml = $this->buildImageDataUriHtml($logoAbs, 'logo');
        }

        $bgStyle = '';
        if ($bgAbs !== null) {
            $bgStyle = $this->buildBackgroundDataUriStyle($bgAbs);
        }

        $learnerName = htmlspecialchars((string) $payload['learner_name'], ENT_QUOTES, 'UTF-8');
        $courseTitle = htmlspecialchars((string) $payload['course_title'], ENT_QUOTES, 'UTF-8');
        $num = htmlspecialchars((string) $payload['certificate_number'], ENT_QUOTES, 'UTF-8');
        $issued = htmlspecialchars((string) $payload['issued_date_fr'], ENT_QUOTES, 'UTF-8');
        $expires = (string) $payload['expires_date_fr'];
        $scoreLine = '';
        if ($tpl['show_final_score']) {
            $scoreLine = '<p class="meta">Score final : ' . htmlspecialchars((string) $payload['final_score'], ENT_QUOTES, 'UTF-8') . ' %</p>';
        }
        $expiresLine = '';
        if ($tpl['show_valid_until'] && $expires !== '') {
            $expiresLine = '<p class="meta">Valide jusqu’au ' . htmlspecialchars($expires, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>
        @page { margin: 48px; }
        body { font-family: DejaVu Sans, sans-serif; color:' . $primary . '; margin:0; }
        .wrap { min-height: 100%; padding: 36px; border: 3px solid ' . $accent . '; box-sizing: border-box; ' . $bgStyle . ' }
        .inner { background: rgba(255,255,255,0.92); padding: 32px; border-radius: 8px; }
        .logo { max-height: 64px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 8px 0; color: ' . $primary . '; }
        .sub { font-size: 13px; color: #475569; margin: 0 0 24px 0; }
        .course { font-size: 18px; font-weight: bold; color: ' . $accent . '; margin: 16px 0; }
        .meta { font-size: 12px; color: #64748b; margin: 8px 0; }
        .learner { font-size: 15px; margin: 20px 0; }
        .footer { font-size: 9px; color: #94a3b8; margin-top: 32px; }
        </style></head><body><div class="wrap"><div class="inner">'
            . $logoHtml
            . '<h1>' . $headline . '</h1>' . $subtitle
            . '<p class="learner">Décernée à <strong>' . $learnerName . '</strong></p>'
            . '<p class="course">' . $courseTitle . '</p>'
            . '<p class="meta">Référence : ' . $num . '</p>'
            . '<p class="meta">Délivrée le ' . $issued . '</p>'
            . $expiresLine
            . $scoreLine
            . $footer
            . '</div></div></body></html>';
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
        $bgAbs = $withImages ? $this->tcpdfSafeImagePath($this->assetStorage->absolutePath($tpl['background_relative_path'])) : null;

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(16, 16, 16);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $pageH = $pdf->getPageHeight();

        if ($bgAbs !== null) {
            try {
                $pdf->Image($bgAbs, 0, 0, $pageW, $pageH, '', '', '', false, 300, '', false, false, 0, false, false, false);
            } catch (\Throwable $e) {
                // Error() détruit le document : il faut abandonner cette tentative.
                $this->lastFailureReason = 'L’image de fond du gabarit n’a pas pu être intégrée. Utilisez un JPEG ou un PNG.';
                error_log('[training_certificate_pdf] TCPDF fond : ' . $e->getMessage());

                return null;
            }
        }

        // Fond blanc opaque (évite setAlpha, plus fragile selon versions / caches).
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(14, 14, $pageW - 28, $pageH - 28, 'F');

        $accentRgb = $this->hexToRgb($tpl['accent_hex']);
        $pdf->SetDrawColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $pdf->SetLineWidth(1.2);
        $pdf->Rect(12, 12, $pageW - 24, $pageH - 24, 'D');

        $contentX = 22;
        $contentW = $pageW - 44;
        $y = 20;

        if ($logoAbs !== null) {
            try {
                $pdf->Image($logoAbs, $contentX, $y, 45, 0);
                $y += 20;
            } catch (\Throwable $e) {
                $this->lastFailureReason = 'Le logo du gabarit n’a pas pu être intégré. Utilisez un JPEG ou un PNG.';
                error_log('[training_certificate_pdf] TCPDF logo : ' . $e->getMessage());

                return null;
            }
        }

        $primaryRgb = $this->hexToRgb($tpl['primary_hex']);
        $pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        $pdf->SetFont($font, 'B', 20);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 10, $tpl['headline'], 0, 'L', false, 1);
        $y = $pdf->GetY() + 2;

        if ($tpl['subtitle'] !== '') {
            $pdf->SetTextColor(71, 85, 105);
            $pdf->SetFont($font, '', 11);
            $pdf->SetXY($contentX, $y);
            $pdf->MultiCell($contentW, 6, $tpl['subtitle'], 0, 'L', false, 1);
            $y = $pdf->GetY() + 6;
        }

        $pdf->SetTextColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        $pdf->SetFont($font, '', 12);
        $pdf->SetXY($contentX, $y);
        $pdf->Write(8, 'Décernée à ');
        $pdf->SetFont($font, 'B', 12);
        $pdf->Write(8, (string) $payload['learner_name']);
        $y = $pdf->GetY() + 12;

        $pdf->SetFont($font, 'B', 15);
        $pdf->SetTextColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $pdf->SetXY($contentX, $y);
        $pdf->MultiCell($contentW, 9, (string) $payload['course_title'], 0, 'L', false, 1);
        $y = $pdf->GetY() + 6;

        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetFont($font, '', 10);
        $lines = [
            'Référence : ' . (string) $payload['certificate_number'],
            'Délivrée le ' . (string) $payload['issued_date_fr'],
        ];
        if ($tpl['show_valid_until'] && trim((string) $payload['expires_date_fr']) !== '') {
            $lines[] = 'Valide jusqu’au ' . (string) $payload['expires_date_fr'];
        }
        if ($tpl['show_final_score']) {
            $lines[] = 'Score final : ' . (string) $payload['final_score'] . ' %';
        }
        foreach ($lines as $line) {
            $pdf->SetXY($contentX, $y);
            $pdf->MultiCell($contentW, 6, $line, 0, 'L', false, 1);
            $y = $pdf->GetY() + 1;
        }

        if ($tpl['footer_legal'] !== '') {
            $pdf->SetTextColor(148, 163, 184);
            $pdf->SetFont($font, '', 8);
            $footerY = $pageH - 28;
            $pdf->SetXY($contentX, $footerY);
            $pdf->MultiCell($contentW, 4, $tpl['footer_legal'], 0, 'L', false, 1);
        }

        $out = $pdf->Output('', 'S');

        return is_string($out) && $out !== '' ? $out : null;
    }

    /**
     * Chemins image utilisables par TCPDF (JPEG / PNG / GIF). WebP et formats exotiques exclus.
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

        return $absolutePath;
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
