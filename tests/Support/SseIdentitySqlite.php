<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use PDO;

/**
 * Base sqlite en mémoire pour COMSPEC IDENTITY : communautés 1 et 2, tables créées par la vraie migration
 * (bootstrap/atak_sse_identity_migration.php, rendu sqlite), branchée sur Database le temps d'un test.
 */
final class SseIdentitySqlite
{
    private static ?PDO $saved = null;

    public static function open(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Alpha', 'alpha'), (2, 'Bravo', 'bravo')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, display_name TEXT)');
        self::migrate($pdo);

        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $current = $prop->getValue();
        self::$saved = $current instanceof PDO ? $current : null;
        $prop->setValue(null, $pdo);

        return $pdo;
    }

    public static function migrate(PDO $pdo): string
    {
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_migration.php';
        $out = '';
        $migrate($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        $papers = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_member_papers_migration.php';
        $papers($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        $lot2 = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_lot2_migration.php';
        $lot2($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        $lot6 = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_lot6_migration.php';
        $lot6($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        $real = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_real_countries_migration.php';
        $real($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        $images = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_images_migration.php';
        $images($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        $suite = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_suite_migration.php';
        $suite($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });

        $editor = require dirname(__DIR__, 2) . '/bootstrap/atak_sse_editor_library_migration.php';
        $editor($pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });

        return $out;
    }

    public static function close(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, self::$saved);
        self::$saved = null;
    }
}
