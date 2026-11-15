<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Support\SseSignalSchema;
use App\Support\SseTriageSchema;
use PDO;

/**
 * Base sqlite en mémoire de l'accueil du bureau SSE : tout ce que ses bandes lisent (boîte « À trier » et ses files, dossiers,
 * dossiers d'intérêt, avis de recherche, transmissions, contrôles, fiches, liens de Cerbère, documents), sur une base qui compte
 * ses requêtes. Le registre d'identités, les contrôles, les avis et les liens viennent des vrais fichiers de migration
 * (SseLinksSqlite) ; les tables de tri de la vraie étape 4740.
 *
 * Jeux : seedBusy (bureau chargé : 22 éléments à trier, 14 dossiers dont 2 en retard, 3 avis dont 1 armé, 9 remontées,
 * activité de Cerbère) ou rien (nouvelle communauté) ; le lecteur à habilitation basse se règle par les droits du test.
 */
final class SseHomeSqlite
{
    public const NOW = '2026-11-15 12:00:00';
    public const MEMBER_USER = 42;
    public const MEMBER_NAME = 'Vidal';
    public const CONTROLLER = 'Cdt Rivière';

    /** Tables redéfinies ici avec les colonnes que lisent les vrais dépôts. */
    private const OWN = ['users', 'sse_cases', 'sse_interest_cases', 'sse_intel_events', 'sse_documents', 'sse_field_notes'];

    private const SIGNALS = "CREATE TABLE sse_engine_signals (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id INT UNSIGNED NOT NULL, case_id INT UNSIGNED DEFAULT NULL, signal_type VARCHAR(40) NOT NULL,
        severity VARCHAR(16) NOT NULL DEFAULT 'info', title VARCHAR(220) NOT NULL, detail MEDIUMTEXT NULL, payload_json MEDIUMTEXT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'open', rule_key VARCHAR(64) NOT NULL DEFAULT '', run_id VARCHAR(40) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    public static function open(): CountingSqlitePdo
    {
        $base = SseLinksSqlite::open();
        $pdo = new CountingSqlitePdo();
        $skip = "'" . implode("', '", array_merge(self::OWN, ['sqlite_sequence'])) . "'";
        foreach ($base->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name NOT IN ({$skip}) ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $pdo->exec((string) $sql);
        }
        foreach (self::ddl() as $sql) {
            $pdo->exec($sql);
        }
        // Tables et colonnes de tri : le vrai fichier de l'étape 4740, traduit puis rejoué ici.
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $scratch->exec(self::SIGNALS);
        self::runTriageStep($scratch);
        foreach ($scratch->query("SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND tbl_name LIKE 'sse_%' ORDER BY type = 'index', name")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
            $pdo->exec((string) $sql);
        }
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Alpha', 'alpha'), (2, 'Bravo', 'bravo')");
        $pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name, callsign) VALUES (7, 1, 's7', 'Alice Marchand', 'A7'), (" . self::MEMBER_USER . ", 1, 's42', 'Marc " . self::MEMBER_NAME . "', 'M42'), (20, 2, 's20', 'Autre', 'X')");

        $pdo->syncInformationSchema();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
        self::forget();
        $pdo->reset();

        return $pdo;
    }

    public static function close(): void
    {
        SseLinksSqlite::close();
        self::forget();
    }

    /**
     * Ouvre une session du bureau pour le compte $userId de la communauté 1, avec ces droits d'origine (porte du portail ouverte,
     * engagement de confidentialité signé). Les services lisent alors la vraie session : aucun droit n'est simulé.
     *
     * @param list<string> $permissions
     */
    public static function signIn(array $permissions, int $userId = 7): void
    {
        $sse = \App\Services\Sse\SseAccessCodeService::class;
        foreach ([
            'tenant_id' => 1, 'user_id' => $userId, 'display_name' => 'Alice Marchand', 'lang' => 'fr',
            $sse::SESSION_GUEST => false, $sse::SESSION_TENANT => 1, $sse::SESSION_UNTIL => time() + 7200, $sse::SESSION_SCOPE => 'all',
            $sse::SESSION_CODE_ID => 0, $sse::SESSION_ACK_VERSION => $sse::CONFIDENTIALITY_VERSION, $sse::SESSION_ACK_TENANT => 1, $sse::SESSION_ACK_CODE_ID => 0,
        ] as $key => $value) {
            \App\Core\Session::set($key, $value);
        }
        \App\Core\Gate::getInstance()->setPermissions($permissions);
        \App\Services\Sse\SseAccessPolicy::useForSession(null);
        \App\Services\Sse\SseMemberImageGate::useForSession(null);
        \App\Services\Sse\SseTriageInbox::useForSession(null);
        \App\Support\ShortCache::setEnabled(false);
    }

    /** Referme la session ouverte par signIn(). */
    public static function signOut(): void
    {
        $sse = \App\Services\Sse\SseAccessCodeService::class;
        \App\Core\Session::forgetMany(['tenant_id', 'user_id', 'display_name', 'lang', $sse::SESSION_GUEST, $sse::SESSION_TENANT, $sse::SESSION_UNTIL, $sse::SESSION_SCOPE,
            $sse::SESSION_CODE_ID, $sse::SESSION_ACK_VERSION, $sse::SESSION_ACK_TENANT, $sse::SESSION_ACK_CODE_ID]);
        \App\Core\Gate::reset();
        \App\Services\Sse\SseAccessPolicy::useForSession(null);
        \App\Services\Sse\SseMemberImageGate::useForSession(null);
        \App\Support\ShortCache::setEnabled(null);
    }

    private static function runTriageStep(PDO $pdo): void
    {
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/4740_sse_triage_automatisms.php';
        } finally {
            ob_end_clean();
        }
    }

    private static function forget(): void
    {
        SseSignalSchema::forget();
        SseTriageSchema::forget();
        \App\Repositories\SseEntityLinkRepository::forgetReady();
        \App\Repositories\SseWantedNoticeRepository::forgetReady();
        \App\Repositories\SseWantedMetaRepository::forgetReady();
    }

    /** @return list<string> */
    private static function ddl(): array
    {
        return [
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, display_name TEXT, callsign TEXT, role_id INTEGER, status TEXT)',
            // Droits d'accès SSE (mêmes tables que SseAccessFixture) : la vraie politique lit les règles de la communauté.
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT, slug TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, parent_id INT NULL, name TEXT, type TEXT, display_order INT DEFAULT 0)',
            'CREATE TABLE user_units (user_id INT, unit_id INT, ended_at TEXT NULL)',
            'CREATE TABLE tenant_user_roles (user_id INT, tenant_id INT, role_id INT, org_unit_id INT NULL, valid_from TEXT NULL, valid_until TEXT NULL)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY, slug TEXT)',
            'CREATE TABLE role_permissions (role_id INT, permission_id INT)',
            'CREATE TABLE sse_access_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, section TEXT, level TEXT, subject_type TEXT, subject_id INT, effect TEXT,
                starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, source TEXT, created_by INT, created_at TEXT, revoked_at TEXT NULL, revoked_by INT NULL)',
            "CREATE TABLE sse_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, parent_id INTEGER, interest_case_id INTEGER, is_folder INTEGER NOT NULL DEFAULT 0,
                reference_code TEXT, title TEXT, summary TEXT, classification TEXT DEFAULT 'interne', status TEXT DEFAULT 'ouvert', unlock_code_hash TEXT, created_by INTEGER,
                created_at TEXT, updated_at TEXT, closed_at TEXT)",
            "CREATE TABLE sse_interest_cases (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT NOT NULL, temporary_designation TEXT NOT NULL,
                suspected_alias TEXT, suspected_affiliation TEXT, status TEXT NOT NULL DEFAULT 'signalement_recu', confidence_level TEXT NOT NULL DEFAULT 'non_evalue',
                interest_level TEXT NOT NULL DEFAULT 'courant', opening_reason TEXT NOT NULL DEFAULT '', description TEXT, created_by INTEGER, validated_by INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, version INTEGER NOT NULL DEFAULT 1,
                classification TEXT NOT NULL DEFAULT 'interne', case_type TEXT, assignee_user_id INTEGER, due_at TEXT, tags TEXT, archive_from_status TEXT,
                deleted_at TEXT, deleted_by INTEGER, delete_reason TEXT)",
            'CREATE TABLE sse_interest_case_acl (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, interest_case_id INTEGER, user_id INTEGER, access_mode TEXT, created_by INTEGER, created_at TEXT)',
            "CREATE TABLE sse_intel_events (id INTEGER PRIMARY KEY AUTOINCREMENT, event_uuid TEXT, tenant_id INTEGER NOT NULL, context_id INTEGER DEFAULT 1, case_id INTEGER,
                interest_case_id INTEGER, entity_uuid TEXT, event_type TEXT, source_system TEXT, raw_source_id TEXT, identity_tier TEXT, event_time TEXT, author_label TEXT,
                unit_label TEXT, summary TEXT, source_reliability TEXT, info_credibility INTEGER, payload_json TEXT, created_at TEXT)",
            "CREATE TABLE sse_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, reference_code TEXT, case_id INTEGER, document_type TEXT, title TEXT,
                status TEXT, classification TEXT DEFAULT 'interne', created_by INTEGER, validated_by INTEGER, created_at TEXT, updated_at TEXT)",
            "CREATE TABLE sse_field_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER DEFAULT 1, reference_code TEXT, title TEXT, body TEXT,
                note_kind TEXT, urgency TEXT, themes TEXT, intel_source TEXT, origin TEXT, place_label TEXT, grid_reference TEXT, classification TEXT DEFAULT 'interne',
                status TEXT DEFAULT 'transmise', author_label TEXT, author_steam_id TEXT, author_user_id INTEGER, case_id INTEGER, interest_case_id INTEGER,
                observed_at TEXT, created_at TEXT, updated_at TEXT)",
            'CREATE TABLE sse_field_note_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, note_id INTEGER, kind TEXT, file_path TEXT, original_name TEXT, mime_type TEXT, caption TEXT, created_at TEXT)',
            "CREATE TABLE sse_suggestion_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, case_id INTEGER, related_case_id INTEGER, kind TEXT, title TEXT,
                reason TEXT, confidence TEXT, score INTEGER DEFAULT 0, status TEXT DEFAULT 'pending', evidence_json TEXT, rule_key TEXT, decided_at TEXT, created_at TEXT, updated_at TEXT)",
        ];
    }

    /**
     * Ferme la lecture de sections au compte $userId par de vraies règles de refus (SseAccessRuleRepository).
     *
     * @param list<string> $sections
     */
    public static function denySections(PDO $pdo, array $sections, int $userId = 7): void
    {
        $rules = new \App\Repositories\SseAccessRuleRepository($pdo);
        foreach ($sections as $section) {
            $rules->insert(1, [
                'section' => $section, 'level' => \App\Support\SseAccessCatalog::LEVEL_READ, 'subject_type' => 'user', 'subject_id' => $userId, 'effect' => 'deny',
                'starts_at' => null, 'ends_at' => null, 'reason' => 'test', 'source' => '',
            ], 7, gmdate('Y-m-d H:i:s', time() - 60));
        }
        \App\Services\Sse\SseAccessPolicy::forSession()->reset();
    }

    /**
     * Bureau chargé. À trier, sans doublon : 8 signaux, 4 fiches transmises, 3 contrôles non rattachés, 2 propositions,
     * 2 documents en relecture, 1 compte rendu de contrôle à valider, 2 dossiers d'intérêt à qualifier = 22.
     */
    public static function seedBusy(PDO $pdo, ?int $now = null): void
    {
        $now ??= (int) strtotime(self::NOW . ' UTC');
        $h = 3600;
        $d = 86400;
        $at = static fn (int $ago): string => gmdate('Y-m-d H:i:s', $now - $ago);
        $local = static fn (int $ago): string => date('Y-m-d H:i:s', $now - $ago);

        // 10 dossiers validés : 9 actifs (dont 1 Très secret et 2 Secret), 1 clos.
        $case = $pdo->prepare('INSERT INTO sse_cases (id, tenant_id, is_folder, reference_code, title, classification, status, created_at, updated_at) VALUES (?, 1, 0, ?, ?, ?, ?, ?, ?)');
        foreach ([
            [1, 'DOS-0012', 'Réseau du port de Girna', 'encadrement', 'en_cours', 3 * $h],
            [2, 'DOS-0015', 'Convoi de la route nord', 'encadrement', 'ouvert', 9 * $h],
            [3, 'DOS-0019', 'Atelier de faux papiers', 'confidentiel', 'en_cours', 1 * $d],
            [4, 'DOS-0021', 'Relais radio de Sagonisi', 'encadrement', 'ouvert', 2 * $d],
            [5, 'DOS-0023', 'Cellule de Kavala', 'confidentiel', 'en_cours', 3 * $d],
            [6, 'DOS-0024', 'Dépôt de carburant', 'interne', 'ouvert', 4 * $d],
            [7, 'DOS-0027', 'Filière de recrutement', 'tres_restreint', 'en_cours', 5 * $d],
            [8, 'DOS-0030', 'Points de passage est', 'interne', 'ouvert', 6 * $d],
            [9, 'DOS-0031', 'Atelier de Pyrgos', 'encadrement', 'ouvert', 8 * $d],
            [10, 'DOS-0008', 'Cache d’armes de Neri', 'encadrement', 'clos', 30 * $d],
        ] as [$id, $ref, $title, $class, $status, $ago]) {
            $case->execute([$id, $ref, $title, $class, $status, $local($ago + 20 * $d), $local($ago)]);
        }

        // 5 dossiers d'intérêt actifs (2 en retard, 2 à qualifier), 1 archivé.
        $interest = $pdo->prepare('INSERT INTO sse_interest_cases (id, tenant_id, reference_code, temporary_designation, status, interest_level, opening_reason, classification, created_by, due_at, created_at, updated_at) VALUES (?, 1, ?, ?, ?, ?, ?, ?, 7, ?, ?, ?)');
        foreach ([
            [1, 'DI-2026-0007', 'Homme au blouson gris', 'en_collecte', 'prioritaire', 'interne', $local(4 * $d), 2 * $d],
            [2, 'DI-2026-0009', 'Conductrice du pick-up blanc', 'en_analyse', 'critique', 'encadrement', $local(1 * $d), 5 * $h],
            [3, 'DI-2026-0011', 'Guetteur du carrefour sud', 'a_qualifier', 'courant', 'interne', $local(-6 * $d), 7 * $h],
            [4, 'DI-2026-0012', 'Porteur de la sacoche noire', 'a_qualifier', 'courant', 'interne', null, 26 * $h],
            [5, 'DI-2026-0004', 'Vendeur du marché couvert', 'en_collecte', 'a_surveiller', 'interne', $local(-12 * $d), 6 * $d],
            [6, 'DI-2026-0001', 'Passeur de la crique', 'archive', 'courant', 'interne', null, 40 * $d],
        ] as [$id, $ref, $name, $status, $level, $class, $due, $ago]) {
            $interest->execute([$id, $ref, $name, $status, $level, 'Signalement de patrouille', $class, $due, $local($ago + 10 * $d), $local($ago)]);
        }

        // 8 signaux du moteur (1 critique, 2 hauts).
        $signal = $pdo->prepare("INSERT INTO sse_engine_signals (tenant_id, case_id, signal_type, severity, title, detail, status, rule_key, run_id, created_at, updated_at) VALUES (1, ?, ?, ?, ?, ?, 'open', ?, 'r', ?, ?)");
        foreach ([
            [9, 'anomaly', 'critical', 'Contradiction non résolue', 'Divergence sur le détenteur du relais.', 'single_divergence', 20 * $h],
            [1, 'gap_auto', 'high', 'Lacune / besoin proposé : Appréciation analytique absente', 'BESOIN PRIORITAIRE', 'auto_gap', 3 * $d],
            [8, 'single_source', 'high', 'Alerte source unique', 'Une appréciation importante sans recoupement.', 'single_source_alert', 9 * $d],
            [2, 'gap_auto', 'medium', 'Lacune / besoin proposé : Photographie manquante', 'BESOIN', 'auto_gap', 2 * $d],
            [3, 'gap_auto', 'medium', 'Lacune / besoin proposé : Biométrie manquante', 'BESOIN', 'auto_gap', 4 * $d],
            [4, 'completeness', 'medium', 'Complétude faible (22/100)', 'Le dossier présente des trous majeurs.', 'low_completeness', 6 * $d],
            [6, 'completeness', 'medium', 'Complétude faible (18/100)', 'Le dossier présente des trous majeurs.', 'low_completeness', 11 * $d],
            [5, 'anomaly', 'medium', 'Chronologie incohérente', 'Deux dates se contredisent.', 'timeline_gap', 5 * $d],
        ] as [$c, $type, $sev, $title, $detail, $rule, $ago]) {
            $signal->execute([$c, $type, $sev, $title, $detail, $rule, $at($ago), $at($ago)]);
        }

        // 4 fiches transmises (2 dans la semaine), 1 brouillon de compte rendu de contrôle.
        $note = $pdo->prepare("INSERT INTO sse_field_notes (id, tenant_id, reference_code, title, body, note_kind, urgency, themes, status, author_label, origin, observed_at, created_at, updated_at) VALUES (?, 1, ?, ?, 'Compte rendu.', 'OBSERVATION', ?, '[]', ?, ?, 'web', ?, ?, ?)");
        foreach ([
            [1, 'FR-0041', 'Mouvement de camions au port', 'urgent', 'transmise', 6 * $h],
            [2, 'FR-0040', 'Rencontre au café de la gare', 'routine', 'transmise', 3 * $d],
            [3, 'FR-0037', 'Dépôt suspect près du pont', 'routine', 'transmise', 9 * $d],
            [4, 'FR-0035', 'Rumeur de livraison de nuit', 'routine', 'transmise', 12 * $d],
            [5, 'FR-0042', 'Contrôle de la berline grise', 'routine', 'brouillon', 10 * $d],
        ] as [$id, $ref, $title, $urgency, $status, $ago]) {
            $note->execute([$id, $ref, $title, $urgency, $status, self::CONTROLLER, $at($ago), $at($ago), $at($ago)]);
        }

        // Registre : 3 identités sous avis, dont celle d'un membre (jamais nommée sans le droit).
        $identity = $pdo->prepare('INSERT INTO sse_identities (id, tenant_id, kind, last_name, first_name, user_id, created_at) VALUES (?, 1, ?, ?, ?, ?, ?)');
        $identity->execute([101, 'civil', 'Benali', 'Karim', null, $at(20 * $d)]);
        $identity->execute([102, 'civil', 'Novak', 'Jan', null, $at(20 * $d)]);
        $identity->execute([103, 'civil', self::MEMBER_NAME, 'Marc', self::MEMBER_USER, $at(20 * $d)]);
        $notice = $pdo->prepare("INSERT INTO sse_wanted_notices (id, tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, reason, status, active_key, issued_at, expires_at, created_at, updated_at) VALUES (?, 1, ?, ?, 2026, ?, 'poi', ?, ?, 'Recherché', 'active', 1, ?, ?, ?, ?)");
        foreach ([[1, 101, 'AVR-2026-0004', 'high', 'yes', 2 * $d], [2, 102, 'AVR-2026-0003', 'medium', 'no', 5 * $d], [3, 103, 'AVR-2026-0002', 'critical', 'unknown', 8 * $d]] as [$id, $identityId, $ref, $threat, $armed, $ago]) {
            $notice->execute([$id, $identityId, $ref, $id, $threat, $armed, $at($ago), $at(-30 * $d), $at($ago), $at($ago)]);
        }
        $pdo->prepare("INSERT INTO sse_detections (id, tenant_id, identity_id, notice_id, detection_type, source, status, repeat_count, created_at) VALUES (1, 1, 101, 1, 'id_check', 'atak', 'seen', 1, ?)")->execute([$at(5 * $h)]);

        // 4 contrôles : 3 non rattachés dans la semaine, 1 rattaché à un papier avec son compte rendu en brouillon.
        $check = $pdo->prepare('INSERT INTO sse_id_checks (id, tenant_id, idempotency_key, document_id, presented_last, presented_first, controller_callsign, verdict, grid, field_note_id, created_at) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ([
            [1, null, 'Martin', 'Paul', 'ras', '034512', null, 2 * $h],
            [2, null, 'Kovac', 'Anna', 'recherche', '031498', null, 1 * $d],
            [3, null, self::MEMBER_NAME, 'Marc', 'inconnu', '030077', null, 4 * $d],
            [4, 1, 'Horak', 'Pavel', 'signale', '029911', 5, 10 * $d],
        ] as [$id, $doc, $last, $first, $verdict, $grid, $noteId, $ago]) {
            $check->execute([$id, 'k' . $id, $doc, $last, $first, self::CONTROLLER, $verdict, $grid, $noteId, $at($ago)]);
        }

        // 4 transmissions du terrain dans la semaine, 1 plus ancienne.
        $event = $pdo->prepare("INSERT INTO sse_intel_events (id, event_uuid, tenant_id, event_type, source_system, event_time, author_label, summary, source_reliability, info_credibility, created_at) VALUES (?, ?, 1, ?, 'ARMA_SSE', ?, ?, ?, 'B', 2, ?)");
        foreach ([
            [1, 'SPOTREP', 'Deux véhicules à l’arrêt devant l’entrepôt nord', 1 * $h],
            [2, 'CONTACT', 'Contact bref au point de passage est', 14 * $h],
            [3, 'SPOTREP', 'Antenne nouvelle sur le toit de la mairie', 2 * $d],
            [4, 'SITREP', 'Patrouille rentrée sans incident', 5 * $d],
            [5, 'SPOTREP', 'Feux de camp sur la crête', 15 * $d],
        ] as [$id, $type, $summary, $ago]) {
            $event->execute([$id, 'ev-' . $id, $type, $at($ago), self::CONTROLLER, $summary, $at($ago)]);
        }

        // Cerbère : 5 liens posés (3 dans les 24 h), 2 propositions en attente. Le motif cite un membre : jamais affiché à l'accueil.
        $link = $pdo->prepare("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, ?, ?, ?, ?, 'related', ?, ?, 'cerbere', ?)");
        $rules = array_keys(\App\Support\SseCerbere::RULES);
        foreach ([2 * $h, 7 * $h, 19 * $h, 3 * $d, 6 * $d] as $k => $ago) {
            $link->execute(['check', (string) ($k + 1), 'identity', (string) (101 + $k % 3), 'Même numéro de papier que Marc ' . self::MEMBER_NAME, $rules[$k % count($rules)], $at($ago)]);
        }
        $suggestion = $pdo->prepare("INSERT INTO sse_suggestion_queue (tenant_id, case_id, kind, title, reason, confidence, score, status, created_at, updated_at) VALUES (1, ?, ?, ?, 'Deux fiches décrivent la même personne.', ?, ?, 'pending', ?, ?)");
        $kind = (string) array_key_first(\App\Repositories\SseSuggestionQueueRepository::KINDS);
        $suggestion->execute([1, $kind, 'Rapprocher deux signalements du port', 'probable', 70, $at(8 * $h), $at(8 * $h)]);
        $suggestion->execute([3, $kind, 'Consolider une identité de l’atelier', 'confirme_candidat', 90, $at(30 * $h), $at(30 * $h)]);

        // 2 documents en relecture, 1 brouillon.
        $doc = $pdo->prepare("INSERT INTO sse_documents (tenant_id, reference_code, case_id, document_type, title, status, classification, created_by, created_at, updated_at) VALUES (1, ?, ?, 'note', ?, ?, 'interne', 7, ?, ?)");
        $doc->execute(['DOC-0031', 1, 'Synthèse hebdomadaire du port', 'en_relecture', $at(2 * $d), $at(1 * $d)]);
        $doc->execute(['DOC-0029', 3, 'Note sur l’atelier de faux papiers', 'en_relecture', $at(6 * $d), $at(4 * $d)]);
        $doc->execute(['DOC-0033', 2, 'Brouillon de point de situation', 'brouillon', $at(1 * $d), $at(1 * $d)]);
    }
}
