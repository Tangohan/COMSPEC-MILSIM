<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LmsAthenaModuleBannerAssetTest extends TestCase
{
    public function testRecruitmentAndFormationShellsShowAthenaModuleBannerWithReturn(): void
    {
        $root = dirname(__DIR__, 2);
        $partial = (string) file_get_contents($root . '/views/partials/lms_athena_module_banner.php');
        $css = (string) file_get_contents($root . '/public/assets/css/training_lms.css');
        $recruitment = (string) file_get_contents($root . '/views/layout/recruitment_lms.php');
        $staff = (string) file_get_contents($root . '/views/layout/training_lms_staff_shell.php');
        $catalogue = (string) file_get_contents($root . '/views/training/catalogue.php');
        $sessions = (string) file_get_contents($root . '/views/training/sessions.php');

        self::assertStringContainsString('Vous visionnez un module ATHENA', $partial);
        self::assertStringContainsString('lms-athena-banner__back', $partial);
        self::assertStringContainsString("url('dashboard')", $partial);
        self::assertStringContainsString('Retour', $partial);
        self::assertStringNotContainsString('LMS', $partial);

        self::assertStringContainsString('.lms-athena-banner', $css);
        self::assertStringContainsString('.lms-athena-banner__back', $css);

        foreach ([$recruitment, $staff, $catalogue, $sessions] as $shell) {
            self::assertStringContainsString('lms_athena_module_banner.php', $shell);
        }
    }
}
