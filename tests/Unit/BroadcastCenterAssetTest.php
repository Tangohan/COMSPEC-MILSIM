<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Centre de diffusion : routes protégées, jeton CSRF, repli sans JavaScript, fenêtre au lieu de confirm(),
 * anciennes pages gardées, format lu par le mod inchangé, migration idempotente.
 */
final class BroadcastCenterAssetTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testRoutesKeepTheAddressAndTheOldFormAndAreProtected(): void
    {
        $routes = self::read('routes/web.php');
        foreach (["get('/back-office/alerts', ", "post('/back-office/alerts/apercu'", "post('/back-office/alerts/diffuser'", "post('/back-office/alerts/centre/modifier'", "post('/back-office/alerts/centre/retirer'"] as $route) {
            self::assertMatchesRegularExpression('#' . preg_quote($route, '#') . '.*BroadcastCenterController::class.*AuthMiddleware::class, OrganizationAdminMiddleware::class#', $routes);
        }
        foreach (["get('/back-office/alerts/create'", "post('/back-office/alerts', [TenantAlertsController", "get('/back-office/alerts/{id}/edit'", "post('/back-office/alerts/affichage-a-venir'", "get('/back-office/ressources/atak-consignes'", "post('/back-office/atak/parc-atak/message/tous'"] as $kept) {
            self::assertStringContainsString($kept, $routes);
        }
        self::assertStringContainsString("get('/api/atak/community-brief', [AtakApiController::class, 'communityBrief'])", $routes);
    }

    public function testEveryWriteChecksCsrfAndChannelRights(): void
    {
        $c = self::read('app/Controllers/Admin/Organization/BroadcastCenterController.php');
        foreach (['public function preview', 'public function send', 'private function write'] as $method) {
            $start = (int) strpos($c, $method);
            $block = substr($c, $start, (int) strpos($c, 'try', $start) - $start + 200);
            self::assertStringContainsString('Csrf::verify($request)', $block, $method);
        }
        self::assertStringContainsString("Session::get('tenant_id')", $c);
        self::assertStringContainsString("allows('atak.command.alert')", $c);
        self::assertStringContainsString("\$this->features->allows(\$tenantId, 'alerts')", $c);

        $mw = self::read('app/Middleware/OrganizationAdminMiddleware.php');
        self::assertStringContainsString("'/back-office/alerts/centre/retirer'], true)", $mw);
        self::assertStringNotContainsString("'/back-office/alerts/create'", $mw);
    }

    public function testPagesWorkWithoutScriptAndNeverUseConfirm(): void
    {
        foreach (['views/admin/broadcast/index.php', 'views/admin/broadcast/_live.php', 'views/admin/broadcast/_compose.php', 'views/admin/broadcast/_preview.php', 'public/assets/js/back-office-broadcast.js'] as $file) {
            self::assertStringNotContainsString('confirm(', self::read($file), $file);
        }
        $live = self::read('views/admin/broadcast/_live.php');
        self::assertStringContainsString("method=\"post\" action=\"<?= \$h(\$bcBase . '/centre/modifier') ?>\"", $live);
        self::assertStringContainsString("method=\"post\" action=\"' . \$h(\$bcBase . '/centre/retirer') . '\"", $live);
        self::assertStringContainsString('method="get"', $live);
        self::assertStringContainsString('\App\Core\Csrf::field()', $live);
        $compose = self::read('views/admin/broadcast/_compose.php');
        self::assertStringContainsString("formaction=\"<?= \$h(\$bcBase . '/apercu') ?>\"", $compose);
        self::assertStringContainsString('\App\Core\Csrf::field()', $compose);
        self::assertStringNotContainsString('$nav', $compose . $live . self::read('views/admin/broadcast/index.php'));

        $js = self::read('public/assets/js/back-office-broadcast.js');
        self::assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $js);
        self::assertStringContainsString('json.ok !== true', $js);
        self::assertStringContainsString('showModal', $js);
    }

    public function testModFormatAndConsignesPageAreUntouched(): void
    {
        $api = self::read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("return Response::json(\\App\\Support\\AtakCommunityBrief::payload(\$pack['settings'], \$pack['updated_at']));", $api);
        $brief = self::read('app/Support/AtakCommunityBrief.php');
        self::assertStringContainsString("return implode(\"\\n\", array_map(static fn (array \$n): string => \$n['kind'] . ' | ' . \$n['title'] . ' | ' . \$n['body'], \$notes));", $brief);
        $service = self::read('app/Services/Broadcast/BroadcastCenterService.php');
        self::assertStringContainsString('$this->brief->change($tenantId, AtakCommunityBriefService::NOTES', $service);
        self::assertStringContainsString("'source' => 'web'", $service);
        self::assertStringContainsString('back-office/alerts', self::read('views/admin/atak-brief/_live.php'));
    }

    public function testServicesAreDeclaredInTheContainer(): void
    {
        $container = self::read('app/Core/Container.php');
        self::assertStringContainsString('\App\Services\Broadcast\BroadcastCenterService::class => new', $container);
        self::assertStringContainsString('\App\Controllers\Admin\Organization\BroadcastCenterController::class => new', $container);
    }

    public function testMigrationIsRegisteredAndIdempotent(): void
    {
        $registry = require dirname(__DIR__, 2) . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $e): bool => $e['id'] === 'broadcast_center'))[0] ?? null;
        self::assertNotNull($entry);
        self::assertSame('4190_broadcast_center.php', $entry['file']);
        self::assertTrue($entry['rerun']);
        $step = self::read('database/steps/4190_broadcast_center.php');
        self::assertStringContainsString('addColumnIfMissing(', $step);
        self::assertStringContainsString('addIndexIfMissing(', $step);
        self::assertStringNotContainsString('CREATE TABLE', $step);
    }
}
