<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Photo d'identité d'un membre (fiche membre › Identité & papiers), jointe à une identité et transmise au jeu.
 *
 * Contrôles : JPEG ou PNG seulement (type lu dans le contenu, jamais l'extension ni le type annoncé), 4 Mo au plus,
 * image réellement lisible. Métadonnées (EXIF, GPS, XMP, commentaires) toujours retirées : réencodage GD recadré au carré
 * (600 px) ; sans GD, blocs de métadonnées retirés du fichier d'origine (repli, sans recadrage).
 *
 * Rangement hors de la racine web, comme les autres fichiers privés du dépôt : storage/uploads/sse-id-photos/<tenant>/
 * <aléa>.<ext> ; chemin enregistré « private:sse-id-photos/<tenant>/<nom> », servi par
 * MemberIdentityPapersController::photo (connexion, même communauté, droit de voir les papiers). Les photos plus
 * anciennes (atelier, public/uploads/sse-id-photos) restent lisibles telles quelles (url()).
 *
 * Jeu : Arma n'affiche pas une image téléchargée (il faudrait -filePatching et un fichier sur le disque de chaque joueur).
 * La photo part donc dans le paquet sous forme d'un portrait réduit (mosaic : 15 × 20 niveaux de gris, 300 caractères
 * hexadécimaux) que la visionneuse du mod redessine case par case ; sans GD sur le serveur web, pas de portrait réduit :
 * le jeu montre le visage du personnage (scène en direct, comme avant).
 */
final class SseIdMemberPhotoService
{
    public const PREFIX = 'private:';
    public const DIR = 'sse-id-photos';
    public const MAX_BYTES = 4 * 1024 * 1024;
    public const SIDE = 600;
    public const MOSAIC_W = 15;
    public const MOSAIC_H = 20;

    public function __construct(private ?string $storageRoot = null, private ?bool $useGd = null)
    {
        $this->storageRoot ??= base_path('storage/uploads');
        $this->useGd ??= function_exists('imagecreatefromstring');
    }

    /**
     * @param mixed $file entrée de $_FILES
     * @return array{ok: bool, path?: string, error?: string, none?: bool}
     */
    public function storeUpload(mixed $file, int $tenantId): array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'none' => true, 'error' => 'Choisissez une photo (JPEG ou PNG).'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'La photo dépasse 4 Mo ou n’a pas pu être reçue.'];
        }

        return $this->storeFile($tmp, $tenantId);
    }

    /** @return array{ok: bool, path?: string, error?: string} */
    public function storeFile(string $source, int $tenantId): array
    {
        if ($tenantId <= 0) {
            return ['ok' => false, 'error' => 'Photo refusée.'];
        }
        $size = @filesize($source);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'La photo dépasse 4 Mo ou n’a pas pu être reçue.'];
        }
        $bytes = (string) @file_get_contents($source);
        $mime = SseIdImageService::sniff($bytes);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return ['ok' => false, 'error' => 'La photo doit être un fichier JPEG ou PNG valide (4 Mo au plus).'];
        }
        if ($this->useGd) {
            $data = self::squareJpeg($bytes);
            if ($data === null) {
                return ['ok' => false, 'error' => 'La photo doit être un fichier JPEG ou PNG valide (4 Mo au plus).'];
            }
            $ext = 'jpg';
        } else {
            $data = SseIdImageService::stripMetadata($bytes, $mime);
            if ($data === null) {
                return ['ok' => false, 'error' => 'La photo doit être un fichier JPEG ou PNG valide (4 Mo au plus).'];
            }
            $ext = $mime === 'image/png' ? 'png' : 'jpg';
        }
        $dir = rtrim((string) $this->storageRoot, '/\\') . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR . $tenantId;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Enregistrement de la photo impossible.'];
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $data) === false) {
            return ['ok' => false, 'error' => 'Enregistrement de la photo impossible.'];
        }

        return ['ok' => true, 'path' => self::PREFIX . self::DIR . '/' . $tenantId . '/' . $name];
    }

    /**
     * Reprend le portrait opérateur du membre (personnel_profiles.character_portrait_path, public/uploads/…) : copié,
     * recadré, nettoyé comme un envoi.
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public function fromPortrait(?string $portraitPath, int $tenantId, ?string $publicDir = null): array
    {
        $rel = ltrim(str_replace('\\', '/', trim((string) $portraitPath)), '/');
        if ($rel === '' || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
            return ['ok' => false, 'error' => 'Ce membre n’a pas de photo de profil.'];
        }
        $abs = rtrim($publicDir ?? base_path('public'), '/\\') . '/' . $rel;
        if (!is_file($abs)) {
            return ['ok' => false, 'error' => 'La photo de profil est introuvable sur le serveur.'];
        }

        return $this->storeFile($abs, $tenantId);
    }

    /** Fichier sur le disque d'une photo (privée ou ancienne publique), null si le chemin n'est pas sûr. */
    public function absolute(?string $path, ?string $publicDir = null): ?string
    {
        $path = trim((string) $path);
        if (str_starts_with($path, self::PREFIX)) {
            $rel = substr($path, strlen(self::PREFIX));
            if (preg_match('#^' . self::DIR . '/\d+/[a-f0-9]{16}\.(jpg|png)$#', $rel) !== 1) {
                return null;
            }

            return rtrim((string) $this->storageRoot, '/\\') . '/' . $rel;
        }
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if ($rel === '' || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
            return null;
        }

        return rtrim($publicDir ?? base_path('public'), '/\\') . '/' . $rel;
    }

    /** Communauté d'une photo privée (null : chemin public ou invalide). */
    public static function tenantOf(?string $path): ?int
    {
        return preg_match('#^' . preg_quote(self::PREFIX, '#') . self::DIR . '/(\d+)/#', (string) $path, $m) === 1 ? (int) $m[1] : null;
    }

    /** Adresse d'affichage : photo privée servie par le contrôleur, ancienne photo publique telle quelle. */
    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, self::PREFIX)) {
            $rel = substr($path, strlen(self::PREFIX) + strlen(self::DIR) + 1);

            return (string) url('back-office/sse-id-photos/' . $rel);
        }

        return (string) url($path);
    }

    /**
     * Portrait réduit pour le jeu : 15 × 20 cases, 16 niveaux de gris (0 noir … f blanc), contraste étiré, centre de
     * l'image au format 3:4. Null sans GD ou image illisible.
     */
    public static function mosaic(string $absPath): ?string
    {
        if (!function_exists('imagecreatefromstring') || !is_file($absPath)) {
            return null;
        }
        $src = @imagecreatefromstring((string) @file_get_contents($absPath));
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = self::MOSAIC_W / self::MOSAIC_H;
        $sw = $w;
        $sh = $h;
        if ($w / max(1, $h) > $ratio) {
            $sw = (int) round($h * $ratio);
        } else {
            $sh = (int) round($w / $ratio);
        }
        $dst = imagecreatetruecolor(self::MOSAIC_W, self::MOSAIC_H);
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $sw, 2), intdiv($h - $sh, 2), self::MOSAIC_W, self::MOSAIC_H, $sw, $sh);
        $lum = [];
        for ($y = 0; $y < self::MOSAIC_H; $y++) {
            for ($x = 0; $x < self::MOSAIC_W; $x++) {
                $c = imagecolorat($dst, $x, $y);
                $lum[] = 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
            }
        }
        imagedestroy($src);
        imagedestroy($dst);
        $min = min($lum);
        $span = max(1.0, max($lum) - $min);
        $out = '';
        foreach ($lum as $l) {
            $out .= dechex((int) min(15, max(0, round(15 * ($l - $min) / $span))));
        }

        return $out;
    }

    /** Réencodage GD : métadonnées perdues, recadrage carré au centre, 600 px au plus, JPEG. */
    private static function squareJpeg(string $bytes): ?string
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = min(self::SIDE, $side);
        $dst = imagecreatetruecolor($out, $out);
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $out, $out, $side, $side);
        ob_start();
        $ok = imagejpeg($dst, null, 88);
        $data = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $ok && $data !== '' ? $data : null;
    }
}
