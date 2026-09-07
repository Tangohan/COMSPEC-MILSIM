<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GradeDoctrineCascadeAssetTest extends TestCase
{
    public function testInviteAndEditFormsFilterGradesByDoctrine(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/grade-doctrine-cascade.js');
        $invite = (string) file_get_contents($root . '/views/admin/organization/partials/user_invite_form_fields.php');
        $edit = (string) file_get_contents($root . '/views/admin/organization/users/edit.php');
        $platform = (string) file_get_contents($root . '/views/admin/system/user_edit.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/GradeRepository.php');
        $users = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/UserAdminController.php');
        $hub = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $platformSvc = (string) file_get_contents($root . '/app/Services/Admin/PlatformUserProfileService.php');

        self::assertStringContainsString('data-grade-doctrine', $invite);
        self::assertStringContainsString('data-grade-doctrine-nation', $invite);
        self::assertStringContainsString('data-grade-doctrine-category', $invite);
        self::assertStringContainsString('data-grade-doctrine-grade', $invite);
        self::assertStringContainsString('data-country', $invite);
        self::assertStringContainsString('data-category', $invite);
        self::assertStringContainsString('grade-doctrine-cascade.js', $invite);
        self::assertStringContainsString('selon la doctrine choisie', $invite);

        self::assertStringContainsString('data-grade-doctrine', $edit);
        self::assertStringContainsString('grade-doctrine-cascade.js', $edit);

        self::assertStringContainsString('data-grade-doctrine', $platform);
        self::assertStringContainsString('grade-doctrine-cascade.js', $platform);

        self::assertStringContainsString('listActiveForDoctrinePicker', $repo);
        self::assertStringContainsString('listActiveForDoctrinePicker($tenantId)', $users);
        self::assertStringContainsString('listActiveForDoctrinePicker($tenantId)', $hub);
        self::assertStringContainsString('listActiveForDoctrinePicker($tenantId)', $platformSvc);
        self::assertStringContainsString('gradeIsAvailableForDoctrinePicker', $users);

        self::assertStringContainsString('data-country', $js);
        self::assertStringContainsString('data-category', $js);
        self::assertStringContainsString('[data-grade-doctrine]', $js);
    }
}
