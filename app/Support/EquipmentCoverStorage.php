<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Media\ImageCompressionService;

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

    public static function hintText(): string
    {
        $mo = max(1, (int) floor(self::effectiveMaxBytes() / (1024 * 1024)));

        return 'JPG, PNG ou WebP, ' . $mo . ' Mo maximum. Une photo iPhone doit d’abord être enregistrée en JPG.';
    }

    public static function effectiveMaxBytes(): int
    {
        $php = min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));
        if ($php < 1) {
            return self::MAX_BYTES;
        }

        return min(self::MAX_BYTES, $php);
    }

    /**
     * @return array{path:?string, error:?string}
     */
    public static function storeFromUpload(int $tenantId, string $kind, array $file): array
    {
        if ($tenantId < 1) {
            return ['path' => null, 'error' => 'Communauté introuvable.'];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => self::uploadErrorMessage($err)];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return ['path' => null, 'error' => 'Fichier image invalide.'];
        }
        if (self::looksLikeHeic($tmp, (string) ($file['name'] ?? ''))) {
            return ['path' => null, 'error' => 'Cette photo est au format iPhone. Enregistrez-la en JPG ou PNG, puis renvoyez-la.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size > self::effectiveMaxBytes()) {
            return ['path' => null, 'error' => self::tooLargeMessage()];
        }
        $ext = TerrainUploadedImage::detectExtension($tmp, (string) ($file['name'] ?? ''));
        if ($ext === null) {
            return ['path' => null, 'error' => 'Formats acceptés : JPG, PNG ou WebP.'];
        }
        $kind = $kind === 'collection' ? 'c' : 't';
        $dirRel = 'uploads/equipment/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        $stored = (new ImageCompressionService())->storeUpload(
            $file,
            $dirAbs,
            $dirRel,
            $kind,
            1_500_000,
            self::effectiveMaxBytes(),
            1600
        );
        $path = ($stored['ok'] ?? false) ? trim((string) ($stored['relative'] ?? '')) : '';
        if ($path !== '') {
            return ['path' => $path, 'error' => null];
        }
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['path' => null, 'error' => 'Stockage des photos indisponible pour le moment.'];
        }
        $name = $kind . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
        $destRel = $dirRel . '/' . $name;
        if (!TerrainUploadedImage::move($tmp, $destAbs)) {
            return ['path' => null, 'error' => $stored['error'] ?? 'Impossible d’enregistrer la photo.'];
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

    public static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => self::tooLargeMessage(),
            UPLOAD_ERR_PARTIAL => 'L’envoi de la photo a été interrompu. Réessayez.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Stockage temporaire indisponible. Réessayez dans un instant.',
            default => 'Le téléversement de la photo a échoué. Réessayez avec un JPG ou un PNG plus léger.',
        };
    }

    private static function tooLargeMessage(): string
    {
        $mo = max(1, (int) floor(self::effectiveMaxBytes() / (1024 * 1024)));

        return 'Photo trop volumineuse (maximum ' . $mo . ' Mo). Choisissez une image plus légère, ou enregistrez-la en JPG.';
    }

    private static function looksLikeHeic(string $tmpPath, string $originalName): bool
    {
        $n = strtolower($originalName);
        if (str_ends_with($n, '.heic') || str_ends_with($n, '.heif')) {
            return true;
        }
        $head = '';
        if (is_readable($tmpPath)) {
            $head = (string) @file_get_contents($tmpPath, false, null, 0, 16);
        }
        if ($head === '' || strlen($head) < 12) {
            return false;
        }
        $brand = strtolower(substr($head, 4, 12));

        return str_contains($brand, 'heic')
            || str_contains($brand, 'heif')
            || str_contains($brand, 'mif1')
            || str_contains($brand, 'msf1');
    }

    private static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));
        if ($raw === '' || $raw === '-1') {
            return self::MAX_BYTES;
        }
        $n = (float) $raw;
        $u = strtolower(substr($raw, -1));
        $mul = match ($u) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };

        return (int) round($n * $mul);
    }
}
