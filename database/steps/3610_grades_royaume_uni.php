<?php

declare(strict_types=1);

/**
 * Étape « Grades britanniques » : systèmes GB_ARMY (British Army et Royal Marines), GB_RN (Royal Navy) et GB_RAF
 * (Royal Air Force) du catalogue partagé, avec leurs grades (App\Support\BritishGradeCatalog).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : un système ou un grade déjà présent
 * (même code) n’est ni recréé ni modifié ; une relance ne change rien. Référentiel des grades absent : rien à faire.
 */

$gbGradeSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$gbGradeTable = null;
foreach (['grades', 'grades_referentiel'] as $gbCandidate) {
    if ($gbGradeSchema->tableExists($gbCandidate) && $gbGradeSchema->columnExists($gbCandidate, 'grade_system_id')) {
        $gbGradeTable = $gbCandidate;
        break;
    }
}
if ($gbGradeTable === null || !$gbGradeSchema->tableExists('grade_systems') || !$gbGradeSchema->tableExists('grade_categories')) {
    echo "  [OK] Référentiel des grades absent : rien à faire.\n";
} else {
    $gbCategories = [];
    foreach ($pdo->query('SELECT id, code FROM grade_categories')->fetchAll(PDO::FETCH_ASSOC) as $gbRow) {
        $gbCategories[(string) $gbRow['code']] = (int) $gbRow['id'];
    }
    $gbOwner = $gbGradeSchema->columnExists('grade_systems', 'owner_tenant_id');
    $gbFindSystem = $pdo->prepare('SELECT id FROM grade_systems WHERE code = ?' . ($gbOwner ? ' AND owner_tenant_id IS NULL' : '') . ' LIMIT 1');
    $gbInsertSystem = $pdo->prepare("INSERT INTO grade_systems (code, label, country_code, format_type, is_active, created_at, updated_at) VALUES (?, ?, 'GB', 'classic', 1, ?, ?)");
    $gbNow = date('Y-m-d H:i:s');
    $gbSystems = [];
    foreach (\App\Support\BritishGradeCatalog::SYSTEMS as $gbCode => $gbLabel) {
        $gbFindSystem->execute([$gbCode]);
        $gbId = $gbFindSystem->fetchColumn();
        if ($gbId === false) {
            $gbInsertSystem->execute([$gbCode, $gbLabel, $gbNow, $gbNow]);
            $gbId = $pdo->lastInsertId();
            echo "  [OK] Système de grades {$gbCode} créé.\n";
        }
        $gbSystems[$gbCode] = (int) $gbId;
    }
    $gbExists = $pdo->prepare("SELECT 1 FROM {$gbGradeTable} WHERE grade_system_id = ? AND code = ? LIMIT 1");
    $gbInsert = $pdo->prepare("INSERT INTO {$gbGradeTable} (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
    $gbAdded = 0;
    $gbSkipped = 0;
    foreach (\App\Support\BritishGradeCatalog::rows() as [$gbSystem, $gbCategory, $gbCode, $gbShort, $gbLong, $gbOtan, $gbOrder, $gbCommissioned]) {
        if (!isset($gbSystems[$gbSystem], $gbCategories[$gbCategory])) {
            $gbSkipped++;
            continue;
        }
        $gbExists->execute([$gbSystems[$gbSystem], $gbCode]);
        if ($gbExists->fetchColumn()) {
            continue;
        }
        $gbInsert->execute([$gbSystems[$gbSystem], $gbCategories[$gbCategory], $gbCode, $gbShort, $gbLong, $gbOtan, $gbOrder, $gbCommissioned, $gbNow, $gbNow]);
        $gbAdded++;
    }
    echo '  [OK] Grades britanniques : ' . ($gbAdded > 0 ? $gbAdded . ' grade(s) ajouté(s)' : 'déjà présents')
        . ($gbSkipped > 0 ? ', ' . $gbSkipped . ' ignoré(s) (catégorie absente)' : '') . ".\n";
}
