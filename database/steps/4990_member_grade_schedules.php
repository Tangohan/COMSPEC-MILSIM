<?php

declare(strict_types=1);

/**
 * Étape « Changements de grade planifiés » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_23_110_member_grade_schedules.sql). Aucune colonne ajoutée, aucune
 * donnée modifiée, aucun droit créé (mêmes droits que le changement de grade immédiat). Sans cette table, le changement de
 * grade immédiat fonctionne comme avant et la planification n'est pas proposée. Compatible SQLite et MySQL.
 * Retour arrière : migrations/rollback/member_grade_schedules_down.sql.
 */

$gradeScheduleSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($gradeScheduleSchema->tableExists('member_grade_schedules')) {
    echo "  [OK] Table des changements de grade planifiés déjà présente.\n";
} else {
    $gradeScheduleSchema->runSqlFile($root . '/migrations/2026_11_23_110_member_grade_schedules.sql');
    if (!$gradeScheduleSchema->tableExists('member_grade_schedules')) {
        throw new RuntimeException('Création de la table member_grade_schedules impossible.');
    }
    echo "  [OK] Table créée : member_grade_schedules.\n";
}
