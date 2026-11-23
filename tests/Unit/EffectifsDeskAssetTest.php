<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Bureau des effectifs plein écran : route, droits, zones du modèle, écritures protégées, pas de classe « surtitre ». */
final class EffectifsDeskAssetTest extends TestCase
{
    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testRoutesUseTheSameMiddlewareAsTheRoster(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("get('/back-office/bureau-effectifs', [\\App\\Controllers\\Admin\\EffectifsDeskController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        self::assertStringContainsString("get('/back-office/bureau-effectifs/donnees', [\\App\\Controllers\\Admin\\EffectifsDeskController::class, 'data'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        self::assertStringNotContainsString("post('/back-office/bureau-effectifs", $routes);
        self::assertStringContainsString("str_starts_with(\$path, '/back-office/bureau-effectifs')", self::read('app/Middleware/OrganizationAdminMiddleware.php'));
    }

    public function testControllerGatesOnRosterBureauModeAndEffectifsAccess(): void
    {
        $c = self::read('app/Controllers/Admin/EffectifsDeskController.php');
        self::assertStringContainsString('TenantTypeConfig::rosterBureauMode(', $c);
        self::assertStringContainsString('EffectifsLmsAccess::allows(Gate::getInstance())', $c);
        self::assertStringContainsString('Response::redirect(effectifs_workspace_url())', $c);
        self::assertStringContainsString("Session::get('tenant_id')", $c);
        self::assertStringNotContainsString('$request->query', $c);
    }

    public function testPageHasEveryZoneOfTheModel(): void
    {
        $p = self::read('views/admin/effectifs_desk/page.php');
        foreach (['titlebar', 'menubar', 'toolbar', 'shortcuts', 'cards', 'filters', 'formula', 'tree', 'main', 'dossier', 'footer', 'overlay', 'toast', 'boot'] as $zone) {
            self::assertStringContainsString('data-zone="' . $zone . '"', $p, $zone);
        }
        self::assertSame(1, substr_count($p, '<h1'));
        self::assertStringContainsString('Retour au back-office', $p);
        self::assertStringContainsString('effectifs-desk.css', $p);
        self::assertStringContainsString('effectifs-desk.js', $p);
        $all = strtolower($p . self::read('public/assets/css/effectifs-desk.css') . self::read('public/assets/js/effectifs-desk.js'));
        foreach (['kicker', 'eyebrow', 'overline', 'surtitre'] as $w) {
            self::assertStringNotContainsString($w, $all);
        }
    }

    public function testScriptOnlyWritesThroughProtectedInvitationRoute(): void
    {
        $js = self::read('public/assets/js/effectifs-desk.js');
        self::assertSame(1, substr_count($js, "method: 'POST'"));
        self::assertStringContainsString("fd.set('_csrf_token', CFG.csrf)", $js);
        self::assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $js);
        self::assertStringContainsString("'/invitations'", $js);
        self::assertStringNotContainsString("method: 'PUT'", $js);
        self::assertStringNotContainsString("method: 'DELETE'", $js);
        foreach (['role="grid"', 'role="tree"', 'role="treeitem"', 'ArrowDown', 'Escape', "'Enter'"] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
    }

    public function testHeroAndMenuPointToTheDeskAndImportComesBack(): void
    {
        self::assertStringContainsString('effectifs_desk_url()', self::read('views/admin/organization/_bureau_hero.php'));
        self::assertStringContainsString('effectifs_desk_url()', self::read('views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString("=== 'bureau-effectifs' ? effectifs_desk_url()", self::read('app/Controllers/Admin/EffectifsWorkspaceController.php'));
        self::assertStringContainsString('@media (max-width: 900px)', self::read('public/assets/css/effectifs-desk.css'));
    }
}
