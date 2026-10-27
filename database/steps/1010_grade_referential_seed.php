<?php

declare(strict_types=1);

/**
 * Étape « Référentiel de grades minimal (installation vide) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Seed référentiel grades : catégories, systèmes, grades FR/US minimal (si grade_categories vide)
$stmt = $pdo->query("SELECT 1 FROM grade_categories LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Seed référentiel grades (catégories, systèmes, grades FR/US)...\n";
    $now = date('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO grade_categories (code, label, sort_order, is_active, created_at, updated_at) VALUES
        ('OFFICIER', 'Officier', 10, 1, '$now', '$now'),
        ('SOUS_OFFICIER', 'Sous-officier', 20, 1, '$now', '$now'),
        ('MDR', 'Militaire du rang', 30, 1, '$now', '$now'),
        ('CIVIL', 'Civil', 40, 1, '$now', '$now'),
        ('HORS_GRADE', 'Hors grades', 50, 1, '$now', '$now')");
    $pdo->exec("INSERT INTO grade_systems (code, label, country_code, format_type, is_active, created_at, updated_at) VALUES
        ('FR_CLASSIC', 'Grades français (classique)', 'FR', 'classic', 1, '$now', '$now'),
        ('US_CLASSIC', 'Grades américains (classique)', 'US', 'classic', 1, '$now', '$now')");
    $cat = [];
    foreach ($pdo->query("SELECT id, code FROM grade_categories")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cat[$r['code']] = (int) $r['id'];
    }
    $sys = [];
    foreach ($pdo->query("SELECT id, code FROM grade_systems")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sys[$r['code']] = (int) $r['id'];
    }
    $insGrade = $pdo->prepare("INSERT INTO grades_referentiel (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, '$now', '$now')");
    $gradesFr = [
        [1, 'SL', 'Sous-lieutenant', 'Sous-lieutenant', 'OF-1', 11, 1],
        [2, 'LT', 'Lieutenant', 'Lieutenant', 'OF-1', 12, 1],
        [3, 'CNE', 'Capitaine', 'Capitaine', 'OF-2', 13, 1],
        [4, 'CDT', 'Commandant', 'Commandant', 'OF-3', 14, 1],
        [5, 'LCL', 'Lieutenant-colonel', 'Lieutenant-colonel', 'OF-4', 15, 1],
        [6, 'COL', 'Colonel', 'Colonel', 'OF-5', 16, 1],
    ];
    foreach ($gradesFr as $g) {
        $insGrade->execute([$sys['FR_CLASSIC'], $cat['OFFICIER'], $g[1], $g[2], $g[3], $g[4], $g[5], $g[6]]);
    }
    $gradesUs = [
        [1, '2LT', 'Second Lieutenant', 'Second Lieutenant', 'O-1', 11, 1],
        [2, '1LT', 'First Lieutenant', 'First Lieutenant', 'O-2', 12, 1],
        [3, 'CPT', 'Captain', 'Captain', 'O-3', 13, 1],
        [4, 'MAJ', 'Major', 'Major', 'O-4', 14, 1],
        [5, 'LTC', 'Lieutenant Colonel', 'Lieutenant Colonel', 'O-5', 15, 1],
        [6, 'COL', 'Colonel', 'Colonel', 'O-6', 16, 1],
    ];
    foreach ($gradesUs as $g) {
        $insGrade->execute([$sys['US_CLASSIC'], $cat['OFFICIER'], $g[1], $g[2], $g[3], $g[4], $g[5], $g[6]]);
    }
    echo "Seed référentiel grades OK.\n";
}
