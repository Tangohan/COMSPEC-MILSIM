<?php

declare(strict_types=1);

/**
 * Étape « SSE identifiants des données » (Schéma + rattrapage).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 * - crée sse_data_identifiers (numéro d'identification, nom de code, nom de fiche, DSE, IA reliée ; unicités par communauté
 *   dans le CREATE TABLE), sse_identifier_counters (séquence qui ne recule jamais) et sse_identifier_history (journal des
 *   changements et noms de code abandonnés) uniquement si elles manquent ;
 * - attribue des identifiants aux personnes, identités du registre, fiches de renseignement, comptes rendus et documents qui
 *   n'en ont pas. Une donnée déjà pourvue n'est jamais touchée ; aucune donnée existante n'est modifiée.
 * Relancer l'étape ne fait que traiter les données arrivées depuis. Compatible SQLite et MySQL / MariaDB.
 * Retour arrière : migrations/rollback/sse_data_identifiers_down.sql.
 */

$identSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$identDriver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$identCreated = [];
foreach (\App\Services\Sse\SseIdentifierService::schema($identDriver) as $identTable => $identSql) {
    if (!$identSchema->tableExists($identTable)) {
        $pdo->exec($identSql);
        $identCreated[] = $identTable;
    }
    if (!$identSchema->tableExists($identTable)) {
        throw new RuntimeException('Table ' . $identTable . ' impossible à créer.');
    }
}
echo $identCreated === [] ? "  [OK] Tables des identifiants SSE déjà en place.\n" : '  [OK] Tables créées : ' . implode(', ', $identCreated) . ".\n";

$identCounts = (new \App\Services\Sse\SseIdentifierService($pdo))->backfill();
foreach ($identCounts as $identType => $identCount) {
    echo '  [OK] ' . $identType . ' : ' . $identCount . " donnée(s) pourvue(s) d'identifiants.\n";
}
