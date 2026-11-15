<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire des alertes SSE : fiches PDF et avis de recherche (SseSheetSqlite) plus les tables de l'étape 4830,
 * créées depuis le vrai fichier SQL de la migration (traduit par MysqlDdlSqlitePdo, puis rejoué sur la base du test).
 */
final class SseAlertSqlite
{
    public const TABLES = ['sse_alert_links', 'sse_alert_reports', 'sse_alert_proposals', 'sse_alert_acks', 'sse_service_emblems'];

    public static function open(bool $withAlertTables = true): PDO
    {
        $pdo = SseSheetSqlite::open();
        if ($withAlertTables) {
            self::createAlertTables($pdo);
        }
        \App\Repositories\SseAlertRepository::forgetReady();

        return $pdo;
    }

    public static function createAlertTables(PDO $target): void
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
        return dirname(__DIR__, 2) . '/migrations/2026_11_20_100_sse_alerts.sql';
    }

    public static function close(): void
    {
        SseSheetSqlite::close();
        \App\Repositories\SseAlertRepository::forgetReady();
    }
}
