<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire des fichiers SSE : registre d'identité (SseIdentitySqlite), journal des consultations (4330),
 * fiches et recoupements (4340), téléphones et lore (4350), plus deux sources de comptes rendus minimales.
 */
final class SseFilesSqlite
{
    public static function open(): PDO
    {
        $pdo = SseIdentitySqlite::open();
        $root = dirname(__DIR__, 2);
        (require $root . '/bootstrap/sse_file_queries_migration.php')($pdo);
        (require $root . '/bootstrap/sse_files_records_migration.php')($pdo);
        (require $root . '/bootstrap/sse_phone_lore_migration.php')($pdo);
        $pdo->exec('CREATE TABLE IF NOT EXISTS atak_tactical_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER, report_type TEXT, report_number TEXT,
            classification TEXT DEFAULT \'UNCLASSIFIED\', grid_reference TEXT, location_description TEXT, summary TEXT, details TEXT, remarks TEXT, report_timestamp TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_field_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, title TEXT, body TEXT,
            place_label TEXT, grid_reference TEXT, classification TEXT DEFAULT \'interne\', status TEXT DEFAULT \'transmise\', created_at TEXT)');

        return $pdo;
    }

    public static function close(): void
    {
        SseIdentitySqlite::close();
    }
}
