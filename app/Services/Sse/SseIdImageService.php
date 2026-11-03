<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Images des papiers fictifs (COMSPEC IDENTITY) : photo du titulaire, signature, drapeau ou emblème d'un pays,
 * image de fond d'un gabarit personnalisé.
 *
 * Contrôles : vrai type du fichier lu dans son contenu (PNG, JPG ou WebP seulement, jamais l'extension ni le type
 * annoncé par le navigateur), taille maximale par usage, image réellement lisible. Les métadonnées (EXIF, GPS, XMP,
 * commentaires) sont toujours retirées : l'image est réencodée par GD (et réduite, la photo recadrée au format
 * 35×45 si besoin) ; sans GD, les blocs de métadonnées sont retirés du fichier d'origine (repli, sans réduction).
 *
 * Rangement : public/uploads/<dossier>/<tenant>_<aléa>.<ext> (nom imprévisible, préfixé par la communauté).
 */
final class SseIdImageService
{
    /** Usage => [dossier, taille max en octets, plus grand côté en pixels, format de sortie GD]. */
    public const KINDS = [
        'photo' => ['sse-id-photos', 4 * 1024 * 1024, 1200, 'jpg'],
        'signature' => ['sse-id-signatures', 1024 * 1024, 900, 'png'],
        'flag' => ['sse-flags', 1024 * 1024, 800, 'png'],
        'background' => ['sse-id-backgrounds', 3 * 1024 * 1024, 1600, 'jpg'],
    ];

    /** Rapport largeur / hauteur de la photo d'identité (35 × 45 mm) ; le carré est aussi accepté. */
    public const PHOTO_RATIO = 35 / 45;

    private const EXT = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(private ?string $publicDir = null, private ?bool $useGd = null)
    {
        $this->publicDir ??= base_path('public');
        $this->useGd ??= function_exists('imagecreatefromstring');
    }

    /** Limite affichée à l'utilisateur, en Mo. */
    public static function maxMb(string $kind): int
    {
        return (int) ((self::KINDS[$kind][1] ?? 1024 * 1024) / (1024 * 1024));
    }

    /**
     * Fichier envoyé par un formulaire ($_FILES[…]).
     *
     * @param mixed $file entrée de $_FILES
     * @return array{ok: bool, path?: string, error?: string, none?: bool}
     */
    public function storeUpload(mixed $file, string $kind, int $tenantId): array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'none' => true];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => self::sizeError($kind)];
        }

        return $this->storeFile($tmp, $kind, $tenantId);
    }

    /**
     * Fichier déjà sur le disque (envoi vérifié par storeUpload, ou tests).
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public function storeFile(string $source, string $kind, int $tenantId): array
    {
        if (!isset(self::KINDS[$kind]) || $tenantId <= 0) {
            return ['ok' => false, 'error' => 'Image refusée.'];
        }
        [$folder, $maxBytes, $maxSide, $outFormat] = self::KINDS[$kind];
        $size = @filesize($source);
        if ($size === false || $size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'error' => self::sizeError($kind)];
        }
        $bytes = (string) @file_get_contents($source);
        $mime = self::sniff($bytes);
        if ($mime === null) {
            return ['ok' => false, 'error' => self::typeError($kind)];
        }
        $out = $this->useGd ? self::reencode($bytes, $kind, $maxSide, $outFormat) : null;
        if ($out === null) {
            if ($this->useGd) {
                return ['ok' => false, 'error' => self::typeError($kind)];
            }
            $clean = self::stripMetadata($bytes, $mime);
            if ($clean === null) {
                return ['ok' => false, 'error' => self::typeError($kind)];
            }
            $out = [$clean, self::EXT[$mime]];
        }
        [$data, $ext] = $out;
        $dir = rtrim((string) $this->publicDir, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Enregistrement de l’image impossible.'];
        }
        $name = $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $data) === false) {
            return ['ok' => false, 'error' => 'Enregistrement de l’image impossible.'];
        }

        return ['ok' => true, 'path' => 'uploads/' . $folder . '/' . $name];
    }

    /** Vrai type d'image lu dans les premiers octets (finfo s'il est présent, sinon signature du format). */
    public static function sniff(string $bytes): ?string
    {
        if ($bytes === '') {
            return null;
        }
        $mime = null;
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $mime = finfo_buffer($f, $bytes) ?: null;
                finfo_close($f);
            }
        }
        $magic = match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            strlen($bytes) > 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
        if ($magic === null || ($mime !== null && $mime !== $magic)) {
            return null;
        }

        return $magic;
    }

    /**
     * Réencodage GD : métadonnées perdues, plus grand côté limité, photo recadrée au centre entre 35×45 et le carré.
     *
     * @return array{0: string, 1: string}|null [données, extension]
     */
    public static function reencode(string $bytes, string $kind, int $maxSide, string $format): ?array
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $sx = 0;
        $sy = 0;
        $sw = $w;
        $sh = $h;
        if ($kind === 'photo' && $h > 0) {
            $ratio = $w / $h;
            if ($ratio > 1.0) {
                $sw = $h;
                $sx = intdiv($w - $sw, 2);
            } elseif ($ratio < self::PHOTO_RATIO - 0.01) {
                $sh = (int) round($w / self::PHOTO_RATIO);
                $sy = intdiv($h - $sh, 2);
            }
        }
        $scale = min(1.0, $maxSide / max($sw, $sh, 1));
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));
        $dst = imagecreatetruecolor($dw, $dh);
        if ($format === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);
        ob_start();
        $ok = $format === 'png' ? imagepng($dst, null, 6) : imagejpeg($dst, null, 88);
        $data = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $ok && $data !== '' ? [$data, $format] : null;
    }

    /**
     * Repli sans GD : retire les blocs de métadonnées sans toucher à l'image (JPEG : segments APP1 à APP15 et
     * commentaires ; PNG : blocs texte, eXIf et date ; WebP : blocs EXIF et XMP). Null si le fichier est mal formé.
     */
    public static function stripMetadata(string $bytes, string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => self::stripJpeg($bytes),
            'image/png' => self::stripPng($bytes),
            'image/webp' => self::stripWebp($bytes),
            default => null,
        };
    }

    private static function stripJpeg(string $b): ?string
    {
        $len = strlen($b);
        $out = "\xFF\xD8";
        $i = 2;
        while ($i + 4 <= $len) {
            if ($b[$i] !== "\xFF") {
                return null;
            }
            $marker = ord($b[$i + 1]);
            if ($marker === 0xDA) {
                return $out . substr($b, $i); // début des données de l'image : le reste est gardé tel quel
            }
            $seg = unpack('n', substr($b, $i + 2, 2))[1] ?? 0;
            if ($seg < 2 || $i + 2 + $seg > $len) {
                return null;
            }
            if (!(($marker >= 0xE1 && $marker <= 0xEF) || $marker === 0xFE)) {
                $out .= substr($b, $i, 2 + $seg);
            }
            $i += 2 + $seg;
        }

        return null;
    }

    private static function stripPng(string $b): ?string
    {
        $len = strlen($b);
        $out = substr($b, 0, 8);
        $i = 8;
        while ($i + 12 <= $len) {
            $n = unpack('N', substr($b, $i, 4))[1] ?? 0;
            $type = substr($b, $i + 4, 4);
            if ($i + 12 + $n > $len) {
                return null;
            }
            if (!in_array($type, ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'], true)) {
                $out .= substr($b, $i, 12 + $n);
            }
            $i += 12 + $n;
            if ($type === 'IEND') {
                return $out;
            }
        }

        return null;
    }

    private static function stripWebp(string $b): ?string
    {
        $len = strlen($b);
        $body = '';
        $i = 12;
        while ($i + 8 <= $len) {
            $type = substr($b, $i, 4);
            $n = unpack('V', substr($b, $i + 4, 4))[1] ?? 0;
            $full = 8 + $n + ($n % 2);
            if ($i + 8 + $n > $len) {
                return null;
            }
            $chunk = substr($b, $i, $full);
            if ($type === 'VP8X' && strlen($chunk) > 8) {
                $chunk[8] = chr(ord($chunk[8]) & ~0x0C); // plus de drapeaux EXIF ni XMP
            }
            if ($type !== 'EXIF' && $type !== 'XMP ') {
                $body .= $chunk;
            }
            $i += $full;
        }

        return 'RIFF' . pack('V', 4 + strlen($body)) . 'WEBP' . $body;
    }

    private static function sizeError(string $kind): string
    {
        return 'L’image dépasse ' . self::maxMb($kind) . ' Mo ou n’a pas pu être reçue.';
    }

    private static function typeError(string $kind): string
    {
        return 'L’image doit être un fichier PNG, JPG ou WebP valide (' . self::maxMb($kind) . ' Mo maximum).';
    }
}
