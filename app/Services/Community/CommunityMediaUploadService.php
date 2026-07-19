<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Support\CommunityMediaDetails;

/**
 * Stockage des médias communauté sous public/uploads/community-media/{tenantId}/.
 */
final class CommunityMediaUploadService
{
    public const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
    public const MAX_SHORT_VIDEO_BYTES = 80 * 1024 * 1024;
    /** Limite indicative côté formulaire (pas de lecture durée serveur sans FFmpeg). */
    public const MAX_SHORT_VIDEO_SECONDS_HINT = 90;

    /**
     * @return array{path:?string,mime:?string,size:?int,width:?int,height:?int,error:?string}
     */
    public function storeImage(array $file, int $tenantId): array
    {
        $check = $this->validateUploadEnvelope($file, self::MAX_IMAGE_BYTES, 'Image trop volumineuse (maximum 8 Mo).');
        if ($check['error'] !== null) {
            return $this->emptyResult($check['error']);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->emptyResult('Formats image acceptés : JPG, PNG ou WebP.');
        }
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $stored = $this->moveToTenantDir($tmp, $tenantId, 'img', $ext);
        if ($stored['error'] !== null) {
            return $this->emptyResult($stored['error']);
        }
        $wh = $this->imageSize($stored['abs']);

        return [
            'path' => $stored['rel'],
            'mime' => $mime,
            'size' => (int) ($file['size'] ?? 0),
            'width' => $wh[0],
            'height' => $wh[1],
            'error' => null,
        ];
    }

    /**
     * Vidéo courte : upload local MP4/WebM.
     *
     * @return array{path:?string,mime:?string,size:?int,width:?int,height:?int,error:?string}
     */
    public function storeShortVideo(array $file, int $tenantId): array
    {
        $check = $this->validateUploadEnvelope(
            $file,
            self::MAX_SHORT_VIDEO_BYTES,
            'Vidéo trop volumineuse (maximum 80 Mo pour une vidéo courte).'
        );
        if ($check['error'] !== null) {
            return $this->emptyResult($check['error']);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
        if (!in_array($mime, $allowed, true)) {
            return $this->emptyResult('Formats vidéo courte acceptés : MP4 ou WebM.');
        }
        $ext = match ($mime) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };
        $stored = $this->moveToTenantDir($tmp, $tenantId, 'short', $ext);
        if ($stored['error'] !== null) {
            return $this->emptyResult($stored['error']);
        }

        return [
            'path' => $stored['rel'],
            'mime' => $mime,
            'size' => (int) ($file['size'] ?? 0),
            'width' => null,
            'height' => null,
            'error' => null,
        ];
    }

    /**
     * Vidéo longue : URL externe (YouTube, Vimeo ou lien HTTPS).
     *
     * @return array{url:?string,error:?string}
     */
    public function normalizeLongVideoUrl(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['url' => null, 'error' => 'Indiquez le lien de la vidéo longue.'];
        }
        if (!preg_match('#^https://#i', $raw)) {
            return ['url' => null, 'error' => 'Le lien de la vidéo longue doit commencer par https://.'];
        }
        if (strlen($raw) > 1024) {
            return ['url' => null, 'error' => 'Lien trop long.'];
        }
        $embed = CommunityMediaDetails::embedUrl($raw);
        if ($embed === null) {
            return ['url' => null, 'error' => 'Lien vidéo non reconnu. Utilisez YouTube, Vimeo ou un lien HTTPS direct.'];
        }

        return ['url' => $raw, 'error' => null];
    }

    public function deleteStoredFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, 'uploads/community-media/')) {
            return;
        }
        $abs = base_path('public/' . ltrim($norm, '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    /**
     * @return array{error:?string}
     */
    private function validateUploadEnvelope(array $file, int $maxBytes, string $tooLargeMessage): array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'Aucun fichier sélectionné.'];
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['error' => 'Le téléversement a échoué. Réessayez.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['error' => 'Fichier invalide.'];
        }
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            return ['error' => $tooLargeMessage];
        }

        return ['error' => null];
    }

    /**
     * @return array{rel:?string,abs:?string,error:?string}
     */
    private function moveToTenantDir(string $tmp, int $tenantId, string $prefix, string $ext): array
    {
        $dirRel = 'uploads/community-media/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['rel' => null, 'abs' => null, 'error' => 'Stockage des médias indisponible pour le moment.'];
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . '/' . $name;
        $destRel = $dirRel . '/' . $name;
        if (!@move_uploaded_file($tmp, $destAbs) && !@copy($tmp, $destAbs)) {
            return ['rel' => null, 'abs' => null, 'error' => 'Impossible d’enregistrer le fichier.'];
        }

        return ['rel' => $destRel, 'abs' => $destAbs, 'error' => null];
    }

    /** @return array{0:?int,1:?int} */
    private function imageSize(string $absPath): array
    {
        if (!is_file($absPath)) {
            return [null, null];
        }
        $info = @getimagesize($absPath);
        if (!is_array($info)) {
            return [null, null];
        }

        return [(int) ($info[0] ?? 0) ?: null, (int) ($info[1] ?? 0) ?: null];
    }

    /**
     * @return array{path:?string,mime:?string,size:?int,width:?int,height:?int,error:?string}
     */
    private function emptyResult(string $error): array
    {
        return [
            'path' => null,
            'mime' => null,
            'size' => null,
            'width' => null,
            'height' => null,
            'error' => $error,
        ];
    }
}
