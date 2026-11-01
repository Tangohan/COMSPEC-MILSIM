<?php

declare(strict_types=1);

/**
 * Étape « Double vérification : anti-rejeu des codes de l’application (user_totp_used_steps) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : TotpReplayGuard::ensureSchema() (CREATE TABLE IF NOT EXISTS), aussi lancée à la demande.
 */

$totpStepsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($totpStepsSchema->tableExists('user_totp_used_steps')) {
    echo "  [OK] Table user_totp_used_steps déjà présente.\n";
} elseif ((new \App\Services\Auth\TotpReplayGuard($pdo))->ensureSchema() && $totpStepsSchema->tableExists('user_totp_used_steps')) {
    echo "  [OK] Table user_totp_used_steps créée.\n";
} else {
    throw new RuntimeException('Création de la table user_totp_used_steps impossible.');
}
