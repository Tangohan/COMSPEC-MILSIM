<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UserIdentityOneAccountAssetTest extends TestCase
{
    public function testJoinReusesAccountInsteadOfInsertingASecondUser(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('function addMembershipToTenant', $repo);
        self::assertStringContainsString('return $this->addMembershipToTenant', $repo);
        self::assertStringContainsString('Un compte existe déjà avec cette adresse e-mail', $repo);
        self::assertStringContainsString('function overlayCommunityProfile', $repo);
        self::assertStringNotContainsString(
            '$newId = $this->create($newTenantId, $cloneData);',
            $repo
        );
    }

    public function testLoginListsMembershipsOfOnePassword(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        $auth = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Auth/AuthService.php');
        self::assertStringContainsString('listUsersForLoginByEmail', $repo);
        self::assertStringContainsString('user_community_memberships', $repo);
        self::assertStringContainsString('setCurrentTenant', $auth);
        self::assertStringContainsString('même user_id', $auth);
    }

    public function testAdminCreateJoinsExistingEmailWithoutSecondUser(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertStringContainsString('findFirstByEmailGlobal', $ctrl);
        self::assertStringContainsString('addMembershipToTenant', $ctrl);
    }

    public function testPersonnelLookupsAcceptTenantScope(): void
    {
        $pp = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/PersonnelProfileRepository.php');
        $ex = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/PersonnelExtrasRepository.php');
        $admin = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('getByUserId(int $userId, ?int $tenantId = null)', $pp);
        self::assertStringContainsString('AND tenant_id = ?', $pp);
        self::assertStringContainsString('getByUserId(int $userId, ?int $tenantId = null)', $ex);
        self::assertStringContainsString('getByUserId($uid, $tid)', $admin);
    }

    public function testMergeIsAuditedAndReversible(): void
    {
        $action = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Audit/AuditAction.php');
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Identity/UserIdentityMergeService.php');
        $mig = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/user_community_identity_migration.php');
        self::assertStringContainsString('USER_IDENTITY_MERGED', $action);
        self::assertStringContainsString('user_identity_merges', $svc);
        self::assertStringContainsString('source_user_id', $mig);
        self::assertStringContainsString('personnel_profiles', $mig);
        self::assertFileExists(dirname(__DIR__, 2) . '/app/Services/Identity/UserIdentityProfileRestoreService.php');
        self::assertStringContainsString('UserIdentityProfileRestoreService', $svc);
    }
}
