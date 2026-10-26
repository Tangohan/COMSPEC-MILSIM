<?php

declare(strict_types=1);

/**
 * Étape « Courrier : notifications in-app (document signalé aux membres) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Courrier : notifications in-app (document signalé aux membres)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_document_notifications'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table courrier_document_notifications...\n";
    $cdnPath = $root . '/migrations/courrier_document_notifications.sql';
    if (is_file($cdnPath)) {
        $sql = file_get_contents($cdnPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                try {
                    $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                } catch (PDOException $e) {
                    echo "  [ATTENTION] " . $e->getMessage() . "\n";
                }
            }
        }
        echo "courrier_document_notifications OK.\n";
    }
}
