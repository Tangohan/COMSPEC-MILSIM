<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire des fiches PDF : avis de recherche (SseWantedSqlite), fiches personnes minimales et les trois tables
 * de l'étape 4640, créées depuis le vrai fichier SQL de la migration (traduit par MysqlDdlSqlitePdo).
 */
final class SseSheetSqlite
{
    public const TABLES = ['sse_doc_issues', 'sse_doc_drafts', 'sse_doc_phrases'];

    public static function open(bool $withSheetTables = true): PDO
    {
        $pdo = SseWantedSqlite::open();
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_persons (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER DEFAULT 1, status TEXT DEFAULT \'civil\', last_name TEXT DEFAULT \'\', first_name TEXT DEFAULT \'\', alias TEXT, sex_apparent TEXT, age_estimated INTEGER, birth_date TEXT, birth_place TEXT, nationality TEXT, language_spoken TEXT, id_document_present INTEGER DEFAULT 0, id_document_type TEXT, id_document_number TEXT, distinguishing_marks TEXT, affiliation TEXT, circumstances TEXT, statements TEXT, weapons_json TEXT, equipment_json TEXT, grid_reference TEXT, location_description TEXT, submitter_callsign TEXT, primary_photo_id INTEGER, subject_id TEXT, seek_stage TEXT, acquisition_quality_avg INTEGER, bio_key TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_person_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, person_id INTEGER, image_path TEXT, angle TEXT, photo_type TEXT, quality INTEGER, created_at TEXT)');
        if ($withSheetTables) {
            self::createSheetTables($pdo);
        }
        \App\Repositories\SseSheetRepository::forgetReady();

        return $pdo;
    }

    public static function createSheetTables(PDO $target): void
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
        return dirname(__DIR__, 2) . '/migrations/2026_11_16_100_sse_documents.sql';
    }

    public static function close(): void
    {
        SseWantedSqlite::close();
    }
}
