<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingCertificateTemplateRepository;
use App\Repositories\UserRepository;
use App\Support\Pdf\AthenaCertificateLayout;
use App\Support\Pdf\AthenaPdfTheme;
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
        $payload['tenant_id'] = $tenantId;

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

        return $this->renderPdfBinary($tpl, ['tenant_id' => $tenantId] + $this->previewPayload());
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
                $binary = AthenaCertificateLayout::dompdfRender($this->buildDompdfHtml($tpl, $payload));
                if ($binary !== null && $binary !== '') {
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
     * Données du certificat au gabarit commun ({@see AthenaCertificateLayout}) : personnalisations
     * du staff (couleurs, logo, fond, intitulés, mentions) appliquées à la mise en page A4 portrait.
     *
     * @param array<string, mixed> $tpl
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function certificateData(array $tpl, array $payload, ?string $logoAbs, ?string $bgAbs): array
    {
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $meta = AthenaPdfTheme::meta(['tenant_id' => $tenantId, 'community' => (string) ($payload['community'] ?? '')]);
        $community = $meta['community'];
        if ($logoAbs === null && $meta['logo'] !== null) {
            $logoAbs = $meta['logo'];
        }
        $headline = (string) $tpl['headline'];
        $feminine = (bool) preg_match('/^\s*attestation/iu', $headline);
        $number = trim((string) ($payload['certificate_number'] ?? ''));
        $issued = trim((string) ($payload['issued_date_fr'] ?? ''));
        $expires = trim((string) ($payload['expires_date_fr'] ?? ''));

        $fields = [['Validée le', $issued !== '' ? $issued : '—']];
        if ($tpl['show_final_score']) {
            $fields[] = ['Résultat', $this->formatScoreFr($payload['final_score'] ?? 0) . ' %'];
        }
        if ($tpl['show_valid_until']) {
            $fields[] = ['Validité', $expires !== '' ? 'Jusqu’au ' . $expires : 'Permanente'];
        }
        if (count($fields) < 3 && $number !== '') {
            $fields[] = ['Référence', $number, true];
        }
        $name = $community !== '' ? $community : 'Athena';

        return [
            'variant' => 'classique',
            'accent' => $tpl['accent_hex'],
            'ink' => $tpl['primary_hex'],
            'community' => $community,
            'logo' => $logoAbs ?? '',
            'background' => $bgAbs ?? '',
            'reference' => $number,
            'kicker' => 'Centre d’instruction ' . $name,
            'title' => $headline,
            'subtitle' => $tpl['subtitle'],
            'awarded_intro' => $feminine ? 'est décernée à' : 'est décerné à',
            'holder' => (string) $payload['learner_name'],
            'reason' => 'pour avoir suivi et validé avec succès la formation',
            'subject' => (string) $payload['course_title'],
            'description' => 'et en avoir satisfait l’ensemble des exigences pédagogiques.',
            'fields' => $fields,
            'signatures' => [
                ['name' => 'Responsable pédagogique', 'role' => 'Signature', 'art_svg' => $this->signatureSvgMarkup('pedagogy', $tpl['primary_hex'])],
                ['name' => 'Direction', 'role' => $community !== '' ? $community : 'Signature', 'art_svg' => $this->signatureSvgMarkup('direction', $tpl['accent_hex'])],
            ],
            'seal_letter' => mb_substr($name, 0, 1, 'UTF-8'),
            'seal_label' => AthenaCertificateLayout::shortName($name),
            'verify' => AthenaCertificateLayout::verificationLine($number, $community),
            'legal' => $tpl['footer_legal'],
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

        return AthenaCertificateLayout::dompdfHtml($this->certificateData($tpl, $payload, $logoAbs, $bgAbs));
    }

    private function formatScoreFr(mixed $score): string
    {
        return number_format(round((float) $score, 1), 1, ',', '');
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
        if (TrainingCertificatePdfEngine::resolveCertificateFontFamily() === null) {
            return null;
        }

        $logoAbs = $withImages ? $this->tcpdfSafeImagePath($this->assetStorage->absolutePath($tpl['logo_relative_path'])) : null;
        $bgRaw = $withImages ? $this->tcpdfSafeImagePath($this->assetStorage->absolutePath($tpl['background_relative_path'])) : null;
        $bgAbs = $bgRaw !== null ? ($this->diplomaBackgroundWatermarkPath($bgRaw) ?? $bgRaw) : null;

        $data = $this->certificateData($tpl, $payload, $logoAbs, $bgAbs);
        if (!$withImages) {
            $data['logo'] = '';
            $data['background'] = '';
        } elseif ($logoAbs === null && $data['logo'] !== '') {
            // Logo de la communauté (repli) : rendu sûr pour TCPDF ou ignoré.
            $data['logo'] = $this->tcpdfSafeImagePath((string) $data['logo']) ?? '';
        }

        try {
            return AthenaCertificateLayout::tcpdf($data);
        } catch (\Throwable $e) {
            if ($withImages) {
                $this->lastFailureReason = 'Le logo ou l’image de fond du gabarit n’a pas pu être intégré. Utilisez un JPEG ou un PNG.';
                error_log('[training_certificate_pdf] TCPDF images : ' . $e->getMessage());

                return null;
            }
            throw $e;
        }
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
