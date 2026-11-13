<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cache de quelques secondes partagé entre les requêtes PHP-FPM (APCu si présent, sinon un fichier par clé dans
 * storage/cache/short) pour les lectures que tous les joueurs d'une communauté sondent en même temps : unités,
 * marqueurs, repères. 40 joueurs qui demandent la même liste toutes les 2 à 8 s ne refont plus 40 fois la même
 * lecture : une requête la calcule, les autres la relisent pendant le TTL.
 *
 * Génération : bump($ns) rend périmées d'un coup toutes les clés construites avec gen($ns) (écriture d'un marqueur
 * → la liste suivante est relue en base, sans attendre la fin du TTL).
 *
 * Désactivé en CLI (tests, cron, worker) : chaque appel y calcule la valeur, comme avant. setEnabled() pour les tests.
 */
final class ShortCache
{
    private static ?bool $enabled = null;

    public static function setEnabled(?bool $on): void
    {
        self::$enabled = $on;
    }

    public static function enabled(): bool
    {
        return self::$enabled ?? PHP_SAPI !== 'cli';
    }

    /**
     * @template T
     * @param callable(): T $compute
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $compute): mixed
    {
        if (!self::enabled() || $ttl < 1) {
            return $compute();
        }
        $hit = self::fetch($key);
        if ($hit !== null) {
            return $hit[0];
        }
        $value = $compute();
        self::store($key, [$value], $ttl);

        return $value;
    }

    /** Valeur gardée (null : absente, expirée ou cache désactivé). Ne jamais garder null comme valeur. */
    public static function get(string $key): mixed
    {
        if (!self::enabled()) {
            return null;
        }

        return self::fetch($key)[0] ?? null;
    }

    public static function put(string $key, mixed $value, int $ttl): void
    {
        if (!self::enabled() || $ttl < 1 || $value === null) {
            return;
        }
        self::store($key, [$value], $ttl);
    }

    /** Numéro de génération courant d'un espace de clés (0 tant que rien n'a été écrit). */
    public static function gen(string $ns): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $hit = self::fetch('gen:' . $ns);

        return is_int($hit[0] ?? null) ? $hit[0] : 0;
    }

    /** Écriture : les clés construites avec gen($ns) sont relues au prochain appel. */
    public static function bump(string $ns): void
    {
        if (!self::enabled()) {
            return;
        }
        // Horodatage en µs plutôt qu'un compteur : pas de lecture-écriture concurrente à protéger.
        self::store('gen:' . $ns, [(int) (microtime(true) * 1_000_000)], 3600);
    }

    /** @return array{0: mixed}|null */
    private static function fetch(string $key): ?array
    {
        try {
            if (self::apcuOn()) {
                $ok = false;
                $v = apcu_fetch('comspec_short:' . $key, $ok);

                return $ok && is_array($v) ? $v : null;
            }
            $raw = @file_get_contents(self::file($key));
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $data = @unserialize($raw, ['allowed_classes' => false]);
            if (!is_array($data) || !isset($data['exp']) || (float) $data['exp'] < microtime(true)) {
                return null;
            }

            return [$data['v'] ?? null];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array{0: mixed} $value */
    private static function store(string $key, array $value, int $ttl): void
    {
        try {
            if (self::apcuOn()) {
                apcu_store('comspec_short:' . $key, $value, $ttl);

                return;
            }
            $file = self::file($key);
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return;
            }
            // Écriture dans un fichier temporaire puis rename : un lecteur ne voit jamais un fichier à moitié écrit.
            $tmp = $file . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, serialize(['exp' => microtime(true) + $ttl, 'v' => $value[0]])) === false) {
                return;
            }
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
            }
            StaleFileSweeper::maybe($dir, '*.c', 3600);
        } catch (\Throwable) {
        }
    }

    private static function apcuOn(): bool
    {
        return function_exists('apcu_fetch') && function_exists('apcu_enabled') && apcu_enabled();
    }

    private static function file(string $key): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/short/' . sha1($key) . '.c';
    }
}
