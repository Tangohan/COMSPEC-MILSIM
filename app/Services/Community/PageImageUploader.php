<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Envoi d'une image pour l'éditeur de blocs (vitrine publique et bloc personnalisé de l'accueil du back-office) :
 * JPG, PNG ou WebP, 6 Mo au plus, réencodée (métadonnées retirées, largeur bornée) et rangée sous
 * assets/img/communities/{slug}/page/. Les droits et le jeton CSRF sont vérifiés par l'appelant.
 */
final class PageImageUploader
{
    public const MAX_BYTES = 6 * 1024 * 1024;

    /**
     * @param mixed $file entrée de $_FILES
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function store(mixed $file, string $slug): array
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            return self::fail(422, 'Définissez d’abord l’identifiant public de la communauté.');
        }
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return self::fail(422, 'Envoi impossible. Vérifiez la taille (6 Mo maximum) et réessayez.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp) || (int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return self::fail(422, 'Image trop volumineuse ou invalide (6 Mo maximum).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return self::fail(422, 'Format non pris en charge. Utilisez JPG, PNG ou WebP.');
        }
        $rel = 'assets/img/communities/' . $slug . '/page';
        $dir = base_path('public/' . $rel);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return self::fail(500, 'Le stockage des images n’est pas disponible pour le moment.');
        }
        $name = gmdate('Ymd') . '-' . bin2hex(random_bytes(6));
        $ext = self::writeImage($tmp, $mime, $dir . '/' . $name);
        if ($ext === null) {
            return self::fail(422, 'Impossible de traiter cette image. Essayez avec un autre fichier.');
        }
        $path = $rel . '/' . $name . '.' . $ext;

        return ['status' => 200, 'body' => ['ok' => true, 'path' => $path, 'url' => PublicPageBuilder::imageUrl($path)]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private static function fail(int $status, string $message): array
    {
        return ['status' => $status, 'body' => ['ok' => false, 'error' => $message]];
    }

    /** Réencode l'image (retire les métadonnées, borne la largeur) ; retourne l'extension écrite. */
    private static function writeImage(string $tmp, string $mime, string $destNoExt): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            $ext = match ($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };

            return @copy($tmp, $destNoExt . '.' . $ext) ? $ext : null;
        }
        $bin = @file_get_contents($tmp);
        $im = $bin !== false ? @imagecreatefromstring($bin) : false;
        if (!$im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $maxW = 2400;
        if ($w > $maxW) {
            $nh = max(1, (int) round($h * $maxW / $w));
            $scaled = imagecreatetruecolor($maxW, $nh);
            if ($mime === 'image/png') {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }
        if ($mime === 'image/png') {
            imagesavealpha($im, true);
            $ok = @imagepng($im, $destNoExt . '.png', 7);
            $ext = 'png';
        } else {
            $ok = @imagejpeg($im, $destNoExt . '.jpg', 86);
            $ext = 'jpg';
        }
        imagedestroy($im);

        return $ok ? $ext : null;
    }
}
