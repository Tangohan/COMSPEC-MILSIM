<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingCertificateTemplateRepository;
use App\Repositories\UserRepository;

class TrainingCertificatePdfService
{
    public function __construct(
        private TrainingCertificateRepository $certificateRepository,
        private TrainingCertificateTemplateRepository $templateRepository,
        private UserRepository $userRepository,
        private TrainingCertificateAssetStorageService $assetStorage,
    ) {}

    /**
     * Génère le PDF, l’enregistre et met à jour pdf_path. Retourne le chemin relatif ou null.
     */
    public function generateAndStore(int $certificateId, int $tenantId): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }

        $cert = $this->certificateRepository->findById($certificateId, $tenantId);
        if (!$cert) {
            return null;
        }

        $tplRow = $this->templateRepository->findByTenantId($tenantId);
        $tpl = $this->normalizeTemplate($tplRow);

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

        $courseTitle = (string) ($cert['course_title'] ?? 'Formation');
        $num = (string) ($cert['certificate_number'] ?? '');
        $issued = !empty($cert['issued_at']) ? date('d/m/Y', strtotime((string) $cert['issued_at'])) : '';
        $expires = !empty($cert['expires_at']) ? date('d/m/Y', strtotime((string) $cert['expires_at'])) : '';
        $score = round((float) ($cert['final_score'] ?? 0), 1);

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
            $data = @file_get_contents($logoAbs);
            if ($data !== false) {
                $mime = 'image/png';
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi !== false) {
                        $m = finfo_file($fi, $logoAbs);
                        finfo_close($fi);
                        if (is_string($m) && str_starts_with($m, 'image/')) {
                            $mime = $m;
                        }
                    }
                }
                $logoHtml = '<img class="logo" src="data:' . $mime . ';base64,' . base64_encode($data) . '" alt="" />';
            }
        }

        $bgStyle = '';
        if ($bgAbs !== null) {
            $data = @file_get_contents($bgAbs);
            if ($data !== false) {
                $mime = 'image/jpeg';
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi !== false) {
                        $m = finfo_file($fi, $bgAbs);
                        finfo_close($fi);
                        if (is_string($m) && str_starts_with($m, 'image/')) {
                            $mime = $m;
                        }
                    }
                }
                $bgStyle = 'background-image:url(data:' . $mime . ';base64,' . base64_encode($data) . ');background-size:cover;background-position:center;';
            }
        }

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>
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
            . '<p class="learner">Décernée à <strong>' . htmlspecialchars($learnerName, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p class="course">' . htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p class="meta">Référence : ' . htmlspecialchars($num, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p class="meta">Délivrée le ' . htmlspecialchars($issued, ENT_QUOTES, 'UTF-8') . '</p>'
            . ($expires !== '' ? '<p class="meta">Valide jusqu’au ' . htmlspecialchars($expires, ENT_QUOTES, 'UTF-8') . '</p>' : '')
            . '<p class="meta">Score final : ' . htmlspecialchars((string) $score, ENT_QUOTES, 'UTF-8') . ' %</p>'
            . $footer
            . '</div></div></body></html>';

        $root = realpath(base_path()) ?: base_path();
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'chroot' => $root]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $binary = $dompdf->output();
        if ($binary === false || $binary === '') {
            return null;
        }

        $relDir = 'storage/app/training-certificates-generated/' . $tenantId;
        $absDir = base_path($relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return null;
        }
        $relPath = $relDir . '/' . $certificateId . '.pdf';
        $absPath = base_path($relPath);
        if (file_put_contents($absPath, $binary) === false) {
            return null;
        }
        $this->certificateRepository->updatePdfPath($certificateId, $relPath);

        return $relPath;
    }

    /** @param ?array<string, mixed> $tplRow */
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
        ];
        if ($tplRow === null) {
            return $defaults;
        }

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
        ];
    }

    private function sanitizeHex(string $v, string $fallback): string
    {
        $v = trim($v);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
            return $v;
        }

        return $fallback;
    }
}
