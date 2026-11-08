<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use App\Services\ConfigurationUpdate\ConfigurationUpdateCatalog;
use App\Services\ConfigurationUpdate\ConfigurationUpdateDefinition;
use App\Services\ConfigurationUpdate\ConfigurationUpdateProbes;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;

/** Étapes de Mise à niveau déclarées dans config/configuration_release_steps.php. */
final class ConfigurationReleaseStepsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<array<string, mixed>> */
    private static function steps(): array
    {
        return require self::ROOT . '/config/configuration_release_steps.php';
    }

    /** Toutes les routes GET réellement enregistrées : première qui répond, comme Router::dispatch(). */
    private static function getRoute(string $path): ?array
    {
        static $routes = null;
        if ($routes === null) {
            $router = new Router(new Request());
            (require self::ROOT . '/routes/web.php')($router);
            $routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
        }
        foreach ($routes as $route) {
            if (($route['method'] ?? '') === 'GET' && ($route['path'] ?? '') === $path) {
                return $route;
            }
        }

        return null;
    }

    public function testDeclarationsAreComplete(): void
    {
        $codes = [];
        foreach (self::steps() as $step) {
            self::assertMatchesRegularExpression('/^[A-Z0-9_]{1,64}$/', $step['code']);
            self::assertNotContains($step['code'], $codes, 'code déjà utilisé');
            $codes[] = $step['code'];
            self::assertLessThanOrEqual(16, strlen($step['version']));
            self::assertLessThanOrEqual(191, mb_strlen($step['title']), $step['code']);
            self::assertNotSame('', trim($step['description']), $step['code']);
            self::assertContains($step['applies'], ['all', 'atak', 'sse'], $step['code']);
            self::assertContains($step['level'], ['informative', 'recommended'], $step['code']);
            self::assertGreaterThan(0, $step['minutes'], $step['code']);
        }
    }

    public function testEachPathIsARealPageNotARedirect(): void
    {
        foreach (self::steps() as $step) {
            self::assertStringStartsNotWith('/', $step['path'], $step['code']);
            $base = (string) strtok($step['path'], '?#');
            parse_str((string) parse_url($step['path'], PHP_URL_QUERY), $query);
            self::assertNotNull(self::getRoute('/' . $base), $step['code'] . ' : aucune page GET /' . $base);
            // Page à onglets : l'onglet demandé existe, et ses droits sont ceux de la famille (BackOfficeTabs).
            $family = BackOfficeTabs::FAMILIES[$base] ?? null;
            if ($family !== null) {
                self::assertNotEmpty($family['middleware'], $step['code']);
                if (isset($query['onglet'])) {
                    self::assertArrayHasKey($query['onglet'], $family['tabs'], $step['code'] . ' : onglet inconnu');
                }
                continue;
            }
            $route = self::getRoute('/' . $base);
            self::assertIsArray($route['handler'], $step['code'] . ' : /' . $base . ' est une redirection');
            self::assertNotEmpty($route['middleware'], $step['code'] . ' : page sans contrôle d’accès');
        }
    }

    public function testCatalogExposesEveryStepWithManualCompletion(): void
    {
        $catalog = new ConfigurationUpdateCatalog($this->createStub(ConfigurationUpdateProbes::class));
        $byCode = [];
        foreach ($catalog->definitions() as $def) {
            self::assertArrayNotHasKey($def->code, $byCode, 'code en double dans le catalogue');
            $byCode[$def->code] = $def;
        }
        foreach (self::steps() as $step) {
            self::assertArrayHasKey($step['code'], $byCode);
            $def = $byCode[$step['code']];
            self::assertInstanceOf(ConfigurationUpdateDefinition::class, $def);
            self::assertTrue($def->manualDone);
            self::assertSame($step['path'], $def->configurePath);
        }
        self::assertFalse($byCode['TIMEZONE_V1']->manualDone, 'les étapes historiques restent détectées par leurs données');
    }

    public function testMigrationIsRegisteredAndRerun(): void
    {
        $registry = require self::ROOT . '/database/steps/registry.php';
        $found = array_values(array_filter($registry, static fn (array $s): bool => $s['id'] === 'configuration_release_steps'));
        self::assertCount(1, $found);
        self::assertTrue($found[0]['rerun']);
        self::assertFileExists(self::ROOT . '/database/steps/' . $found[0]['file']);
    }

    public function testDoneButtonIsProtected(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertMatchesRegularExpression("#'/back-office/mise-a-niveau/terminer', \[ConfigurationUpdateController::class, 'complete'\], \[AuthMiddleware::class, OrganizationAdminMiddleware::class#", $routes);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/configuration_updates.php');
        self::assertStringContainsString("url('back-office/mise-a-niveau/terminer')", $view);
    }
}
