<?php

declare(strict_types=1);

/**
 * Étape « Bascule grades : ancienne table -> référentiel (mapping par libellé), renommage tables » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Bascule grades : ancienne table -> référentiel (mapping par libellé), renommage tables
$hasOldGrades = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' LIMIT 1")->fetch();
$hasRef = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades_referentiel' LIMIT 1")->fetch();
$oldGradesHasTenant = $hasOldGrades && $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'tenant_id' LIMIT 1")->fetch();
if ($hasOldGrades && $hasRef && $oldGradesHasTenant) {
    echo "Bascule grades (ancienne table -> référentiel)...\n";
    $frSystem = $pdo->query("SELECT id FROM grade_systems WHERE code = 'FR_CLASSIC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $frSystemId = $frSystem ? (int) $frSystem['id'] : null;
    $oldRows = $pdo->query("SELECT id, name, short_name FROM grades")->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    if ($frSystemId) {
        $stmtMap = $pdo->prepare("SELECT id FROM grades_referentiel WHERE grade_system_id = ? AND (label_long = ? OR label_short = ?) LIMIT 1");
        foreach ($oldRows as $o) {
            $stmtMap->execute([$frSystemId, $o['name'], $o['short_name']]);
            $n = $stmtMap->fetch(PDO::FETCH_ASSOC);
            if ($n) {
                $map[(int) $o['id']] = (int) $n['id'];
            }
        }
    }
    $pdo->exec("ALTER TABLE users ADD COLUMN grade_ref_id BIGINT UNSIGNED DEFAULT NULL AFTER grade_id");
    $upd = $pdo->prepare("UPDATE users SET grade_ref_id = ? WHERE grade_id = ?");
    foreach ($map as $oldId => $newId) {
        $upd->execute([$newId, $oldId]);
    }
    $pdo->exec("ALTER TABLE users DROP FOREIGN KEY users_grade_id_fk");
    $pdo->exec("ALTER TABLE users DROP COLUMN grade_id");
    $pdo->exec("ALTER TABLE users CHANGE COLUMN grade_ref_id grade_id BIGINT UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD KEY grade_id (grade_id)");
    $pdo->exec("RENAME TABLE grades TO grades_legacy");
    $pdo->exec("RENAME TABLE grades_referentiel TO grades");
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_grade_id_fk FOREIGN KEY (grade_id) REFERENCES grades (id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "Bascule grades OK (grades_referentiel -> grades, ancienne table -> grades_legacy).\n";
}
