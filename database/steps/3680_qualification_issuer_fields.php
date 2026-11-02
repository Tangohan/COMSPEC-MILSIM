<?php

declare(strict_types=1);

/**
 * Étape « Qualifications : champs des émetteurs » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute à qualification_issuers
 * pays (country_code), localisation, site officiel (website_url), présentation anglaise (description_en), clé du
 * catalogue réel (issuer_key) et type du catalogue (issuer_type), index (tenant_id, country_code), puis y reporte
 * les détails écrits en lignes dans la présentation (App\Support\QualificationCatalog\IssuerFieldsMigration). Le
 * texte libre reste intact, aucune valeur présente n’est écrasée, une relance ne change rien.
 */

$issuerFields = \App\Support\QualificationCatalog\IssuerFieldsMigration::run($pdo);
if ($issuerFields === null) {
    echo "  [OK] Table qualification_issuers absente : rien à faire.\n";
} else {
    echo ($issuerFields['added'] === [] ? '  [OK] Colonnes des émetteurs déjà présentes' : '  [OK] Ajouté : ' . implode(', ', $issuerFields['added']))
        . ' ; ' . $issuerFields['migrated'] . " émetteur(s) migré(s).\n";
}
