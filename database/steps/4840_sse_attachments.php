<?php

declare(strict_types=1);

/**
 * Étape « SSE : fenêtre Rattacher universelle (rattachements enrichis, étiquettes, pistes) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_19_100_sse_attachments.sql, unicités dans le CREATE TABLE). Aucune
 * colonne ajoutée, aucune donnée modifiée : sans cette étape, la fenêtre « Rattacher » fonctionne en lecture et pour le
 * rattachement historique à un dossier, le reste est désactivé avec un message. Retour arrière :
 * migrations/rollback/sse_attachments_down.sql.
 */

$sseAttachSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseAttachTables = ['sse_link_attachments', 'sse_attach_tags', 'sse_leads'];
$sseAttachMissing = array_values(array_filter($sseAttachTables, static fn (string $t): bool => !$sseAttachSchema->tableExists($t)));
if ($sseAttachMissing === []) {
    echo "  [OK] Tables des rattachements, des étiquettes et des pistes déjà présentes.\n";
} else {
    $sseAttachSchema->runSqlFile($root . '/migrations/2026_11_19_100_sse_attachments.sql');
    foreach ($sseAttachTables as $sseAttachTable) {
        if (!$sseAttachSchema->tableExists($sseAttachTable)) {
            throw new RuntimeException('Création de la table ' . $sseAttachTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseAttachMissing) . ".\n";
}
