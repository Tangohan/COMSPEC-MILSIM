<?php

declare(strict_types=1);

/**
 * Étape « Module Bureau Courrier : tables document_presets, document_templates, courrier_documents, etc » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Module Bureau Courrier : tables document_presets, document_templates, courrier_documents, etc.
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_presets'");
if ($stmt && !$stmt->fetch()) {
    echo "Création tables module Courrier (document_presets, document_templates, courrier_documents, etc.)...\n";
    $courrierPath = $root . '/migrations/courrier_module.sql';
    if (is_file($courrierPath)) {
        $sql = file_get_contents($courrierPath);
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
        echo "Module Courrier tables OK.\n";
    }
}
