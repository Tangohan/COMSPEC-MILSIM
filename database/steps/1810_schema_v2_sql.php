<?php

declare(strict_types=1);

/**
 * Étape « Schéma V2 : préférences, branding, notifications, modules, quotas » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// ----- Schéma V2 : user_ui_preferences, tenant_branding, notifications, modules, quotas -----
$schemaV2Path = $root . '/migrations/schema_v2_tenant_user_prefs.sql';
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_ui_preferences'");
if ($stmt && !$stmt->fetch() && is_file($schemaV2Path)) {
    echo "Migration schema_v2_tenant_user_prefs.sql...\n";
    $sql = file_get_contents($schemaV2Path);
    $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
    $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
    $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
    foreach ($chunks as $stmtSql) {
        $stmtSql = trim($stmtSql);
        if ($stmtSql !== '') {
            $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
        }
    }
    echo "Schéma V2 (préférences / tenant typé) OK.\n";
}
