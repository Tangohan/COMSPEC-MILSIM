<?php

declare(strict_types=1);

/**
 * Étape « Academy : vérification automatique par Athena des chapitres en attente d'un formateur » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : crée la table des réglages par
 * communauté (activation, délai en heures, cours exclus) si elle est absente ; une relance ne change rien.
 * La fonction est désactivée tant qu'une communauté ne l'a pas activée.
 */

$academyAutoSchema = new \App\Support\Migrations\SchemaInspector($pdo);
echo "Academy : vérification automatique des chapitres en attente…\n";
if ($academyAutoSchema->tableExists('academy_auto_validation_settings')) {
    echo "  [OK] Table academy_auto_validation_settings déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE academy_auto_validation_settings (
            tenant_id INT UNSIGNED NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            delay_hours SMALLINT UNSIGNED NOT NULL DEFAULT 72,
            excluded_courses TEXT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id),
            KEY idx_aavs_enabled (enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table academy_auto_validation_settings créée.\n";
}
