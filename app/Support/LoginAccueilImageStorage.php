<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Services\Media\ImageCompressionService;

/**
 * Photos du sas d’accueil après connexion (public/uploads + repli storage/uploads).
 */
final class LoginAccueilImageStorage
{
    public const MAX_BYTES = 12 * 1024 * 1024;

    public const DEFAULT_ASSET = 'assets/images/login-accueil-nvg-forest.jpg';

    public const PREFIX = 'uploads/login-accueil/';

    public static function defaultPublicUrl(): string
    {
        return asset_url(self::DEFAULT_ASSET);
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

    public static function publicUrl(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, self::PREFIX)) {
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

        return url('login/accueil/fond/' . $tenantId . '/' . rawurlencode($file));
    }

    /**
     * @return array{path:?string, error:?string}
     */
    public static function storeFromUpload(int $tenantId, array $file): array
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
        $dirRel = 'uploads/login-accueil/' . $tenantId;
        $dirAbs = self::ensureWritableDir($tenantId);
        if ($dirAbs === null) {
            return ['path' => null, 'error' => 'Stockage des photos indisponible pour le moment.'];
        }
        $stored = (new ImageCompressionService())->storeUpload(
            $file,
            $dirAbs,
            $dirRel,
            'a',
            1_800_000,
            self::effectiveMaxBytes(),
            1920
        );
        $path = ($stored['ok'] ?? false) ? trim((string) ($stored['relative'] ?? '')) : '';
        if ($path !== '') {
            return ['path' => $path, 'error' => null];
        }
        $name = 'a-' . bin2hex(random_bytes(8)) . '.' . $ext;
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
        if (!str_starts_with($norm, self::PREFIX)) {
            return;
        }
        foreach ([self::publicAbsolute($norm), self::storageAbsolute($norm)] as $abs) {
            if ($abs !== null && is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    public static function stream(int $viewerTenantId, int $imageTenantId, string $file): Response
    {
        if ($viewerTenantId < 1 || $imageTenantId < 1 || $viewerTenantId !== $imageTenantId || !self::isSafeFileName($file)) {
            return (new Response())->setStatusCode(404)->setBody('Photo introuvable.');
        }
        $rel = 'uploads/login-accueil/' . $imageTenantId . '/' . $file;
        $abs = self::absoluteReadable($rel);
        if ($abs === null) {
            return (new Response())->setStatusCode(404)->setBody('Photo introuvable.');
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
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
        $rel = 'uploads/login-accueil/' . $tenantId;
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
