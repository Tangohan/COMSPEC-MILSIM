<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelPassAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testPassAdminSurfaceIsWired(): void
    {
        $root = $this->root();
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $migration = (string) file_get_contents($root . '/bootstrap/personnel_pass_migration.php');
        $runner = (string) file_get_contents($root . '/run-migrations.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $index = (string) file_get_contents($root . '/views/admin/organization/passes_index.php');
        $form = (string) file_get_contents($root . '/views/admin/organization/passes_form.php');
        $gradeForm = (string) file_get_contents($root . '/views/admin/advancement/grade_form.php');
        $offerForm = (string) file_get_contents($root . '/views/admin/organization/recruitment_offers/form.php');
        $container = (string) file_get_contents($root . '/app/Core/Container.php');

        self::assertFileExists($root . '/app/Services/Personnel/Pass/PassConditionEngine.php');
        self::assertFileExists($root . '/app/Repositories/PersonnelPassRepository.php');
        self::assertFileExists($root . '/app/Controllers/Admin/Organization/PersonnelPassAdminController.php');
        self::assertStringContainsString('personnel_pass_migration.php', $runner);
        self::assertStringContainsString('run_personnel_pass_migration', $runner);
        self::assertStringContainsString('personnel_passes', $migration);
        self::assertStringContainsString('personnel_pass_conditions', $migration);
        self::assertStringContainsString('personnel_hierarchical_opinions', $migration);
        self::assertStringContainsString('required_pass_id', $migration);
        self::assertStringContainsString("back-office/organisation/passes", $routes);
        self::assertStringContainsString('PersonnelPassAdminController', $routes);
        self::assertStringContainsString('PersonnelPassAdminController', $container);
        self::assertStringContainsString('PASS RH', $nav);
        self::assertStringContainsString('PASS JTAC', $index);
        self::assertStringContainsString('Avis hiérarchique', $form);
        self::assertStringContainsString('Formation suivie', $form);
        self::assertStringContainsString('required_pass_id', $gradeForm);
        self::assertStringContainsString('required_pass_id', $offerForm);
    }
}
