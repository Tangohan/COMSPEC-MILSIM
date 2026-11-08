<?php

declare(strict_types=1);

/**
 * Étape « Centre de diffusion » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute une colonne broadcast_key
 * (12 caractères, facultative) aux annonces du site (tenant_alerts) et aux messages imposés aux joueurs
 * (atak_admin_messages), avec un index (tenant_id, broadcast_key). Elle relie les morceaux d'une même diffusion du
 * centre (/back-office/alerts) : bandeau du site et un message par joueur. Une table absente est laissée de côté
 * (elle est créée par sa propre étape ou au premier usage). Aucune donnée existante n'est modifiée ; le mod ne lit
 * pas cette colonne.
 */

$broadcastCenterSchema = new \App\Support\Migrations\SchemaInspector($pdo);
echo "Centre de diffusion : lien entre les morceaux d'une diffusion…\n";
foreach (['tenant_alerts' => 'idx_tenant_alerts_broadcast', 'atak_admin_messages' => 'idx_aam_broadcast'] as $broadcastCenterTable => $broadcastCenterIdx) {
    if (!$broadcastCenterSchema->tableExists($broadcastCenterTable)) {
        echo "  {$broadcastCenterTable} absente : rien à faire.\n";
        continue;
    }
    if ($broadcastCenterSchema->addColumnIfMissing($broadcastCenterTable, 'broadcast_key', "char(12) DEFAULT NULL COMMENT 'Diffusion du centre (back-office/alerts)'")) {
        echo "  [OK] {$broadcastCenterTable}.broadcast_key\n";
    }
    if ($broadcastCenterSchema->addIndexIfMissing($broadcastCenterTable, $broadcastCenterIdx, ['tenant_id', 'broadcast_key'])) {
        echo "  [OK] {$broadcastCenterTable}.{$broadcastCenterIdx}\n";
    }
}
