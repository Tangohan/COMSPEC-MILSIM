<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Quitter une communauté puis revenir (ou en rejoindre une autre) ne doit rien ramener de l'ancien séjour :
 * ni rôles, ni grade, ni historique, et la page publique ne montre que la communauté et le séjour en cours.
 */
final class MembershipStayIsolationTest extends TestCase
{
    private static function src(string $rel): string
    {
        $s = file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
        self::assertNotFalse($s, $rel);

        return str_replace("\r\n", "\n", (string) $s);
    }

    public function testLeavingDropsRolesAndClosesTheStay(): void
    {
        $s = self::src('app/Repositories/UserCommunityMembershipRepository.php');
        self::assertStringContainsString('$this->closePeriod($userId, $tenantId);', $s);
        self::assertStringContainsString('DELETE FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ?', $s);
        self::assertStringContainsString('SET role_id = NULL, preferred_display_role_id = NULL', $s);
    }

    public function testComingBackStartsAFreshStay(): void
    {
        $s = self::src('app/Repositories/UserCommunityMembershipRepository.php');
        self::assertStringContainsString("joined_at = IF(status = 'left', NOW(), joined_at)", $s);
        self::assertStringContainsString('resetStayData($userId, $tenantId)', $s);
        self::assertStringContainsString('grade_id = NULL, tenant_member_number = NULL', $s);
        self::assertStringContainsString('openPeriod($userId, $tenantId)', $s);
    }

    public function testHistoryAndQualificationsAreScopedToTheCommunity(): void
    {
        $h = self::src('app/Repositories/PersonnelServiceHistoryRepository.php');
        self::assertStringContainsString('AND (tenant_id = ?', $h);
        self::assertStringContainsString('currentPeriodStart', $h);
        $q = self::src('app/Repositories/PersonnelQualificationRepository.php');
        self::assertStringContainsString('AND (tenant_id = ? OR tenant_id IS NULL)', $q);
        $c = self::src('app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString('listForUser($uid, 50, (int) $tenantId)', $c);
        self::assertStringContainsString('listForUser($uid, (int) $tenantId)', $c);
    }

    public function testPublicPageOnlyShowsTheCurrentStayOfThisCommunity(): void
    {
        $l = self::src('app/Services/PublicCv/PublicCvDataLoader.php');
        self::assertStringContainsString("AND event_date >= ?", $l);
        self::assertStringContainsString('KIND_COMMANDEMENT', $l);
        self::assertStringContainsString('listForUser($tenantId, $userId', $l);
        self::assertStringContainsString("discord.com/users/", $l);
    }

    public function testNewPublicSectionsAreOptIn(): void
    {
        $st = self::src('app/Support/PublicCv/PublicCvSettings.php');
        self::assertStringContainsString("OPT_IN = ['since', 'career', 'evaluations', 'mutations', 'discord']", $st);
    }

    public function testMigrationCreatesStaysAndTenantColumn(): void
    {
        $m = self::src('migrations/20261114000001_membership_periods.sql');
        self::assertStringContainsString('user_community_membership_periods', $m);
        self::assertStringContainsString('personnel_service_history', $m);
    }
}
