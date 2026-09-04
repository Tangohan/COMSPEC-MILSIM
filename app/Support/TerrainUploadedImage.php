<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Image terrain reçue en multipart (reconnaissance / fiche SEEK).
 * Détecte PNG/JPEG/WebP même si le type MIME annoncé est trompeur.
 */
final class TerrainUploadedImage
{
    public const MAX_RAW_BYTES = 16 * 1024 * 1024;

    /** @var list<string> */
    private const FILE_KEYS = ['image', 'photo', 'file', 'piece'];

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
        $file = self::fromFilesSuperglobal();
        if ($file !== null) {
            return $file;
        }

        $hydrated = self::hydrateFromRawMultipart();
        if ($hydrated !== null) {
            return $hydrated;
        }

        return self::fromFilesSuperglobal();
    }

    public static function declaredBodyExceedsPostMax(): bool
    {
        $declared = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMax = self::iniBytes('post_max_size');

        return $declared > 0 && $postMax > 0 && $declared > $postMax;
    }

    /**
     * @return array{post: array<string, string>, file: array{name: string, tmp_name: string, size: int, error: int, field?: string}|null}
     */
    public static function parseMultipartBody(string $raw, string $contentType): array
    {
        $out = ['post' => [], 'file' => null];
        $boundary = self::boundaryFromContentType($contentType);
        if ($boundary === null || $raw === '') {
            return $out;
        }
        $delim = '--' . $boundary;
        $chunks = explode($delim, $raw);
        foreach ($chunks as $chunk) {
            if ($chunk === '' || $chunk === "\r\n" || str_starts_with($chunk, '--')) {
                continue;
            }
            $chunk = ltrim($chunk, "\r\n");
            $split = strpos($chunk, "\r\n\r\n");
            if ($split === false) {
                continue;
            }
            $headerBlock = substr($chunk, 0, $split);
            $body = substr($chunk, $split + 4);
            if (str_ends_with($body, "\r\n")) {
                $body = substr($body, 0, -2);
            }
            $disposition = self::headerValue($headerBlock, 'content-disposition');
            if ($disposition === '') {
                continue;
            }
            $name = self::dispositionParam($disposition, 'name');
            if ($name === '') {
                continue;
            }
            $filename = self::dispositionParam($disposition, 'filename');
            if ($filename === '') {
                if (!array_key_exists($name, $out['post'])) {
                    $out['post'][$name] = $body;
                }
                continue;
            }
            if ($out['file'] !== null && !in_array($name, self::FILE_KEYS, true)) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'comspec_up_');
            if ($tmp === false) {
                continue;
            }
            if (@file_put_contents($tmp, $body) === false) {
                @unlink($tmp);
                continue;
            }
            $parsed = [
                'name' => $filename,
                'tmp_name' => $tmp,
                'size' => strlen($body),
                'error' => UPLOAD_ERR_OK,
                'field' => $name,
            ];
            if (in_array($name, self::FILE_KEYS, true) || $out['file'] === null) {
                $out['file'] = $parsed;
            }
        }

        return $out;
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
     * @return array{name: string, tmp_name: string, size: int, error: int}|null
     */
    private static function fromFilesSuperglobal(): ?array
    {
        foreach (self::FILE_KEYS as $key) {
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

    /**
     * @return array{name: string, tmp_name: string, size: int, error: int}|null
     */
    private static function hydrateFromRawMultipart(): ?array
    {
        $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($ct, 'multipart/') === false) {
            return null;
        }
        $raw = self::readRawInput(self::MAX_RAW_BYTES);
        if ($raw === '') {
            return null;
        }
        $parsed = self::parseMultipartBody($raw, $ct);
        foreach ($parsed['post'] as $key => $value) {
            if (!isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
        $file = $parsed['file'];
        if ($file === null) {
            return null;
        }
        $slot = (string) ($file['field'] ?? 'image');
        unset($file['field']);
        if (!in_array($slot, self::FILE_KEYS, true)) {
            $slot = 'image';
        }
        if (!isset($_FILES[$slot])) {
            $_FILES[$slot] = $file;
        }

        return $file;
    }

    private static function readRawInput(int $maxBytes): string
    {
        $maxBytes = max(1024, $maxBytes);
        $declared = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declared > $maxBytes) {
            return '';
        }
        $fh = @fopen('php://input', 'rb');
        if ($fh === false) {
            return '';
        }
        try {
            $raw = stream_get_contents($fh, $maxBytes + 1);
        } finally {
            fclose($fh);
        }
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        if (strlen($raw) > $maxBytes) {
            return '';
        }

        return $raw;
    }

    public static function boundaryFromContentType(string $contentType): ?string
    {
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;\\s]+))/i', $contentType, $m)) {
            return null;
        }
        $boundary = ($m[1] ?? '') !== '' ? $m[1] : (string) ($m[2] ?? '');

        return $boundary !== '' ? $boundary : null;
    }

    private static function headerValue(string $headers, string $name): string
    {
        foreach (preg_split("/\r\n/", $headers) ?: [] as $line) {
            $cut = strpos($line, ':');
            if ($cut === false) {
                continue;
            }
            if (strcasecmp(trim(substr($line, 0, $cut)), $name) === 0) {
                return trim(substr($line, $cut + 1));
            }
        }

        return '';
    }

    private static function dispositionParam(string $disposition, string $param): string
    {
        $pattern = '/(?:^|;)\\s*' . preg_quote($param, '/') . '\\s*=\\s*(?:"([^"]*)"|([^;\\s]+))/i';
        if (!preg_match($pattern, $disposition, $m)) {
            return '';
        }

        return ($m[1] ?? '') !== '' ? $m[1] : (string) ($m[2] ?? '');
    }

    private static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));
        if ($raw === '') {
            return 0;
        }
        $n = (int) $raw;
        $unit = strtolower(substr($raw, -1));
        return match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => (int) $raw,
        };
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
        if ($tmp === '' && $name === '') {
            return null;
        }
        if ($tmp === '' && (int) $error === UPLOAD_ERR_OK) {
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
