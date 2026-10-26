<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mémoire persistante des sondes de schéma (« la table / colonne existe ») entre requêtes PHP-FPM.
 *
 * Les caches static ou d’instance ne vivent qu’une requête : sur les chemins sondés par le jeu
 * (positions, unités, commandes du poste) chaque appel relisait information_schema.
 * Ici, seul un résultat POSITIF est mémorisé (APCu si présent, sinon un fichier vide dans
 * storage/cache/schema-ready) pendant TTL secondes : une table encore absente est re-sondée à
 * chaque requête, comme avant, et apparaît dès que la migration l’a créée.
 * La clé intègre la base courante et, si fourni, la date du fichier de migration : une migration
 * modifiée est rejouée au déploiement suivant. Vider storage/cache suffit à tout re-sonder.
 */
final class SchemaReadyCache
{
    private const TTL = 3600;

    /** @var array<string, true> mémo de la requête courante */
    private static array $memo = [];

    /**
     * @param callable(): bool $probe sonde réelle (information_schema, migration idempotente…)
     */
    public static function remember(string $key, callable $probe, ?string $migrationFile = null): bool
    {
        $full = self::fullKey($key, $migrationFile);
        if (isset(self::$memo[$full]) || self::hit($full)) {
            self::$memo[$full] = true;

            return true;
        }
        $ok = (bool) $probe();
        if ($ok) {
            self::$memo[$full] = true;
            self::store($full);
        }

        return $ok;
    }

    private static function fullKey(string $key, ?string $migrationFile): string
    {
        $db = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
        $ver = '';
        if ($migrationFile !== null && $migrationFile !== '') {
            $ver = (string) (@filemtime($migrationFile) ?: '');
        }

        return 'comspec_schema_ready:' . sha1($db . '|' . $key . '|' . $ver);
    }

    private static function apcuOn(): bool
    {
        return function_exists('apcu_fetch') && function_exists('apcu_enabled') && apcu_enabled();
    }

    private static function file(string $full): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/schema-ready/' . substr($full, strrpos($full, ':') + 1) . '.ok';
    }

    private static function hit(string $full): bool
    {
        try {
            if (self::apcuOn()) {
                return apcu_fetch($full) === 1;
            }
            $f = self::file($full);
            $m = @filemtime($f);

            return $m !== false && (time() - $m) < self::TTL;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function store(string $full): void
    {
        try {
            if (self::apcuOn()) {
                apcu_store($full, 1, self::TTL);

                return;
            }
            $f = self::file($full);
            $dir = dirname($f);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @touch($f);
        } catch (\Throwable) {
        }
    }
}
