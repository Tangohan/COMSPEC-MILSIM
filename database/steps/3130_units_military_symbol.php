<?php

declare(strict_types=1);

/**
 * Étape « ORBAT : symbole militaire MIL-STD-2525 et échelon des unités » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$unitsMilitarySymbol = require $root . '/bootstrap/units_military_symbol_migration.php';
$migrationRunner->step('units_military_symbol', static function () use ($unitsMilitarySymbol, $pdo): void {
    $unitsMilitarySymbol($pdo);
});
