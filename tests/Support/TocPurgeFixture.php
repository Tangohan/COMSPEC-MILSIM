<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base SQLite des tests de suppression et de purge du poste TOC web : tables de la carte telles qu'en production (avant la
 * migration), puis étape 4770 jouée pour de vrai (colonnes deleted_at, deleted_by, delete_batch et table atak_toc_purges).
 */
final class TocPurgeFixture
{
    public const NOW = '2026-11-15 14:00:00';

    public static function now(): int
    {
        return (int) strtotime(self::NOW . ' UTC');
    }

    /** @param bool $migrate false : base d'avant la migration 4770 (mode dégradé) */
    public static function open(bool $migrate = true): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS `atak_markers` (
          `id` int unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `map_id` int unsigned NOT NULL DEFAULT 1,
          `layer_id` int unsigned NOT NULL DEFAULT 1, `marker_data` text NOT NULL, `arma_name` varchar(255) DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP, `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`), KEY `tm_markers` (`tenant_id`,`map_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS `atak_map_shapes` (
          `id` int unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `map_id` int unsigned NOT NULL DEFAULT 1,
          `mission_id` varchar(128) DEFAULT NULL, `shape_uid` varchar(64) NOT NULL, `type` varchar(32) NOT NULL, `label` varchar(255) DEFAULT NULL,
          `color` varchar(32) DEFAULT \'#3388ff\', `stroke` int unsigned DEFAULT 2, `fill_opacity` decimal(3,2) DEFAULT 0.15,
          `created_by` varchar(128) DEFAULT NULL, `visible_to` json DEFAULT NULL, `geometry` json NOT NULL, `meta` json DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP, `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`), KEY `tm_shapes` (`tenant_id`,`map_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS `atak_pings` (
          `id` int unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `map_id` int unsigned NOT NULL DEFAULT 1,
          `author` varchar(255) NOT NULL, `pos_x` decimal(15,4) NOT NULL, `pos_y` decimal(15,4) NOT NULL, `message` text DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `tm_pings` (`tenant_id`,`map_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS `atak_units` (
          `id` int unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `map_id` int unsigned NOT NULL DEFAULT 1,
          `call_sign` varchar(255) NOT NULL, `role` varchar(255) DEFAULT NULL, `group_name` varchar(96) DEFAULT NULL,
          `pos_x` decimal(15,4) DEFAULT NULL, `pos_y` decimal(15,4) DEFAULT NULL, `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`), KEY `tm_units` (`tenant_id`,`map_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if ($migrate) {
            self::migrate($pdo);
        }

        return $pdo;
    }

    /** Joue l'étape 4770 comme run-migrations.php : fichier inclus avec $pdo et $root. */
    public static function migrate(PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require_once $root . '/database/steps/_context.php';
        ob_start();
        try {
            require $root . '/database/steps/4770_atak_toc_soft_delete.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    public static function marker(PDO $pdo, int $tenant, int $map, array $data, ?string $arma = null, ?string $at = null): int
    {
        $at ??= '2026-11-15 12:30:00';
        $pdo->prepare('INSERT INTO atak_markers (tenant_id, map_id, layer_id, marker_data, arma_name, created_at, updated_at) VALUES (?, ?, 1, ?, ?, ?, ?)')
            ->execute([$tenant, $map, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $arma, $at, $at]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $geometry @param array<string, mixed>|null $meta */
    public static function shape(PDO $pdo, int $tenant, int $map, string $type, array $geometry, ?string $by = 'Alpha', ?array $meta = null, ?string $at = null): int
    {
        $at ??= '2026-11-15 12:30:00';
        static $n = 0;
        $n++;
        $pdo->prepare('INSERT INTO atak_map_shapes (tenant_id, map_id, shape_uid, type, label, created_by, geometry, meta, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tenant, $map, 'uid' . $n . '-' . bin2hex(random_bytes(3)), $type, 'T' . $n, $by, json_encode($geometry), $meta === null ? null : json_encode($meta), $at, $at]);

        return (int) $pdo->lastInsertId();
    }

    public static function ping(PDO $pdo, int $tenant, int $map, string $author = 'Bravo', string $at = '2026-11-15 13:50:00'): int
    {
        $pdo->prepare('INSERT INTO atak_pings (tenant_id, map_id, author, pos_x, pos_y, message, created_at) VALUES (?, ?, ?, 1500, 1500, ?, ?)')
            ->execute([$tenant, $map, $author, '[Info]', $at]);

        return (int) $pdo->lastInsertId();
    }

    public static function unit(PDO $pdo, int $tenant, int $map, string $callSign, string $at, string $group = 'Alpha 1'): int
    {
        $pdo->prepare('INSERT INTO atak_units (tenant_id, map_id, call_sign, group_name, pos_x, pos_y, updated_at) VALUES (?, ?, ?, ?, 2000, 2000, ?)')
            ->execute([$tenant, $map, $callSign, $group, $at]);

        return (int) $pdo->lastInsertId();
    }
}
