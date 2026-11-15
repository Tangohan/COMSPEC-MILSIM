<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire des avis de recherche : fichiers SSE (SseFilesSqlite) plus les quatre tables de l'étape 4600, créées
 * depuis le vrai fichier SQL de la migration (traduit par MysqlDdlSqlitePdo, puis rejoué sur la base du test).
 */
final class SseWantedSqlite
{
    public const TABLES = ['sse_wanted_notices', 'sse_identity_events', 'sse_detections', 'sse_wanted_settings'];

    public static function open(bool $withWantedTables = true): PDO
    {
        $pdo = SseFilesSqlite::open();
        if ($withWantedTables) {
            self::createWantedTables($pdo);
        }
        \App\Repositories\SseWantedNoticeRepository::forgetReady();
        \App\Repositories\SseIdentityEventRepository::forgetReady();

        return $pdo;
    }

    public static function createWantedTables(PDO $target): void
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(self::sqlFile());
        $in = "'" . implode("', '", self::TABLES) . "'";
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name IN ({$in}) ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $target->exec((string) $sql);
        }
    }

    public static function sqlFile(): string
    {
        return dirname(__DIR__, 2) . '/migrations/2026_11_15_120_sse_wanted_notices.sql';
    }

    public static function close(): void
    {
        SseFilesSqlite::close();
    }
}
