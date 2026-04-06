<?php

declare(strict_types=1);

namespace App\Services\Training;

/**
 * Fichiers joints aux ressources de leçon (hors bibliothèque documentaire), stockés hors web public.
 */
class TrainingLessonResourceStorageService
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'zip' => ['application/zip'],
        'mp4' => ['video/mp4'],
        'mp3' => ['audio/mpeg'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    public function relativeDirForTenant(int $tenantId): string
    {
        return 'storage/uploads/training-lesson-resources/' . $tenantId;
    }

    /**
     * @param array{name?: string, tmp_name?: string, error?: int, size?: int}|null $file
     * @return array{path: string, mime: string, size: int}
     */
    public function storeUpload(int $tenantId, ?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \InvalidArgumentException('Aucun fichier n’a été envoyé.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new \InvalidArgumentException('Le fichier n’a pas pu être reçu. Réessayez ou choisissez un autre fichier.');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Le fichier est trop volumineux (maximum 15 Mo).');
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '' || !isset(self::ALLOWED[$ext])) {
            throw new \InvalidArgumentException('Format non pris en charge pour une ressource de leçon (PDF, images, ZIP, audio MP3, vidéo MP4, Word…).');
        }
        $mime = $this->detectMime($file['tmp_name']);
        if ($mime === null || !in_array($mime, self::ALLOWED[$ext], true)) {
            throw new \InvalidArgumentException('Le type du fichier ne correspond pas à une pièce autorisée.');
        }

        $dir = base_path($this->relativeDirForTenant($tenantId));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de préparer l’espace de stockage.');
        }

        $safe = 'res-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dir . DIRECTORY_SEPARATOR . $safe;
        if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
            throw new \RuntimeException('Impossible d’enregistrer le fichier.');
        }

        $rel = $this->relativeDirForTenant($tenantId) . '/' . $safe;
        $size = (int) filesize($destAbs);

        return ['path' => $rel, 'mime' => $mime, 'size' => $size];
    }

    private function detectMime(string $tmp): ?string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $m = finfo_file($f, $tmp) ?: null;
                finfo_close($f);

                return $m !== false && $m !== null ? $m : null;
            }
        }

        return null;
    }
}
