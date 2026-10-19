<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Fichiers partagés d'une communauté : rangés hors racine web (storage/private/partage/{communauté}/{année-mois}/),
 * sous un nom aléatoire sans extension ; jamais servis en direct, seulement par le contrôleur après contrôle d'accès.
 */
final class FileShareStorage
{
    public const DIR = 'storage/private/partage';

    public const MAX_BYTES = 100 * 1024 * 1024;

    /** Espace total d'une communauté. */
    public const TENANT_QUOTA_BYTES = 5 * 1024 * 1024 * 1024;

    /** @var array<string, string> extension acceptée => type servi */
    public const TYPES = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'md' => 'text/plain',
        'csv' => 'text/csv',
        'rtf' => 'application/rtf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'zip' => 'application/zip',
        '7z' => 'application/x-7z-compressed',
        'rar' => 'application/vnd.rar',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'kml' => 'application/vnd.google-earth.kml+xml',
        'kmz' => 'application/vnd.google-earth.kmz',
        'gpx' => 'application/gpx+xml',
        'sqm' => 'application/octet-stream',
        'pbo' => 'application/octet-stream',
        'paa' => 'application/octet-stream',
    ];

    /** Types affichables dans le navigateur (le reste est toujours téléchargé). */
    private const INLINE = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'text/plain',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'video/mp4', 'video/webm',
    ];

    public static function extension(string $name): string
    {
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    public static function accepts(string $name): bool
    {
        return isset(self::TYPES[self::extension($name)]);
    }

    public static function acceptedList(): string
    {
        return implode(', ', array_map(static fn (string $e): string => '.' . $e, array_keys(self::TYPES)));
    }

    public static function root(): string
    {
        return base_path(self::DIR);
    }

    /**
     * Range un fichier reçu. $tmp doit venir d'un envoi HTTP (is_uploaded_file), sauf dans les tests.
     *
     * @return array{path:?string, mime:string, size:int, sha256:string, error:?string}
     */
    public static function store(int $tenantId, string $tmp, string $originalName, bool $fromUpload = true): array
    {
        $fail = static fn (string $e): array => ['path' => null, 'mime' => '', 'size' => 0, 'sha256' => '', 'error' => $e];
        if ($tenantId < 1 || $tmp === '' || !is_file($tmp) || ($fromUpload && !is_uploaded_file($tmp))) {
            return $fail('Fichier invalide.');
        }
        $ext = self::extension($originalName);
        if (!isset(self::TYPES[$ext])) {
            return $fail('Format refusé pour « ' . $originalName . ' ».');
        }
        $size = (int) filesize($tmp);
        if ($size <= 0) {
            return $fail('« ' . $originalName . ' » est vide.');
        }
        if ($size > self::MAX_BYTES) {
            return $fail('« ' . $originalName . ' » dépasse 100 Mo.');
        }
        $rel = 'partage/' . $tenantId . '/' . date('Y-m');
        $absDir = base_path('storage/private/' . $rel);
        if (!is_dir($absDir) && !@mkdir($absDir, 0770, true) && !is_dir($absDir)) {
            return $fail('Stockage indisponible pour le moment.');
        }
        $name = bin2hex(random_bytes(20));
        $abs = $absDir . DIRECTORY_SEPARATOR . $name;
        $moved = $fromUpload ? @move_uploaded_file($tmp, $abs) : @rename($tmp, $abs);
        if (!$moved) {
            return $fail('Enregistrement de « ' . $originalName . ' » impossible.');
        }
        @chmod($abs, 0640);

        return [
            'path' => $rel . '/' . $name,
            'mime' => self::TYPES[$ext],
            'size' => $size,
            'sha256' => (string) hash_file('sha256', $abs),
            'error' => null,
        ];
    }

    public static function isStoredPath(string $rel): bool
    {
        return (bool) preg_match('#^partage/[1-9][0-9]*/[0-9]{4}-[0-9]{2}/[a-f0-9]{40}$#', $rel);
    }

    /** Chemin absolu d'un fichier rangé, à condition qu'il appartienne bien à cette communauté. */
    public static function absolutePath(string $rel, int $tenantId): ?string
    {
        if (!self::isStoredPath($rel) || !str_starts_with($rel, 'partage/' . $tenantId . '/')) {
            return null;
        }
        $abs = base_path('storage/private/' . $rel);
        $real = realpath($abs);
        $root = realpath(self::root());
        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        return $real;
    }

    public static function remove(string $rel, int $tenantId): void
    {
        $abs = self::absolutePath($rel, $tenantId);
        if ($abs !== null) {
            @unlink($abs);
        }
    }

    public static function isInline(string $mime): bool
    {
        return in_array($mime, self::INLINE, true);
    }

    /**
     * @param array<string, mixed> $file ligne tenant_share_files
     */
    public static function response(array $file, int $tenantId, bool $preview): Response
    {
        $abs = self::absolutePath((string) ($file['stored_path'] ?? ''), $tenantId);
        if ($abs === null) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $name = trim(str_replace(['"', "\r", "\n", '/', '\\'], '', (string) ($file['name'] ?? '')));
        if ($name === '') {
            $name = 'fichier';
        }
        $mime = self::TYPES[self::extension($name)] ?? 'application/octet-stream';
        $inline = $preview && self::isInline($mime);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'fichier';

        $response = new Response();
        $response->header('Content-Type', $mime === 'text/plain' ? 'text/plain; charset=UTF-8' : $mime);
        $response->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        $response->header('Content-Length', (string) filesize($abs));
        $response->header('X-Content-Type-Options', 'nosniff');
        // Le lecteur PDF de Chrome refuse une page « sandbox » : le PDF garde seulement default-src 'none'.
        $response->header('Content-Security-Policy', "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'" . ($mime === 'application/pdf' ? '' : '; sandbox'));
        $response->header('Cache-Control', 'private, no-store');
        $response->header('Referrer-Policy', 'no-referrer');
        $response->setBodyStream(static function () use ($abs): void {
            $h = fopen($abs, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });

        return $response;
    }
}
