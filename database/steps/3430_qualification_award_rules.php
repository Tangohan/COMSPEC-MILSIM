<?php

declare(strict_types=1);

/**
 * Étape « Qualifications : modes d’obtention, critères automatiques, prérequis et visibilité » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n’est créée que si elle
 * manque, depuis le seul fichier migrations/2026_11_02_010_qualification_award_rules.sql (CREATE TABLE IF NOT EXISTS).
 * Une qualification sans ligne dans cette table garde le fonctionnement d’avant : délivrée à la main, visible partout.
 * Sans le référentiel des qualifications (personnel_qualification_definitions), rien n’est créé.
 */

$qualificationRulesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($qualificationRulesSchema->tableExists('qualification_award_rules')) {
    echo "  [OK] Table qualification_award_rules déjà présente.\n";
} elseif (!$qualificationRulesSchema->tableExists('personnel_qualification_definitions')) {
    echo "  [OK] Référentiel des qualifications absent : rien à créer.\n";
} else {
    $qualificationRulesSchema->runSqlFile($root . '/migrations/2026_11_02_010_qualification_award_rules.sql');
    echo "  [OK] Table qualification_award_rules créée.\n";
}
