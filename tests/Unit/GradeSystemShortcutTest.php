<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Référentiel des grades : raccourci pour changer le système de grades de la communauté. */
final class GradeSystemShortcutTest extends TestCase
{
    public function testRouteActionAndViewAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/back-office/referentiels/grades/systeme', [GradeReferentielController::class, 'setSystem'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/GradeReferentielController.php');
        self::assertStringContainsString('public function setSystem(', $ctrl);
        self::assertStringContainsString("in_array(\$code, ['FR_CLASSIC', 'US_CLASSIC'], true)", $ctrl);
        self::assertStringContainsString('Csrf::validate', $ctrl);
        self::assertStringContainsString("mergeSettings(\$tenantId, ['grade_system_code' => \$code])", $ctrl);

        $view = (string) file_get_contents($root . '/views/admin/organization/referentiels/grades/index.php');
        self::assertStringContainsString("url('back-office/referentiels/grades/systeme')", $view);
        self::assertStringContainsString('name="grade_system_code"', $view);
        self::assertStringContainsString('Csrf::field()', $view);
    }
}
