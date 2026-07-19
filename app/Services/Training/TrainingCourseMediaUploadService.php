<?php

declare(strict_types=1);

namespace App\Services\Training;

/**
 * Médias de couverture / présentation d'une formation : fichiers joints depuis le poste,
 * rangés sous public/uploads pour être servis directement par le web (même logique que
 * les autres visuels du produit — avatar, bannières communauté, etc.).
 */
class TrainingCourseMediaUploadService
{
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    private const MAX_AUDIO_BYTES = 12 * 1024 * 1024;

    /** @var array<string, list<string>> extension => mime autorisés */
    private const ALLOWED_IMAGES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_AUDIO = [
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a'],
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
        return $this->storeMedia(
            $tenantId,
            $file,
            $prefix,
            self::ALLOWED_IMAGES,
            self::MAX_IMAGE_BYTES,
            'L’image n’a pas pu être envoyée. Réessayez ou choisissez un autre fichier.',
            'Le fichier reçu est vide. Choisissez une image valide.',
            'L’image est trop volumineuse (maximum 4 Mo).',
            'Format non pris en charge. Utilisez une image JPEG, PNG, WEBP ou GIF.',
            'Le fichier envoyé n’est pas reconnu comme une image valide.',
            'Impossible de préparer l’espace de stockage des images.',
            'Impossible d’enregistrer l’image sur le serveur (droits d’écriture ou quota).'
        );
    }

    /**
     * Consignes audio de présentation : MP3 / OGG / WAV / M4A.
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     * @return string|null Chemin relatif public, ou null si aucun fichier.
     */
    public function storeAudioUpload(int $tenantId, ?array $file, string $prefix = 'audio'): ?string
    {
        return $this->storeMedia(
            $tenantId,
            $file,
            $prefix,
            self::ALLOWED_AUDIO,
            self::MAX_AUDIO_BYTES,
            'Le fichier audio n’a pas pu être envoyé. Réessayez ou choisissez un autre fichier.',
            'Le fichier reçu est vide. Choisissez un fichier audio valide.',
            'Le fichier audio est trop volumineux (maximum 12 Mo).',
            'Format non pris en charge. Utilisez un fichier MP3, OGG, WAV ou M4A.',
            'Le fichier envoyé n’est pas reconnu comme un audio valide.',
            'Impossible de préparer l’espace de stockage des fichiers audio.',
            'Impossible d’enregistrer le fichier audio sur le serveur (droits d’écriture ou quota).'
        );
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     * @param array<string, list<string>> $allowed
     */
    private function storeMedia(
        int $tenantId,
        ?array $file,
        string $prefix,
        array $allowed,
        int $maxBytes,
        string $errUpload,
        string $errEmpty,
        string $errSize,
        string $errExt,
        string $errMime,
        string $errMkdir,
        string $errMove
    ): ?string {
        if ($file === null || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException($errUpload);
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \InvalidArgumentException($errUpload);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            throw new \InvalidArgumentException($errEmpty);
        }
        if ($size > $maxBytes) {
            throw new \InvalidArgumentException($errSize);
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            throw new \InvalidArgumentException($errExt);
        }
        $mime = $this->detectMime($tmpName);
        if ($mime === null || !in_array($mime, $allowed[$ext], true)) {
            throw new \InvalidArgumentException($errMime);
        }

        $relDir = $this->relativeDirForTenant($tenantId);
        $dirFs = base_path('public/' . $relDir);
        if (!is_dir($dirFs) && !@mkdir($dirFs, 0775, true) && !is_dir($dirFs)) {
            throw new \RuntimeException($errMkdir);
        }

        $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '-', $prefix) ?: 'media';
        $name = $safePrefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destFs = $dirFs . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmpName, $destFs)) {
            throw new \RuntimeException($errMove);
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
