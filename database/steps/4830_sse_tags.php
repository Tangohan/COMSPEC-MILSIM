<?php

declare(strict_types=1);

/**
 * Étape « SSE : étiquettes partagées du bureau et réglages des fiches de renseignement » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_21_100_sse_tags.sql, unicités dans le CREATE TABLE). Aucune colonne
 * ajoutée, aucune donnée modifiée : sans cette étape, le catalogue livré des étiquettes s'affiche en lecture seule et
 * l'étiquetage des fiches est désactivé avec un message. Retour arrière : migrations/rollback/sse_tags_down.sql.
 */

$sseTagsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseTagsTables = ['sse_tags', 'sse_tag_links', 'sse_field_note_settings'];
$sseTagsMissing = array_values(array_filter($sseTagsTables, static fn (string $t): bool => !$sseTagsSchema->tableExists($t)));
if ($sseTagsMissing === []) {
    echo "  [OK] Tables des étiquettes et des réglages des fiches déjà présentes.\n";
} else {
    $sseTagsSchema->runSqlFile($root . '/migrations/2026_11_21_100_sse_tags.sql');
    foreach ($sseTagsTables as $sseTagsTable) {
        if (!$sseTagsSchema->tableExists($sseTagsTable)) {
            throw new RuntimeException('Création de la table ' . $sseTagsTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseTagsMissing) . ".\n";
}
