<?php

declare(strict_types=1);

namespace App\Services\Training;

/**
 * Fichiers logo / fond des attestations, rangés hors web public, par communauté.
 */
class TrainingCertificateAssetStorageService
{
    private const MAX_BYTES = 4194304;

    /** @var array<string, list<string>> extension => mime */
    private const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function relativeDirForTenant(int $tenantId): string
    {
        return 'storage/app/training-certificate-assets/' . $tenantId;
    }

    public function absolutePath(?string $relative): ?string
    {
        if ($relative === null || trim($relative) === '') {
            return null;
        }
        $rel = str_replace(['..', "\0"], '', $relative);
        $full = base_path($rel);

        return is_file($full) ? $full : null;
    }

    /**
     * @param array{name: string, tmp_name: string, error: int, size: int}|null $file $_FILES[*]
     * @return string chemin relatif projet (ex. storage/app/.../logo-xxx.png)
     */
    public function storeUpload(int $tenantId, ?array $file, string $prefix): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new \InvalidArgumentException('Le fichier n’a pas pu être reçu. Réessayez ou choisissez un autre fichier.');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Le fichier est trop volumineux (maximum 4 Mo).');
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw new \InvalidArgumentException('Format non pris en charge. Utilisez une image JPEG, PNG ou WebP.');
        }
        $mime = $this->detectMime($file['tmp_name']);
        if ($mime === null || !in_array($mime, self::ALLOWED[$ext], true)) {
            throw new \InvalidArgumentException('Le type du fichier ne correspond pas à une image autorisée.');
        }

        $dir = base_path($this->relativeDirForTenant($tenantId));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de préparer l’espace de stockage.');
        }

        $safe = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dir . DIRECTORY_SEPARATOR . $safe;
        if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
            throw new \RuntimeException('Impossible d’enregistrer le fichier.');
        }

        return $this->relativeDirForTenant($tenantId) . '/' . $safe;
    }

    public function deleteRelative(?string $relative): void
    {
        $abs = $this->absolutePath($relative);
        if ($abs !== null && is_file($abs)) {
            @unlink($abs);
        }
    }

    private function detectMime(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = finfo_file($f, $path);
                finfo_close($f);
                if (is_string($m) && $m !== '') {
                    return $m;
                }
            }
        }

        return null;
    }
}
