<?php

declare(strict_types=1);

/**
 * Étape « Cartes ATAK : les 289 cartes de l'atlas plan-ops » (Données seulement, aucun schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo, après atak_maps. Lit database/data/atlas_maps.json
 * (tools/atlas_refresh_maps.py le rafraîchit) et crée, pour chaque carte d'Arma 3 de https://atlas.plan-ops.fr, la ligne
 * atak_maps qui manque : nom, taille, tuiles et repère. Les cartes déjà présentes avec une autre origine (les huit de départ,
 * une carte réglée à la main) ne sont jamais modifiées ; une carte importée d'ici est remise à jour. Idempotente.
 */

try {
    $atlasMapsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
    if (!$atlasMapsSchema->tableExists('atak_maps')) {
        echo "  [OK] Table atak_maps absente : lancer d'abord atak_maps.\n";
    } else {
        $atlasMapsResult = \App\Services\Tactical\AtlasMapCatalog::import($pdo);
        echo sprintf(
            "  [OK] Cartes de l'atlas : +%d ajoutée(s), %d mise(s) à jour, %d déjà réglée(s) à la main ou de départ (inchangées).\n",
            $atlasMapsResult['inserted'],
            $atlasMapsResult['updated'],
            $atlasMapsResult['kept']
        );
    }
} catch (\Throwable $e) {
    echo '  [ATTENTION] cartes de l\'atlas : ' . $e->getMessage() . "\n";
}
