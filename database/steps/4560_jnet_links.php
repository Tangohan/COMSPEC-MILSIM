<?php

declare(strict_types=1);

/**
 * Étape « JNET : liaisons entre unités et liens entre contenus » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_14_070_jnet_links.sql). Aucune donnée n'est ajoutée ni
 * modifiée : sans liaison ni lien, rien ne change dans JNET. Retour arrière : migrations/rollback/jnet_links_down.sql.
 */

$jnetLinksSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$jnetLinksTables = ['jnet_unit_links', 'jnet_links'];
$jnetLinksMissing = array_values(array_filter($jnetLinksTables, static fn (string $t): bool => !$jnetLinksSchema->tableExists($t)));
if ($jnetLinksMissing === []) {
    echo "  [OK] Tables des liaisons et liens JNET déjà présentes.\n";
} else {
    $jnetLinksSchema->runSqlFile($root . '/migrations/2026_11_14_070_jnet_links.sql');
    foreach ($jnetLinksTables as $jnetLinksTable) {
        if (!$jnetLinksSchema->tableExists($jnetLinksTable)) {
            throw new RuntimeException('Création de la table ' . $jnetLinksTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $jnetLinksMissing) . ".\n";
}
