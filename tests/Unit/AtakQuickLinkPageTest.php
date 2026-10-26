<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Page /lier : le joueur autorise le jeu depuis son compte Athena, sans clé de communauté. */
final class AtakQuickLinkPageTest extends TestCase
{
    public function testQuickLinkPageIsRoutedAndPairingPointsToIt(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $svc = (string) file_get_contents($root . '/app/Services/Game/GameAtakPairingService.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/AtakQuickLinkController.php');

        self::assertStringContainsString("->get('/lier', [\\App\\Controllers\\Web\\AtakQuickLinkController::class, 'show'])", $routes);
        self::assertStringContainsString("->post('/lier', [\\App\\Controllers\\Web\\AtakQuickLinkController::class, 'confirm'], [AuthMiddleware::class", $routes);
        self::assertStringContainsString("->get('/lier/etat', [\\App\\Controllers\\Web\\AtakQuickLinkController::class, 'state'], [AuthMiddleware::class])", $routes);
        self::assertStringContainsString('describePending', $ctrl);
        self::assertStringContainsString("'verification_uri' => url('lier')", $svc);
        self::assertStringContainsString("'verification_uri_complete'", $svc);
        self::assertStringContainsString('approveFromWeb', $ctrl);
        self::assertStringContainsString('Csrf::validate', $ctrl);
        self::assertFileExists($root . '/views/link/lier.php');
        self::assertStringContainsString("Response::view('link.lier'", $ctrl);
        $view = (string) file_get_contents($root . '/views/link/lier.php');
        self::assertStringContainsString('Autoriser ce jeu', $view);
        self::assertStringContainsString('method="post"', $view);
        self::assertStringContainsString("url('lier/etat')", $view);
        self::assertStringContainsString('Éditeur Eden · COMSPEC SSE', $view);
        self::assertStringContainsString('Téléphone ATAK · pack Overwatch', $view);
        self::assertStringContainsString('Téléphone ATAK · app Athena', $view);
        self::assertStringContainsString('assets/css/link-lier.css', $view);
        self::assertStringNotContainsString('atak_quick_link_code_shown', $view);
        self::assertStringNotContainsString('game-link.css', $view);
        self::assertStringNotContainsString('.submit(', $view);
        self::assertStringNotContainsString('requestSubmit', $view);
        self::assertFileExists($root . '/public/assets/css/link-lier.css');
        $css = (string) file_get_contents($root . '/public/assets/css/link-lier.css');
        self::assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function testRequestSourceTellsPhoneEdenAndOverwatchApart(): void
    {
        self::assertSame('eden', \App\Services\Game\GameAtakPairingService::requestSource('sse-eden'));
        self::assertSame('phone', \App\Services\Game\GameAtakPairingService::requestSource('native'));
        self::assertSame('phone', \App\Services\Game\GameAtakPairingService::requestSource(''));
        self::assertSame('overwatch', \App\Services\Game\GameAtakPairingService::requestSource('1.6.16'));
    }
}
