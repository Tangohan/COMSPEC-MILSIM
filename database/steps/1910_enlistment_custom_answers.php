<?php

declare(strict_types=1);

/**
 * Étape « Migration enlistment_custom_answers (réponses questions perso + refus auto) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$enlistmentCustomAnswersMigrate = require $root . '/bootstrap/enlistment_custom_answers_migration.php';
try {
    echo "Migration enlistment_custom_answers (réponses questions perso + refus auto)...\n";
    $enlistmentCustomAnswersMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] enlistment_custom_answers : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('enlistment_custom_answers', $e);
}
