<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire de la fenêtre « Rattacher » : celle du widget « Liaisons » (SseLinksSqlite), complétée des colonnes
 * réelles que la recherche lit (résumé, responsable, classification des personnes...), des investigations et de leurs nœuds,
 * des réglages du portail, des éléments liés d'un dossier d'intérêt, et des trois tables de l'étape sse_attachments créées
 * depuis le vrai fichier SQL de la migration. `$withAttach = false` donne la base NON migrée (mode dégradé).
 */
final class SseAttachSqlite
{
    public const TABLES = ['sse_link_attachments', 'sse_attach_tags', 'sse_leads'];

    public static function open(bool $withAttach = true, bool $withLinks = true): PDO
    {
        $pdo = SseLinksSqlite::open($withLinks);
        $add = [
            'sse_cases' => ['summary TEXT', 'created_by INTEGER', 'updated_at TEXT', 'context_id INTEGER DEFAULT 1', 'is_folder INTEGER DEFAULT 0', 'parent_id INTEGER', 'interest_case_id INTEGER', 'closed_at TEXT'],
            'sse_persons' => ['classification TEXT', 'affiliation TEXT', 'circumstances TEXT', 'submitter_user_id INTEGER', 'submitter_callsign TEXT', 'updated_at TEXT'],
            'sse_interest_cases' => ['suspected_alias TEXT', 'opening_reason TEXT', 'updated_at TEXT'],
            'sse_documents' => ['document_type TEXT', 'author_label TEXT', 'updated_at TEXT', 'body TEXT'],
            'sse_field_notes' => ['themes TEXT', 'author_label TEXT', 'updated_at TEXT'],
            'sse_intel_events' => ['author_label TEXT', 'unit_label TEXT'],
            'sse_sites' => ['summary TEXT', 'grid_reference TEXT', 'team_label TEXT', 'updated_at TEXT'],
            'sse_campaigns' => ['missions INTEGER DEFAULT 0', 'updated_at TEXT'],
            'sse_wanted_notices' => ['reason TEXT', 'zone TEXT', 'notice_type TEXT'],
        ];
        foreach ($add as $table => $cols) {
            foreach ($cols as $col) {
                try {
                    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $col);
                } catch (\Throwable) {
                    // déjà là
                }
            }
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_meshes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, title TEXT, summary TEXT, case_id INTEGER, classification TEXT DEFAULT \'encadrement\', status TEXT DEFAULT \'ouvert\', created_by INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_mesh_nodes (id INTEGER PRIMARY KEY AUTOINCREMENT, mesh_id INTEGER NOT NULL, tenant_id INTEGER NOT NULL, kind TEXT DEFAULT \'custom\', label TEXT, detail TEXT, ref_type TEXT, ref_id INTEGER, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_portal_settings (tenant_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT, updated_by INTEGER, PRIMARY KEY (tenant_id, setting_key))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_interest_case_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, interest_case_id INTEGER NOT NULL, link_type TEXT NOT NULL, target_id TEXT NOT NULL DEFAULT \'\', label TEXT NOT NULL, note TEXT, created_by INTEGER, created_at TEXT, UNIQUE (interest_case_id, link_type, target_id))');
        if ($withAttach) {
            self::createAttachTables($pdo);
        }
        \App\Repositories\SseAttachmentRepository::forgetReady();

        return $pdo;
    }

    public static function createAttachTables(PDO $target): void
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(self::sqlFile());
        $in = "'" . implode("', '", self::TABLES) . "'";
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name IN ({$in}) ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $target->exec((string) $sql);
        }
        \App\Repositories\SseAttachmentRepository::forgetReady();
    }

    public static function sqlFile(): string
    {
        return dirname(__DIR__, 2) . '/migrations/2026_11_19_100_sse_attachments.sql';
    }

    public static function close(): void
    {
        \App\Repositories\SseAttachmentRepository::forgetReady();
        SseLinksSqlite::close();
    }
}
