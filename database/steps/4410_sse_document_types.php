<?php

declare(strict_types=1);

/**
 * Étape « Papiers : types du mod SSE » (Données).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_document_types_migration.php, qui ajoute aux pays réels déjà importés (sse_id_countries, real_sim = 1)
 * le titre de séjour et le certificat d'immatriculation quand le mod les délivre pour ce pays. Aucun changement de
 * schéma ; rejouée, elle ne rajoute pas un type déjà présent.
 */

$sseDocumentTypesMigrate = require $root . '/bootstrap/sse_document_types_migration.php';
try {
    echo "Migration sse_document_types (titre de séjour et certificat d'immatriculation des pays réels importés)…\n";
    $sseDocumentTypesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_document_types : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_document_types', $e);
}
$migrationEnsurePdo();
