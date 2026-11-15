<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rangement des images SSE sensibles (portraits d'identité, signatures, fonds de papiers, copies de la photothèque)
 * HORS du dossier public : storage/private/sse-images/<dossier>/<communauté>_<aléa>.<ext>, lu seulement par les routes
 * contrôlées du portail SSE (/atak/sse/fichiers/identites/{id}/photo|signature, /photos/{id}/image, /documents/{id}/fond).
 *
 * Deux formes de chemin gardées en base :
 *  - « sse-private/<dossier>/<fichier> » : espace privé (tout nouvel envoi) ;
 *  - « uploads/<dossier>/<fichier> »     : emplacement hérité sous public/, lu le temps de la migration des fichiers
 *    (tools/sse-move-images-private.php).
 * Les lecteurs passent par absolute() : jamais de « .. », jamais un fichier hors du dossier attendu.
 */
final class SseImageStorage
{
    /** Dossier privé, relatif à la racine du dépôt. */
    public const PRIVATE_DIR = 'storage/private/sse-images';

    public const PATH_PREFIX = 'sse-private/';

    /**
     * Dossiers d'images sensibles (« sse-wanted » : pièces jointes des avis de recherche, servies par la route des avis ;
     * « sse-id-checks » : photo du papier d'un contrôle d'identité, servie par la route du contrôle ;
     * « sse-service-emblems » : emblème téléversé d'un service demandeur, servi par la route des services ;
     * « sse-alert-reports » : photo jointe à un signalement, servie aux gestionnaires par la route des signalements).
     */
    public const FOLDERS = ['sse-id-photos', 'sse-id-signatures', 'sse-id-backgrounds', 'sse-wanted', 'sse-id-checks', 'sse-service-emblems', 'sse-alert-reports'];

    private const NAME = '[0-9]{1,10}_[a-f0-9]{16}\.(?:jpg|png|webp)';

    public static function privateRoot(?string $privateDir = null): string
    {
        return rtrim($privateDir ?? base_path(self::PRIVATE_DIR), '/\\');
    }

    /** Chemin gardé en base pour un fichier rangé dans l'espace privé. */
    public static function privatePath(string $folder, string $name): string
    {
        return self::PATH_PREFIX . $folder . '/' . $name;
    }

    /** Le chemin gardé en base désigne-t-il l'espace privé ? */
    public static function isPrivatePath(string $path): bool
    {
        return preg_match('#^' . preg_quote(self::PATH_PREFIX, '#') . '(?:' . implode('|', self::FOLDERS) . ')/' . self::NAME . '$#', $path) === 1;
    }

    /** Emplacement hérité (sous public/uploads) d'un dossier sensible. */
    public static function isLegacyPath(string $path): bool
    {
        return preg_match('#^uploads/(?:' . implode('|', self::FOLDERS) . ')/' . self::NAME . '$#', $path) === 1;
    }

    /** Image sensible rangée dans l'un des deux emplacements : toujours affichée par une route contrôlée, jamais par une adresse publique. */
    public static function isStoredPath(string $path): bool
    {
        return self::isPrivatePath($path) || self::isLegacyPath($path);
    }

    /** Équivalent privé d'un chemin hérité (null si le chemin n'est pas un chemin hérité sensible). */
    public static function toPrivatePath(string $legacy): ?string
    {
        return self::isLegacyPath($legacy) ? self::PATH_PREFIX . substr($legacy, strlen('uploads/')) : null;
    }

    /**
     * Fichier sur disque (null si le chemin est étranger, traverse un dossier, ou si le fichier est absent). Accepte les
     * deux emplacements ; tout chemin hors forme stricte est refusé, y compris « .. » et les octets nuls.
     */
    public static function absolute(string $path, ?string $publicDir = null, ?string $privateDir = null): ?string
    {
        if (self::isPrivatePath($path)) {
            $base = self::privateRoot($privateDir);
            $rel = substr($path, strlen(self::PATH_PREFIX));
        } elseif (self::isLegacyPath($path)) {
            $base = rtrim($publicDir ?? base_path('public'), '/\\') . DIRECTORY_SEPARATOR . 'uploads';
            $rel = substr($path, strlen('uploads/'));
        } else {
            return null;
        }
        $root = realpath($base);
        $abs = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . $rel);
        if ($root === false || $abs === false || !is_file($abs) || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $abs;
    }

    /**
     * Adresse d'affichage d'une image d'identité : la route protégée pour un fichier privé (jamais d'adresse publique),
     * l'adresse publique héritée sinon. $kind : photo|signature.
     *
     * @param array<string, mixed> $identity
     */
    public static function identityUrl(array $identity, string $kind): ?string
    {
        $path = trim((string) ($identity[$kind . '_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        if (self::isStoredPath($path)) {
            $id = (int) ($identity['id'] ?? 0);

            return $id > 0 && function_exists('url') ? (string) url('atak/sse/fichiers/identites/' . $id . '/' . $kind) : null;
        }

        return function_exists('url') ? (string) url($path) : $path;
    }

    /**
     * Adresse d'affichage de la photo d'une fiche (écrans de fiche et de recherche) : route protégée pour un fichier privé,
     * adresse publique héritée sinon.
     */
    public static function photoSrc(string $path, int $identityId): ?string
    {
        if (self::isStoredPath($path)) {
            return $identityId > 0 && function_exists('url') ? (string) url('atak/sse/fichiers/identites/' . $identityId . '/photo') : null;
        }

        return function_exists('user_media_public_url') ? user_media_public_url($path) : null;
    }

    /** Chemin exposé à un client externe (pack de papiers du jeu) : jamais le chemin d'une image sensible (privée ou héritée). */
    public static function publicOnly(?string $path): ?string
    {
        return $path !== null && !self::isStoredPath($path) ? $path : null;
    }

    /**
     * Fichier image d'un chemin quelconque gardé en base : espace privé SSE, ou n'importe quel fichier image sous
     * public/uploads (photos de fiches, terrain, ATAK). Null si le chemin sort de ces dossiers ou si le fichier manque.
     */
    public static function readable(string $path, ?string $publicDir = null, ?string $privateDir = null): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if (self::isPrivatePath($path)) {
            return self::absolute($path, $publicDir, $privateDir);
        }
        if (preg_match('#(?:^|/)(uploads/[A-Za-z0-9_\-./]+\.(?:png|jpe?g|webp))$#i', $path, $m) !== 1 || str_contains($m[1], '..')) {
            return null;
        }
        $file = rtrim($publicDir ?? base_path('public'), '/\\') . '/' . $m[1];

        return is_file($file) ? $file : null;
    }

    /**
     * Adresse d'une image affichée dans une page déjà protégée (signature tracée au SEEK II) : donnée intégrée pour un fichier
     * privé (aucune adresse de fichier), adresse publique héritée sinon. Chaîne vide si le fichier privé manque.
     */
    public static function inlineSrc(string $path): string
    {
        if (self::isStoredPath($path)) {
            return (string) self::dataUri($path);
        }

        return function_exists('url') ? (string) url($path) : $path;
    }

    /** Adresse du fond d'un papier : route protégée si le fichier est privé. @param array<string, mixed> $doc */
    public static function backgroundUrl(array $doc): ?string
    {
        $path = trim((string) ($doc['background_path'] ?? ''));
        if ($path === '') {
            return null;
        }
        if (self::isStoredPath($path)) {
            $id = (int) ($doc['id'] ?? 0);

            return $id > 0 && function_exists('url') ? (string) url('atak/sse/fichiers/documents/' . $id . '/fond') : null;
        }

        return function_exists('url') ? (string) url($path) : $path;
    }

    /** Type MIME d'après l'extension d'un fichier déjà validé par absolute(). */
    public static function mimeFor(string $abs): string
    {
        return ['png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'][strtolower(pathinfo($abs, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /**
     * Octets d'une image après contrôle du vrai type (les premiers octets, pas l'extension) : null si le contenu n'est pas
     * un PNG, JPEG ou WebP. Sert aux routes contrôlées pour annoncer un Content-Type vérifié.
     */
    public static function verifiedMime(string $abs): ?string
    {
        $h = @fopen($abs, 'rb');
        if ($h === false) {
            return null;
        }
        $head = (string) fread($h, 16);
        fclose($h);

        return match (true) {
            str_starts_with($head, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
            strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }

    /**
     * Image en donnée intégrée pour les écrans hors portail SSE (fiche du titulaire, espace d'administration) : seulement un
     * fichier image reconnu par son contenu, 2 Mo au plus ; sinon null.
     */
    public static function dataUri(string $path, ?string $publicDir = null, ?string $privateDir = null): ?string
    {
        $abs = self::absolute($path, $publicDir, $privateDir);
        if ($abs === null || filesize($abs) > 2 * 1024 * 1024) {
            return null;
        }
        $mime = self::verifiedMime($abs);

        return $mime !== null ? 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs)) : null;
    }
}
