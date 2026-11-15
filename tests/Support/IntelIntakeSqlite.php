<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Intel\IntelIntakeService;
use PDO;

/**
 * Base sqlite des « Remontées » (back-office et bureau SSE), posée sur celle du widget « Liaisons » :
 * comptes rendus, fiches de renseignement, suivi (intel_intake_*), dossiers, liens de provenance, bot Discord.
 *
 * Jeu de données :
 *   a : la fiche technique FR-2026-000002 de TA1 (3 thèmes, C3, classée sans suite, ni pièce ni dossier) ;
 *   b : une fiche urgente reçue il y a 3 h, non prise en compte, avec 2 pièces, 6 commentaires, 2 transmissions,
 *       un passage caviardé, versée à un dossier ;
 *   cr : un compte rendu SPOTREP du même émetteur, le même jour que b (proposé comme doublon).
 */
final class IntelIntakeSqlite
{
    public static function open(): PDO
    {
        $pdo = SseLinksSqlite::open();
        $add = static function (string $table, array $columns) use ($pdo): void {
            $have = array_map('strval', $pdo->query('SELECT name FROM pragma_table_info(' . $pdo->quote($table) . ')')->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($columns as $name => $type) {
                if (!in_array($name, $have, true)) {
                    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $type);
                }
            }
        };
        $add('users', ['callsign' => 'TEXT', 'status' => "TEXT DEFAULT 'active'", 'email' => 'TEXT']);
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_field_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, title TEXT, body TEXT, created_at TEXT)');
        $add('sse_field_notes', [
            'context_id' => 'INTEGER DEFAULT 1', 'note_kind' => "TEXT DEFAULT 'FRM'", 'themes' => 'TEXT', 'title' => 'TEXT', 'body' => 'TEXT',
            'observed_at' => 'TEXT', 'place_label' => 'TEXT', 'grid_reference' => 'TEXT', 'pos_x' => 'REAL', 'pos_y' => 'REAL', 'lat' => 'REAL', 'lng' => 'REAL',
            'urgency' => "TEXT DEFAULT 'routine'", 'intel_source' => 'TEXT', 'classification' => "TEXT DEFAULT 'interne'",
            'source_reliability' => 'TEXT', 'info_credibility' => 'INTEGER', 'status' => "TEXT DEFAULT 'transmise'", 'origin' => "TEXT DEFAULT 'atak'",
            'author_user_id' => 'INTEGER', 'author_label' => 'TEXT', 'author_unit' => 'TEXT', 'author_steam_id' => 'TEXT',
            'case_id' => 'INTEGER', 'interest_case_id' => 'INTEGER', 'triage_note' => 'TEXT', 'triaged_by' => 'INTEGER', 'triaged_at' => 'TEXT',
            'idempotency_key' => 'TEXT', 'updated_at' => 'TEXT',
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_field_note_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, note_id INTEGER NOT NULL, kind TEXT DEFAULT \'photo\', file_path TEXT, original_name TEXT, mime_type TEXT, caption TEXT, blurred INTEGER DEFAULT 0, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS atak_tactical_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, report_type TEXT, report_number TEXT, created_at TEXT)');
        $add('atak_tactical_reports', [
            'context_id' => 'INTEGER', 'priority' => "TEXT DEFAULT 'ROUTINE'", 'classification' => "TEXT DEFAULT 'UNCLASSIFIED'",
            'submitter_callsign' => 'TEXT', 'submitter_unit' => 'TEXT', 'submitter_user_id' => 'INTEGER', 'submitter_steam_id' => 'TEXT',
            'summary' => 'TEXT', 'details' => 'TEXT', 'remarks' => 'TEXT', 'location_description' => 'TEXT', 'grid_reference' => 'TEXT',
            'pos_x' => 'REAL', 'pos_y' => 'REAL', 'mission_key' => 'TEXT', 'attachment_count' => 'INTEGER DEFAULT 0', 'status' => "TEXT DEFAULT 'SUBMITTED'",
            'structured_data' => 'TEXT', 'acknowledged_at' => 'TEXT', 'acknowledged_by_user_id' => 'INTEGER', 'event_timestamp' => 'TEXT',
            'report_timestamp' => 'TEXT', 'dtg' => 'TEXT', 'visibility' => 'TEXT', 'source_chat_id' => 'INTEGER', 'action_taken' => 'TEXT',
            'updated_at' => 'TEXT', 'deleted_at' => 'TEXT',
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS atak_report_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, report_id INTEGER, file_path TEXT, file_type TEXT, original_filename TEXT, caption TEXT, taken_at TEXT)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS intel_intake_state (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, source TEXT NOT NULL, source_id INTEGER NOT NULL, state TEXT NOT NULL DEFAULT 'a_traiter',
            assignee_user_id INTEGER, assigned_by INTEGER, assigned_at TEXT, exploited_by INTEGER, exploited_at TEXT, deleted_by INTEGER, deleted_at TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (tenant_id, source, source_id))");
        $pdo->exec('CREATE TABLE IF NOT EXISTS intel_intake_events (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, source TEXT NOT NULL, source_id INTEGER NOT NULL, kind TEXT NOT NULL,
            actor_user_id INTEGER, actor_label TEXT, body TEXT, meta_json TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS intel_intake_redactions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, source TEXT NOT NULL, source_id INTEGER NOT NULL, field TEXT NOT NULL DEFAULT 'body',
            phrase TEXT NOT NULL, clear_level TEXT NOT NULL DEFAULT 'tres_restreint', allowed_user_ids TEXT NOT NULL DEFAULT '[]', reason TEXT, created_by INTEGER, created_by_label TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS intel_intake_reads (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, source TEXT NOT NULL, source_id INTEGER NOT NULL, user_id INTEGER NOT NULL, channel TEXT NOT NULL DEFAULT 'web',
            read_count INTEGER NOT NULL DEFAULT 1, first_read_at TEXT DEFAULT CURRENT_TIMESTAMP, last_read_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (tenant_id, source, source_id, user_id, channel))");
        $pdo->exec('CREATE TABLE IF NOT EXISTS discord_bot_reminders (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, kind TEXT NOT NULL, ref TEXT NOT NULL, sent_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (tenant_id, kind, ref))');
        $add('sse_cases', ['context_id' => 'INTEGER DEFAULT 1', 'summary' => 'TEXT', 'is_folder' => 'INTEGER DEFAULT 0', 'parent_id' => 'INTEGER', 'interest_case_id' => 'INTEGER', 'created_by' => 'INTEGER', 'updated_at' => 'TEXT']);
        $add('sse_field_notes', ['pos_z' => 'REAL']);
        $interest = [];
        foreach (['suspected_alias', 'apparent_sex', 'estimated_age_range', 'suspected_nationality', 'suspected_affiliation', 'confidence_level', 'interest_level', 'opening_reason', 'description', 'origin_operator', 'observed_elements', 'analysis_facts', 'analysis_assumptions', 'analysis_contradictions', 'analysis_questions', 'collection_needs', 'operational_risk', 'recommendations', 'source_label', 'source_reliability', 'signed_by_label', 'signed_at', 'acquisition_at', 'mission_label', 'classification', 'case_type', 'assignee_user_id', 'due_at', 'tags'] as $col) {
            $interest[$col] = 'TEXT';
        }
        $add('sse_interest_cases', $interest + ['version' => 'INTEGER DEFAULT 1']);
        $pdo->exec('CREATE TABLE IF NOT EXISTS sse_intel_requirements (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, tenant_id INTEGER NOT NULL, context_id INTEGER, case_id INTEGER, req_type TEXT, reference_code TEXT, title TEXT, question TEXT,
            priority TEXT, status TEXT, coverage_pct INTEGER, linked_hypothesis TEXT, confirmation_criterion TEXT, assignee_label TEXT, due_at TEXT, author_label TEXT, created_by INTEGER, pos_x REAL, pos_y REAL, visible_on_atak INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $add('tenants', ['settings' => 'TEXT', 'updated_at' => 'TEXT']);
        $pdo->exec('CREATE TABLE IF NOT EXISTS discord_companion_guilds (guild_id TEXT PRIMARY KEY, tenant_id INTEGER NOT NULL UNIQUE, guild_name TEXT, channel_id TEXT, channels_json TEXT, synced_at TEXT)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS discord_bot_settings (tenant_id INTEGER PRIMARY KEY, announce_channel_id TEXT, applications_channel_id TEXT, events_channel_id TEXT, log_channel_id TEXT, member_role_id TEXT, manager_role_id TEXT, roles_json TEXT,
            sync_roles INTEGER DEFAULT 0, reminders_enabled INTEGER DEFAULT 1, welcome_enabled INTEGER DEFAULT 0, bot_seen_at TEXT, test_requested_at TEXT, linked_by_user_id INTEGER, linked_at TEXT, updated_at TEXT)");
        $pdo->exec('CREATE TABLE IF NOT EXISTS member_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, type TEXT, payload_json TEXT, read_at TEXT, created_at TEXT)');
        IntelIntakeService::forgetSchema();

        return $pdo;
    }

    /** Relie un serveur Discord à la communauté 1, bot vu à l'instant, salons et rôles relevés. */
    public static function linkDiscord(PDO $pdo, ?int $now = null): void
    {
        $now ??= time();
        $pdo->prepare('INSERT OR REPLACE INTO discord_companion_guilds (guild_id, tenant_id, guild_name, channels_json, synced_at) VALUES (?, 1, ?, ?, ?)')
            ->execute(['900000000000000001', 'Task Force Alpha', '{"800000000000000001":"renseignement","800000000000000002":"etat-major"}', date('Y-m-d H:i:s', $now)]);
        $pdo->prepare('INSERT OR REPLACE INTO discord_bot_settings (tenant_id, roles_json, reminders_enabled, bot_seen_at) VALUES (1, ?, 0, ?)')
            ->execute(['{"700000000000000001":"Officier renseignement"}', date('Y-m-d H:i:s', $now)]);
    }

    public static function close(): void
    {
        IntelIntakeService::forgetSchema();
        SseLinksSqlite::close();
    }

    /** @param array<string, mixed> $row */
    public static function insert(PDO $pdo, string $table, array $row): int
    {
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array{a: int, b: int, cr: int, case: int, me: int, analyst: int}
     */
    public static function seed(PDO $pdo, ?int $now = null): array
    {
        $now ??= time();
        $at = static fn (int $secondsAgo): string => date('Y-m-d H:i:s', $now - $secondsAgo);
        foreach ([
            [7, 'TA1', 'Thomas Armand', '76561198000004821'],
            [8, 'VIPER', 'Léa Morel', '76561198000117733'],
            [9, 'ORION', 'Hugo Besson', '76561198000220945'],
            [11, 'SABLE', 'Nina Karoui', ''],
        ] as [$id, $callsign, $name, $steam]) {
            $pdo->prepare('INSERT OR REPLACE INTO users (id, tenant_id, steam_id, display_name, callsign, status) VALUES (?, 1, ?, ?, ?, ?)')->execute([$id, $steam, $name, $callsign, 'active']);
        }
        $pdo->exec("INSERT OR REPLACE INTO users (id, tenant_id, steam_id, display_name, callsign, status) VALUES (90, 2, '76561198000999999', 'Autre communauté', 'ETRANGER', 'active')");
        $case = self::insert($pdo, 'sse_cases', ['tenant_id' => 1, 'reference_code' => 'SSE-2026-0007', 'title' => 'Dépôt de la vallée de Nidasos', 'status' => 'ouvert', 'classification' => 'encadrement', 'created_at' => $at(86400 * 20)]);
        self::insert($pdo, 'sse_cases', ['tenant_id' => 1, 'reference_code' => 'SSE-2026-0011', 'title' => 'Convois de nuit sur la route côtière', 'status' => 'ouvert', 'classification' => 'interne', 'created_at' => $at(86400 * 9)]);
        self::insert($pdo, 'sse_cases', ['tenant_id' => 2, 'reference_code' => 'SSE-2026-0001', 'title' => 'Dossier d’une autre communauté', 'status' => 'ouvert', 'created_at' => $at(86400)]);

        $a = self::insert($pdo, 'sse_field_notes', [
            'tenant_id' => 1, 'reference_code' => 'FR-2026-000002', 'note_kind' => 'FRT', 'themes' => '["ARMEMENT","LOGIST","SECUR"]',
            'title' => '', 'body' => "Trois caisses de munitions de 7,62 mm et deux lance-roquettes entreposés sous une bâche, à l’arrière du hangar agricole.\nUn pick-up blanc sans plaque stationne devant l’entrée.",
            'observed_at' => '2026-09-01 20:10:00', 'place_label' => 'Nidasos', 'grid_reference' => '24227 22393', 'pos_x' => 15193.4, 'pos_y' => 17303.2,
            'urgency' => 'routine', 'intel_source' => 'TECHINT', 'classification' => 'interne', 'source_reliability' => 'C', 'info_credibility' => 3,
            'status' => 'sans_suite', 'origin' => 'atak', 'author_user_id' => null, 'author_label' => 'TA1', 'author_unit' => 'TA1', 'author_steam_id' => '76561198000004821',
            'triaged_by' => 7, 'triaged_at' => '2026-10-19 20:46:00', 'created_at' => '2026-09-01 18:12:00', 'updated_at' => '2026-10-19 20:46:00',
        ]);
        self::insert($pdo, 'intel_intake_state', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $a, 'state' => 'close']);
        self::insert($pdo, 'intel_intake_events', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $a, 'kind' => 'state', 'actor_user_id' => 7, 'actor_label' => 'TA1', 'meta_json' => '{"from":"a_traiter","to":"close"}', 'created_at' => '2026-10-19 20:46:00']);
        self::insert($pdo, 'intel_intake_reads', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $a, 'user_id' => 7, 'channel' => 'bo', 'read_count' => 7, 'first_read_at' => '2026-10-19 20:40:00', 'last_read_at' => $at(3600)]);
        self::insert($pdo, 'intel_intake_reads', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $a, 'user_id' => 7, 'channel' => 'web', 'read_count' => 9, 'first_read_at' => '2026-10-19 20:41:00', 'last_read_at' => $at(7200)]);

        $b = self::insert($pdo, 'sse_field_notes', [
            'tenant_id' => 1, 'reference_code' => 'FR-2026-000041', 'note_kind' => 'FRO', 'themes' => '["TERROR"]',
            'title' => 'Engin suspect sous le pont nord', 'body' => "Un colis relié à des fils est posé contre la pile est du pont nord, côté amont.\nDeux hommes armés surveillent l’accès depuis la maison du passeur, chez Karimi.\nUn blessé civil a été évacué par les habitants.",
            'observed_at' => $at(3 * 3600 + 900), 'place_label' => 'Pont nord de Nidasos', 'grid_reference' => '24231 22410', 'pos_x' => 15240.0, 'pos_y' => 17355.0,
            'lat' => 35.12345, 'lng' => 33.54321,
            'urgency' => 'urgent', 'intel_source' => 'HUMINT', 'classification' => 'encadrement', 'source_reliability' => 'B', 'info_credibility' => 2,
            'status' => 'transmise', 'origin' => 'atak', 'author_user_id' => 8, 'author_label' => 'VIPER', 'author_unit' => 'Section Alpha', 'author_steam_id' => '76561198000117733',
            'case_id' => $case, 'created_at' => $at(3 * 3600), 'updated_at' => $at(3 * 3600),
        ]);
        foreach ([['photo', 'pont-nord-pile-est.jpg', 'image/jpeg', 'Pile est, vue depuis la berge'], ['croquis', 'croquis-acces.png', 'image/png', 'Croquis des accès']] as [$kind, $name, $mime, $caption]) {
            self::insert($pdo, 'sse_field_note_attachments', ['tenant_id' => 1, 'note_id' => $b, 'kind' => $kind, 'file_path' => '', 'original_name' => $name, 'mime_type' => $mime, 'caption' => $caption, 'blurred' => 0, 'created_at' => $at(3 * 3600)]);
        }
        self::insert($pdo, 'intel_intake_redactions', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $b, 'field' => 'body', 'phrase' => 'chez Karimi', 'clear_level' => 'confidentiel', 'allowed_user_ids' => '[]', 'reason' => 'Protection de la source', 'created_by' => 9, 'created_by_label' => 'ORION', 'created_at' => $at(9000)]);
        $events = [
            [9900, 'comment', 9, 'ORION', 'Je recoupe avec le compte rendu de la patrouille Bravo.', null],
            [9600, 'state', 9, 'ORION', 'Relève de l’échelon supérieur', '{"from":"a_traiter","to":"transmise","forwarded_to":"Cellule renseignement (S2)"}'],
            [9300, 'comment', 11, 'SABLE', 'Le S2 demande la position exacte du colis.', null],
            [9000, 'redact', 9, 'ORION', 'Protection de la source', '{"field":"Texte","level":"Secret (S)","people":0,"length":11}'],
            [8400, 'comment', 9, 'ORION', 'Position confirmée par VIPER : pile est, côté amont.', null],
            [7800, 'state', 11, 'SABLE', 'À verser au dossier en cours', '{"from":"transmise","to":"transmise","forwarded_to":"Dossier SSE SSE-2026-0007","case_id":' . $case . '}'],
            [7200, 'comment', 11, 'SABLE', 'Versée au dossier du dépôt de la vallée.', null],
            [6600, 'state', 11, 'SABLE', 'Personne ne répond de la fiche : je la remets en file.', '{"from":"transmise","to":"a_traiter"}'],
            [5400, 'comment', 9, 'ORION', 'Toujours pas de retour du commandement.', null],
            [1800, 'comment', 11, 'SABLE', 'Qui prend ? Le délai est dépassé.', null],
        ];
        foreach ($events as [$ago, $kind, $uid, $label, $body, $meta]) {
            self::insert($pdo, 'intel_intake_events', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $b, 'kind' => $kind, 'actor_user_id' => $uid, 'actor_label' => $label, 'body' => $body, 'meta_json' => $meta, 'created_at' => $at($ago)]);
        }
        self::insert($pdo, 'intel_intake_state', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $b, 'state' => 'a_traiter']);
        foreach ([[9, 'bo', 4, 5400], [11, 'bo', 6, 1800], [11, 'web', 2, 7000], [8, 'jeu', 1, 10000]] as [$uid, $channel, $n, $ago]) {
            self::insert($pdo, 'intel_intake_reads', ['tenant_id' => 1, 'source' => 'fiche', 'source_id' => $b, 'user_id' => $uid, 'channel' => $channel, 'read_count' => $n, 'first_read_at' => $at(10000), 'last_read_at' => $at($ago)]);
        }

        $cr = self::insert($pdo, 'atak_tactical_reports', [
            'tenant_id' => 1, 'report_type' => 'SPOTREP', 'report_number' => 'SPOT-2026-0117', 'priority' => 'IMMEDIATE', 'classification' => 'UNCLASSIFIED',
            'submitter_callsign' => 'VIPER', 'submitter_unit' => 'Section Alpha', 'submitter_user_id' => 8, 'submitter_steam_id' => '76561198000117733',
            'summary' => 'Colis suspect au pont nord', 'details' => 'Colis relié à des fils contre la pile est. Deux hommes armés à proximité.',
            'location_description' => 'Pont nord de Nidasos', 'grid_reference' => '24231 22410', 'pos_x' => 15240.0, 'pos_y' => 17355.0, 'mission_key' => 'OP-NORD',
            'status' => 'SUBMITTED', 'structured_data' => '{"size":"2 hommes","activity":"Surveillance du pont","equipment":"Fusils d’assaut"}',
            'event_timestamp' => $at(4 * 3600), 'created_at' => $at(4 * 3600 - 600), 'updated_at' => $at(4 * 3600 - 600),
        ]);
        // Une remontée d'une autre communauté : jamais lisible depuis la communauté 1.
        self::insert($pdo, 'sse_field_notes', ['tenant_id' => 2, 'reference_code' => 'FR-2026-000900', 'note_kind' => 'FRM', 'body' => 'Fiche d’une autre communauté.', 'status' => 'transmise', 'author_label' => 'ETRANGER', 'created_at' => $at(600)]);

        return ['a' => $a, 'b' => $b, 'cr' => $cr, 'case' => $case, 'me' => 7, 'analyst' => 9];
    }
}
