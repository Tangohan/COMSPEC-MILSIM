<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsSeniorityManagementAssetTest extends TestCase
{
    public function testRosterAndMemberExposeRealSeniorityEditing(): void
    {
        $roster = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/roster.php');
        $member = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        $summary = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Personnel/SenioritySummaryService.php');
        $pre = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Personnel/SeniorityPrePlatformService.php');

        self::assertStringContainsString('anciennete-entite', $roster);
        self::assertStringContainsString('eff-catalog__notice', $roster);
        self::assertStringNotContainsString('class="eff-panel"', $roster);
        self::assertStringContainsString('pre_platform_start_date', $roster);
        self::assertStringContainsString('Arrivée avant le site', $roster);
        self::assertStringContainsString('pre_platform_start_date', $member);
        self::assertStringContainsString('Dans la communauté', $member);
        self::assertStringContainsString('Création de l’organisation', $member);
        self::assertStringContainsString('updateMemberSeniority', $ctrl);
        self::assertStringContainsString('updateOrgFounding', $ctrl);
        self::assertStringContainsString('alignTenureCommunityFromStaffEdit', $ctrl);
        self::assertStringContainsString('tenure_pre_platform', $summary);
        self::assertStringContainsString('org_founding_reviewed', $pre);
        self::assertStringContainsString('applyStoredOrgFoundingToUser', $pre);
    }
}
