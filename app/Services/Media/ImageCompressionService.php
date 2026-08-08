<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * Compression / redimensionnement d’images uploadées.
 * Conserve une taille cible (ex. 5 Mo) en acceptant un upload plus lourd en amont.
 */
final class ImageCompressionService
{
    public const TARGET_MAX_BYTES = 5_000_000;
    public const UPLOAD_MAX_BYTES = 25_000_000;
    public const MAX_EDGE_PX = 2048;

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Enregistre un fichier uploadé sous $destDirAbs, compressé si besoin.
     *
     * @param array<string,mixed> $file Entrée $_FILES[...]
     * @return array{
     *   ok: bool,
     *   relative: ?string,
     *   bytes: int,
     *   compressed: bool,
     *   error: ?string
     * }
     */
    public function storeUpload(
        array $file,
        string $destDirAbs,
        string $relativeDir,
        string $filenamePrefix = 'img',
        int $targetMaxBytes = self::TARGET_MAX_BYTES,
        int $uploadMaxBytes = self::UPLOAD_MAX_BYTES,
        int $maxEdgePx = self::MAX_EDGE_PX
    ): array {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            return $this->result(true, null, 0, false, null);
        }
        if ($err !== UPLOAD_ERR_OK) {
            return $this->result(false, null, 0, false, 'Envoi d’image impossible. Réessayez avec un autre fichier.');
        }
        $tmp = (string) $file['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $this->result(false, null, 0, false, 'Fichier image invalide.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return $this->result(false, null, 0, false, 'Fichier image vide.');
        }
        if ($size > $uploadMaxBytes) {
            $mo = (int) round($uploadMaxBytes / 1_000_000);

            return $this->result(
                false,
                null,
                0,
                false,
                "Image trop lourde même pour compression (maximum {$mo} Mo à l’envoi)."
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmp) ?: '');
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return $this->result(false, null, 0, false, 'Formats acceptés : JPEG, PNG, WebP ou GIF.');
        }

        $destDirAbs = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destDirAbs), DIRECTORY_SEPARATOR);
        if (!is_dir($destDirAbs) && !@mkdir($destDirAbs, 0755, true) && !is_dir($destDirAbs)) {
            return $this->result(false, null, 0, false, 'Stockage image indisponible.');
        }

        $needsCompress = $size > $targetMaxBytes || $this->exceedsEdge($tmp, $maxEdgePx);
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');

        if (!$needsCompress) {
            $ext = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'jpg',
            };
            $name = $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $abs = $destDirAbs . DIRECTORY_SEPARATOR . $name;
            if (!@move_uploaded_file($tmp, $abs)) {
                return $this->result(false, null, 0, false, 'Impossible d’enregistrer l’image.');
            }

            return $this->result(true, $relativeDir . '/' . $name, (int) filesize($abs), false, null);
        }

        $name = $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $abs = $destDirAbs . DIRECTORY_SEPARATOR . $name;
        $written = $this->compressToJpeg($tmp, $abs, $targetMaxBytes, $maxEdgePx);
        if ($written === null) {
            // Repli : copie brute si GD indisponible et fichier déjà sous la cible
            if (!function_exists('imagecreatefromstring') && $size <= $targetMaxBytes) {
                $ext = match ($mime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                    default => 'jpg',
                };
                $name = $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $abs = $destDirAbs . DIRECTORY_SEPARATOR . $name;
                if (@move_uploaded_file($tmp, $abs)) {
                    return $this->result(true, $relativeDir . '/' . $name, (int) filesize($abs), false, null);
                }
            }

            return $this->result(
                false,
                null,
                0,
                false,
                'Impossible de compresser cette image. Essayez un JPEG plus léger.'
            );
        }

        return $this->result(true, $relativeDir . '/' . $name, $written, true, null);
    }

    /**
     * Compresse une image source vers un JPEG sous $maxBytes.
     *
     * @return int|null Taille écrite en octets, ou null si échec
     */
    public function compressToJpeg(
        string $sourcePath,
        string $destPath,
        int $maxBytes = self::TARGET_MAX_BYTES,
        int $maxEdgePx = self::MAX_EDGE_PX
    ): ?int {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $bin = @file_get_contents($sourcePath);
        if ($bin === false || $bin === '') {
            return null;
        }
        $im = @imagecreatefromstring($bin);
        if ($im === false) {
            return null;
        }

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);

            return null;
        }

        // Fond opaque (transparence PNG/GIF → blanc) pour JPEG.
        $canvas = imagecreatetruecolor($w, $h);
        if ($canvas === false) {
            imagedestroy($im);

            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);
        imagedestroy($im);
        $im = $canvas;

        $edge = $maxEdgePx;
        $qualities = [88, 82, 76, 70, 64, 58, 52, 45, 38];

        for ($pass = 0; $pass < 8; $pass++) {
            $scaled = $this->scaleDown($im, $edge);
            if ($scaled === null) {
                imagedestroy($im);

                return null;
            }
            foreach ($qualities as $q) {
                if (!@imagejpeg($scaled, $destPath, $q)) {
                    continue;
                }
                clearstatcache(true, $destPath);
                $bytes = (int) (@filesize($destPath) ?: 0);
                if ($bytes > 0 && $bytes <= $maxBytes) {
                    if ($scaled !== $im) {
                        imagedestroy($scaled);
                    }
                    imagedestroy($im);

                    return $bytes;
                }
            }
            if ($scaled !== $im) {
                imagedestroy($scaled);
            }
            $edge = max(640, (int) floor($edge * 0.82));
        }

        // Dernier essai très compressé
        $scaled = $this->scaleDown($im, max(480, $edge));
        $ok = $scaled !== null && @imagejpeg($scaled, $destPath, 32);
        if ($scaled !== null && $scaled !== $im) {
            imagedestroy($scaled);
        }
        imagedestroy($im);
        if (!$ok) {
            @unlink($destPath);

            return null;
        }
        clearstatcache(true, $destPath);
        $bytes = (int) (@filesize($destPath) ?: 0);
        if ($bytes <= 0) {
            @unlink($destPath);

            return null;
        }
        // Si encore trop lourd après tout : on garde quand même le plus petit obtenu
        // seulement s’il reste sous 1,5× la cible (sinon échec métier).
        if ($bytes > (int) ($maxBytes * 1.5)) {
            @unlink($destPath);

            return null;
        }

        return $bytes;
    }

    private function exceedsEdge(string $path, int $maxEdgePx): bool
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }
        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);

        return $w > $maxEdgePx || $h > $maxEdgePx;
    }

    /** @return \GdImage|null */
    private function scaleDown(\GdImage $im, int $maxEdgePx): ?\GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $long = max($w, $h);
        if ($long <= $maxEdgePx) {
            return $im;
        }
        $ratio = $maxEdgePx / $long;
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $scaled = imagecreatetruecolor($nw, $nh);
        if ($scaled === false) {
            return null;
        }
        imagecopyresampled($scaled, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $scaled;
    }

    /**
     * @return array{ok:bool,relative:?string,bytes:int,compressed:bool,error:?string}
     */
    private function result(bool $ok, ?string $relative, int $bytes, bool $compressed, ?string $error): array
    {
        return [
            'ok' => $ok,
            'relative' => $relative,
            'bytes' => $bytes,
            'compressed' => $compressed,
            'error' => $error,
        ];
    }
}
