<?php

declare(strict_types=1);

/**
 * Étape « Codes unité : tables et numéro administratif de chaque communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : UnitCodeRepository::MIGRATION_FILE (CREATE TABLE IF NOT EXISTS). Chaque communauté encore
 * sans code reçoit ensuite le sien dans l’ordre de création, comme à la création à la demande ; un code
 * existant (proposé ou saisi) n’est jamais modifié. Les codes des unités restent calculés par UnitCodeService.
 */

$unitCodesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($unitCodesSchema->tableExists('tenant_unit_codes') && $unitCodesSchema->tableExists('unit_codes')) {
    echo "  [OK] Tables tenant_unit_codes et unit_codes déjà présentes.\n";
} else {
    $unitCodesSchema->runSqlFile($root . '/' . \App\Repositories\UnitCodeRepository::MIGRATION_FILE);
    echo "  [OK] Tables tenant_unit_codes et unit_codes créées.\n";
}
$unitCodesRepo = new \App\Repositories\UnitCodeRepository($pdo);
$unitCodesAdded = 0;
foreach ($pdo->query('SELECT id, name, slug FROM tenants ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $unitCodesTenant) {
    $unitCodesTenantId = (int) $unitCodesTenant['id'];
    if ($unitCodesRepo->findTenant($unitCodesTenantId) !== null) {
        continue;
    }
    if ($unitCodesRepo->ensureTenant($unitCodesTenantId, (string) ($unitCodesTenant['name'] ?? ''), (string) ($unitCodesTenant['slug'] ?? '')) === null) {
        throw new RuntimeException('Numéro administratif impossible à attribuer à la communauté #' . $unitCodesTenantId . '.');
    }
    $unitCodesAdded++;
}
echo '  [OK] ' . ($unitCodesAdded > 0 ? $unitCodesAdded . ' communauté(s) numérotée(s).' : 'Toutes les communautés ont déjà leur code.') . "\n";
