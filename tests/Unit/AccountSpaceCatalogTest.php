<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use App\Support\AccountSpaceCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Le menu et la recherche de l'espace compte viennent du même plan : chaque entrée doit mener à une page
 * servie, et chaque réglage à une ancre réellement présente dans la vue.
 */
final class AccountSpaceCatalogTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<string> */
    private function getRoutes(): array
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        try {
            $router = new Router(new Request());
            (require self::ROOT . '/routes/web.php')($router);
        } finally {
            $_SERVER = $server;
        }
        $routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);

        return array_values(array_map(
            static fn (array $r): string => $r['path'],
            array_filter($routes, static fn (array $r): bool => $r['method'] === 'GET')
        ));
    }

    public function testEveryPageIsAServedRouteWithItsView(): void
    {
        $routes = $this->getRoutes();
        $keys = [];
        foreach (AccountSpaceCatalog::pages() as $page) {
            self::assertContains('/' . $page['path'], $routes, 'Route absente : ' . $page['path']);
            self::assertFileExists(self::ROOT . '/' . $page['view'], $page['key']);
            self::assertNotSame('', trim($page['label']));
            self::assertNotEmpty($page['keywords'], $page['key'] . ' : mots-clés manquants');
            $keys[] = $page['key'];
        }
        self::assertSame($keys, array_unique($keys), 'Clés de page en double.');
    }

    public function testEveryAnchorExistsInThePageView(): void
    {
        $views = [];
        foreach (AccountSpaceCatalog::pages() as $page) {
            $views[$page['key']] = (string) file_get_contents(self::ROOT . '/' . $page['view']);
        }
        foreach (AccountSpaceCatalog::sections() as $section) {
            self::assertArrayHasKey($section['page'], $views, $section['title']);
            self::assertStringContainsString(
                'id="' . $section['anchor'] . '"',
                $views[$section['page']],
                'Ancre #' . $section['anchor'] . ' introuvable dans la page « ' . $section['page'] . ' ».'
            );
            self::assertNotEmpty($section['keywords'], $section['title']);
        }
    }

    public function testNavigationKeysMatchThePagesThatUseTheShell(): void
    {
        $navKeys = [];
        foreach (AccountSpaceCatalog::navGroups() as $group) {
            foreach ($group['items'] as $item) {
                $navKeys[] = $item['key'];
            }
        }
        foreach (['overview', 'preferences', 'mail', 'password', 'security', 'devices', 'portrait', 'banner', 'access', 'public_cv', 'donnees'] as $key) {
            self::assertContains($key, $navKeys);
        }
        self::assertNotContains('recruitment', $navKeys, 'Les profils de candidature sont retirés du menu.');
        foreach (AccountSpaceCatalog::pages() as $page) {
            $view = (string) file_get_contents(self::ROOT . '/' . $page['view']);
            self::assertStringContainsString("\$accountNavKey = '" . $page['key'] . "'", $view, $page['view']);
        }
    }

    public function testSearchIndexCoversPagesSectionsAndBilingualKeywords(): void
    {
        $entries = AccountSpaceCatalog::searchEntries();
        self::assertCount(count(AccountSpaceCatalog::pages()) + count(AccountSpaceCatalog::sections()), $entries);
        $ids = array_column($entries, 'id');
        self::assertSame($ids, array_unique($ids));

        $find = static function (string $word) use ($entries): array {
            return array_values(array_filter($entries, static fn (array $e): bool => str_contains(
                mb_strtolower($e['title'] . ' ' . $e['keywords']),
                $word
            )));
        };
        // Un mot anglais mène au bon réglage français.
        self::assertStringContainsString('#section-interface', $find('dark mode')[0]['href']);
        self::assertStringContainsString('account/password', $find('change password')[0]['href']);
        self::assertStringContainsString('#privacy-delete-heading', $find('delete account')[0]['href']);
        self::assertStringContainsString('#language', $find('english')[0]['href']);

        $hidden = AccountSpaceCatalog::searchEntries(['section-discord', 'charter']);
        self::assertSame([], array_values(array_filter($hidden, static fn (array $e): bool => str_contains($e['href'], 'section-discord'))));
        self::assertSame([], array_values(array_filter($hidden, static fn (array $e): bool => $e['id'] === 'page-charter')));
    }
}
