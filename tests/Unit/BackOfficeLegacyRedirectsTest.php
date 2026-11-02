<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Support\BackOfficeLegacyRedirects;
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
        foreach (array_keys(BackOfficeLegacyRedirects::MAP) as $legacy) {
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
        [$route] = $this->match('POST', '/back-office/organisation/parametres');
        self::assertIsArray($route['handler']);
        self::assertSame('update', $route['handler'][1]);
    }
}
