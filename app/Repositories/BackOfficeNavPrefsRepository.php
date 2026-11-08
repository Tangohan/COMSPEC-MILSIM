<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Préférences du back-office par personne ET par communauté (bo_user_nav_prefs) : favoris du menu, pages
 * « Nouveau » déjà vues, disposition de l'accueil. Une ligne par (communauté, compte), lue une fois par page.
 * Table absente (migration pas encore passée) : lecture vide, écriture refusée sans erreur 500.
 */
class BackOfficeNavPrefsRepository
{
    public const COL_FAVORITES = 'favorites_json';
    public const COL_SEEN = 'seen_new_json';
    public const COL_HOME = 'home_json';

    private const COLUMNS = [self::COL_FAVORITES, self::COL_SEEN, self::COL_HOME];

    private ?PDO $pdo;

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array{favorites_json: mixed, seen_new_json: mixed, home_json: mixed}
     */
    public function get(int $tenantId, int $userId): array
    {
        $empty = [self::COL_FAVORITES => null, self::COL_SEEN => null, self::COL_HOME => null];
        if ($tenantId < 1 || $userId < 1) {
            return $empty;
        }
        $k = $tenantId . ':' . $userId;
        if (isset($this->cache[$k])) {
            return $this->cache[$k];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT favorites_json, seen_new_json, home_json FROM bo_user_nav_prefs WHERE tenant_id = ? AND user_id = ? LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = false;
        }
        $out = $empty;
        if (is_array($row)) {
            foreach (self::COLUMNS as $col) {
                $raw = $row[$col] ?? null;
                $out[$col] = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            }
        }

        return $this->cache[$tenantId . ':' . $userId] = $out;
    }

    /** Écrit une colonne (JSON). Faux si la table n'existe pas encore. */
    public function put(int $tenantId, int $userId, string $column, mixed $value): bool
    {
        if ($tenantId < 1 || $userId < 1 || !in_array($column, self::COLUMNS, true)) {
            return false;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        try {
            $st = $this->pdo()->prepare(
                "INSERT INTO bo_user_nav_prefs (tenant_id, user_id, {$column}, updated_at) VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE {$column} = VALUES({$column}), updated_at = NOW()"
            );
            $st->execute([$tenantId, $userId, $json]);
        } catch (\Throwable $e) {
            error_log('[bo-nav-prefs] ' . $e->getMessage());

            return false;
        }
        unset($this->cache[$tenantId . ':' . $userId]);

        return true;
    }
}
