<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use RuntimeException;

/**
 * Stockage des insignes (badges) de qualifications.
 */
final class QualificationBadgeStorageService
{
    private const MAX_BYTES = 2_000_000;

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/png',
        'image/webp',
        'image/svg+xml',
        'image/svg',
    ];

    /**
     * @param array{tmp_name?: string, name?: string, size?: int, error?: int, type?: string} $file
     */
    public function storeUpload(int $tenantId, int $qualificationId, array $file, string $scope = 'definition'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Le transfert de l’insigne a échoué.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Fichier d’insigne invalide.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('L’insigne dépasse la taille maximale autorisée (2 Mo).');
        }

        $mime = $this->detectMime($tmp, (string) ($file['type'] ?? ''));
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Formats acceptés : PNG, WebP ou SVG.');
        }

        $ext = match ($mime) {
            'image/webp' => 'webp',
            'image/svg+xml', 'image/svg' => 'svg',
            default => 'png',
        };
        $relDir = 'qualifications/' . $tenantId . '/' . $qualificationId;
        $absDir = base_path('storage/uploads/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new RuntimeException('Impossible de préparer le stockage de l’insigne.');
        }
        $name = $scope . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $abs = $absDir . '/' . $name;
        if (!@move_uploaded_file($tmp, $abs) && !@copy($tmp, $abs)) {
            throw new RuntimeException('Enregistrement de l’insigne impossible.');
        }

        return $relDir . '/' . $name;
    }

    public function publicUrl(?string $relativePath): string
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return $this->fallbackUrl();
        }
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');

        return url('storage/uploads/' . $rel);
    }

    public function absolutePath(?string $relativePath): ?string
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return $this->fallbackAbsolutePath();
        }
        $abs = base_path('storage/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/'));

        return is_file($abs) ? $abs : $this->fallbackAbsolutePath();
    }

    public function fallbackUrl(): string
    {
        return url('assets/img/qualification-badge-default.svg');
    }

    public function fallbackAbsolutePath(): ?string
    {
        $path = base_path('public/assets/img/qualification-badge-default.svg');

        return is_file($path) ? $path : null;
    }

    private function detectMime(string $tmp, string $declared): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmp) ?: $declared;
        $detected = strtolower((string) $detected);
        if ($detected === 'text/plain' || $detected === 'text/xml' || $detected === 'application/xml') {
            $head = (string) @file_get_contents($tmp, false, null, 0, 256);
            if (str_contains($head, '<svg')) {
                return 'image/svg+xml';
            }
        }

        return $detected;
    }
}
