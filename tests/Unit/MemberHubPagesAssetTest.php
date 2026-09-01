<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MemberHubPagesAssetTest extends TestCase
{
    public function testEffectifsMemberAndAccountShareHubAndRhActions(): void
    {
        $member = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php');
        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/users/edit.php');
        $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/member_hub_nav.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString('member_hub_nav.php', $member);
        self::assertStringContainsString('member_hub_nav.php', $edit);
        self::assertStringContainsString('Fiche Effectifs', $nav);
        self::assertStringContainsString('Compte', $nav);
        self::assertStringContainsString('Dossier personnel', $nav);
        self::assertStringContainsString('eff-fiche-hero', $member);
        self::assertStringContainsString('id="anciennete"', $member);
        self::assertStringContainsString('updateMemberRoles', $ctrl);
        self::assertStringContainsString('updateMemberGrade', $ctrl);
        self::assertStringContainsString('membres/{id}/roles', $routes);
        self::assertStringContainsString('membres/{id}/grade', $routes);
        self::assertStringContainsString('Situation RH', $edit);
        self::assertStringContainsString('pre_platform_start_date', $edit);
        self::assertStringContainsString('Enregistrer les rôles', $member);
        self::assertStringContainsString('Enregistrer le grade', $member);
        $personnelEdit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');
        self::assertStringContainsString('Fiche Effectifs', $personnelEdit);
    }
}
