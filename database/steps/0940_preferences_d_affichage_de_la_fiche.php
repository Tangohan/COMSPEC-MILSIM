<?php

declare(strict_types=1);

/**
 * Étape « Préférences d’affichage de la fiche » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Préférences d’affichage de la fiche
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings'");
if ($stmt && !$stmt->fetch()) {
    echo "Création user_profile_display_settings...\n";
    $udsPath = $root . '/migrations/user_profile_display_settings.sql';
    if (is_file($udsPath)) {
        $sql = file_get_contents($udsPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
            }
        }
        echo "user_profile_display_settings OK.\n";
    }
}
