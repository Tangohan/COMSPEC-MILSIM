<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Base sqlite en mémoire du widget « Liaisons » : registre d'identités, papiers, contrôles, fiches SEEK II, photos, avis de
 * recherche et documents émis (SseSheetSqlite), plus les tables minimales des dossiers, fiches de renseignement, sites,
 * transmissions et campagnes, et la vraie table des liens de provenance créée depuis le fichier SQL de l'étape 4690.
 */
final class SseLinksSqlite
{
    public const TABLE = 'sse_entity_links';

    public static function open(bool $withLinksTable = true): PDO
    {
        $pdo = SseSheetSqlite::open();
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, title TEXT, status TEXT DEFAULT \'ouvert\', classification TEXT DEFAULT \'interne\', unlock_code_hash TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_case_persons (id INTEGER PRIMARY KEY AUTOINCREMENT, case_id INTEGER, person_id INTEGER, tenant_id INTEGER, linked_by INTEGER, note TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_interest_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, temporary_designation TEXT, status TEXT DEFAULT \'a_qualifier\', created_by INTEGER, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, case_id INTEGER, title TEXT, status TEXT, classification TEXT DEFAULT \'interne\', created_by INTEGER, validated_by INTEGER, created_at TEXT)');
        foreach (['case_id INTEGER', 'interest_case_id INTEGER', 'author_user_id INTEGER'] as $col) {
            try {
                $pdo->exec('ALTER TABLE sse_field_notes ADD COLUMN ' . $col);
            } catch (\Throwable) {
                // déjà là
            }
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_sites (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, name TEXT, site_type TEXT, status TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_intel_events (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, event_type TEXT, summary TEXT, event_time TEXT, created_at TEXT, case_id INTEGER, interest_case_id INTEGER)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, name TEXT, type TEXT, status TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_cross_decisions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, interest_case_id INTEGER, person_id INTEGER, decision TEXT, reason TEXT, decided_by INTEGER, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_relations (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, case_id INTEGER, from_type TEXT, from_id INTEGER, to_type TEXT, to_id INTEGER, relation TEXT, author_label TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT, action TEXT, object_type TEXT, object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        if ($withLinksTable) {
            self::createLinksTable($pdo);
        }
        \App\Repositories\SseEntityLinkRepository::forgetReady();

        return $pdo;
    }

    public static function createLinksTable(PDO $target): void
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(self::sqlFile());
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name = '" . self::TABLE . "' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $target->exec((string) $sql);
        }
    }

    public static function sqlFile(): string
    {
        return dirname(__DIR__, 2) . '/migrations/2026_11_16_110_sse_entity_links.sql';
    }

    public static function close(): void
    {
        \App\Repositories\SseEntityLinkRepository::forgetReady();
        SseSheetSqlite::close();
    }
}
