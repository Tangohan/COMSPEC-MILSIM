<?php

declare(strict_types=1);

/**
 * Étape « Module Courrier : table user_signatures et colonnes signature sur courrier_documents » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Module Courrier : table user_signatures et colonnes signature sur courrier_documents
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_signatures'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table user_signatures...\n";
    $courrierSigPath = $root . '/migrations/courrier_signatures.sql';
    if (is_file($courrierSigPath)) {
        $sql = file_get_contents($courrierSigPath);
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
        echo "user_signatures OK.\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'signed_at'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout courrier_documents.signed_at...\n";
    $pdo->exec("ALTER TABLE courrier_documents ADD COLUMN signed_at DATETIME DEFAULT NULL AFTER signed_by");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'signature_data_json'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout courrier_documents.signature_data_json...\n";
    $pdo->exec("ALTER TABLE courrier_documents ADD COLUMN signature_data_json JSON DEFAULT NULL AFTER signed_at");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'content_hash'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout courrier_documents.content_hash...\n";
    $pdo->exec("ALTER TABLE courrier_documents ADD COLUMN content_hash VARCHAR(64) DEFAULT NULL AFTER signature_data_json");
}
