<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Session;
use App\Services\GameEquipment\PaaDecoder;
use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Icônes des objets observés en jeu (table game_item_icons, migrations/20261018000002_game_item_icons.sql).
 *
 * COMSPEC Link (« Item.Icons ») demande quelles classes n’ont pas encore d’icône (missing), lit le .paa de la
 * config `picture` dans le PBO du mod chez le joueur et l’envoie une fois ; le site le convertit en PNG
 * (256 px au plus), le range par empreinte sous storage/game-item-icons/ (un même PNG n’est stocké qu’une fois)
 * et note la classe. Les échecs (introuvable, illisible) sont notés aussi pour ne pas redemander avant 14 jours.
 */
final class GameItemIconRepository
{
    use LazyDatabaseConnection;

    public const MAX_SIDE = 256;
    public const MAX_PAA_BYTES = 1024 * 1024;
    public const MAX_MISSING_PER_CALL = 25;
    public const RETRY_FAILED_DAYS = 14;
    public const STATUSES = ['ok', 'not_found', 'invalid'];

    private static ?bool $ready = null;

    /** @var array<string, string> classe en minuscules → empreinte, pour la communauté de la requête */
    private static array $tenantHashes = [];
    private static int $tenantHashesFor = 0;

    public function __construct(?PDO $pdo = null, private ?string $storageDir = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['game_item_icons']);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    public static function validClass(mixed $class): ?string
    {
        if (!is_string($class)) {
            return null;
        }
        $class = trim($class);

        return preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.\-]{0,127}$/', $class) === 1 ? $class : null;
    }

    /**
     * Classes de la liste qui n’ont pas d’icône (ni d’échec récent) pour cette communauté.
     *
     * @param list<mixed> $classes
     * @return list<string>
     */
    public function missing(int $tenantId, array $classes): array
    {
        $want = [];
        foreach (array_slice($classes, 0, 200) as $c) {
            $c = self::validClass($c);
            if ($c !== null) {
                $want[strtolower($c)] = $c;
            }
        }
        if ($tenantId < 1 || $want === []) {
            return [];
        }
        $ph = implode(', ', array_fill(0, count($want), '?'));
        $st = $this->pdo()->prepare(
            'SELECT class_name FROM game_item_icons
             WHERE tenant_id = ? AND class_name IN (' . $ph . ')
               AND (status = \'ok\' OR updated_at > DATE_SUB(NOW(), INTERVAL ' . self::RETRY_FAILED_DAYS . ' DAY))'
        );
        $st->execute(array_merge([$tenantId], array_values($want)));
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $known) {
            unset($want[strtolower((string) $known)]);
        }

        return array_slice(array_values($want), 0, self::MAX_MISSING_PER_CALL);
    }

    /**
     * Convertit le .paa reçu en PNG, le range et l’attache à la classe. Retourne le statut enregistré.
     *
     * @return array{status: string, hash?: string, error?: string}
     */
    public function storePaa(int $tenantId, string $class, string $paa, string $picturePath): array
    {
        if (strlen($paa) > self::MAX_PAA_BYTES) {
            $this->mark($tenantId, $class, 'invalid', null, null, null, $picturePath);

            return ['status' => 'invalid', 'error' => 'paa_too_large'];
        }
        try {
            $png = self::paaToPng($paa);
        } catch (\Throwable $e) {
            $this->mark($tenantId, $class, 'invalid', null, null, null, $picturePath);

            return ['status' => 'invalid', 'error' => substr($e->getMessage(), 0, 40)];
        }
        $hash = hash('sha256', $png['body']);
        $file = $this->pathForHash($hash);
        if (!is_file($file)) {
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('icon_dir_unwritable');
            }
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $png['body']) === false || !@rename($tmp, $file)) {
                @unlink($tmp);
                throw new \RuntimeException('icon_write_failed');
            }
        }
        $this->mark($tenantId, $class, 'ok', $hash, $png['width'], $png['height'], $picturePath);

        return ['status' => 'ok', 'hash' => $hash];
    }

    public function mark(int $tenantId, string $class, string $status, ?string $hash, ?int $w, ?int $h, string $picturePath): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'invalid';
        }
        $picturePath = mb_substr(preg_replace('/[^\x20-\x7E]/', '', $picturePath) ?? '', 0, 255);
        // Une icône déjà valide n’est jamais écrasée par un échec venu d’un autre joueur.
        $this->pdo()->prepare(
            'INSERT INTO game_item_icons (tenant_id, class_name, status, icon_hash, width, height, picture_path, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                icon_hash = IF(VALUES(status) = \'ok\' OR status <> \'ok\', VALUES(icon_hash), icon_hash),
                width = IF(VALUES(status) = \'ok\' OR status <> \'ok\', VALUES(width), width),
                height = IF(VALUES(status) = \'ok\' OR status <> \'ok\', VALUES(height), height),
                picture_path = IF(VALUES(status) = \'ok\' OR status <> \'ok\', VALUES(picture_path), picture_path),
                updated_at = IF(VALUES(status) = \'ok\' OR status <> \'ok\', VALUES(updated_at), updated_at),
                status = IF(VALUES(status) = \'ok\' OR status <> \'ok\', VALUES(status), status)'
        )->execute([$tenantId, $class, $status, $hash, $w, $h, $picturePath]);
    }

    /**
     * URL d’icône par classe pour l’affichage (classes sans icône absentes du résultat).
     *
     * @param list<string> $classNames
     * @return array<string, string>
     */
    public function iconUrls(int $tenantId, array $classNames): array
    {
        $classNames = array_values(array_unique(array_filter($classNames, static fn ($c): bool => is_string($c) && $c !== '')));
        if ($tenantId < 1 || $classNames === [] || !$this->schemaReady()) {
            return [];
        }
        $out = [];
        foreach (array_chunk($classNames, 500) as $chunk) {
            $ph = implode(', ', array_fill(0, count($chunk), '?'));
            $st = $this->pdo()->prepare(
                'SELECT class_name, icon_hash FROM game_item_icons
                 WHERE tenant_id = ? AND status = \'ok\' AND icon_hash IS NOT NULL AND class_name IN (' . $ph . ')'
            );
            $st->execute(array_merge([$tenantId], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(string) $row['class_name']] = self::urlForHash((string) $row['icon_hash']);
            }
        }

        return $out;
    }

    public static function urlForHash(string $hash): string
    {
        $path = 'equipment/icones/' . $hash . '.png';

        return function_exists('url') ? url($path) : '/' . $path;
    }

    /**
     * URL d’icône d’une classe pour la communauté connectée (null sans icône). Toutes les icônes de la communauté
     * sont lues en une requête au premier appel, puis gardées pour la requête HTTP : appelable objet par objet.
     */
    public static function urlFor(string $class): ?string
    {
        $class = strtolower(trim($class));
        $tenantId = (int) Session::get('tenant_id');
        if ($class === '' || $tenantId < 1) {
            return null;
        }
        if (self::$tenantHashesFor !== $tenantId) {
            self::$tenantHashesFor = $tenantId;
            self::$tenantHashes = [];
            try {
                $repo = new self();
                if ($repo->schemaReady()) {
                    $st = $repo->pdo()->prepare(
                        'SELECT class_name, icon_hash FROM game_item_icons
                         WHERE tenant_id = ? AND status = \'ok\' AND icon_hash IS NOT NULL LIMIT 20000'
                    );
                    $st->execute([$tenantId]);
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        self::$tenantHashes[strtolower((string) $row['class_name'])] = (string) $row['icon_hash'];
                    }
                }
            } catch (\Throwable) {
                // Pas d’icône plutôt qu’une page en erreur.
            }
        }
        $hash = self::$tenantHashes[$class] ?? null;

        return $hash !== null ? self::urlForHash($hash) : null;
    }

    public function tenantHasHash(int $tenantId, string $hash): bool
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return false;
        }
        $st = $this->pdo()->prepare('SELECT 1 FROM game_item_icons WHERE tenant_id = ? AND icon_hash = ? LIMIT 1');
        $st->execute([$tenantId, $hash]);

        return (bool) $st->fetchColumn();
    }

    /** Chemin du PNG (l’empreinte est validée : aucun chemin ne vient du client). */
    public function pathForHash(string $hash): string
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new \InvalidArgumentException('invalid_hash');
        }
        $root = $this->storageDir ?? base_path('storage/game-item-icons');

        return rtrim($root, '/') . '/' . substr($hash, 0, 2) . '/' . $hash . '.png';
    }

    /**
     * @return array{body: string, width: int, height: int}
     */
    public static function paaToPng(string $paa): array
    {
        $img = PaaDecoder::decode($paa, self::MAX_SIDE * 2);
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        if ($scale < 1.0) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $small = imagecreatetruecolor($nw, $nh);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
            imagecopyresampled($small, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $img = $small;
            $w = $nw;
            $h = $nh;
        }
        ob_start();
        imagepng($img, null, 9);
        $body = (string) ob_get_clean();
        if ($body === '') {
            throw new \RuntimeException('png_encode_failed');
        }

        return ['body' => $body, 'width' => $w, 'height' => $h];
    }
}
