<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Portraits opérateur (personnel_profiles.character_portrait_path) en lot, mis en cache pour la requête.
 * La photo de compte / Steam n'est jamais utilisée : seul le portrait opérateur s'affiche.
 */
final class OperatorPortraits
{
    /** @var array<string, ?string> clé "tenant:user" → URL publique ou null */
    private static array $cache = [];

    private static ?bool $hasPortraitColumn = null;

    private static ?bool $hasTenantColumn = null;

    /**
     * @param list<int|string> $userIds
     * @return array<int, string> user_id → URL du portrait (seulement ceux qui en ont un)
     */
    public static function forUsers(int $tenantId, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $missing = array_values(array_filter($ids, static fn (int $id): bool => !array_key_exists($tenantId . ':' . $id, self::$cache)));
        if ($missing !== []) {
            self::load($tenantId, $missing);
        }

        $out = [];
        foreach ($ids as $id) {
            $url = self::$cache[$tenantId . ':' . $id] ?? null;
            if ($url !== null && $url !== '') {
                $out[$id] = $url;
            }
        }

        return $out;
    }

    public static function forUser(int $tenantId, int $userId): ?string
    {
        return self::forUsers($tenantId, [$userId])[$userId] ?? null;
    }

    /**
     * Portrait opérateur pour le téléphone en jeu (ATAK natif) : Arma n'affiche que du JPEG ou du PAA,
     * et cadre l'image dans un carré. On sert donc une vignette carrée JPEG 256 px, recadrée vers le haut
     * (le visage), générée une fois à côté du portrait (uploads/portraits/atak/). Le nom du portrait change
     * à chaque envoi, la vignette suit. Sans portrait, ou si l'image n'est pas lisible : null (le jeu
     * affiche alors les initiales). Jamais la photo de compte ni Steam.
     */
    public static function gameThumbnailUrl(int $tenantId, int $userId): ?string
    {
        return self::gameThumbnail($tenantId, $userId)['url'];
    }

    /**
     * Comme gameThumbnailUrl, avec la raison quand il n'y a pas d'image (renvoyée au jeu, qui l'affiche) :
     *   ok                  vignette (ou portrait JPEG d'origine) servie
     *   no_portrait         aucun portrait opérateur sur la fiche
     *   source_missing      portrait enregistré mais fichier absent du serveur (public/ et storage/uploads)
     *   unsupported_format  PNG / WebP sans conversion possible (GD absent ou image illisible)
     *   write_failed        conversion faite mais vignette impossible à écrire (droits du dossier)
     *
     * Le portrait est cherché dans public/uploads puis storage/uploads (même repli que public/index.php) ;
     * la vignette est écrite dans public/uploads/portraits/atak, sinon storage/uploads/portraits/atak,
     * servie à la même adresse /uploads/portraits/atak/… par le repli de public/index.php.
     *
     * @return array{url: ?string, status: string}
     */
    public static function gameThumbnail(int $tenantId, int $userId): array
    {
        $url = self::forUser($tenantId, $userId);
        if ($url === null) {
            return ['url' => null, 'status' => 'no_portrait'];
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $pos = strpos($path, '/uploads/portraits/');
        $root = function_exists('base_path') ? base_path() : '';
        if ($pos === false || $root === '') {
            return self::isJpeg($url) ? ['url' => $url, 'status' => 'ok'] : ['url' => null, 'status' => 'unsupported_format'];
        }
        $rel = substr($path, $pos + 1);
        if (str_contains($rel, '..')) {
            return ['url' => null, 'status' => 'source_missing'];
        }
        $bases = [$root . '/public', $root . '/storage'];
        $src = null;
        foreach ($bases as $base) {
            if (is_file($base . '/' . $rel)) {
                $src = $base . '/' . $rel;
                break;
            }
        }
        if ($src === null) {
            return ['url' => null, 'status' => 'source_missing'];
        }
        $thumbRel = 'uploads/portraits/atak/' . pathinfo($rel, PATHINFO_FILENAME) . '.jpg';
        $thumbUrl = substr($url, 0, (int) strpos($url, $path) + $pos + 1) . $thumbRel;
        foreach ($bases as $base) {
            // Une vignette vide (écriture interrompue) est refaite.
            if (is_file($base . '/' . $thumbRel) && (int) @filesize($base . '/' . $thumbRel) > 0) {
                return ['url' => $thumbUrl, 'status' => 'ok'];
            }
        }
        $im = self::decode($src);
        if ($im === null) {
            return self::isJpeg($url) ? ['url' => $url, 'status' => 'ok'] : ['url' => null, 'status' => 'unsupported_format'];
        }
        foreach ($bases as $base) {
            if (self::writeSquareJpeg($im, $base . '/' . $thumbRel, 256)) {
                imagedestroy($im);

                return ['url' => $thumbUrl, 'status' => 'ok'];
            }
        }
        imagedestroy($im);

        return self::isJpeg($url) ? ['url' => $url, 'status' => 'ok'] : ['url' => null, 'status' => 'write_failed'];
    }

    /**
     * @return \GdImage|null
     */
    private static function decode(string $src): mixed
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $bin = @file_get_contents($src);
        $im = $bin !== false ? @imagecreatefromstring($bin) : false;

        return $im === false ? null : $im;
    }

    private static function isJpeg(string $url): bool
    {
        return (bool) preg_match('/\.jpe?g$/i', (string) parse_url($url, PHP_URL_PATH));
    }

    private static function writeSquareJpeg(mixed $im, string $dest, int $size): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $side = min($w, $h);
        // Recadrage centré en largeur, au quart supérieur en hauteur : le visage reste dans le cadre.
        $sx = (int) floor(($w - $side) / 2);
        $sy = (int) floor(($h - $side) * 0.25);
        $out = imagecreatetruecolor($size, $size);
        imagefill($out, 0, 0, imagecolorallocate($out, 18, 22, 20));
        imagecopyresampled($out, $im, 0, 0, $sx, $sy, $size, $size, $side, $side);
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ok = is_dir($dir) && is_writable($dir) && @imagejpeg($out, $dest, 88);
        imagedestroy($out);

        return $ok;
    }

    /**
     * @param list<int> $ids
     */
    private static function load(int $tenantId, array $ids): void
    {
        foreach ($ids as $id) {
            self::$cache[$tenantId . ':' . $id] = null;
        }
        try {
            $pdo = Database::getPdo();
            if (!self::portraitColumnReady($pdo)) {
                return;
            }
            $withTenant = self::tenantColumnReady($pdo);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare(
                'SELECT user_id, ' . ($withTenant ? 'tenant_id' : '0 AS tenant_id') . ', character_portrait_path
                 FROM personnel_profiles
                 WHERE user_id IN (' . $placeholders . ")
                   AND character_portrait_path IS NOT NULL AND character_portrait_path <> ''"
            );
            $st->execute($ids);
            // Un même compte peut avoir un dossier par communauté : celui de la communauté courante l'emporte.
            $best = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $isHome = $tenantId > 0 && (int) ($row['tenant_id'] ?? 0) === $tenantId;
                if (!isset($best[$uid]) || ($isHome && !$best[$uid]['home'])) {
                    $best[$uid] = ['row' => $row, 'home' => $isHome];
                }
            }
            foreach ($best as $uid => $pick) {
                $url = function_exists('personnel_operator_portrait_url') ? personnel_operator_portrait_url($pick['row']) : null;
                self::$cache[$tenantId . ':' . $uid] = $url !== null && $url !== '' ? $url : null;
            }
        } catch (Throwable) {
        }
    }

    private static function portraitColumnReady(PDO $pdo): bool
    {
        return self::$hasPortraitColumn ??= self::columnExists($pdo, 'character_portrait_path');
    }

    private static function tenantColumnReady(PDO $pdo): bool
    {
        return self::$hasTenantColumn ??= self::columnExists($pdo, 'tenant_id');
    }

    private static function columnExists(PDO $pdo, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute(['personnel_profiles', $column]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
