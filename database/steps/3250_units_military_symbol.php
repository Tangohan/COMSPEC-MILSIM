<?php

declare(strict_types=1);

/**
 * Étape « ORBAT : symbole militaire MIL-STD-2525 et échelon des unités (units.sidc, units.echelon) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * NULL = symbole et échelon déduits du nom et du type de l’unité (App\Support\UnitMilitarySymbol) : rien ne change
 * à l’affichage pour les unités existantes tant qu’un gestionnaire ne choisit pas un symbole.
 */

$unitSymbolSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$unitSymbolSchema->tableExists('units')) {
    echo "  [OK] Table units absente : rien à faire.\n";
} else {
    foreach (['sidc' => 'VARCHAR(30) NULL DEFAULT NULL', 'echelon' => 'VARCHAR(20) NULL DEFAULT NULL'] as $unitSymbolColumn => $unitSymbolDefinition) {
        echo $unitSymbolSchema->addColumnIfMissing('units', $unitSymbolColumn, $unitSymbolDefinition)
            ? "  [OK] Colonne units.{$unitSymbolColumn} ajoutée.\n"
            : "  [OK] Colonne units.{$unitSymbolColumn} déjà présente.\n";
    }
}
