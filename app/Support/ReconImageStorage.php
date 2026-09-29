<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Photos terrain (jeu → poste) : public/uploads/recon en priorité, repli storage/uploads/recon.
 */
final class ReconImageStorage
{
    public const REL_DIR = 'uploads/recon';

    public static function isSafeFileName(string $file): bool
    {
        return $file !== ''
            && preg_match('/^[a-zA-Z0-9._-]+$/', $file) === 1
            && !str_contains($file, '..');
    }

    /**
     * Chemin relatif stocké en base (recon/fichier.jpg).
     */
    public static function relativeImagePath(string $filename): string
    {
        return 'recon/' . ltrim(str_replace('\\', '/', $filename), '/');
    }

    public static function publicUrl(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $rel = self::REL_DIR . '/' . $filename;
        try {
            $url = user_media_public_url($rel);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        } catch (\Throwable) {
        }

        return '/' . $rel;
    }

    /**
     * Répertoire writable (public d’abord, sinon storage).
     */
    public static function ensureWritableDir(): ?string
    {
        foreach ([
            base_path('public/' . self::REL_DIR),
            base_path('storage/' . self::REL_DIR),
        ] as $abs) {
            if (self::makeWritable($abs)) {
                return $abs;
            }
        }

        return null;
    }

    /**
     * Enregistre le fichier temporaire ; retourne le chemin absolu final ou null.
     */
    public static function storeFromTemp(string $tmpPath, string $filename): ?string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if (!self::isSafeFileName($filename) || $tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }
        $dir = self::ensureWritableDir();
        if ($dir === null) {
            return null;
        }
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!TerrainUploadedImage::move($tmpPath, $dest)) {
            return null;
        }
        if (!is_file($dest) || (int) filesize($dest) < 1) {
            @unlink($dest);

            return null;
        }

        return $dest;
    }

    /**
     * Fichier lisible pour un basename (ou image_path recon/…).
     */
    public static function absoluteReadable(string $imagePathOrName): ?string
    {
        $base = basename(str_replace('\\', '/', trim($imagePathOrName)));
        if (!self::isSafeFileName($base)) {
            return null;
        }
        $norm = self::REL_DIR . '/' . $base;
        foreach ([
            base_path('public/' . $norm),
            base_path('storage/' . $norm),
        ] as $abs) {
            if (is_file($abs) && is_readable($abs)) {
                return $abs;
            }
        }

        return null;
    }

    public static function delete(string $imagePathOrName): void
    {
        $abs = self::absoluteReadable($imagePathOrName);
        if ($abs !== null) {
            @unlink($abs);
        }
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
}
