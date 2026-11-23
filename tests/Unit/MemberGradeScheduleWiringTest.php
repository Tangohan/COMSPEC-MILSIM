<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Câblage des grades planifiés : tâche, route, droits (fichiers seulement). */
final class MemberGradeScheduleWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testWiring(): void
    {
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('MemberGradeScheduleCronJob::class', $container);
        self::assertSame(15, \App\Services\Cron\CronSchedule::intervalMinutes('member_grade_schedule'));
        $route = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertMatchesRegularExpression("~membres/\\{id\\}/grade/planifier', \\[EffectifsWorkspaceController::class, 'scheduleMemberGrade'\\], \\[AuthMiddleware::class, OrganizationAdminMiddleware::class\\]~", $route);
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertMatchesRegularExpression('/function scheduleMemberGrade\(.*?canManageGrades.*?Csrf::validate/s', $ctrl);
        $home = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/HomeController.php');
        self::assertMatchesRegularExpression('/canManageGrades\(\$gate\)\)\s*\{\s*try \{\s*\$gradeSchedules/', $home);
    }
}
