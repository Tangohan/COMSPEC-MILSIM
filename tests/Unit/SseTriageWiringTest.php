<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseTriageController;
use App\Core\Request;
use App\Core\Session;
use App\Services\Sse\SseTriageInbox;
use PHPUnit\Framework\TestCase;

/** Câblage : routes, layout (bouton, pastilles, assets), endpoint JSON des compteurs, traduction anglaise, mobile. */
final class SseTriageWiringTest extends TestCase
{
    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    protected function tearDown(): void
    {
        SseTriageInbox::useForSession(null);
    }

    public function testRoutesAreReadOnlyAndBehindThePortalGuards(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("get('/atak/sse/a-trier', [\\App\\Controllers\\Web\\SseTriageController::class, 'index'], \$mwSsePortal)", $routes);
        self::assertStringContainsString("get('/atak/sse/a-trier/compteurs', [\\App\\Controllers\\Web\\SseTriageController::class, 'counters'], \$mwSsePortal)", $routes);
        self::assertStringNotContainsString("post('/atak/sse/a-trier", $routes);
        self::assertStringNotContainsString('Csrf', self::read('app/Controllers/Web/SseTriageController.php'), 'lecture seule : pas de jeton');
    }

    public function testLayoutShowsTheTriageButtonAndTheBadgesInTheAside(): void
    {
        $layout = self::read('views/atak/sse/_layout.php');
        self::assertStringContainsString('class="sb-triage', $layout);
        self::assertStringContainsString("url('atak/sse/a-trier/compteurs')", $layout);
        self::assertStringContainsString('SseTriageBadges::badge(', $layout);
        self::assertStringContainsString('SseTriageBadges::pill(', $layout);
        self::assertStringContainsString('assets/css/sse-aside-badges.css', $layout);
        self::assertStringContainsString('assets/js/sse-aside-badges.js', $layout);
        $asidePos = strpos($layout, '<aside class="iw-nav sx-nav"');
        self::assertNotFalse($asidePos);
        self::assertTrue(strpos($layout, 'class="sb-triage', (int) $asidePos) > $asidePos, 'le bouton est dans la barre latérale (tiroir mobile compris)');
    }

    public function testTheTriageButtonIsAtLeast44PixelsAndNothingBlinks(): void
    {
        $css = self::read('public/assets/css/sse-aside-badges.css');
        self::assertMatchesRegularExpression('/\.sb-triage\s*\{[^}]*min-height:\s*44px/s', $css);
        self::assertStringNotContainsString('@keyframes', $css);
        self::assertSame(0, preg_match('/animation\s*:/', $css));
        self::assertStringContainsString('font-variant-numeric: tabular-nums', $css);
        self::assertStringContainsString('border-radius: 999px', $css);
        self::assertStringContainsString('--sb-accent: #12d18e', $css);
        self::assertStringContainsString('--sb-warn: #e0a233', $css);
        self::assertStringContainsString('--sb-bad: #ff6b5e', $css);
        self::assertStringContainsString('--sb-neutral: #8a979e', $css);
    }

    public function testPollingIsSoftPausedWhenHiddenAndEveryMinute(): void
    {
        $js = self::read('public/assets/js/sse-aside-badges.js');
        self::assertStringContainsString('60000', $js);
        self::assertStringContainsString('visibilitychange', $js);
        self::assertStringContainsString('document.hidden', $js);
        self::assertStringNotContainsString('requestAnimationFrame', $js);
        self::assertStringNotContainsString('setTimeout', $js);
    }

    public function testCountersEndpointReturnsLightJson(): void
    {
        Session::set('tenant_id', 1);
        $now = time();
        $it = static fn (string $k, int $age, int $p = 0): array => ['key' => $k, 'title' => $k, 'meta' => '', 'at' => gmdate('Y-m-d H:i:s', $now - $age * 86400), 'href' => '#', 'priority' => $p];
        SseTriageInbox::useForSession(new SseTriageInbox([
            ['key' => 'fiches', 'nav' => 'fiches', 'label' => 'F', 'tab' => 'F', 'path' => 'atak/sse/fiches', 'load' => static fn (int $t): array => array_map(static fn (int $i): array => $it('n:' . $i, 9), range(1, 120))],
            ['key' => 'anomalies', 'nav' => 'anomalies', 'label' => 'A', 'tab' => 'A', 'path' => 'atak/sse/anomalies', 'load' => static fn (int $t): array => [$it('s:1', 1, 2)]],
        ], fn (): bool => true));
        $response = (new SseTriageController())->counters(new Request('GET', '/atak/sse/a-trier/compteurs'), []);
        $data = json_decode($response->body(), true);

        self::assertSame(200, $response->statusCode());
        self::assertSame(101, $data['total']);
        self::assertSame('99+', $data['label']);
        self::assertSame('99+ à trier', $data['aria']);
        self::assertSame(100, $data['entries']['fiches']['count']);
        self::assertSame('warn', $data['entries']['fiches']['tone']);
        self::assertSame('bad', $data['entries']['anomalies']['tone']);
        self::assertSame('1 à trier', $data['entries']['anomalies']['aria']);
        self::assertSame('private, no-store', $response->headerValue('Cache-Control'));
    }

    public function testNewTextsAreTranslatedToEnglish(): void
    {
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['À trier', '%1 à trier', 'Rien à trier', 'Files à trier', 'Toutes %1', 'Identité #%1', 'Expire %1'] as $fr) {
            self::assertTrue(isset($dict[$fr]), $fr);
        }
        self::assertSame('%1 to sort', $dict['%1 à trier']);
    }

    public function testTheViewHasNoEyebrowAndNoSimulationWording(): void
    {
        $view = self::read('views/atak/sse/triage.php');
        self::assertStringNotContainsString('eyebrow', strtolower($view));
        self::assertStringNotContainsString('simulation', strtolower($view));
        self::assertStringContainsString('Rien à trier', $view);
    }
}
