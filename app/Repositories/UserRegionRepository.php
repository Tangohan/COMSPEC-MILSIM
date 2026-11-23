<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AccountRegions;
use PDO;

/**
 * Région déclarée par un compte (user_regions) : un code de AccountRegions, rien d'autre. Jamais d'adresse IP.
 * Table absente (étape non lancée) : lectures vides, écriture refusée sans erreur.
 */
class UserRegionRepository
{
    public const SOURCE_DECLARED = 'declared';

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** @return array{region_code: string, source: string, updated_at: string}|null */
    public function get(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare('SELECT region_code, source, updated_at FROM user_regions WHERE user_id = ?');
            $st->execute([$userId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r ? ['region_code' => (string) $r['region_code'], 'source' => (string) $r['source'], 'updated_at' => (string) $r['updated_at']] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Enregistre la région choisie ; false si le code n'est pas dans le catalogue ou si l'écriture échoue. */
    public function save(int $userId, string $code, string $at): bool
    {
        if ($userId < 1 || !AccountRegions::isValid($code)) {
            return false;
        }
        try {
            $up = $this->pdo()->prepare('UPDATE user_regions SET region_code = ?, source = ?, updated_at = ? WHERE user_id = ?');
            $up->execute([$code, self::SOURCE_DECLARED, $at, $userId]);
            if ($up->rowCount() > 0) {
                return true;
            }
            $has = $this->pdo()->prepare('SELECT 1 FROM user_regions WHERE user_id = ?');
            $has->execute([$userId]);
            if ($has->fetchColumn() !== false) {
                return true;
            }
            $this->pdo()->prepare('INSERT INTO user_regions (user_id, region_code, source, updated_at) VALUES (?, ?, ?, ?)')
                ->execute([$userId, $code, self::SOURCE_DECLARED, $at]);

            return true;
        } catch (\Throwable $e) {
            error_log('[user-region] enregistrement impossible : ' . $e->getMessage());

            return false;
        }
    }

    public function clear(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        try {
            $this->pdo()->prepare('DELETE FROM user_regions WHERE user_id = ?')->execute([$userId]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
