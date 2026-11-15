<?php

declare(strict_types=1);

/**
 * Étape « SSE avis de recherche : codification, signalement physique et pièces jointes » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_16_130_sse_wanted_codes.sql). Aucune colonne ajoutée, aucune donnée
 * modifiée : sans ces tables, l'avis se place sans codes ni pièces jointes. Retour arrière :
 * migrations/rollback/sse_wanted_codes_down.sql.
 */

$sseCodesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$sseCodesTables = ['sse_wanted_notice_extras', 'sse_wanted_attachments'];
$sseCodesMissing = array_values(array_filter($sseCodesTables, static fn (string $t): bool => !$sseCodesSchema->tableExists($t)));
if (!$sseCodesSchema->tableExists('sse_wanted_notices')) {
    echo "  [OK] Table sse_wanted_notices absente : l'étape des avis de recherche passe d'abord.\n";
} elseif ($sseCodesMissing === []) {
    echo "  [OK] Tables de codification des avis déjà présentes.\n";
} else {
    $sseCodesSchema->runSqlFile($root . '/migrations/2026_11_16_130_sse_wanted_codes.sql');
    foreach ($sseCodesTables as $sseCodesTable) {
        if (!$sseCodesSchema->tableExists($sseCodesTable)) {
            throw new RuntimeException('Création de la table ' . $sseCodesTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $sseCodesMissing) . ".\n";
}
