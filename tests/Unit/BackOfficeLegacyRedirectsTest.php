<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Support\BackOfficeLegacyRedirects;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;

/**
 * Nettoyage du back-office : chaque page retirée ou fusionnée répond 301 vers sa remplaçante
 * (requête d’origine conservée, onglet de la cible prioritaire), et rien ne la sert encore en direct.
 */
final class BackOfficeLegacyRedirectsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @var list<array{method:string,path:string,pattern:string,handler:mixed,middleware:array}> */
    private static array $routes = [];

    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_GET = $_POST = [];
        if (self::$routes === []) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            $router = new Router(new Request());
            (require self::ROOT . '/routes/web.php')($router);
            self::$routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = [];
    }

    /** Première route qui répond, comme Router::dispatch(). */
    private function match(string $method, string $path): array
    {
        foreach (self::$routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $m)) {
                return [$route, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        self::fail($method . ' ' . $path . ' : aucune route');
    }

    /** @return list<array{0:string,1:array<string,string>,2:string}> */
    public static function redirects(): array
    {
        return [
            ['/back-office/centre-operations', [], '/back-office'],
            ['/back-office/operations-admin', ['profile' => 'rh'], '/back-office?profile=rh'],
            ['/back-office/organisation-effectifs', [], '/orbat'],
            ['/back-office/organisation/parametres', ['onglet' => 'inscription'], '/back-office/community?onglet=inscription'],
            ['/back-office/ressources/forum-config', [], '/back-office'],
            ['/back-office/onboarding-members', [], '/back-office/integration-membres'],
            ['/back-office/personnel/advanced-edit', [], '/admin/system/advanced-fiche-edit'],
            ['/back-office/ressources/effectifs/integration', [], '/back-office/integration-membres'],
            ['/back-office/organisation/progression', [], '/back-office/rh/avancement'],
            // Pages devenues onglets : filtres d’origine conservés, onglet de la cible prioritaire.
            ['/back-office/planification', [], '/back-office/missions?onglet=planification'],
            ['/back-office/atak/suivi-cr', ['cr' => '12'], '/back-office/atak/comptes-rendus?cr=12&onglet=suivi'],
            ['/back-office/roleplay/immersion', [], '/back-office/roleplay-followup?onglet=immersion'],
            ['/back-office/roleplay/regles-phases', ['phase' => '3'], '/back-office/roleplay-followup?phase=3&onglet=phases'],
            ['/back-office/ressources/effectifs/roleplay', [], '/back-office/roleplay-followup?onglet=dossiers'],
            ['/back-office/atak/temps-ecran', ['jours' => '7'], '/back-office/metriques-joueurs?jours=7&onglet=temps-ecran'],
            ['/back-office/atak/fire-teams', ['vue' => 'mission'], '/back-office/atak/escouades?vue=mission&onglet=fire-teams'],
            ['/back-office/ressources/atak-realism/config', [], '/back-office/atak/realisme?onglet=reglages'],
            ['/back-office/documents/compliance', ['document_id' => '4'], '/back-office/doctrine?document_id=4&onglet=conformite'],
            ['/back-office/documents/nomenclature', [], '/back-office/doctrine?onglet=nomenclature'],
            ['/back-office/documents/types', ['onglet' => 'vieux'], '/back-office/doctrine?onglet=types'],
            ['/back-office/nouveautes-organisation', [], '/back-office/mise-a-niveau?onglet=nouveautes'],
            ['/back-office/ressources/effectifs/alertes', [], '/back-office/ressources/effectifs/a-traiter?onglet=alertes'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('redirects')]
    public function testOldAddressRedirectsPermanentlyToItsReplacement(string $path, array $query, string $target): void
    {
        [$route, $params] = $this->match('GET', $path);
        self::assertInstanceOf(\Closure::class, $route['handler'], $path . ' doit être servi par la redirection, pas par une page');
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        $response = ($route['handler'])(new Request(), $params);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(301, $response->statusCode(), $path);
        self::assertStringEndsWith($target, (string) $response->headerValue('Location'), $path);
    }

    public function testEveryMapEntryIsCoveredAndAnswersBeforeAnyOtherRoute(): void
    {
        $covered = array_map(static fn (array $row): string => ltrim($row[0], '/'), self::redirects());
        foreach (array_keys(BackOfficeLegacyRedirects::map()) as $legacy) {
            self::assertContains($legacy, $covered, $legacy . ' : ajoutez un cas à redirects()');
            [$route] = $this->match('GET', '/' . $legacy);
            self::assertInstanceOf(\Closure::class, $route['handler'], $legacy);
            $response = ($route['handler'])(new Request(), []);
            self::assertSame(301, $response->statusCode(), $legacy);
        }
    }

    public function testTargetTabWinsOverAStaleQueryTab(): void
    {
        $location = BackOfficeLegacyRedirects::location('back-office/centre-operations', ['onglet' => 'x', 'a' => '1']);
        self::assertStringEndsWith('/back-office?onglet=x&a=1', (string) $location);
        self::assertNull(BackOfficeLegacyRedirects::location('back-office/inconnue'));
    }

    public function testRemovedPagesLeftNoLinkBehind(): void
    {
        $removed = ['back-office/centre-operations', 'back-office/operations-admin', 'back-office/organisation-effectifs'];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/views', \FilesystemIterator::SKIP_DOTS));
        $sources = [self::ROOT . '/config/navigation.php', self::ROOT . '/config/back_office_pages.php'];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $sources[] = $file->getPathname();
            }
        }
        foreach (['app/Support/PilotageDashboardPresenter.php', 'app/Services/Portal/BackOfficeSearchService.php', 'app/Services/Community/TenantTypeConfig.php'] as $rel) {
            $sources[] = self::ROOT . '/' . $rel;
        }
        foreach ($sources as $src) {
            $code = (string) file_get_contents($src);
            foreach ($removed as $path) {
                self::assertStringNotContainsString("'" . $path . "'", $code, $src . ' renvoie encore vers ' . $path);
            }
        }
    }

    public function testFormsKeepPostingToTheirAddresses(): void
    {
        $posts = [
            '/back-office/organisation/parametres' => 'update',
            '/back-office/planification' => 'store',
            '/back-office/roleplay/immersion' => 'immersionSettingsUpdate',
            '/back-office/roleplay/regles-phases' => 'save',
            '/back-office/documents/types' => 'typesSave',
            '/back-office/documents/nomenclature' => 'nomenclatureSave',
        ];
        foreach ($posts as $path => $action) {
            [$route] = $this->match('POST', $path);
            self::assertIsArray($route['handler'], $path);
            self::assertSame($action, $route['handler'][1], $path);
        }
    }

    public function testEachTabHomeIsServedByTheTabDispatcherWithTheOldControllers(): void
    {
        foreach (BackOfficeTabs::FAMILIES as $base => $family) {
            [$route] = $this->match('GET', '/' . $base);
            self::assertInstanceOf(\Closure::class, $route['handler'], $base);
            self::assertTrue(method_exists($family['handler'][0], $family['handler'][1]), $base);
            self::assertNotSame([], $family['middleware'], $base);
            foreach ($family['tabs'] as $key => $tab) {
                self::assertTrue(method_exists($tab['handler'][0], $tab['handler'][1]), $base . ' › ' . $key);
                self::assertContains('App\\Middleware\\AuthMiddleware', $tab['middleware'], $key);
                self::assertSame($base . '?onglet=' . $key, BackOfficeLegacyRedirects::target($tab['legacy']), $tab['legacy']);
                // En-tête et styles : ceux de l’ancienne page.
                self::assertSame($tab['legacy'], BackOfficeTabs::contentPath($base, $key));
            }
        }
        self::assertSame('back-office/missions', BackOfficeTabs::contentPath('back-office/missions', 'inconnu'));
    }

    public function testTabBarListsTheHomeAndOnlyAllowedTabs(): void
    {
        $bar = BackOfficeTabs::barFor('back-office/roleplay-followup', 'immersion');
        self::assertIsArray($bar);
        self::assertCount(4, $bar);
        self::assertFalse($bar[0]['active']);
        self::assertSame([false, true, false], array_map(static fn (array $t): bool => $t['active'], array_slice($bar, 1)));
        self::assertStringEndsWith('/back-office/roleplay-followup?onglet=immersion', $bar[2]['href']);

        $onlyPhases = BackOfficeTabs::barFor('back-office/roleplay-followup', '', static fn (string $legacy): bool => $legacy === 'back-office/roleplay/regles-phases');
        self::assertSame(['Bureau de suivi', 'Parcours RH'], array_column((array) $onlyPhases, 'label'));
        self::assertTrue($onlyPhases[0]['active']);

        // Pages qui ont déjà leurs onglets, ou sans onglet autorisé : pas de seconde barre.
        self::assertNull(BackOfficeTabs::barFor('back-office/doctrine', ''));
        self::assertNull(BackOfficeTabs::barFor('back-office/ressources/effectifs/a-traiter', 'alertes'));
        self::assertNull(BackOfficeTabs::barFor('back-office/missions', '', static fn (): bool => false));
        self::assertNull(BackOfficeTabs::barFor('back-office/events', ''));
    }
}
