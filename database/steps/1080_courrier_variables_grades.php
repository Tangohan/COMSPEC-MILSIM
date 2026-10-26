<?php

declare(strict_types=1);

/**
 * Étape « Variables courrier : grades multi-doctrine (si pas déjà présentes) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Variables courrier : grades multi-doctrine (si pas déjà présentes)
$stmt = $pdo->query("SELECT 1 FROM document_variables_catalog WHERE code = 'user.grade_text' LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout variables courrier (grades)...\n";
    $gradeVars = [
        ['user.grade_text', 'Grade (texte classique)', 'user', 'Libellé classique du grade', 'user'],
        ['user.grade_short', 'Grade (code court)', 'user', 'Code court (ex. CNE)', 'user'],
        ['user.grade_otan', 'Grade (code OTAN)', 'user', 'Code OTAN (ex. OF-2)', 'user'],
        ['user.grade_full', 'Grade (hybride)', 'user', 'Libellé avec code OTAN (ex. Capitaine (OF-2))', 'user'],
        ['user.category_label', 'Catégorie de personnel', 'user', 'Libellé de la catégorie (Officier, etc.)', 'user'],
        ['superior.grade_text', 'Grade du supérieur (texte)', 'hierarchy', 'Grade classique du supérieur', 'hierarchy'],
        ['superior.grade_otan', 'Grade du supérieur (OTAN)', 'hierarchy', 'Code OTAN du supérieur', 'hierarchy'],
    ];
    $insVar = $pdo->prepare("INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
    foreach ($gradeVars as $v) {
        $insVar->execute([$v[0], $v[1], $v[2], $v[3], $v[4]]);
    }
    echo "Variables grades courrier OK.\n";
}

$stmt = $pdo->query("SELECT 1 FROM document_variables_catalog WHERE code = 'tenant.name' LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout variables courrier (communauté / groupe)...\n";
    $orgVars = [
        ['tenant.name', 'Communauté', 'structure', 'Nom de la communauté', 'structure'],
        ['group.name', 'Groupe', 'structure', 'Groupe d’affectation', 'structure'],
    ];
    $insVar = $pdo->prepare("INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
    foreach ($orgVars as $v) {
        $insVar->execute([$v[0], $v[1], $v[2], $v[3], $v[4]]);
    }
    echo "Variables communauté / groupe courrier OK.\n";
}
