<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\DemoNdaController;
use App\Services\DemoNda\DemoNdaGateService;
use PHPUnit\Framework\TestCase;

final class PreviewFeedbackPageAssetTest extends TestCase
{
    public function testPublicPreviewFeedbackUrlIsShareableAndLinked(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $footer = (string) file_get_contents($root . '/views/partials/portal_footer.php');
        $dash = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $view = (string) file_get_contents($root . '/views/demo_nda/feedback.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/DemoNdaController.php');
        $gate = (string) file_get_contents($root . '/app/Services/DemoNda/DemoNdaGateService.php');

        self::assertSame('/retour-preview', DemoNdaGateService::FEEDBACK_PATH);
        self::assertSame('/retour-demonstration', DemoNdaGateService::LEGACY_FEEDBACK_PATH);
        self::assertStringContainsString("get('/retour-preview'", $routes);
        self::assertStringContainsString("post('/retour-preview'", $routes);
        self::assertStringContainsString("get('/retour-demonstration'", $routes);
        self::assertStringContainsString('Donner votre avis', $footer);
        self::assertStringContainsString('FEEDBACK_PATH', $footer);
        self::assertStringContainsString('Ouvrir le questionnaire', $dash);
        self::assertStringContainsString('Athena · Preview', $view);
        self::assertStringContainsString('Les trois niveaux sont-ils clairs', $view);
        self::assertStringContainsString('name="access_clarity"', $view);
        self::assertStringContainsString('name="access_enough"', $view);
        self::assertStringContainsString('name="access_jobs"', $view);
        self::assertStringNotContainsString('RBAC', $view);
        self::assertStringContainsString('feedbackAccessClarityLabels', $ctrl);
        self::assertStringContainsString('LEGACY_FEEDBACK_PATH', $gate);
        self::assertArrayHasKey('unseen', DemoNdaController::feedbackAccessClarityLabels());
        self::assertArrayHasKey('enough', DemoNdaController::feedbackAccessEnoughLabels());
    }
}
