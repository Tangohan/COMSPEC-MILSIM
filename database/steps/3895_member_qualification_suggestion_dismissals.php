<?php

declare(strict_types=1);

/**
 * Étape « Qualifications suggérées : suggestions ignorées par membre » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente (CREATE TABLE IF NOT EXISTS) :
 * member_qualification_suggestion_dismissals garde, par communauté et par membre, les qualifications suggérées
 * qu'un responsable a choisi d'ignorer (clé « q:<id du catalogue> » ou « d:<fiche du référentiel> »). Une ligne par
 * membre et par suggestion ; rien d'existant n'est modifié.
 */

$schema3895 = new \App\Support\Migrations\SchemaInspector($pdo);
if ($schema3895->tableExists('member_qualification_suggestion_dismissals')) {
    echo "  [OK] Table member_qualification_suggestion_dismissals déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS member_qualification_suggestion_dismissals (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            suggestion_key VARCHAR(80) NOT NULL,
            dismissed_by INT UNSIGNED DEFAULT NULL,
            dismissed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_member_qualif_suggestion (tenant_id, user_id, suggestion_key),
            KEY idx_member_qualif_suggestion_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table member_qualification_suggestion_dismissals créée.\n";
}
