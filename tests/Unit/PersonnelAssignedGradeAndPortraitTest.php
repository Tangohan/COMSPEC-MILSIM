<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelAssignedGradeAndPortraitTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
    }

    public function testAssignedGradePrefersCatalogLongNameOverRoleAndOverride(): void
    {
        $label = personnel_assigned_grade_label([
            'grade_long' => 'Colonel',
            'grade_short' => 'COL',
            'rank_display' => 'Administrateur système',
            'rank_display_override' => 'O-5',
            'role_name' => 'Administrateur système',
        ]);
        self::assertSame('Colonel', $label);
    }

    public function testAssignedGradeIgnoresPlatformRoleWhenNoCatalogGrade(): void
    {
        $label = personnel_assigned_grade_label([
            'grade_long' => '',
            'grade_short' => '',
            'rank_display' => 'Administrateur système',
            'role_name' => 'Administrateur système',
        ]);
        self::assertSame('', $label);
    }

    public function testAssignedGradeKeepsCustomTitleWhenItIsNotThePlatformRole(): void
    {
        $label = personnel_assigned_grade_label([
            'grade_long' => '',
            'grade_short' => '',
            'rank_display' => 'Chef de section',
            'role_name' => 'Opérateur',
        ]);
        self::assertSame('Chef de section', $label);
    }

    public function testOperatorPortraitPrefersCharacterPortrait(): void
    {
        $url = personnel_operator_portrait_url([
            'avatar_url' => 'uploads/avatars/account.jpg',
            'character_portrait_path' => 'uploads/portraits/op.jpg',
        ]);
        self::assertNotNull($url);
        self::assertStringContainsString('uploads/portraits/op.jpg', (string) $url);
    }

    public function testEffectifsSurfacesUseAssignedGradeAndOperatorPortrait(): void
    {
        $root = dirname(__DIR__, 2);
        $table = (string) file_get_contents($root . '/views/partials/dashboard_effectifs_table.php');
        $directory = (string) file_get_contents($root . '/views/personnel/directory.php');
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');

        self::assertStringContainsString('personnel_assigned_grade_label', $table);
        self::assertStringContainsString('personnel_operator_portrait_url', $table);
        self::assertStringNotContainsString("\$row['rank_display_override']", $table);
        self::assertStringContainsString('personnel_assigned_grade_label', $directory);
        self::assertStringContainsString('personnel_operator_portrait_url', $directory);
        self::assertStringContainsString('personnel_assigned_grade_label', $file);
        self::assertStringContainsString('$gradeCodeBeside', $file);
        self::assertStringContainsString('character_portrait_path', $repo);
    }
}
