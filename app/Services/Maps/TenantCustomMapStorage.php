<?php

declare(strict_types=1);

namespace App\Services\Maps;

/**
 * Stockage des images de fond pour cartes custom (Overwatch / TACMAP).
 */
final class TenantCustomMapStorage
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_EDGE = 8192;

    /** @var list<string> */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param array{tmp_name?:string,error?:int,size?:int,name?:string,type?:string} $file
     * @return array{ok:true,path:string,width:int,height:int}|array{ok:false,error:string}
     */
    public function storeUpload(int $tenantId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Le fichier n’a pas pu être envoyé. Réessayez.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Fichier invalide.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'L’image doit faire moins de 10 Mo.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'Formats acceptés : JPEG, PNG ou WebP.'];
        }

        $info = @getimagesize($tmp);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return ['ok' => false, 'error' => 'Impossible de lire les dimensions de l’image.'];
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 64 || $height < 64) {
            return ['ok' => false, 'error' => 'L’image est trop petite (minimum 64 × 64).'];
        }
        if ($width > self::MAX_EDGE || $height > self::MAX_EDGE) {
            return ['ok' => false, 'error' => 'L’image est trop grande (maximum 8192 px de côté).'];
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $relDir = 'uploads/tenant-maps/' . $tenantId;
        $absDir = public_file_path($relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            return ['ok' => false, 'error' => 'Impossible de préparer le stockage.'];
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $relPath = $relDir . '/' . $name;
        $absPath = public_file_path($relPath);
        if (!@move_uploaded_file($tmp, $absPath)) {
            return ['ok' => false, 'error' => 'Échec de l’enregistrement de l’image.'];
        }

        return [
            'ok' => true,
            'path' => $relPath,
            'width' => $width,
            'height' => $height,
        ];
    }

    public function deleteFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, 'uploads/tenant-maps/')) {
            return;
        }
        $abs = public_file_path($norm);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public static function publicUrl(string $relativePath): string
    {
        $norm = ltrim(str_replace('\\', '/', $relativePath), '/');

        return asset_url($norm);
    }
}
