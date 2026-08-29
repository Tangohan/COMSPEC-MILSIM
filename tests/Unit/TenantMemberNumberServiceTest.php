<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\TenantMemberNumberService;
use PHPUnit\Framework\TestCase;

final class TenantMemberNumberServiceTest extends TestCase
{
    public function testFormatNumberPaddingYearPrefixAndTenant(): void
    {
        $svc = $this->serviceWithoutDeps();

        self::assertSame(
            'GEND-0001',
            $svc->format('{PREFIX}-{NUMBER:4}', 'GEND', 1)
        );
        self::assertSame(
            'OPS-042',
            $svc->format('{PREFIX}-{NUMBER:3}', 'OPS', 42)
        );
        self::assertSame(
            'COMSPEC/' . date('Y') . '/128',
            $svc->format('{PREFIX}/{YEAR}/{NUMBER}', 'COMSPEC', 128)
        );
        self::assertSame(
            'ALPHA-' . substr(date('Y'), -2) . '-0007',
            $svc->format('{TENANT}-{YEAR:2}-{NUMBER:4}', '', 7, 'ALPHA')
        );
        self::assertSame(
            'U1-G2-9',
            $svc->format('{UNIT}-{GRADE}-{NUMBER}', '', 9, null, 'U1', 'G2')
        );
        // UNIT/GRADE absents : pas de dépendance obligatoire, séparateurs nettoyés.
        self::assertSame(
            '9',
            $svc->format('{UNIT}/{GRADE}/{NUMBER}', '', 9)
        );
    }

    public function testIdentityPayloadPrefersOrgNumber(): void
    {
        $payload = TenantMemberNumberService::identityPayload([
            'athena_identifier' => 'COM2026001',
            'tenant_member_number' => 'GEND-0458',
        ]);
        self::assertSame('COM2026001', $payload['platform_number']);
        self::assertSame('GEND-0458', $payload['tenant_member_number']);
        self::assertSame('GEND-0458', $payload['display_number']);

        $fallback = TenantMemberNumberService::identityPayload([
            'athena_identifier' => 'COM2026001',
            'tenant_member_number' => '',
        ]);
        self::assertSame('COM2026001', $fallback['display_number']);
        self::assertNull($fallback['tenant_member_number']);
    }

    public function testDisplayNumberHelper(): void
    {
        self::assertSame('OPS-001', TenantMemberNumberService::displayNumber('OPS-001', 'ATH123'));
        self::assertSame('ATH123', TenantMemberNumberService::displayNumber(null, 'ATH123'));
        self::assertNull(TenantMemberNumberService::displayNumber('', ''));
    }

    public function testAssetsWireMigrationPermissionRoutesAndDocs(): void
    {
        $root = dirname(__DIR__, 2);

        $migration = (string) file_get_contents($root . '/bootstrap/tenant_member_number_migration.php');
        self::assertStringContainsString('tenant_member_number', $migration);
        self::assertStringContainsString('tenant_member_number_config', $migration);
        self::assertStringContainsString('tenant_member_number_audit', $migration);
        self::assertStringContainsString('uniq_users_tenant_member_number', $migration);

        $run = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('tenant_member_number_migration.php', $run);
        self::assertStringContainsString('run_tenant_member_number_migration', $run);

        $catalog = (string) file_get_contents($root . '/app/Authorization/TenantPermissionCatalog.php');
        self::assertStringContainsString('personnel.member_number.manage', $catalog);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('organisation/matricules', $routes);
        self::assertStringContainsString('member-number/regenerate', $routes);
        self::assertStringContainsString('OrganizationMemberNumberController', $routes);

        $service = (string) file_get_contents($root . '/app/Services/Personnel/TenantMemberNumberService.php');
        self::assertStringContainsString('findById($userId, $tenantId)', $service);
        self::assertStringContainsString('updateTenantMemberNumber', $service);

        $repo = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('updateTenantMemberNumber', $repo);
        self::assertStringContainsString('WHERE id = ? AND tenant_id = ?', $repo);
        self::assertStringContainsString('tenant_member_number LIKE', $repo);

        $docs = (string) file_get_contents($root . '/docs/TENANT-MEMBER-NUMBER.md');
        self::assertStringContainsString('tenant_member_number', $docs);
        self::assertStringContainsString('personnel.member_number.manage', $docs);
        self::assertStringContainsString('display_number', $docs);
    }

    public function testSqlNeverUpdatesWithoutTenantScope(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertMatchesRegularExpression(
            '/UPDATE users SET tenant_member_number = \?.*WHERE id = \? AND tenant_id = \?/s',
            $repo
        );
        self::assertDoesNotMatchRegularExpression(
            '/UPDATE users SET tenant_member_number = \?\s*WHERE id = \?\s*;/',
            $repo
        );
    }

    private function serviceWithoutDeps(): TenantMemberNumberService
    {
        $config = $this->createMock(\App\Repositories\TenantMemberNumberConfigRepository::class);
        $users = $this->createMock(\App\Repositories\UserRepository::class);
        $tenants = $this->createMock(\App\Repositories\TenantRepository::class);

        return new TenantMemberNumberService($config, $users, $tenants, null);
    }
}
