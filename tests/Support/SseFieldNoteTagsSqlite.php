<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire des étiquettes de fiches : celle du widget « Liaisons » (SseLinksSqlite), la table des fiches de
 * renseignement complétée de ses colonnes réelles, la table des pièces jointes, et les trois tables de l'étape 4830 créées
 * depuis le VRAI fichier SQL de la migration (traduit par MysqlDdlSqlitePdo). $withTagTables = false : base non migrée.
 */
final class SseFieldNoteTagsSqlite
{
    public const TABLES = ['sse_tags', 'sse_tag_links', 'sse_field_note_settings'];

    public static function open(bool $withTagTables = true): PDO
    {
        $pdo = SseLinksSqlite::open();
        foreach ([
            'context_id INTEGER DEFAULT 1', 'note_kind TEXT DEFAULT \'FRM\'', 'themes TEXT DEFAULT \'[]\'', 'observed_at TEXT', 'pos_x REAL', 'pos_y REAL', 'pos_z REAL',
            'lat REAL', 'lng REAL', 'urgency TEXT DEFAULT \'routine\'', 'intel_source TEXT', 'source_reliability TEXT DEFAULT \'C\'', 'info_credibility INTEGER DEFAULT 3',
            'origin TEXT DEFAULT \'web\'', 'author_label TEXT', 'author_steam_id TEXT', 'author_unit TEXT', 'triage_note TEXT', 'triaged_by INTEGER', 'triaged_at TEXT',
            'idempotency_key TEXT', 'updated_at TEXT',
        ] as $col) {
            try {
                $pdo->exec('ALTER TABLE sse_field_notes ADD COLUMN ' . $col);
            } catch (\Throwable) {
                // déjà là
            }
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_field_note_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, note_id INTEGER NOT NULL, file_path TEXT, original_name TEXT, mime_type TEXT, byte_size INTEGER, kind TEXT DEFAULT \'photo\', caption TEXT, grid_reference TEXT, pos_x REAL, pos_y REAL, pos_z REAL, author_label TEXT, created_at TEXT)');
        if ($withTagTables) {
            self::createTagTables($pdo);
        }
        \App\Repositories\SseTagRepository::forgetReady();

        return $pdo;
    }

    public static function createTagTables(PDO $target): void
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(self::sqlFile());
        $in = "'" . implode("', '", self::TABLES) . "'";
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name IN ({$in}) ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $target->exec((string) $sql);
        }
        \App\Repositories\SseTagRepository::forgetReady();
    }

    public static function sqlFile(): string
    {
        return dirname(__DIR__, 2) . '/migrations/2026_11_21_100_sse_tags.sql';
    }

    public static function close(): void
    {
        \App\Repositories\SseTagRepository::forgetReady();
        SseLinksSqlite::close();
    }
}
