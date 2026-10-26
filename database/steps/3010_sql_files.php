<?php

declare(strict_types=1);

/**
 * Étape « Fichiers migrations/*.sql en attente (nouveaux, modifiés ou en échec) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Tous les fichiers migrations/*.sql en attente (nouveaux, modifiés ou en échec au passage précédent).
$migrationRunner->runPendingSqlFiles($root . '/migrations');
$migrationEnsurePdo();
