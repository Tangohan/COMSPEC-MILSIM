<?php

declare(strict_types=1);

namespace App\Support\PublicCv;

use App\Services\Media\ImageCompressionService;
use App\Support\TerrainUploadedImage;

/**
 * Bannière personnelle de la page publique du joueur (fond de l’en-tête).
 *
 * Mêmes contrôles que les autres images du site (format réel lu dans le fichier, photo iPhone refusée, poids
 * maximal borné par la configuration PHP), puis l’image est toujours ré-encodée en JPEG redimensionné : les
 * métadonnées de l’appareil (position GPS, modèle…) ne sont jamais publiées. Sans bibliothèque d’images sur le
 * serveur, l’envoi est refusé plutôt que de publier le fichier d’origine.
 *
 * Stockage public : public/uploads/public-cv/ (repli storage/uploads/public-cv/, servi à la même adresse).
 */
final class PublicCvBannerStorage
{
    public const DIR = 'uploads/public-cv';
    public const MAX_BYTES = 10 * 1024 * 1024;
    private const TARGET_BYTES = 900_000;
    private const MAX_EDGE_PX = 2400;
    private const MIN_WIDTH_PX = 800;

    public function __construct(private ?string $rootDir = null)
    {
    }

    private function root(): string
    {
        return rtrim($this->rootDir ?? (function_exists('base_path') ? base_path() : dirname(__DIR__, 3)), '/\\');
    }

    public static function hintText(): string
    {
        $mo = max(1, (int) floor(self::effectiveMaxBytes() / (1024 * 1024)));

        return 'JPG, PNG ou WebP, ' . $mo . ' Mo maximum, au moins ' . self::MIN_WIDTH_PX . ' pixels de large. Format paysage conseillé (par exemple 2400 × 900). Les informations de l’appareil photo (dont la position) sont retirées.';
    }

    public static function effectiveMaxBytes(): int
    {
        $php = min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));

        return $php < 1 ? self::MAX_BYTES : min(self::MAX_BYTES, $php);
    }

    /**
     * @param array<string, mixed> $file entrée de $_FILES
     * @return array{path: ?string, error: ?string} path null et error null : aucun fichier envoyé
     */
    public function store(int $userId, array $file): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || ($err === UPLOAD_ERR_OK && trim((string) ($file['tmp_name'] ?? '')) === '')) {
            return ['path' => null, 'error' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? self::tooLargeMessage()
                : 'L’envoi de l’image a échoué. Réessayez avec un JPG ou un PNG plus léger.'];
        }
        if ($userId < 1) {
            return ['path' => null, 'error' => 'Connectez-vous pour envoyer une bannière.'];
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_file($tmp) || !is_readable($tmp)) {
            return ['path' => null, 'error' => 'Fichier image invalide.'];
        }
        $size = (int) ($file['size'] ?? 0) ?: (int) @filesize($tmp);
        if ($size <= 0) {
            return ['path' => null, 'error' => 'Fichier image vide.'];
        }
        if ($size > self::effectiveMaxBytes()) {
            return ['path' => null, 'error' => self::tooLargeMessage()];
        }
        if (self::looksLikeHeic($tmp, (string) ($file['name'] ?? ''))) {
            return ['path' => null, 'error' => 'Cette photo est au format iPhone. Enregistrez-la en JPG ou PNG, puis renvoyez-la.'];
        }
        // Format lu dans le contenu du fichier, jamais d’après son nom.
        if (TerrainUploadedImage::detectExtension($tmp) === null) {
            return ['path' => null, 'error' => 'Formats acceptés : JPG, PNG ou WebP.'];
        }
        if (!function_exists('imagecreatefromstring')) {
            return ['path' => null, 'error' => 'Les bannières ne peuvent pas être enregistrées pour le moment. Réessayez plus tard.'];
        }
        $dims = @getimagesize($tmp);
        if (!is_array($dims) || (int) $dims[0] < 1) {
            return ['path' => null, 'error' => 'Fichier image invalide.'];
        }
        if ((int) $dims[0] < self::MIN_WIDTH_PX) {
            return ['path' => null, 'error' => 'Image trop petite : choisissez une image d’au moins ' . self::MIN_WIDTH_PX . ' pixels de large.'];
        }

        $dirAbs = $this->writableDir();
        if ($dirAbs === null) {
            return ['path' => null, 'error' => 'Les bannières ne peuvent pas être enregistrées pour le moment. Réessayez plus tard.'];
        }
        $name = 'b-' . $userId . '-' . bin2hex(random_bytes(8)) . '.jpg';
        $abs = $dirAbs . DIRECTORY_SEPARATOR . $name;
        $written = (new ImageCompressionService())->compressToJpeg($tmp, $abs, self::TARGET_BYTES, self::MAX_EDGE_PX);
        if ($written === null || !is_file($abs)) {
            @unlink($abs);

            return ['path' => null, 'error' => 'Impossible de traiter cette image. Essayez avec un JPG.'];
        }

        return ['path' => self::DIR . '/' . $name, 'error' => null];
    }

    public function delete(?string $relativePath): void
    {
        if (!self::isStoredPath($relativePath)) {
            return;
        }
        foreach (['public', 'storage'] as $base) {
            $abs = $this->root() . DIRECTORY_SEPARATOR . $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $relativePath);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    /** Chemin enregistré par store() : dossier attendu et nom de fichier sans caractère spécial. */
    public static function isStoredPath(?string $relativePath): bool
    {
        $p = str_replace('\\', '/', trim((string) $relativePath));

        return preg_match('#^' . preg_quote(self::DIR, '#') . '/b-[0-9]+-[a-f0-9]{16}\.jpg$#', $p) === 1;
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        if (!self::isStoredPath($relativePath)) {
            return null;
        }
        $p = str_replace('\\', '/', trim((string) $relativePath));
        if (function_exists('user_media_public_url')) {
            return user_media_public_url($p);
        }

        return function_exists('asset_url') ? asset_url($p) : '/' . $p;
    }

    private function writableDir(): ?string
    {
        foreach (['public', 'storage'] as $base) {
            $abs = $this->root() . DIRECTORY_SEPARATOR . $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::DIR);
            if ((is_dir($abs) || @mkdir($abs, 0775, true) || is_dir($abs)) && is_writable($abs)) {
                return $abs;
            }
        }

        return null;
    }

    private static function tooLargeMessage(): string
    {
        $mo = max(1, (int) floor(self::effectiveMaxBytes() / (1024 * 1024)));

        return 'Image trop lourde (maximum ' . $mo . ' Mo). Choisissez une image plus légère.';
    }

    private static function looksLikeHeic(string $tmpPath, string $originalName): bool
    {
        $n = strtolower($originalName);
        if (str_ends_with($n, '.heic') || str_ends_with($n, '.heif')) {
            return true;
        }
        $head = (string) @file_get_contents($tmpPath, false, null, 0, 16);
        if (strlen($head) < 12) {
            return false;
        }
        $brand = strtolower(substr($head, 4, 12));

        return str_contains($brand, 'heic') || str_contains($brand, 'heif') || str_contains($brand, 'mif1') || str_contains($brand, 'msf1');
    }

    private static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));
        if ($raw === '' || $raw === '-1') {
            return self::MAX_BYTES;
        }
        $n = (float) $raw;
        $mul = match (strtolower(substr($raw, -1))) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };

        return (int) round($n * $mul);
    }
}
