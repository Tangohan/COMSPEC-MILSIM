<?php

declare(strict_types=1);

/**
 * Étape « Profils d'IA du Bureau SSE » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_ai_profiles_migration.php, rejoué sans effet si les tables sont en place.
 */

$atakSseAiProfilesMigrate = require $root . '/bootstrap/atak_sse_ai_profiles_migration.php';
$atakSseAiProfilesMigrate($pdo, static function (string $m): void {
    echo $m;
});
