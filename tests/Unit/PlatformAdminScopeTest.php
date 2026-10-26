<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * « Admin » = administrateur de la plateforme complète, et lui seul.
 * Les outils d’une communauté (gestionnaires du tenant) vivent sous /back-office.
 */
final class PlatformAdminScopeTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testEveryAdminPageIsGuardedByAPlatformMiddlewareOrOnlyRedirects(): void
    {
        $offenders = [];
        foreach (explode("\n", $this->read('routes/web.php')) as $n => $line) {
            if (preg_match("#router->(get|post|put|patch|delete)\\('(/admin(?:/[^']*)?)'#", $line, $m) !== 1) {
                continue;
            }
            [, $method, $path] = $m;
            if (str_contains($line, 'SystemAdminMiddleware::class') || str_contains($line, 'PlatformHubMiddleware::class')) {
                continue;
            }
            // Anciennes adresses : simple renvoi GET vers la nouvelle page, qui applique ses propres contrôles.
            if ($method === 'get' && str_contains($line, 'fn (') && stripos($line, 'redirect') !== false) {
                continue;
            }
            // Sortie d’une intervention : le contrôleur ne fait que clore la session d’intervention en cours.
            if ($path === '/admin/system/tenant-intervention/leave') {
                continue;
            }
            $offenders[] = ($n + 1) . ' ' . $method . ' ' . $path;
        }
        self::assertSame([], $offenders, 'Route /admin ouverte aux gestionnaires de communauté');
    }

    public function testLegacyAdminAddressesOfCommunityToolsRedirectToTheBackOffice(): void
    {
        $routes = $this->read('routes/web.php');
        foreach ([
            "'/admin/atak-config' => 'back-office/ressources/atak-config'",
            "'/admin/modpacks' => 'back-office/ressources/modpacks'",
            "'/admin/content-moderation' => 'back-office/content-moderation'",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        self::assertDoesNotMatchRegularExpression("#router->(post|get)\\('/admin/[^']*', \\[[^\\]]+\\], \\[AuthMiddleware::class, TenantResourceAdminMiddleware::class\\]#", $routes);
    }

    public function testCrossCommunityModDataIsPlatformOnly(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (['/admin/atak-beta', '/admin/atak-mod-reports'] as $path) {
            self::assertMatchesRegularExpression("#router->get\\('" . preg_quote($path, '#') . "', \\[[^\\]]+\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]#", $routes, $path);
        }
        self::assertStringNotContainsString("'/back-office/ressources/atak-beta'", $routes);
    }

    public function testBackOfficeMenuNeverLinksToThePlatformAdministration(): void
    {
        $nav = $this->read('views/partials/ath_sidebar_nav.php');
        self::assertDoesNotMatchRegularExpression("#url\\('admin(/|')#", $nav);
    }

    public function testPlatformMenuOnlyListsPlatformPages(): void
    {
        $nav = $this->read('views/partials/platform_admin_sidebar.php');
        preg_match_all("#\\['path' => '([^']+)'#", $nav, $m);
        foreach ($m[1] as $path) {
            if ($path === 'back-office') {
                continue; // sortie vers sa propre communauté
            }
            self::assertTrue($path === 'admin' || str_starts_with($path, 'admin/'), $path);
        }
        self::assertStringNotContainsString("'access' => 'forum'", $nav);
    }

    public function testTopMenuSeparatesPlatformAdministrationFromCommunityManagement(): void
    {
        $config = $this->read('config/navigation.php');
        $adminPos = strpos($config, "'label' => 'Administration',");
        $gestionPos = strpos($config, "'label' => 'Gestion',");
        self::assertNotFalse($adminPos);
        self::assertNotFalse($gestionPos);
        $adminBlock = substr($config, (int) $adminPos, 400);
        self::assertStringContainsString("'any_permissions' => ['admin.system'],", $adminBlock);
        $gestionBlock = substr($config, (int) $gestionPos, 400);
        self::assertStringNotContainsString("'admin.system'", $gestionBlock);
    }

    public function testUnmappedAdminPathIsHiddenFromCommunityManagers(): void
    {
        $helpers = $this->read('app/Support/helpers.php');
        self::assertStringContainsString("if (str_starts_with(\$path, 'admin/') || \$path === 'admin') {\n            return ['any_permissions' => ['admin.system']];", $helpers);
    }
}
