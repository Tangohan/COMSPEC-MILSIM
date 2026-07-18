<?php

declare(strict_types=1);

namespace App\Services\Training;

/**
 * Miniature (carte) / bannière (modale) d'une formation : fichiers joints depuis le poste,
 * rangés sous public/uploads pour être servis directement par le web (même logique que
 * les autres visuels du produit — avatar, bannières communauté, etc.).
 */
class TrainingCourseMediaUploadService
{
    private const MAX_BYTES = 4 * 1024 * 1024;

    /** @var array<string, list<string>> extension => mime autorisés */
    private const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];

    public function relativeDirForTenant(int $tenantId): string
    {
        return 'uploads/training-course-media/' . $tenantId;
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file $_FILES[*]
     * @return string|null Chemin relatif (ex. uploads/training-course-media/3/thumbnail-xxxx.webp), ou null si aucun fichier envoyé.
     * @throws \InvalidArgumentException si un fichier a été envoyé mais n'est pas valide.
     */
    public function storeUpload(int $tenantId, ?array $file, string $prefix): ?string
    {
        if ($file === null || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('L’image n’a pas pu être envoyée. Réessayez ou choisissez un autre fichier.');
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \InvalidArgumentException('L’image n’a pas pu être envoyée. Réessayez ou choisissez un autre fichier.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            throw new \InvalidArgumentException('Le fichier reçu est vide. Choisissez une image valide.');
        }
        if ($size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('L’image est trop volumineuse (maximum 4 Mo).');
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw new \InvalidArgumentException('Format non pris en charge. Utilisez une image JPEG, PNG, WEBP ou GIF.');
        }
        $mime = $this->detectMime($tmpName);
        if ($mime === null || !in_array($mime, self::ALLOWED[$ext], true)) {
            throw new \InvalidArgumentException('Le fichier envoyé n’est pas reconnu comme une image valide.');
        }

        $relDir = $this->relativeDirForTenant($tenantId);
        $dirFs = base_path('public/' . $relDir);
        if (!is_dir($dirFs) && !@mkdir($dirFs, 0775, true) && !is_dir($dirFs)) {
            throw new \RuntimeException('Impossible de préparer l’espace de stockage des images.');
        }

        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destFs = $dirFs . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmpName, $destFs)) {
            throw new \RuntimeException('Impossible d’enregistrer l’image sur le serveur (droits d’écriture ou quota).');
        }

        return $relDir . '/' . $name;
    }

    /**
     * Supprime un fichier géré par ce service (n'agit jamais sur une URL externe ou un chemin hors de son dossier).
     */
    public function deleteManagedRelative(?string $relative): void
    {
        $rel = trim((string) $relative);
        if ($rel === '' || preg_match('#^https?://#i', $rel) === 1) {
            return;
        }
        $rel = str_replace(['\\', '..'], ['/', ''], $rel);
        if (!str_starts_with($rel, 'uploads/training-course-media/')) {
            return;
        }
        $abs = base_path('public/' . $rel);
        if (is_file($abs)) {
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
