<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use App\Core\Database;
use PDO;

/**
 * Chiffres de terrain de toute la plateforme pour l’accueil public : téléphones ATAK attribués,
 * heures de jeu remontées par le mod et kilomètres parcourus en jeu.
 *
 * Seuls des totaux sortent d’ici : aucune ligne par joueur ni par communauté. Une source absente
 * (table pas encore migrée, base indisponible) vaut 0, jamais une valeur estimée. Le calcul est
 * mis en cache 15 minutes dans storage/cache.
 */
final class PlatformFieldMetricsService
{
    private const CACHE_TTL = 900;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null, private ?string $cacheFile = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{phones: int, play_hours: int, distance_km: int}
     */
    public function totals(bool $fresh = false): array
    {
        $cached = $fresh ? null : $this->readCache();
        if ($cached !== null) {
            return $cached;
        }
        $totals = $this->compute();
        $this->writeCache($totals);

        return $totals;
    }

    /** @return array{phones: int, play_hours: int, distance_km: int} */
    private function compute(): array
    {
        // Un ATAK attribué = un téléphone du mod rattaché à un joueur (les sessions web ne comptent pas).
        $phones = $this->scalar(
            "SELECT COUNT(*) FROM atak_terminals WHERE terminal_type <> 'web' AND user_id IS NOT NULL"
        );
        // Temps de jeu total mesuré par le serveur Arma (même source que les métriques joueurs).
        $playSeconds = $this->scalar(
            "SELECT COALESCE(SUM(seconds), 0) FROM atak_screen_time WHERE kind = 'play' AND item_key = 'TOTAL'"
        );
        // Distance totale : à pied, véhicule terrestre, mer, air et chute libre (comme la fiche joueur).
        $meters = $this->scalar(
            'SELECT COALESCE(SUM(foot_m + land_m + sea_m + air_m + halo_m), 0) FROM atak_distance_stats'
        );

        return [
            'phones' => $phones,
            'play_hours' => intdiv($playSeconds, 3600),
            'distance_km' => intdiv($meters, 1000),
        ];
    }

    private function scalar(string $sql): int
    {
        try {
            $pdo = $this->pdo ??= Database::getPdo();
            $st = $pdo->query($sql);
            $v = $st !== false ? $st->fetchColumn() : 0;
        } catch (\Throwable) {
            return 0;
        }

        return max(0, (int) $v);
    }

    private function cachePath(): string
    {
        return $this->cacheFile ?? base_path('storage/cache/platform_field_metrics.json');
    }

    /** @return array{phones: int, play_hours: int, distance_km: int}|null */
    private function readCache(): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path) || filemtime($path) < time() - self::CACHE_TTL) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'phones' => max(0, (int) ($data['phones'] ?? 0)),
            'play_hours' => max(0, (int) ($data['play_hours'] ?? 0)),
            'distance_km' => max(0, (int) ($data['distance_km'] ?? 0)),
        ];
    }

    /** @param array{phones: int, play_hours: int, distance_km: int} $totals */
    private function writeCache(array $totals): void
    {
        $dir = dirname($this->cachePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->cachePath(), json_encode($totals), LOCK_EX);
    }
}
