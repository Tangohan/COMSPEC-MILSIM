<?php

declare(strict_types=1);

/**
 * Étape « SSE : étiquettes partagées du bureau et réglages des fiches de renseignement » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root, APRÈS l'étape sse_attachments (4840). Idempotente :
 *  - le catalogue des étiquettes des rattachements (sse_attach_tags) devient le catalogue UNIQUE des étiquettes du bureau :
 *    chaque colonne n'est ajoutée que si elle manque (ALTER TABLE … ADD COLUMN, une par une) ; les étiquettes existantes
 *    gardent la portée « attach », l'unicité communauté + clé (slug) du CREATE TABLE d'origine ne change pas ;
 *  - sse_tag_links et sse_field_note_settings ne sont créées que si elles manquent (CREATE TABLE IF NOT EXISTS, depuis
 *    migrations/2026_11_21_100_sse_tags.sql, unicités dans le CREATE TABLE).
 * Aucune donnée modifiée : sans cette étape, le catalogue livré des étiquettes de fiches s'affiche en lecture seule,
 * l'étiquetage des fiches est désactivé avec un message, et la fenêtre « Rattacher » fonctionne comme avant.
 * Retour arrière : migrations/rollback/sse_tags_down.sql.
 */

$sseTagsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$sseTagsSchema->tableExists('sse_attach_tags')) {
    echo "  [OK] Table sse_attach_tags absente : l'étape de la fenêtre Rattacher (sse_attachments) passe d'abord.\n";

    return;
}
$sseTagsColumns = [
    'scope' => "VARCHAR(24) NOT NULL DEFAULT 'attach'",
    'family' => "VARCHAR(24) NOT NULL DEFAULT ''",
    'code' => "VARCHAR(40) NOT NULL DEFAULT ''",
    'name_fr' => "VARCHAR(80) NOT NULL DEFAULT ''",
    'name_en' => "VARCHAR(80) NOT NULL DEFAULT ''",
    'description' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'description_en' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'qualifier' => "VARCHAR(12) NOT NULL DEFAULT ''",
    'sort_order' => 'INT NOT NULL DEFAULT 0',
    'origin' => "VARCHAR(12) NOT NULL DEFAULT 'custom'",
    'status' => "VARCHAR(12) NOT NULL DEFAULT 'active'",
    'merged_into' => 'VARCHAR(40) NULL',
    'updated_by_user_id' => 'INT NULL',
    'updated_at' => 'DATETIME NULL',
];
$sseTagsAdded = [];
foreach ($sseTagsColumns as $sseTagsColumn => $sseTagsDefinition) {
    if ($sseTagsSchema->addColumnIfMissing('sse_attach_tags', $sseTagsColumn, $sseTagsDefinition)) {
        $sseTagsAdded[] = $sseTagsColumn;
    }
}
if ($sseTagsSchema->addIndexIfMissing('sse_attach_tags', 'idx_sse_attach_tag_family', ['tenant_id', 'scope', 'family', 'status'])) {
    $sseTagsAdded[] = 'index idx_sse_attach_tag_family';
}
echo $sseTagsAdded === []
    ? "  [OK] Colonnes des étiquettes partagées déjà présentes sur sse_attach_tags.\n"
    : '  [OK] sse_attach_tags complétée : ' . implode(', ', $sseTagsAdded) . ".\n";

$sseTagsTables = ['sse_tag_links', 'sse_field_note_settings'];
$sseTagsMissing = array_values(array_filter($sseTagsTables, static fn (string $t): bool => !$sseTagsSchema->tableExists($t)));
if ($sseTagsMissing === []) {
    echo "  [OK] Tables des liaisons d'étiquettes et des réglages des fiches déjà présentes.\n";
} else {
    $sseTagsSchema->runSqlFile($root . '/migrations/2026_11_21_100_sse_tags.sql');
    foreach ($sseTagsTables as $sseTagsTable) {
        if (!$sseTagsSchema->tableExists($sseTagsTable)) {
            throw new RuntimeException('Création de la table ' . $sseTagsTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseTagsMissing) . ".\n";
}
