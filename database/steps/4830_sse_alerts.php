<?php

declare(strict_types=1);

/**
 * Étape « SSE alertes : adresse de signalement, service à informer, signalements, propositions, emblèmes des services » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, unicités dans le CREATE TABLE, depuis migrations/2026_11_20_100_sse_alerts.sql). Aucune colonne
 * ajoutée, aucune donnée modifiée : sans cette étape, les avis et les fiches de vigilance s'émettent comme avant, sans adresse de
 * signalement ni QR code, sans levée d'une fiche de vigilance, et les services gardent leur emblème générique. Retour arrière :
 * migrations/rollback/sse_alerts_down.sql.
 */

$sseAlertSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseAlertTables = ['sse_alert_links', 'sse_alert_reports', 'sse_alert_proposals', 'sse_alert_acks', 'sse_service_emblems'];
$sseAlertMissing = array_values(array_filter($sseAlertTables, static fn (string $t): bool => !$sseAlertSchema->tableExists($t)));
if ($sseAlertMissing === []) {
    echo "  [OK] Tables des alertes SSE déjà présentes.\n";
} else {
    $sseAlertSchema->runSqlFile($root . '/migrations/2026_11_20_100_sse_alerts.sql');
    foreach ($sseAlertTables as $sseAlertTable) {
        if (!$sseAlertSchema->tableExists($sseAlertTable)) {
            throw new RuntimeException('Création de la table ' . $sseAlertTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseAlertMissing) . ".\n";
}
