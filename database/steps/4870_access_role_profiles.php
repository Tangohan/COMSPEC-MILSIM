<?php

declare(strict_types=1);

/**
 * Étape « Niveaux d'accès : fiche descriptive (abrégé, description détaillée, icône ou logo, étiquettes, qualifications,
 * emplois, grades, ordre, archivage) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_23_100_access_role_profiles.sql). Aucune colonne ajoutée à roles,
 * aucune donnée modifiée, aucun droit créé (la page garde les droits actuels de Effectifs > Accès). Sans ces tables, la page
 * des niveaux reste utilisable avec le nom, la description courte et la couleur, et signale l'étape à appliquer.
 * Compatible SQLite et MySQL (unicités dans les CREATE TABLE). Retour arrière : migrations/rollback/access_role_profiles_down.sql.
 */

$accessRoleProfileSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$accessRoleProfileTables = ['access_role_profiles', 'access_role_tag_links', 'access_role_qualifications', 'access_role_jobs'];
$accessRoleProfileMissing = array_values(array_filter($accessRoleProfileTables, static fn (string $t): bool => !$accessRoleProfileSchema->tableExists($t)));
if ($accessRoleProfileMissing === []) {
    echo "  [OK] Tables des fiches de niveaux d’accès déjà présentes.\n";
} else {
    $accessRoleProfileSchema->runSqlFile($root . '/migrations/2026_11_23_100_access_role_profiles.sql');
    foreach ($accessRoleProfileTables as $accessRoleProfileTable) {
        if (!$accessRoleProfileSchema->tableExists($accessRoleProfileTable)) {
            throw new RuntimeException('Création de la table ' . $accessRoleProfileTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $accessRoleProfileMissing) . ".\n";
}
\App\Services\Rbac\AccessRoleProfileService::resetCache();
