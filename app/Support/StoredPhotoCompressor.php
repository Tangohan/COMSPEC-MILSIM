<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Allège les photos terrain (captures Arma en PNG 4K, parfois 10 Mo pièce) : côté long ramené à
 * 1920 px, réencodage JPEG qualité 80.
 *
 * Le fichier garde son nom, donc son identifiant et son URL : un ancien « .png » peut contenir du
 * JPEG (navigateurs, GD et Discord lisent le contenu, pas l’extension). Aucune perte possible :
 * écriture dans un fichier temporaire du même dossier, relu et vérifié, puis renommé par-dessus
 * l’original seulement s’il est nettement plus léger. Un fichier déjà allégé n’est pas réencodé.
 */
final class StoredPhotoCompressor
{
    public const MAX_EDGE = 1920;

    public const QUALITY = 80;

    /** JPEG déjà à la bonne taille et sous ce poids par pixel : laissé tel quel (pas de réencodage). */
    public const SKIP_JPEG_BYTES_PER_PIXEL = 0.45;

    /** Gain minimal pour remplacer un fichier (sinon on garde l’original). */
    public const MIN_SAVING_RATIO = 0.10;

    /** Au-delà, GD manquerait de mémoire : fichier ignoré et signalé. */
    public const MAX_SOURCE_PIXELS = 40_000_000;

    /** Fichier modifié il y a moins longtemps : envoi peut-être en cours, on repasse plus tard. */
    public const MIN_AGE_SECONDS = 600;

    /**
     * Dossiers des photos terrain, relatifs à la racine du dépôt (non récursif).
     *
     * @var list<string>
     */
    public const PHOTO_DIRS = [
        'public/uploads/recon',
        'storage/uploads/recon',
        ReconImageStorage::PRIVATE_DIR,
        ReconImageStorage::PRIVATE_DIR . '/originals',
        'public/uploads/sse',
        'public/uploads/sse/evidence',
        'public/uploads/intel',
    ];

    /**
     * Allège un fichier en place.
     *
     * @return array{status: 'compressed'|'skipped'|'error', reason: string, before: int, after: int}
     */
    public static function compressFile(
        string $absPath,
        int $maxEdge = self::MAX_EDGE,
        int $quality = self::QUALITY,
        int $minAgeSeconds = 0,
        bool $dryRun = false
    ): array {
        $before = is_file($absPath) ? (int) @filesize($absPath) : 0;
        $out = ['status' => 'skipped', 'reason' => '', 'before' => $before, 'after' => $before];
        if ($before < 32 || !is_readable($absPath)) {
            return ['reason' => 'illisible'] + $out;
        }
        if ($minAgeSeconds > 0 && (time() - (int) @filemtime($absPath)) < $minAgeSeconds) {
            return ['reason' => 'récent'] + $out;
        }
        $info = @getimagesize($absPath);
        if (!is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return ['reason' => 'pas une image'] + $out;
        }
        [$w, $h, $type] = [(int) $info[0], (int) $info[1], (int) ($info[2] ?? 0)];
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return ['reason' => 'format ignoré'] + $out;
        }
        $fits = max($w, $h) <= $maxEdge;
        if ($type === IMAGETYPE_JPEG && $fits && ($before / ($w * $h)) <= self::SKIP_JPEG_BYTES_PER_PIXEL) {
            return ['reason' => 'déjà allégé'] + $out;
        }
        if ($w * $h > self::MAX_SOURCE_PIXELS) {
            return ['status' => 'error', 'reason' => 'image trop grande pour GD'] + $out;
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return ['status' => 'error', 'reason' => 'GD absent'] + $out;
        }
        if (!is_writable(dirname($absPath)) || !is_writable($absPath)) {
            return ['status' => 'error', 'reason' => 'droits en écriture'] + $out;
        }

        $tmp = self::encodeToTemp($absPath, $w, $h, $maxEdge, $quality);
        if ($tmp === null) {
            return ['status' => 'error', 'reason' => 'réencodage impossible'] + $out;
        }
        $after = (int) @filesize($tmp);
        if ($after >= (int) floor($before * (1 - self::MIN_SAVING_RATIO))) {
            @unlink($tmp);

            return ['reason' => 'gain trop faible'] + $out;
        }
        if ($dryRun) {
            @unlink($tmp);

            return ['status' => 'compressed', 'reason' => 'simulation', 'before' => $before, 'after' => $after];
        }
        $mtime = (int) @filemtime($absPath);
        $perms = @fileperms($absPath);
        if (!@rename($tmp, $absPath)) {
            @unlink($tmp);

            return ['status' => 'error', 'reason' => 'remplacement impossible'] + $out;
        }
        if ($perms !== false) {
            @chmod($absPath, $perms & 0777);
        }
        if ($mtime > 0) {
            @touch($absPath, $mtime);
        }
        clearstatcache(true, $absPath);

        return ['status' => 'compressed', 'reason' => '', 'before' => $before, 'after' => (int) @filesize($absPath)];
    }

    /**
     * Photo tout juste reçue (fichier temporaire d’envoi) : réécrite en JPEG allégé, au même chemin.
     * Retourne true si le fichier est désormais un JPEG (l’appelant range alors la photo en .jpg).
     */
    public static function compressUploadToJpeg(string $tmpPath): bool
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return false;
        }
        $info = @getimagesize($tmpPath);
        if (!is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return false;
        }
        [$w, $h, $type] = [(int) $info[0], (int) $info[1], (int) ($info[2] ?? 0)];
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return false;
        }
        $before = (int) @filesize($tmpPath);
        $fits = max($w, $h) <= self::MAX_EDGE;
        if ($type === IMAGETYPE_JPEG && $fits && ($before / ($w * $h)) <= self::SKIP_JPEG_BYTES_PER_PIXEL) {
            return true;
        }
        if ($w * $h > self::MAX_SOURCE_PIXELS || !function_exists('imagejpeg')) {
            return $type === IMAGETYPE_JPEG;
        }
        $tmp = self::encodeToTemp($tmpPath, $w, $h, self::MAX_EDGE, self::QUALITY);
        if ($tmp === null) {
            return $type === IMAGETYPE_JPEG;
        }
        if (!@rename($tmp, $tmpPath)) {
            if (!@copy($tmp, $tmpPath)) {
                @unlink($tmp);

                return $type === IMAGETYPE_JPEG;
            }
            @unlink($tmp);
        }
        clearstatcache(true, $tmpPath);

        return true;
    }

    /**
     * Fichiers images des dossiers photo (chemins absolus), les plus anciens d’abord.
     *
     * @return list<string>
     */
    public static function listPhotoFiles(?string $root = null): array
    {
        $root = rtrim($root ?? base_path(''), '/\\');
        $files = [];
        foreach (self::PHOTO_DIRS as $rel) {
            $dir = $root . DIRECTORY_SEPARATOR . $rel;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || !preg_match('/\.(jpe?g|png|webp)$/i', $name)) {
                    continue;
                }
                $abs = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_file($abs)) {
                    $files[$abs] = (int) @filemtime($abs);
                }
            }
        }
        asort($files);

        return array_keys($files);
    }

    /** Écrit la version allégée à côté de la source ; null si GD échoue ou si le résultat est illisible. */
    private static function encodeToTemp(string $absPath, int $w, int $h, int $maxEdge, int $quality): ?string
    {
        $raw = @file_get_contents($absPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $src = @imagecreatefromstring($raw);
        unset($raw);
        if ($src === false) {
            return null;
        }
        $scale = min(1.0, $maxEdge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }
        // Fond noir sous une éventuelle transparence (JPEG n’en a pas).
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 0, 0, 0));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        imageinterlace($dst, true);

        $tmp = dirname($absPath) . DIRECTORY_SEPARATOR . '.' . basename($absPath) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = @imagejpeg($dst, $tmp, max(30, min(95, $quality)));
        imagedestroy($dst);
        if (!$ok || !is_file($tmp)) {
            @unlink($tmp);

            return null;
        }
        $check = @getimagesize($tmp);
        if (!is_array($check) || (int) ($check[2] ?? 0) !== IMAGETYPE_JPEG || (int) $check[0] !== $nw || (int) $check[1] !== $nh) {
            @unlink($tmp);

            return null;
        }

        return $tmp;
    }
}
