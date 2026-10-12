<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Photos terrain (jeu → poste) : public/uploads/recon en priorité, repli storage/uploads/recon.
 *
 * Clichés confidentiels : hors racine web, dans storage/private/recon, sous un nom aléatoire
 * (32 caractères hexadécimaux). Chemin en base « recon-private/<nom> ». Jamais d’URL /uploads/… :
 * le fichier n’est servi que par GET /api/recon/images/{id}/file (même habilitation que les flux).
 */
final class ReconImageStorage
{
    public const REL_DIR = 'uploads/recon';

    /** Dossier des clichés confidentiels, relatif à la racine du dépôt (jamais sous public/). */
    public const PRIVATE_DIR = 'storage/private/recon';

    /** Préfixe du chemin stocké en base pour un cliché confidentiel. */
    public const PRIVATE_PREFIX = 'recon-private/';

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

    /** Chemin en base d’un cliché rangé dans le dossier confidentiel. */
    public static function isPrivatePath(string $imagePath): bool
    {
        return str_starts_with(ltrim(str_replace('\\', '/', trim($imagePath)), '/'), self::PRIVATE_PREFIX);
    }

    /** Nom imprévisible pour un cliché confidentiel : 32 hex + extension image. */
    public static function privateFileName(string $ext): string
    {
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?? '');
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        return bin2hex(random_bytes(16)) . '.' . $ext;
    }

    public static function privateRelativeImagePath(string $filename): string
    {
        return self::PRIVATE_PREFIX . basename(str_replace('\\', '/', $filename));
    }

    /**
     * URL d’affichage d’une ligne recon_images : route authentifiée pour un cliché confidentiel
     * (ou déjà rangé hors racine web), /uploads/recon/… pour les autres.
     *
     * @param array<string, mixed> $row
     */
    public static function urlForRow(array $row): ?string
    {
        $path = trim((string) ($row['image_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        $id = (int) ($row['id'] ?? 0);
        if (self::isPrivatePath($path) || !empty($row['is_confidential'])) {
            return $id > 0 ? url('api/recon/images/' . $id . '/file') : null;
        }

        return self::publicUrl(basename(str_replace('\\', '/', $path)));
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
     * Dossier confidentiel (hors public/), avec un .htaccess « deny » au cas où la racine web
     * serait le dépôt entier (ancien hébergement Apache).
     */
    public static function ensurePrivateDir(): ?string
    {
        $abs = base_path(self::PRIVATE_DIR);
        if (!self::makeWritable($abs, 0770)) {
            return null;
        }
        foreach ([dirname($abs), $abs] as $dir) {
            $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!is_file($ht)) {
                @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
            }
        }

        return $abs;
    }

    /**
     * Enregistre le fichier temporaire ; retourne le chemin absolu final ou null.
     * $confidential : dossier hors racine web (nom attendu : privateFileName()).
     */
    public static function storeFromTemp(string $tmpPath, string $filename, bool $confidential = false): ?string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if (!self::isSafeFileName($filename) || $tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }
        $dir = $confidential ? self::ensurePrivateDir() : self::ensureWritableDir();
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
        if (self::isPrivatePath($imagePathOrName)) {
            $abs = base_path(self::PRIVATE_DIR . '/' . $base);

            return is_file($abs) && is_readable($abs) ? $abs : null;
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
        // Original d'avant la correction de couleur (PhotoAutoLevel).
        $base = basename(str_replace('\\', '/', trim($imagePathOrName)));
        if (self::isSafeFileName($base)) {
            @unlink(PhotoAutoLevel::originalPath($base, self::originalsDir()));
        }
    }

    /** Dossier privé des originaux avant correction de couleur (hors racine web). */
    public static function originalsDir(): string
    {
        return base_path(self::PRIVATE_DIR . '/originals');
    }

    /**
     * Range le fichier d’une ligne selon son drapeau confidentiel ; retourne le nouveau image_path
     * (inchangé si le fichier est déjà au bon endroit), ou null si le fichier manque ou ne bouge pas.
     * Sert à la migration des clichés existants et à tout futur changement du drapeau.
     */
    public static function relocate(string $imagePath, bool $confidential): ?string
    {
        $imagePath = trim($imagePath);
        if ($imagePath === '' || self::isPrivatePath($imagePath) === $confidential) {
            return $imagePath !== '' ? $imagePath : null;
        }
        $src = self::absoluteReadable($imagePath);
        if ($src === null) {
            return null;
        }
        $ext = pathinfo($src, PATHINFO_EXTENSION) ?: 'jpg';
        if ($confidential) {
            $dir = self::ensurePrivateDir();
            $name = self::privateFileName($ext);
            $newPath = self::privateRelativeImagePath($name);
        } else {
            $dir = self::ensureWritableDir();
            $name = 'recon_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . (preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'jpg');
            $newPath = self::relativeImagePath($name);
        }
        if ($dir === null) {
            return null;
        }
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!@rename($src, $dest)) {
            if (!@copy($src, $dest)) {
                return null;
            }
            @unlink($src);
        }

        return $newPath;
    }

    /** Type MIME d’un fichier image servi par la route authentifiée. */
    public static function mimeType(string $abs): string
    {
        return match (strtolower(pathinfo($abs, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private static function makeWritable(string $abs, int $mode = 0775): bool
    {
        $abs = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $abs), DIRECTORY_SEPARATOR);
        if ($abs === '') {
            return false;
        }
        if (!is_dir($abs) && !@mkdir($abs, $mode, true) && !is_dir($abs)) {
            return false;
        }

        return is_writable($abs);
    }
}
