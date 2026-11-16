<?php

declare(strict_types=1);

namespace App\Support\Migrations;

use App\Support\SqlText;
use PDO;
use Throwable;

/**
 * Remet en utf8mb4_unicode_ci (collation de la connexion et des tables récentes) une liste EXPLICITE de petites tables
 * récentes, sous MySQL / MariaDB seulement (étape 4900, voir docs/dev/COLLATIONS-MYSQL.md).
 *
 * Une table n'est convertie (ALTER TABLE … CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) que si :
 *  - elle existe et sa collation, ou celle d'une de ses colonnes texte, diffère de utf8mb4_unicode_ci ;
 *  - elle reste petite (estimation information_schema : MAX_ROWS lignes, MAX_BYTES octets au plus) ;
 *  - aucune de ses colonnes n'a de collation voulue (…_bin) ni d'autre jeu de caractères que utf8mb4.
 * Sinon elle est laissée telle quelle avec un message [ATTENTION]. L'attente d'un verrou est bornée (lock_wait_timeout).
 * Idempotent : une table déjà en utf8mb4_unicode_ci n'est jamais touchée. Les grosses tables historiques ne sont jamais
 * listées : leurs jointures avec les tables récentes passent par SqlText::columnEquals().
 */
final class TableCollationAligner
{
    public const TARGET = 'utf8mb4_unicode_ci';

    /**
     * Tables créées par les étapes 4000 et suivantes (CREATE TABLE des étapes et des fichiers SQL ou bootstrap qu'elles
     * chargent), plus user_community_membership_periods (créée jusque-là à la volée) et personnel_job_role_recovery_log
     * (créée à la volée sans COLLATE). Liste figée : une nouvelle table naît déjà en utf8mb4_unicode_ci
     * (tests/Unit/MigrationCreateTableCollationTest.php).
     */
    public const RECENT_TABLES = [
        'aar_report_annexes', 'aar_syntheses', 'academy_auto_validation_settings', 'access_role_jobs', 'access_role_profiles', 'access_role_qualifications',
        'access_role_tag_links', 'atak_certificates', 'atak_crypto_domains', 'atak_marker_archive_items', 'atak_marker_archives', 'atak_marker_cycle_settings',
        'atak_mod_reports', 'atak_player_auto_notices', 'atak_terminal_sync', 'atak_terminals', 'atak_toc_phone_events', 'atak_toc_phone_settings',
        'atak_toc_purges', 'atak_toc_report_reads', 'bo_sheets', 'bo_table_defs', 'bo_user_nav_prefs', 'contact_mail_attachments',
        'contact_mail_messages', 'contact_mail_sync_state', 'contact_mail_threads', 'data_campaign_flags', 'data_campaign_members', 'data_campaign_reminders',
        'data_campaign_settings', 'data_campaigns', 'discord_bot_audit', 'discord_bot_dismissals', 'discord_bot_reminders', 'discord_bot_settings',
        'discord_photo_queue', 'game_item_favorites', 'game_item_prekits', 'game_item_specs', 'game_item_vanilla_loads', 'game_role_kit_versions',
        'jnet_links', 'jnet_pins', 'jnet_unit_auto_relays', 'jnet_unit_links', 'jnet_unit_page_comments', 'jnet_unit_page_reactions',
        'jnet_unit_page_votes', 'jnet_unit_pages', 'jnet_unit_space_access', 'jnet_unit_space_managers', 'jnet_unit_spaces', 'jnet_unit_tasks',
        'legacy_identity_import_log', 'member_availability_checks', 'member_dossier_step_events', 'member_dossier_steps', 'member_integration_applications', 'member_integration_package_items',
        'member_integration_packages', 'personnel_job_role_recovery_log', 'personnel_qualification_display', 'photo_library_comments', 'photo_library_labels', 'photo_library_masks',
        'photo_library_sse', 'photo_library_state', 'photo_library_tags', 'platform_bo_message_tenants', 'platform_bo_message_users', 'platform_bo_messages',
        'player_sessions', 'roster_fiche_events', 'sse_access_rule_history', 'sse_access_rules', 'sse_alert_acks', 'sse_alert_links',
        'sse_alert_proposals', 'sse_alert_reports', 'sse_attach_tags', 'sse_cover_export_links', 'sse_cover_exports', 'sse_detection_decisions',
        'sse_detection_rule_settings', 'sse_detections', 'sse_doc_drafts', 'sse_doc_issues', 'sse_doc_phrases', 'sse_editor_items',
        'sse_entity_links', 'sse_field_note_settings', 'sse_id_settings', 'sse_identity_events', 'sse_identity_history', 'sse_interest_case_history',
        'sse_interest_case_links', 'sse_interest_case_watchers', 'sse_leads', 'sse_link_attachments', 'sse_member_image_grants', 'sse_person_views',
        'sse_service_emblems', 'sse_tag_links', 'sse_triage_events', 'sse_triage_rules', 'sse_triage_state', 'sse_wanted_attachments',
        'sse_wanted_notice_extras', 'sse_wanted_notice_meta', 'sse_wanted_notices', 'sse_wanted_settings', 'tenant_administrative_positions', 'tenant_member_number_unit_templates',
        'tenant_unit_struct_types', 'user_community_membership_periods', 'workflow_circuits', 'workflow_events', 'workflow_instances',
    ];

    public const MAX_ROWS = 200000;

    public const MAX_BYTES = 256 * 1024 * 1024;

    /** Secondes d'attente maximale du verrou de métadonnées avant d'abandonner une table. */
    public const LOCK_WAIT_SECONDS = 10;

    /** @var callable(string): void */
    private $log;

    /** @param callable(string): void|null $log */
    public function __construct(private PDO $pdo, ?callable $log = null)
    {
        $this->log = $log ?? static function (string $m): void {
            echo $m;
        };
    }

    public function supported(): bool
    {
        return SqlText::isMysqlFamily($this->pdo);
    }

    /**
     * @param list<string> $tables
     * @return array{converted: list<string>, already: list<string>, missing: list<string>, skipped: list<string>}
     */
    public function align(array $tables): array
    {
        $out = ['converted' => [], 'already' => [], 'missing' => [], 'skipped' => []];
        if (!$this->supported()) {
            return $out;
        }
        $previousWait = null;
        try {
            $previousWait = $this->pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn();
            $this->pdo->exec('SET SESSION lock_wait_timeout = ' . self::LOCK_WAIT_SECONDS);
        } catch (Throwable) {
        }
        try {
            foreach (array_values(array_unique($tables)) as $table) {
                if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
                    continue;
                }
                $out[$this->alignTable($table)][] = $table;
            }
        } finally {
            if (is_numeric($previousWait)) {
                try {
                    $this->pdo->exec('SET SESSION lock_wait_timeout = ' . (int) $previousWait);
                } catch (Throwable) {
                }
            }
        }

        return $out;
    }

    /** @return 'converted'|'already'|'missing'|'skipped' */
    private function alignTable(string $table): string
    {
        $st = $this->pdo->prepare(
            'SELECT TABLE_COLLATION, COALESCE(TABLE_ROWS, 0) AS r, COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS b
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);
        $info = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($info)) {
            return 'missing';
        }
        $columns = $this->divergingColumns($table);
        if ((string) $info['TABLE_COLLATION'] === self::TARGET && $columns === []) {
            return 'already';
        }
        foreach ($columns as $column => $collation) {
            if (str_ends_with($collation, '_bin') || !str_starts_with($collation, 'utf8mb4_')) {
                ($this->log)("  [ATTENTION] {$table} laissée telle quelle : colonne {$column} en {$collation} (collation voulue ou autre jeu de caractères).\n");

                return 'skipped';
            }
        }
        $rows = (int) $info['r'];
        $bytes = (int) $info['b'];
        if ($rows > self::MAX_ROWS || $bytes > self::MAX_BYTES) {
            ($this->log)("  [ATTENTION] {$table} laissée telle quelle : trop grosse pour une conversion au déploiement (~{$rows} lignes, ~" . (int) round($bytes / 1048576) . " Mo).\n");

            return 'skipped';
        }
        try {
            $this->pdo->exec('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . self::TARGET);
        } catch (Throwable $e) {
            ($this->log)("  [ATTENTION] {$table} non convertie : " . $e->getMessage() . "\n");

            return 'skipped';
        }
        ($this->log)("  [OK] {$table} : " . (string) $info['TABLE_COLLATION'] . ' → ' . self::TARGET . " (~{$rows} lignes).\n");

        return 'converted';
    }

    /** @return array<string, string> colonne => collation, pour les colonnes texte hors utf8mb4_unicode_ci */
    public function divergingColumns(string $table): array
    {
        $st = $this->pdo->prepare(
            'SELECT COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME <> ?
             ORDER BY ORDINAL_POSITION'
        );
        $st->execute([$table, self::TARGET]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$column, $collation]) {
            $out[(string) $column] = (string) $collation;
        }

        return $out;
    }

    /**
     * Lecture seule : tables hors de la liste dont la collation diffère (historiques créées sans COLLATE). Rien n'est
     * modifié ; sert au journal du déploiement.
     *
     * @param list<string> $listed
     * @return array<string, string> table => collation
     */
    public function otherTablesOffTarget(array $listed): array
    {
        if (!$this->supported()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' AND TABLE_COLLATION IS NOT NULL AND TABLE_COLLATION <> ?
             ORDER BY TABLE_NAME'
        );
        $st->execute([self::TARGET]);
        $skip = array_fill_keys($listed, true);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$table, $collation]) {
            if (!isset($skip[(string) $table])) {
                $out[(string) $table] = (string) $collation;
            }
        }

        return $out;
    }
}
