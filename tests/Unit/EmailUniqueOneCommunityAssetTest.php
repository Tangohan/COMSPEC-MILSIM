<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EmailUniqueOneCommunityAssetTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testRegistrationRejectsDuplicateEmailTowardLogin(): void
    {
        $register = self::read('app/Controllers/Web/RegisterController.php');
        $mapper = self::read('app/Support/UserFacingExceptionMapper.php');
        $repo = self::read('app/Repositories/UserRepository.php');

        self::assertStringContainsString('emailExistsGlobally', $register);
        self::assertStringContainsString('UserFacingExceptionMapper::isDuplicateEmail', $register);
        self::assertStringContainsString("Response::redirect(url('login'))", $register);
        self::assertStringContainsString('public static function isDuplicateEmail', $mapper);
        self::assertStringContainsString('email_identity', $repo);
        self::assertStringContainsString('uk_users_email', $repo);
    }

    public function testLoginForcesDefinitiveCommunityChoice(): void
    {
        $auth = self::read('app/Controllers/Auth/AuthController.php');
        $view = self::read('views/auth/select-community.php');
        $fr = self::read('lang/fr/auth.php');
        $svc = self::read('app/Services/Community/CommunityMembershipConsolidationService.php');

        self::assertStringContainsString('CommunityMembershipConsolidationService', $auth);
        self::assertStringContainsString('consolidateToChoice', $auth);
        self::assertStringContainsString('confirm_definitive', $auth);
        self::assertStringContainsString('temporary_access', $auth);
        self::assertStringContainsString('confirm_definitive', $view);
        self::assertStringContainsString('select_community_confirm', $fr);
        $en = self::read('lang/en/auth.php');
        self::assertStringContainsString('select_community_confirm', $en);
        self::assertStringContainsString('flash_email_already_exists', $en);
        self::assertStringContainsString('select_community_last_owner', $en);
        self::assertStringContainsString('auth.select_community_last_owner', $svc);
        self::assertStringContainsString('leaveCommunity', $svc);
    }

    public function testSecondLiveMembershipIsBlockedUnlessReplacing(): void
    {
        $repo = self::read('app/Repositories/UserRepository.php');
        $bootstrap = self::read('app/Services/Community/TenantBootstrapService.php');

        self::assertStringContainsString('replaceOtherLiveCommunities', $repo);
        self::assertStringContainsString('leaveOtherLiveCommunities', $repo);
        self::assertStringContainsString('appartient déjà à une autre communauté', $repo);
        self::assertStringContainsString('cloneUserToTenant(', $bootstrap);
        self::assertMatchesRegularExpression('/cloneUserToTenant\(\s*\$creatorUserId,\s*\$tenantId,\s*\$communityOwnerRoleId,\s*\$gradeId,\s*\[\],\s*true\s*\)/s', $bootstrap);
    }

    public function testContainerWiresConsolidationService(): void
    {
        $container = self::read('app/Core/Container.php');

        self::assertStringContainsString('CommunityMembershipConsolidationService::class', $container);
    }
}
