<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AthenaAcademyAssetTest extends TestCase
{
    public function testAcademyHomepageIsBilingualAndPreRelease(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/training/catalogue.php');
        $script = (string) file_get_contents($root . '/public/assets/js/athena-academy.js');

        self::assertStringContainsString('ATHENA <b>ACADEMY</b>', $view);
        self::assertStringContainsString('PRE-RELEASE / ACCÈS ANTICIPÉ', $view);
        self::assertStringContainsString('data-fr=', $view);
        self::assertStringContainsString('data-en=', $view);
        self::assertStringContainsString('Cours théorique', $view);
        self::assertStringContainsString('Simulation autonome', $view);
        self::assertStringContainsString('Validation des acquis', $view);
        self::assertStringContainsString('localStorage.setItem', $script);
    }

    public function testAcademyUsesRealCoursesAndPaidTrainingGate(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/training/catalogue.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/TrainingController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString('foreach (array_slice($courses, 0, 6)', $view);
        self::assertStringContainsString("allows(\$tenantId, 'training')", $controller);
        self::assertStringContainsString("'/formations', [TrainingController::class, 'index'], \$mwTraining", $routes);
        self::assertStringNotContainsString('Architecture & DDD', $view);
        self::assertStringNotContainsString('TCCC / Trauma Care', $view);
    }
}
