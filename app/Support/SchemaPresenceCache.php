<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/**
 * Présence de tables / colonnes (information_schema) mémorisée dans storage/cache/schema-flags.json.
 *
 * Seules les réponses positives sont gardées : une colonne ajoutée par migration ne disparaît pas,
 * alors qu’une réponse négative doit être revérifiée (la migration peut passer entre deux appels).
 * Évite plusieurs requêtes information_schema par position reçue du jeu.
 * Réservé à la connexion par défaut (une PDO injectée, en test, interroge toujours la base).
 */
final class SchemaPresenceCache
{
    /** @var array<string, true>|null */
    private static ?array $flags = null;

    public static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $key = self::dbName() . ':' . $table . '.' . $column;
        if (self::has($key)) {
            return true;
        }
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);
            $ok = (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
        if ($ok) {
            self::remember($key);
        }

        return $ok;
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        $key = self::dbName() . ':' . $table;
        if (self::has($key)) {
            return true;
        }
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);
            $ok = (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
        if ($ok) {
            self::remember($key);
        }

        return $ok;
    }

    private static function dbName(): string
    {
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';

        return is_string($name) ? $name : '';
    }

    private static function path(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/schema-flags.json';
    }

    private static function has(string $key): bool
    {
        if (self::$flags === null) {
            self::$flags = [];
            $raw = @file_get_contents(self::path());
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (is_string($k) && $v === true) {
                        self::$flags[$k] = true;
                    }
                }
            }
        }

        return isset(self::$flags[$key]);
    }

    private static function remember(string $key): void
    {
        self::$flags ??= [];
        $path = self::path();
        // Fusion avec ce qu’un autre processus a pu ajouter depuis notre lecture.
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                if (is_string($k) && $v === true) {
                    self::$flags[$k] = true;
                }
            }
        }
        self::$flags[$key] = true;
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        // Écriture atomique (fichier temporaire + rename) : un lecteur concurrent ne voit jamais un JSON tronqué.
        $tmp = $path . '.' . getmypid() . '.tmp';
        $json = json_encode(self::$flags);
        if (is_string($json) && @file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $path);
        } else {
            @unlink($tmp);
        }
    }
}
