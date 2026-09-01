<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Pièces du coffre RH : dépôt hors du dossier public, lecture uniquement via le portail.
 */
final class PersonnelHrDocumentStorage
{
    public const MAX_BYTES = 15 * 1024 * 1024;

    public const PREFIX = 'hr-documents/';

    /** @var array<string, string> mime => extension */
    private const MIME_EXT = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.oasis.opendocument.text' => 'odt',
    ];

    /** @var array<string, string> extension => mime */
    private const EXT_MIME = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'txt' => 'text/plain',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt' => 'application/vnd.oasis.opendocument.text',
    ];

    public static function isStoredPath(?string $path): bool
    {
        $path = str_replace('\\', '/', trim((string) $path));
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        return str_starts_with($path, self::PREFIX);
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path:?string, original_name:?string, error:?string}
     */
    public static function storeFromUpload(int $tenantId, int $userId, mixed $file): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return ['path' => null, 'original_name' => null, 'error' => 'Dossier introuvable.'];
        }
        if (!is_array($file)) {
            return ['path' => null, 'original_name' => null, 'error' => null];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'original_name' => null, 'error' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => null, 'original_name' => null, 'error' => 'Le dépôt du document a échoué. Réessayez avec un fichier plus léger.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['path' => null, 'original_name' => null, 'error' => 'Fichier invalide.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['path' => null, 'original_name' => null, 'error' => 'Document trop volumineux (maximum 15 Mo).'];
        }
        $original = trim((string) ($file['name'] ?? 'document'));
        if ($original === '') {
            $original = 'document';
        }
        $mime = self::detectMime($tmp, $original);
        $ext = self::MIME_EXT[$mime] ?? '';
        if ($ext === '') {
            return ['path' => null, 'original_name' => null, 'error' => 'Formats acceptés : PDF, image (JPG, PNG, WebP), Word ou texte.'];
        }
        $relDir = self::PREFIX . $tenantId . '/' . $userId;
        $absDir = base_path('storage/uploads/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['path' => null, 'original_name' => null, 'error' => 'Impossible d’enregistrer le document pour le moment.'];
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = $absDir . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmp, $abs) && !@copy($tmp, $abs)) {
            return ['path' => null, 'original_name' => null, 'error' => 'Enregistrement du document impossible.'];
        }
        @chmod($abs, 0640);

        return [
            'path' => $relDir . '/' . $name,
            'original_name' => mb_substr($original, 0, 255),
            'error' => null,
        ];
    }

    public static function absolutePath(string $relativePath): ?string
    {
        if (!self::isStoredPath($relativePath)) {
            return null;
        }
        $abs = base_path('storage/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
        $uploadsRoot = realpath(base_path('storage/uploads'));
        $real = realpath($abs);
        if ($uploadsRoot === false || $real === false) {
            return is_file($abs) ? $abs : null;
        }
        if (!str_starts_with($real, $uploadsRoot)) {
            return null;
        }

        return $real;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function downloadResponse(array $row): Response
    {
        $rel = (string) ($row['file_path'] ?? '');
        $abs = self::absolutePath($rel);
        if ($abs === null || !is_file($abs)) {
            return (new Response())->setStatusCode(404)->setBody('Pièce introuvable.');
        }
        $downloadName = trim((string) ($row['original_name'] ?? ''));
        if ($downloadName === '') {
            $downloadName = 'document';
        }
        $downloadName = str_replace(['"', "\r", "\n", '/', '\\'], '', $downloadName);
        $mime = self::detectMime($abs, $downloadName);
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain'], true);
        $response = new Response();
        $response->header('Content-Type', $mime !== '' ? $mime : 'application/octet-stream');
        $response->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"');
        $response->header('Content-Length', (string) filesize($abs));
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

    private static function detectMime(string $path, string $originalName): string
    {
        $mime = '';
        if (is_file($path) && function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $mime = strtolower(trim((string) (finfo_file($f, $path) ?: '')));
                finfo_close($f);
            }
        }
        if (str_contains($mime, ';')) {
            $mime = strtolower(trim(explode(';', $mime, 2)[0]));
        }
        if ($mime !== '' && isset(self::MIME_EXT[$mime])) {
            return $mime;
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return self::EXT_MIME[$ext] ?? $mime;
    }
}
