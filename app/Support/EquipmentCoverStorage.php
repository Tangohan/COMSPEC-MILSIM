<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
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
        $publicAbs = self::publicAbsolute($norm);
        if ($publicAbs !== null && is_file($publicAbs)) {
            return function_exists('user_media_public_url')
                ? user_media_public_url($norm)
                : asset_url($norm);
        }
        $storageAbs = self::storageAbsolute($norm);
        if ($storageAbs === null || !is_file($storageAbs)) {
            return function_exists('user_media_public_url')
                ? user_media_public_url($norm)
                : asset_url($norm);
        }
        $parts = explode('/', $norm);
        $tenantId = (int) ($parts[2] ?? 0);
        $file = (string) ($parts[3] ?? '');
        if ($tenantId < 1 || $file === '' || !self::isSafeFileName($file)) {
            return null;
        }

        return url('equipment/covers/' . $tenantId . '/' . rawurlencode($file));
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
        $kind = match ($kind) {
            'collection' => 'c',
            'figure' => 'f',
            'backdrop' => 'b',
            default => 't',
        };
        $dirRel = 'uploads/equipment/' . $tenantId;
        $dirAbs = self::ensureWritableDir($tenantId);
        if ($dirAbs === null) {
            return ['path' => null, 'error' => 'Stockage des photos indisponible pour le moment.'];
        }
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
        $name = $kind . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
        $destRel = $dirRel . '/' . $name;
        if (!TerrainUploadedImage::move($tmp, $destAbs)) {
            return ['path' => null, 'error' => $stored['error'] ?? 'Impossible d’enregistrer la photo.'];
        }

        return ['path' => $destRel, 'error' => null];
    }

    /**
     * Personnage en PNG (fond transparent conservé) pour la vitrine du tableau de bord.
     *
     * @return array{path:?string, error:?string}
     */
    public static function storeFigureFromUpload(int $tenantId, array $file): array
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
            return ['path' => null, 'error' => 'Formats acceptés : PNG (recommandé pour un personnage), JPG ou WebP.'];
        }
        $dirRel = 'uploads/equipment/' . $tenantId;
        $dirAbs = self::ensureWritableDir($tenantId);
        if ($dirAbs === null) {
            return ['path' => null, 'error' => 'Stockage des photos indisponible pour le moment.'];
        }
        if ($ext === 'png' || $ext === 'webp') {
            $name = 'f-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
            if (!self::savePreservingAlpha($tmp, $destAbs, $ext, 1600)) {
                return ['path' => null, 'error' => 'Impossible d’enregistrer le personnage.'];
            }

            return ['path' => $dirRel . '/' . $name, 'error' => null];
        }

        return self::storeFromUpload($tenantId, 'figure', $file);
    }

    public static function figureHintText(): string
    {
        return 'PNG du personnage, fond transparent de préférence. JPG ou WebP acceptés. Une photo de fond peut s’afficher derrière.';
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
        foreach ([self::publicAbsolute($norm), self::storageAbsolute($norm)] as $abs) {
            if ($abs !== null && is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    public static function streamCover(int $viewerTenantId, int $coverTenantId, string $file): Response
    {
        if ($viewerTenantId < 1 || $coverTenantId < 1 || $viewerTenantId !== $coverTenantId || !self::isSafeFileName($file)) {
            return (new Response())->setStatusCode(404)->setBody('Photo introuvable.');
        }
        $rel = 'uploads/equipment/' . $coverTenantId . '/' . $file;
        $abs = self::absoluteReadable($rel);
        if ($abs === null) {
            return (new Response())->setStatusCode(404)->setBody('Photo introuvable.');
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
        $response = new Response();
        $response->header('Content-Type', $mime);
        $response->header('Content-Length', (string) filesize($abs));
        $response->header('Cache-Control', 'private, max-age=86400');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->setBodyStream(static function () use ($abs): void {
            $h = fopen($abs, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });

        return $response;
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

    public static function isSafeFileName(string $file): bool
    {
        return $file !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $file) === 1 && !str_contains($file, '..');
    }

    private static function ensureWritableDir(int $tenantId): ?string
    {
        $rel = 'uploads/equipment/' . $tenantId;
        foreach ([
            base_path('public/' . $rel),
            base_path('storage/' . $rel),
        ] as $abs) {
            if (self::makeWritable($abs)) {
                return $abs;
            }
        }

        return null;
    }

    private static function savePreservingAlpha(string $tmp, string $destAbs, string $ext, int $maxEdgePx): bool
    {
        $loader = $ext === 'webp' ? 'imagecreatefromwebp' : 'imagecreatefrompng';
        $saver = $ext === 'webp' ? 'imagewebp' : 'imagepng';
        if (!function_exists($loader) || !function_exists($saver)) {
            return TerrainUploadedImage::move($tmp, $destAbs);
        }
        $src = @$loader($tmp);
        if ($src === false) {
            return TerrainUploadedImage::move($tmp, $destAbs);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $long = max($w, $h);
        if ($w < 1 || $h < 1 || $long <= $maxEdgePx) {
            imagedestroy($src);

            return TerrainUploadedImage::move($tmp, $destAbs);
        }
        $ratio = $maxEdgePx / $long;
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($src);

            return TerrainUploadedImage::move($tmp, $destAbs);
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        }
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $ok = $ext === 'webp'
            ? (bool) @$saver($dst, $destAbs, 88)
            : (bool) @$saver($dst, $destAbs, 6);
        imagedestroy($src);
        imagedestroy($dst);

        return $ok && is_file($destAbs);
    }

    private static function makeWritable(string $abs): bool
    {
        $abs = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $abs), DIRECTORY_SEPARATOR);
        if ($abs === '') {
            return false;
        }
        if (!is_dir($abs) && !@mkdir($abs, 0775, true) && !is_dir($abs)) {
            return false;
        }

        return is_writable($abs);
    }

    private static function absoluteReadable(string $norm): ?string
    {
        $publicAbs = self::publicAbsolute($norm);
        if ($publicAbs !== null && is_file($publicAbs)) {
            return $publicAbs;
        }
        $storageAbs = self::storageAbsolute($norm);
        if ($storageAbs !== null && is_file($storageAbs)) {
            return $storageAbs;
        }

        return null;
    }

    private static function publicAbsolute(string $norm): ?string
    {
        if (!function_exists('base_path')) {
            return null;
        }

        return base_path('public/' . ltrim($norm, '/'));
    }

    private static function storageAbsolute(string $norm): ?string
    {
        if (!function_exists('base_path')) {
            return null;
        }

        return base_path('storage/' . ltrim($norm, '/'));
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
