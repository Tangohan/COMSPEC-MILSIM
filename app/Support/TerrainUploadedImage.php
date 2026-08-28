<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Image terrain reçue en multipart (reconnaissance / fiche SEEK).
 * Détecte PNG/JPEG/WebP même si le type MIME annoncé est trompeur.
 */
final class TerrainUploadedImage
{
    /**
     * Capture visage SEEK (screenshot Arma) — ne doit pas aller au canal reconnaissance.
     */
    public static function isSseFaceFileName(string $name): bool
    {
        $base = strtolower(basename(str_replace('\\', '/', $name)));

        return str_starts_with($base, 'comspec_sse_face');
    }

    /**
     * @return array{name: string, tmp_name: string, size: int, error: int}|null
     */
    public static function fromGlobals(): ?array
    {
        foreach (['image', 'photo', 'file'] as $key) {
            if (empty($_FILES[$key]) || !is_array($_FILES[$key])) {
                continue;
            }
            $file = self::flatten($_FILES[$key]);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    public static function detectExtension(string $tmpPath, string $originalName = ''): ?string
    {
        $head = '';
        if ($tmpPath !== '' && is_file($tmpPath) && is_readable($tmpPath)) {
            $head = (string) @file_get_contents($tmpPath, false, null, 0, 16);
        }
        if ($head !== '') {
            if (str_starts_with($head, "\x89PNG")) {
                return 'png';
            }
            if (strlen($head) >= 3 && $head[0] === "\xFF" && $head[1] === "\xD8" && $head[2] === "\xFF") {
                return 'jpg';
            }
            if (str_starts_with($head, 'RIFF') && str_contains(substr($head, 0, 16), 'WEBP')) {
                return 'webp';
            }
        }

        $mime = '';
        if ($tmpPath !== '' && is_file($tmpPath)) {
            try {
                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
            } catch (\Throwable) {
                $mime = '';
            }
        }
        $ext = match (strtolower($mime)) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => 'jpg',
            'image/png', 'image/x-png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext !== null) {
            return $ext;
        }

        return null;
    }

    public static function move(string $tmpPath, string $dest): bool
    {
        if ($tmpPath === '' || $dest === '') {
            return false;
        }
        if (@move_uploaded_file($tmpPath, $dest)) {
            return true;
        }
        if (is_file($tmpPath) && @copy($tmpPath, $dest) && is_file($dest)) {
            @unlink($tmpPath);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $file
     * @return array{name: string, tmp_name: string, size: int, error: int}|null
     */
    private static function flatten(array $file): ?array
    {
        $name = $file['name'] ?? '';
        $tmp = $file['tmp_name'] ?? '';
        $size = $file['size'] ?? 0;
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (is_array($name)) {
            $name = $name[0] ?? '';
            $tmp = is_array($tmp) ? ($tmp[0] ?? '') : $tmp;
            $size = is_array($size) ? ($size[0] ?? 0) : $size;
            $error = is_array($error) ? ($error[0] ?? UPLOAD_ERR_NO_FILE) : $error;
        }
        $name = (string) $name;
        $tmp = (string) $tmp;
        if ($name === '' && $tmp === '') {
            return null;
        }

        return [
            'name' => $name,
            'tmp_name' => $tmp,
            'size' => (int) $size,
            'error' => (int) $error,
        ];
    }
}
