<?php

declare(strict_types=1);

/**
 * Étape « JNET : éléments épinglés de l'espace commun » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table jnet_pins n'est créée que si
 * elle manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_090_jnet_pins.sql). Aucune donnée n'est ajoutée ni
 * modifiée : sans épingle, l'espace commun s'affiche comme avant. Retour arrière : migrations/rollback/jnet_pins_down.sql.
 */

$jnetPinsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($jnetPinsSchema->tableExists('jnet_pins')) {
    echo "  [OK] Table des épingles JNET déjà présente.\n";
} else {
    $jnetPinsSchema->runSqlFile($root . '/migrations/2026_11_15_090_jnet_pins.sql');
    if (!$jnetPinsSchema->tableExists('jnet_pins')) {
        throw new RuntimeException('Création de la table jnet_pins impossible.');
    }
    echo "  [OK] Table créée : jnet_pins.\n";
}
