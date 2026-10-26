<?php

declare(strict_types=1);

/**
 * Étape « Référentiel grades multi-doctrine : grade_categories, grade_systems, grades_referentiel » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Référentiel grades multi-doctrine : grade_categories, grade_systems, grades_referentiel
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grade_categories'");
if ($stmt && !$stmt->fetch()) {
    echo "Création tables référentiel grades (grade_categories, grade_systems, grades_referentiel)...\n";
    $gradeRefPath = $root . '/migrations/grade_referentiel.sql';
    if (is_file($gradeRefPath)) {
        $sql = file_get_contents($gradeRefPath);
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
        echo "Référentiel grades tables OK.\n";
    }
}
