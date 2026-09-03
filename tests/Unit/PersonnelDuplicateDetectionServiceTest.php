<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TenantAdminSettingsRepository;
use App\Repositories\TenantRepository;
use App\Services\Personnel\PersonnelDuplicateDetectionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelDuplicateDetectionServiceTest extends TestCase
{
    public function testScanScopesCommunityProfilesAndDoesNotRepeatMembers(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, settings TEXT)');
        $pdo->exec("INSERT INTO tenants VALUES (7, '{\"admin_runtime\":{\"personnel_duplicates\":{\"enabled\":true,\"fields\":[\"callsign\"]}}}')");
        $pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT, email TEXT,
            deleted_at TEXT, is_service_account INTEGER DEFAULT 0, status TEXT
        )');
        $pdo->exec("INSERT INTO users VALUES
            (1, 3, 'Global Alpha', 'GLOBAL-A', 'alpha@example.test', NULL, 0, 'active'),
            (2, 7, 'Global Bravo', 'GLOBAL-B', 'bravo@example.test', NULL, 0, 'active')");
        $pdo->exec('CREATE TABLE user_community_memberships (user_id INTEGER, tenant_id INTEGER, status TEXT)');
        $pdo->exec("INSERT INTO user_community_memberships VALUES (1, 7, 'active'), (2, 7, 'active')");
        $pdo->exec('CREATE TABLE user_community_profiles (
            user_id INTEGER, tenant_id INTEGER, display_name TEXT, callsign TEXT
        )');
        $pdo->exec("INSERT INTO user_community_profiles VALUES
            (1, 3, 'Alpha elsewhere', 'OTHER'), (1, 7, 'Alpha Seven', 'YA1'),
            (2, 7, 'Bravo Seven', 'YA1')");
        $pdo->exec('CREATE TABLE user_profiles (user_id INTEGER, first_name TEXT, last_name TEXT)');
        $pdo->exec("INSERT INTO user_profiles VALUES (1, 'Alpha', 'Seven'), (2, 'Bravo', 'Seven')");
        $pdo->exec('CREATE TABLE personnel_profiles (
            user_id INTEGER, tenant_id INTEGER, matricule_internal TEXT, callsign TEXT
        )');
        $pdo->exec("INSERT INTO personnel_profiles VALUES
            (1, 3, 'A-3', 'OTHER'), (1, 7, 'A-7', 'YA1'), (2, 7, 'B-7', 'YA1')");
        $pdo->exec('CREATE TABLE personnel_extras (user_id INTEGER, tenant_id INTEGER, service_number TEXT)');
        $pdo->exec("INSERT INTO personnel_extras VALUES
            (1, 3, 'EXTRA-3'), (1, 7, 'EXTRA-7'), (2, 7, 'EXTRA-B')");

        $settings = new TenantAdminSettingsRepository(new TenantRepository($pdo));
        $scan = (new PersonnelDuplicateDetectionService($pdo, $settings))->scan(7);

        self::assertSame(1, $scan['group_count']);
        self::assertSame(2, $scan['member_count']);
        self::assertSame('ya1', $scan['groups'][0]['value']);
        self::assertSame([1, 2], array_column($scan['groups'][0]['members'], 'id'));
        self::assertSame(['Alpha Seven', 'Bravo Seven'], array_column($scan['groups'][0]['members'], 'display_name'));
    }
}
