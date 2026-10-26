<?php

declare(strict_types=1);

/**
 * Contexte partagé par les étapes de database/steps/ (chargé une fois par run-migrations.php, avant la
 * première étape, y compris quand une seule étape est relancée).
 *
 * Variables disponibles dans toutes les étapes : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo, et les helpers ci-dessous.
 */

/** Journal des migrations SSE (ex-variable posée au milieu du script monolithique). */
$sseCliLog = static function (string $m): void {
    echo $m;
};
