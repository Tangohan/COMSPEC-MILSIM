<?php

declare(strict_types=1);

/**
 * Retrait du forum (octobre 2026).
 *
 * Avant le schéma : les signalements, leur chronologie et les notifications des membres quittent les
 * tables forum_* (renommées content_reports, content_report_timeline, member_notifications) pour que
 * schema.sql ne crée pas de tables vides à côté.
 * Après le schéma : suppression définitive des tables, colonnes, droits et réglages du forum.
 * Idempotent — appelée depuis run-migrations.php.
 */

function forum_removal_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $st->execute([$table]);

    return (bool) $st->fetchColumn();
}

function forum_removal_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $st->execute([$table, $column]);

    return (bool) $st->fetchColumn();
}

/**
 * Supprime les clés étrangères d’une table qui pointent vers une colonne donnée.
 */
function forum_removal_drop_column_fks(PDO $pdo, string $table, string $column): void
{
    $st = $pdo->prepare(
        'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $st->execute([$table, $column]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $fk) {
        $pdo->exec('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . str_replace('`', '', (string) $fk) . '`');
    }
}

function forum_removal_drop_columns(PDO $pdo, string $table, array $columns): void
{
    if (!forum_removal_table_exists($pdo, $table)) {
        return;
    }
    foreach ($columns as $column) {
        if (!forum_removal_column_exists($pdo, $table, $column)) {
            continue;
        }
        forum_removal_drop_column_fks($pdo, $table, $column);
        $pdo->exec('ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`');
        echo "  {$table}.{$column} supprimée.\n";
    }
}

function run_forum_removal_pre_schema(PDO $pdo): void
{
    if (forum_removal_table_exists($pdo, 'forum_reports')) {
        // Les signalements ne pointent plus vers des messages ou des sujets.
        forum_removal_drop_columns($pdo, 'forum_reports', ['post_id', 'topic_id']);
    }

    $renames = [
        'forum_reports' => 'content_reports',
        'forum_report_timeline' => 'content_report_timeline',
        'forum_notifications' => 'member_notifications',
    ];
    foreach ($renames as $old => $new) {
        if (forum_removal_table_exists($pdo, $old) && !forum_removal_table_exists($pdo, $new)) {
            $pdo->exec('RENAME TABLE `' . $old . '` TO `' . $new . '`');
            echo "Table {$old} renommée en {$new}.\n";
        }
    }
}

function run_forum_removal_post_schema(PDO $pdo): void
{
    // Droits : les rôles qui modéraient le forum gardent le traitement des signalements.
    if (forum_removal_table_exists($pdo, 'permissions') && forum_removal_table_exists($pdo, 'role_permissions')) {
        $holders = $pdo->query(
            "SELECT DISTINCT rp.role_id, p.tenant_id
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug IN ('forum.moderate', 'forum.moderate_organization', 'forum.reports.manage')"
        );
        $rows = $holders ? $holders->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($rows !== []) {
            $find = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id <=> ? AND slug = 'moderation.reports.manage' LIMIT 1");
            $create = $pdo->prepare(
                "INSERT INTO permissions (tenant_id, name, slug, module, action, created_at)
                 VALUES (?, 'Traiter les signalements des membres', 'moderation.reports.manage', 'moderation', 'moderate', NOW())"
            );
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            $permIds = [];
            foreach ($rows as $row) {
                $tid = $row['tenant_id'] !== null ? (int) $row['tenant_id'] : null;
                $key = $tid === null ? 'site' : (string) $tid;
                if (!isset($permIds[$key])) {
                    $find->execute([$tid]);
                    $pid = (int) ($find->fetchColumn() ?: 0);
                    if ($pid < 1) {
                        $create->execute([$tid]);
                        $pid = (int) $pdo->lastInsertId();
                    }
                    $permIds[$key] = $pid;
                }
                $link->execute([(int) $row['role_id'], $permIds[$key]]);
            }
            echo '  Signalements : ' . count($rows) . " rôle(s) de modération du forum conservent le traitement des signalements.\n";
        }
        $del = $pdo->exec("DELETE FROM permissions WHERE slug LIKE 'forum.%'");
        if ($del) {
            echo "  {$del} droit(s) forum supprimé(s).\n";
        }
    }

    // Intitulés de rôles déjà créés qui parlaient encore du forum.
    if (forum_removal_table_exists($pdo, 'roles')) {
        $pdo->exec(
            "UPDATE roles SET description = 'Même périmètre que le modérateur plateforme, avec la supervision des décisions de modération.'
             WHERE tenant_id IS NULL AND slug = 'site_senior_moderator' AND description LIKE '%forum%'"
        );
        $pdo->exec(
            "UPDATE roles SET name = 'Officier responsable de la communication'
             WHERE tenant_id IS NOT NULL AND slug = 'forum_moderator' AND name LIKE '%forum%'"
        );
        $pdo->exec(
            "UPDATE roles SET description = 'Échanges, annonces, modération et structuration du discours collectif au sein de l’organisation.'
             WHERE tenant_id IS NOT NULL AND slug = 'forum_moderator' AND description LIKE '%forum%'"
        );
    }

    // Réglages du forum et du brief membres.
    if (forum_removal_table_exists($pdo, 'site_settings')) {
        $pdo->exec("DELETE FROM site_settings WHERE `key` LIKE 'forum\\_%' OR `key` IN ('public_mod_forum', 'forum_members_only')");
    }
    if (forum_removal_table_exists($pdo, 'platform_settings')) {
        $pdo->exec("DELETE FROM platform_settings WHERE setting_key IN ('brief_member_access', 'brief_member_closed_message')");
    }

    // Notifications qui renvoyaient vers des sujets du forum.
    if (forum_removal_table_exists($pdo, 'member_notifications')) {
        $pdo->exec("DELETE FROM member_notifications WHERE type IN ('topic_reply', 'forum_mention', 'interteam_coop_reply', 'moderation_alert')");
    }

    // Annonces de coopération : plus de canal forum.
    if (forum_removal_table_exists($pdo, 'cooperation_announcement_templates')) {
        $pdo->exec("DELETE FROM cooperation_announcement_templates WHERE channel = 'forum'");
        forum_removal_drop_columns($pdo, 'cooperation_announcement_templates', ['forum_settings_json']);
        try {
            $pdo->exec("ALTER TABLE cooperation_announcement_templates MODIFY channel ENUM('email','in_app') NOT NULL");
        } catch (\Throwable $e) {
            echo '  [note] cooperation_announcement_templates.channel : ' . $e->getMessage() . "\n";
        }
    }

    forum_removal_drop_columns($pdo, 'interteam_missions', ['coop_forum_category_id', 'coop_forum_topic_id']);
    forum_removal_drop_columns($pdo, 'recruitment_openings', ['forum_topic_id_externe', 'forum_topic_id_interne']);
    forum_removal_drop_columns($pdo, 'user_profile_display_settings', [
        'forum_alias',
        'forum_label_mode',
        'forum_visible_role_id',
        'show_matricule_forum',
        'show_grade_forum',
        'show_unit_forum',
        'show_bio_forum',
        'hide_forum_level',
    ]);

    $tables = [
        'forum_attachments',
        'forum_banned_words',
        'forum_blacklisted_domains',
        'forum_category_subscriptions',
        'forum_internal_flags',
        'forum_message_acks',
        'forum_moderation_logs',
        'forum_moderation_rules',
        'forum_post_audit_logs',
        'forum_post_reactions',
        'forum_post_votes',
        'forum_read',
        'forum_report_events',
        'forum_tags',
        'forum_topic_mandatory_reads',
        'forum_topic_subscriptions',
        'forum_topic_tags',
        'forum_posts',
        'forum_topics',
        'forum_categories',
        'user_forum_stats',
        'cooperation_forum_announcement_log',
        'interteam_mission_forum_grants',
    ];
    $present = array_values(array_filter($tables, static fn (string $t): bool => forum_removal_table_exists($pdo, $t)));
    if ($present === []) {
        return;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($present as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
    echo '  Forum : ' . count($present) . " table(s) supprimée(s) définitivement.\n";
}
