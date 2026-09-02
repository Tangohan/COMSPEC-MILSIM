<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperatorGameRegistryContractTest extends TestCase
{
    private function src(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    public function testUnlinkedOperatorsArePersistedWithoutInventingAUser(): void
    {
        $controller = $this->src('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('OperatorGameObservationNormalizer', $controller);
        self::assertStringContainsString("\$profile = \$repo->upsertProfile(\$tenantId, \$reference, \$steamId, \$payload);", $controller);
        self::assertStringContainsString("'event' => 'UNLINKED_ARMA_OPERATOR'", $controller);
        self::assertStringContainsString("'profile_id' => \$profile['id']", $controller);
        self::assertStringContainsString("'sync_status' => 'NOT_LINKED'", $controller);
        self::assertStringNotContainsString('array_any(', $controller);
    }

    public function testReferenceUsesCommunityCallsignAndTenantScopedSteam(): void
    {
        $repo = $this->src('app/Repositories/OperatorGameProfileRepository.php');
        self::assertStringContainsString('SteamId::normalize', $repo);
        self::assertStringContainsString('COALESCE(NULLIF(TRIM(ucp.callsign)', $repo);
        self::assertStringContainsString('user_community_profiles ucp', $repo);
        self::assertStringContainsString('personnel_profiles pp', $repo);
        self::assertStringContainsString('WHERE u.tenant_id = ? AND u.id = ?', $repo);
        self::assertStringContainsString('WHERE tenant_id = ? AND steam_id = ?', $repo);
        self::assertStringContainsString('positiveInt', $repo);
        self::assertStringContainsString("IF(status='OPEN', occurrence_count+1, 1)", $repo);
        self::assertStringContainsString("status='OPEN'", $repo);
        self::assertStringContainsString('resolved_at=NULL', $repo);
    }

    public function testUserIdStaysNullableOnUnlinkedUpsert(): void
    {
        $repo = $this->src('app/Repositories/OperatorGameProfileRepository.php');
        self::assertStringNotContainsString('(int) $reference[\'user_id\']', $repo);
        self::assertStringContainsString('$userId = $this->positiveInt($reference[\'user_id\'] ?? null);', $repo);
    }

    public function testMigrationKeepsUserIdNullableAndFingerprintUnique(): void
    {
        $migration = $this->src('bootstrap/operator_game_registry_migration.php');
        self::assertStringContainsString('user_id INT UNSIGNED NULL', $migration);
        self::assertStringContainsString('UNIQUE KEY uq_operator_game_tenant_steam (tenant_id,steam_id)', $migration);
        self::assertStringContainsString('UNIQUE KEY uq_ogd_active_fingerprint (fingerprint)', $migration);
    }
}
