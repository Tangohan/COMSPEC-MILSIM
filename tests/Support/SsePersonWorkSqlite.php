<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\SsePersonWorkRepository;
use PDO;

/**
 * Base sqlite en mémoire de la page Personnes : registre d'identité et avis de recherche (SseWantedSqlite), fiches personnes avec
 * les colonnes de l'étape 4760, photos, relevés biométriques, rattachements aux dossiers, réglages, journal d'audit et table des
 * ouvertures (créée depuis le vrai fichier SQL de la migration).
 */
final class SsePersonWorkSqlite
{
    public const PERSON_COLUMNS = <<<'SQL'
id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER DEFAULT 1, status TEXT DEFAULT 'civil',
last_name TEXT DEFAULT '', first_name TEXT DEFAULT '', alias TEXT, sex_apparent TEXT, age_estimated INTEGER, birth_date TEXT,
birth_place TEXT, nationality TEXT, language_spoken TEXT, id_document_present INTEGER DEFAULT 0, id_document_type TEXT,
id_document_number TEXT, distinguishing_marks TEXT, affiliation TEXT, circumstances TEXT, statements TEXT, confidence_level TEXT,
weapons_json TEXT, equipment_json TEXT, biometrics_simulated INTEGER DEFAULT 0, consent_recorded INTEGER DEFAULT 0,
capture_pos_x REAL, capture_pos_y REAL, capture_pos_z REAL, grid_reference TEXT, location_description TEXT, submitter_user_id INTEGER,
submitter_callsign TEXT, submitter_steam_id TEXT, target_unit_netid TEXT, primary_photo_id INTEGER, subject_id TEXT, seek_stage TEXT,
identity_tier TEXT, acquisition_quality_avg INTEGER, medical_context_json TEXT, identity_query_json TEXT, signed_by_callsign TEXT,
signed_terminal_uid TEXT, signed_atak_id TEXT, signed_at TEXT, signature_image TEXT, created_at TEXT, updated_at TEXT,
classification TEXT, locked_at TEXT, locked_by INTEGER, lock_reason TEXT, lock_until TEXT, closed_at TEXT, closed_by INTEGER, close_reason TEXT
SQL;

    /** @param bool $withWorkColumns false : la base d'avant l'étape 4760 (page en lecture seule) */
    public static function open(bool $withWorkColumns = true, bool $withViews = true): PDO
    {
        $pdo = SseWantedSqlite::open();
        $columns = self::PERSON_COLUMNS;
        if (!$withWorkColumns) {
            $columns = preg_replace('/,\s*classification TEXT.*$/s', '', $columns) ?? $columns;
        }
        $pdo->exec('CREATE TABLE sse_persons (' . $columns . ')');
        $pdo->exec('CREATE TABLE sse_person_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, person_id INTEGER, image_path TEXT, angle TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE sse_biometric_samples (id INTEGER PRIMARY KEY AUTOINCREMENT, person_id INTEGER, tenant_id INTEGER, kind TEXT DEFAULT \'empreintes\', quality INTEGER, lab_reference TEXT, operator_callsign TEXT, created_at TEXT, UNIQUE (person_id, kind))');
        $pdo->exec('CREATE TABLE sse_custody_events (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, person_id INTEGER, site_id INTEGER, photo_id INTEGER, event_type TEXT, label TEXT, actor_callsign TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE sse_case_persons (id INTEGER PRIMARY KEY AUTOINCREMENT, case_id INTEGER, person_id INTEGER, tenant_id INTEGER, linked_by INTEGER, note TEXT)');
        $pdo->exec('CREATE TABLE sse_portal_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL DEFAULT \'\', updated_by INTEGER, updated_at TEXT, UNIQUE (tenant_id, setting_key))');
        $pdo->exec('CREATE TABLE sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT DEFAULT \'\', action TEXT NOT NULL, object_type TEXT DEFAULT \'\', object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN callsign TEXT');
        } catch (\Throwable) {
            // déjà présente
        }
        (require dirname(__DIR__, 2) . '/bootstrap/sse_member_image_grants_migration.php')($pdo);
        if ($withViews) {
            self::createViewsTable($pdo);
        }
        SsePersonWorkRepository::forgetReady();

        return $pdo;
    }

    public static function createViewsTable(PDO $target): void
    {
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        (new \App\Support\Migrations\SchemaInspector($scratch))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_17_111_sse_person_views.sql');
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name = 'sse_person_views' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $target->exec((string) $sql);
        }
    }

    public static function close(): void
    {
        SseWantedSqlite::close();
        SsePersonWorkRepository::forgetReady();
    }
}
