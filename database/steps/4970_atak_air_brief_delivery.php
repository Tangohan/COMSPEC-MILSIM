<?php

declare(strict_types=1);

/**
 * Étape « Briefs air : état de livraison au pilote » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque colonne n'est ajoutée que si
 * elle manque, aucune donnée lue ni modifiée, aucune transaction. Ajoute à atak_nine_line_recipients :
 * - polled_as : nom sous lequel le téléphone du pilote a interrogé sa boîte (indicatif de l'appareil, du joueur, nom de jeu) ;
 * - last_polled_at : dernier relevé de la boîte qui a renvoyé ce brief.
 * Le statut (envoyé, livré, lu, acquitté) et ses horodatages existent déjà (étape 3720) ; sans cette étape, la livraison
 * est tenue quand même, sans indicatif interrogé ni dernier relevé. Compatible SQLite et MySQL / MariaDB.
 * Retour arrière : migrations/rollback/atak_air_brief_delivery_down.sql.
 */

$airDeliverySchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$airDeliverySchema->tableExists('atak_nine_line_recipients')) {
    echo "  [INFO] Table atak_nine_line_recipients absente (étape 3720) : rien à ajouter.\n";
} else {
    $airDeliveryCollate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' COLLATE utf8mb4_unicode_ci' : '';
    $airDeliveryAdded = [];
    if ($airDeliverySchema->addColumnIfMissing('atak_nine_line_recipients', 'polled_as', 'VARCHAR(64)' . $airDeliveryCollate . ' NULL DEFAULT NULL')) {
        $airDeliveryAdded[] = 'polled_as';
    }
    if ($airDeliverySchema->addColumnIfMissing('atak_nine_line_recipients', 'last_polled_at', 'DATETIME NULL DEFAULT NULL')) {
        $airDeliveryAdded[] = 'last_polled_at';
    }
    foreach (['polled_as', 'last_polled_at'] as $airDeliveryColumn) {
        if (!$airDeliverySchema->columnExists('atak_nine_line_recipients', $airDeliveryColumn)) {
            throw new RuntimeException('Colonne atak_nine_line_recipients.' . $airDeliveryColumn . ' impossible à créer.');
        }
    }
    echo $airDeliveryAdded === []
        ? "  [OK] Livraison des briefs air déjà en place.\n"
        : '  [OK] Ajouté à atak_nine_line_recipients : ' . implode(', ', $airDeliveryAdded) . ".\n";
}
