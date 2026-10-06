<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use RuntimeException;

/**
 * Stockage des images de motifs de décoration (ruban / médaille personnalisés).
 */
final class DecorationMotifStorageService
{
    private const MAX_BYTES = 2_000_000;

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /**
     * @param array{tmp_name?: string, name?: string, size?: int, error?: int, type?: string} $file
     * @param string $folder « motifs » (rubans de placard) ou « insignes » (image d’une décoration du référentiel)
     */
    public function storeUpload(int $tenantId, array $file, string $folder = 'motifs'): string
    {
        $folder = $folder === 'insignes' ? 'insignes' : 'motifs';
        if ($tenantId < 1) {
            throw new RuntimeException('Communauté introuvable.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Le transfert de l’image a échoué.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Image invalide.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('L’image dépasse la taille maximale autorisée (2 Mo).');
        }

        $mime = $this->detectMime($tmp, (string) ($file['type'] ?? ''));
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Formats acceptés : PNG, JPEG ou WebP.');
        }
        // Le contenu doit être une vraie image raster (pas un script renommé en .png).
        if (@getimagesize($tmp) === false) {
            throw new RuntimeException('Le fichier n’est pas une image lisible.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
        $relDir = 'uploads/decorations/' . $tenantId . '/' . $folder;
        $absDir = base_path('public/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new RuntimeException('Impossible de préparer le stockage de l’image.');
        }
        $name = ($folder === 'insignes' ? 'insigne_' : 'motif_') . bin2hex(random_bytes(8)) . '.' . $ext;
        $abs = $absDir . '/' . $name;
        if (!@move_uploaded_file($tmp, $abs) && !@copy($tmp, $abs)) {
            throw new RuntimeException('Enregistrement de l’image impossible.');
        }

        return $relDir . '/' . $name;
    }

    public function publicUrl(?string $relativePath): string
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return '';
        }
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_contains($rel, '..')) {
            return '';
        }
        if (str_starts_with($rel, 'uploads/decorations/')) {
            return function_exists('asset_url') ? asset_url($rel) : url($rel);
        }
        // Ancien chemin storage/uploads (repli)
        if (is_file(base_path('storage/' . $rel))) {
            return url('storage/' . $rel);
        }

        return function_exists('asset_url') ? asset_url($rel) : url($rel);
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_contains($rel, '..') || !str_starts_with($rel, 'uploads/decorations/')) {
            return;
        }
        $abs = base_path('public/' . $rel);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private function detectMime(string $tmp, string $declared): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = (string) ($finfo->file($tmp) ?: '');
        if ($detected !== '') {
            return strtolower($detected);
        }

        return strtolower(trim($declared));
    }
}
