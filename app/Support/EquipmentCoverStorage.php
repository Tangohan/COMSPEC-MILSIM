<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Photo de présentation d’une tenue ou d’une collection (portail Équipement).
 */
final class EquipmentCoverStorage
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public static function publicUrl(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, 'uploads/equipment/')) {
            return null;
        }

        return function_exists('user_media_public_url')
            ? user_media_public_url($norm)
            : asset_url($norm);
    }

    /**
     * @return array{path:?string, error:?string}
     */
    public static function storeFromUpload(int $tenantId, string $kind, array $file): array
    {
        if ($tenantId < 1) {
            return ['path' => null, 'error' => 'Communauté introuvable.'];
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Le téléversement de la photo a échoué. Réessayez.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return ['path' => null, 'error' => 'Fichier image invalide.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['path' => null, 'error' => 'Photo trop volumineuse (maximum 8 Mo).'];
        }
        $ext = TerrainUploadedImage::detectExtension($tmp, (string) ($file['name'] ?? ''));
        if ($ext === null) {
            return ['path' => null, 'error' => 'Formats acceptés : JPG, PNG ou WebP.'];
        }
        $kind = $kind === 'collection' ? 'c' : 't';
        $dirRel = 'uploads/equipment/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['path' => null, 'error' => 'Stockage des photos indisponible pour le moment.'];
        }
        $name = $kind . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
        $destRel = $dirRel . '/' . $name;
        if (!TerrainUploadedImage::move($tmp, $destAbs)) {
            return ['path' => null, 'error' => 'Impossible d’enregistrer la photo.'];
        }

        return ['path' => $destRel, 'error' => null];
    }

    public static function delete(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, 'uploads/equipment/')) {
            return;
        }
        $abs = base_path('public/' . ltrim($norm, '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
