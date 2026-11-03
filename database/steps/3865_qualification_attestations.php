<?php

declare(strict_types=1);

/**
 * Étape « Qualifications : conditions attestées par un instructeur » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente (CREATE TABLE IF NOT EXISTS) :
 * qualification_attestations garde, par communauté et par membre, ce qu'un instructeur a attesté pour les conditions
 * d'obtention que le jeu ne mesure pas (QualificationCriteriaCatalog::ATTESTATIONS) : un nombre propre au membre
 * (clé « freefall_jumps », « static_line_jumps », « dive_hours ») ou une épreuve propre à une qualification (clé
 * « q<fiche>:<type>:<empreinte de l'intitulé> »). Une ligne par membre et par clé ; rien d'existant n'est modifié.
 */

$schema3865 = new \App\Support\Migrations\SchemaInspector($pdo);
if ($schema3865->tableExists('qualification_attestations')) {
    echo "  [OK] Table qualification_attestations déjà présente.\n";
} else {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS qualification_attestations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            attestation_key VARCHAR(120) NOT NULL,
            value DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            note VARCHAR(255) DEFAULT NULL,
            attested_by INT UNSIGNED DEFAULT NULL,
            attested_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_qualification_attestation (tenant_id, user_id, attestation_key),
            KEY idx_qualification_attestation_tenant (tenant_id, attestation_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table qualification_attestations créée.\n";
}
