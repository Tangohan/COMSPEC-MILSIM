<?php

declare(strict_types=1);

/**
 * Nomenclatures nationales : six tables de référence et de liaison RH/LMS, puis import documentaire additif.
 * Les capacités génériques et les attributions historiques ne sont ni reprises ni remplacées.
 */

$specialtySchema = new \App\Support\Migrations\SchemaInspector($pdo);
foreach ([
    'countries' => ['id', 'iso2', 'iso3', 'name_fr', 'name_en'],
    'military_services' => ['id', 'country_id', 'code', 'name'],
    'military_sources' => ['id', 'name', 'publisher', 'url', 'source_type', 'published_at', 'checked_at'],
    'tenants' => ['id'],
    'users' => ['id'],
    'personnel_job_roles' => ['id', 'tenant_id'],
    'personnel_qualification_definitions' => ['id', 'tenant_id'],
    'personnel_qualifications' => ['id'],
] as $specialtyParent => $specialtyColumns) {
    if (!$specialtySchema->tableExists($specialtyParent)) {
        throw new RuntimeException('Nomenclatures militaires : table préalable absente : ' . $specialtyParent);
    }
    foreach ($specialtyColumns as $specialtyColumn) {
        if (!$specialtySchema->columnExists($specialtyParent, $specialtyColumn)) {
            throw new RuntimeException('Nomenclatures militaires : colonne préalable absente : ' . $specialtyParent . '.' . $specialtyColumn);
        }
    }
}

// Les références vers un emploi ou une définition doivent également verrouiller leur communauté.
foreach ([
    'personnel_job_roles' => 'uq_pjr_specialty_tenant_id',
    'personnel_qualification_definitions' => 'uq_pqd_specialty_tenant_id',
] as $specialtyParent => $specialtyIndex) {
    if (!$specialtySchema->indexExists($specialtyParent, $specialtyIndex)) {
        $pdo->exec($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'CREATE UNIQUE INDEX `' . $specialtyIndex . '` ON `' . $specialtyParent . '` (`tenant_id`, `id`)'
            : 'ALTER TABLE `' . $specialtyParent . '` ADD UNIQUE INDEX `' . $specialtyIndex . '` (`tenant_id`, `id`)');
    }
}

$specialtySchema->runSqlFile($root . '/migrations/2026_11_17_100_military_specialty_codes.sql');
foreach (['military_specialty_systems', 'military_specialty_codes', 'military_specialty_levels',
    'personnel_job_role_specialty_codes', 'personnel_profile_specialty_codes', 'qualification_specialty_targets'] as $specialtyTable) {
    if (!$specialtySchema->tableExists($specialtyTable)) {
        throw new RuntimeException('Création de la table de spécialités impossible : ' . $specialtyTable);
    }
}

$specialtyOwnTransaction = !$pdo->inTransaction();
if ($specialtyOwnTransaction) {
    $pdo->beginTransaction();
}
try {
    $specialtyAdded = \App\Support\MilitarySpecialtyCatalog::seed($pdo);
    if ($specialtyOwnTransaction) {
        $pdo->commit();
    }
} catch (Throwable $specialtyException) {
    if ($specialtyOwnTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $specialtyException;
}
echo '  [OK] Nomenclatures militaires : schéma disponible ; ' . $specialtyAdded . " référence(s) ajoutée(s), aucune attribution modifiée.\n";
