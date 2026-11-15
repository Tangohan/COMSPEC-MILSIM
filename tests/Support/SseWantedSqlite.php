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

    /** Tables de l'étape 4690 (codification, signalement, pièces jointes). */
    public const CODE_TABLES = ['sse_wanted_notice_extras', 'sse_wanted_attachments'];

    /** Table de l'étape 4700 (service demandeur, validation, renouvellement). */
    public const PROCESS_TABLES = ['sse_wanted_notice_meta'];

    public static function open(bool $withWantedTables = true, bool $withProcessTable = true): PDO
    {
        $pdo = SseFilesSqlite::open();
        // Fiche opérateur lue par SseClearanceService (niveau d'habilitation de la session).
        $pdo->exec('CREATE TABLE IF NOT EXISTS personnel_profiles (id INTEGER PRIMARY KEY, user_id INT NOT NULL, tenant_id INT, clearance_level TEXT)');
        if ($withWantedTables) {
            self::createWantedTables($pdo, $withProcessTable);
        }
        \App\Repositories\SseWantedNoticeRepository::forgetReady();
        \App\Repositories\SseWantedMetaRepository::forgetReady();
        \App\Repositories\SseWantedExtrasRepository::forgetReady();
        \App\Repositories\SseWantedAttachmentRepository::forgetReady();
        \App\Repositories\SseIdentityEventRepository::forgetReady();

        return $pdo;
    }

    public static function createWantedTables(PDO $target, bool $withProcessTable = true): void
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $inspector = new \App\Support\Migrations\SchemaInspector($scratch);
        $inspector->runSqlFile(self::sqlFile());
        $inspector->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_16_130_sse_wanted_codes.sql');
        $inspector->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_18_100_sse_wanted_process.sql');
        $in = "'" . implode("', '", array_merge(self::TABLES, self::CODE_TABLES, $withProcessTable ? self::PROCESS_TABLES : [])) . "'";
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
