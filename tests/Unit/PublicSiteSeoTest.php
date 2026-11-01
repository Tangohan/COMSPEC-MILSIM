<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\LegalController;
use App\Controllers\Web\SeoController;
use App\Controllers\Web\SitePagesController;
use App\Core\Request;
use App\Core\Response;
use App\Support\Seo;
use PHPUnit\Framework\TestCase;

/**
 * Référencement du site public : robots.txt, sitemap.xml, balises des pages principales,
 * adresse canonique sur athena-comspec.eu (même servie par l'ancien domaine ou sous /public), noindex conservé.
 */
final class PublicSiteSeoTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $env = [];

    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $_ENV['APP_URL'] = 'https://athena-comspec.eu';
        $_ENV['APP_BASE_PATH'] = '';
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
        unset($GLOBALS['__app_translator']);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;
        unset($_GET['lang']);
        unset($GLOBALS['__app_locale'], $GLOBALS['__app_translator']);
    }

    // ---------- Adresse canonique ----------

    public function testCanonicalUsesCurrentDomainWithoutLegacyPrefixOrTracking(): void
    {
        self::assertSame('https://athena-comspec.eu/a-propos', Seo::canonicalUrl('/a-propos'));
        self::assertSame('https://athena-comspec.eu/a-propos', Seo::canonicalUrl('/public/a-propos'));
        self::assertSame('https://athena-comspec.eu/a-propos', Seo::canonicalUrl('/a-propos/?utm_source=discord&lang=en'));
        self::assertSame('https://athena-comspec.eu/communities?page=2', Seo::canonicalUrl('/communities?page=2&q=lynx'));
        self::assertSame('https://athena-comspec.eu/', Seo::canonicalUrl('/public/'));
    }

    public function testLegacyDomainIsDeclaredOnTheNewDomain(): void
    {
        $_ENV['APP_URL'] = 'https://athena.ttrd.fr';
        $_SERVER['HTTP_HOST'] = 'athena.ttrd.fr';

        self::assertSame('https://athena-comspec.eu', Seo::baseUrl());
        self::assertSame('https://athena-comspec.eu/sse', Seo::canonicalUrl('/public/sse'));
        self::assertSame('https://athena-comspec.eu/joueur/x', Seo::onCurrentHost('https://athena.ttrd.fr/joueur/x'));
        self::assertSame('https://example.test/a', Seo::onCurrentHost('https://example.test/a'));
    }

    public function testPrivatePathsCoverToolsAccountAndApiButNotPublicPages(): void
    {
        foreach (['/account/profil', '/api/atak/ping', '/back-office', '/login', '/register', '/join', '/atak/connect', '/academy/alpha', '/public/admin/system'] as $path) {
            self::assertTrue(Seo::isPrivatePath(Seo::requestPath($path)), $path);
        }
        foreach (['/a-propos', '/academy', '/atak-natif', '/joueur/alpha', '/notre-histoire', '/communities'] as $path) {
            self::assertFalse(Seo::isPrivatePath($path), $path);
        }
    }

    // ---------- robots.txt ----------

    public function testRobotsBlocksMemberAreasAndPointsToSitemapOnCurrentDomain(): void
    {
        $body = (new SeoController())->robots(new Request())->body();

        foreach (['/back-office/', '/account/', '/api/', '/admin/', '/login', '/academy/'] as $path) {
            self::assertStringContainsString("Disallow: {$path}\n", $body);
        }
        // Page joueur : noindex dans la page, mais lisible par les moteurs pour qu'ils voient ce noindex.
        self::assertStringNotContainsString('Disallow: /joueur', $body);
        self::assertStringNotContainsString("Disallow: /academy\n", $body);
        self::assertStringContainsString("Sitemap: https://athena-comspec.eu/sitemap.xml\n", $body);

        $_ENV['APP_URL'] = 'https://athena.ttrd.fr';
        $legacy = (new SeoController())->robots(new Request())->body();
        self::assertStringContainsString('Sitemap: https://athena-comspec.eu/sitemap.xml', $legacy);
    }

    // ---------- sitemap.xml ----------

    public function testSitemapListsRealPublicPagesWithDates(): void
    {
        $response = (new SeoController())->sitemap(new Request());
        $xml = simplexml_load_string($response->body());
        self::assertNotFalse($xml, 'sitemap.xml doit être un XML valide');

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[(string) $url->loc] = (string) $url->lastmod;
        }

        foreach (['/', '/a-propos', '/notre-histoire', '/atak-natif', '/sse', '/contact', '/nouveautes', '/legal/site'] as $path) {
            $loc = 'https://athena-comspec.eu' . $path;
            self::assertArrayHasKey($loc, $locs, $path);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $locs[$loc], $path . ' sans lastmod');
        }
        // Bulletins du journal : une adresse par bulletin.
        self::assertNotEmpty(array_filter(array_keys($locs), static fn (string $l): bool => str_contains($l, '/nouveautes/update/')
            || str_contains($l, '/nouveautes/spotrep/') || str_contains($l, '/nouveautes/techrep/')));

        foreach (array_keys($locs) as $loc) {
            self::assertStringStartsWith('https://athena-comspec.eu/', $loc);
            $path = (string) parse_url($loc, PHP_URL_PATH);
            self::assertFalse(Seo::isPrivatePath($path), 'adresse privée dans le sitemap : ' . $path);
            // Adresses qui redirigent : jamais dans le sitemap.
            self::assertNotContains($path, ['/cgu', '/cgv', '/cookies', '/mentions-legales', '/donnees-personnelles', '/register', '/join']);
        }
        self::assertStringContainsString('hreflang="en"', $response->body());
    }

    // ---------- Balises des pages publiques ----------

    public function testAboutPageHasCompleteMetaAndRealFigures(): void
    {
        $_SERVER['REQUEST_URI'] = '/a-propos';
        $body = $this->renderAbout([
            'founded_at' => '2024-03-15 10:00:00', 'communities' => 4, 'members' => 1234, 'operations' => 56,
            'play_hours' => 0, 'distance_km' => 789, 'academy_validated' => 0,
        ]);

        $this->assertPublicMeta($body, 'https://athena-comspec.eu/a-propos');
        self::assertSame(1, substr_count($body, '<h1'));
        self::assertStringContainsString('"@type":"AboutPage"', $body);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $body);
        self::assertStringContainsString('La plateforme aujourd’hui', $body);
        self::assertStringContainsString("1\u{202F}234", $body);
        self::assertStringContainsString('membres actifs', $body);
        // Compteur nul : jamais affiché.
        self::assertStringNotContainsString('heures de jeu', $body);
        self::assertStringContainsString('Le téléphone ATAK et les mods', $body);
        self::assertStringContainsString('L’équipe', $body);
        self::assertStringNotContainsString('cdn.tailwindcss.com', $body);
        // Images : texte alternatif et dimensions.
        preg_match_all('/<img\b[^>]*>/', $body, $imgs);
        foreach ($imgs[0] as $tag) {
            self::assertStringContainsString('alt=', $tag);
        }
        self::assertStringContainsString('width="960" height="669"', $body);
    }

    public function testAboutPageWithoutFiguresHidesTheStatsBlock(): void
    {
        $_SERVER['REQUEST_URI'] = '/a-propos';
        $body = $this->renderAbout(null);

        self::assertStringNotContainsString('La plateforme aujourd’hui', $body);
        self::assertStringContainsString('Ce qu’est ATHENA', $body);
    }

    public function testAboutPageInEnglish(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        unset($GLOBALS['__app_translator']);
        $_SERVER['REQUEST_URI'] = '/a-propos?lang=en';
        $_GET['lang'] = 'en';
        $body = $this->renderAbout(null);

        self::assertStringContainsString('<title>About ATHENA', $body);
        self::assertStringContainsString('<link rel="canonical" href="https://athena-comspec.eu/a-propos?lang=en">', $body);
        self::assertStringContainsString('What ATHENA is', $body);
    }

    public function testMainPublicPagesHaveDistinctTitlesAndDescriptions(): void
    {
        $pages = [
            '/contact' => static fn (): Response => (new SitePagesController())->contact(new Request()),
            '/sse' => static fn (): Response => (new SitePagesController())->sse(new Request()),
            '/atak-natif' => static fn (): Response => (new SitePagesController())->atakNative(new Request()),
            '/nouveautes' => static fn (): Response => (new SitePagesController())->changelog(new Request()),
            '/legal/site' => static fn (): Response => (new LegalController())->site(new Request()),
        ];
        $titles = [];
        $descriptions = [];
        foreach ($pages as $path => $render) {
            $_SERVER['REQUEST_URI'] = $path;
            $body = $render()->body();
            $this->assertPublicMeta($body, 'https://athena-comspec.eu' . $path);
            preg_match('#<title>(.*?)</title>#s', $body, $t);
            preg_match('#<meta name="description" content="([^"]*)"#', $body, $d);
            $titles[$path] = trim($t[1] ?? '');
            $descriptions[$path] = $d[1] ?? '';
            self::assertNotSame('', $titles[$path], $path);
            self::assertSame(1, substr_count($body, '<h1'), $path . ' : un seul titre principal');
        }
        self::assertSame(count($titles), count(array_unique($titles)), 'titres en double');
        self::assertSame(count($descriptions), count(array_unique($descriptions)), 'descriptions en double');
    }

    public function testLegalAliasDeclaresSingleAddress(): void
    {
        $_SERVER['REQUEST_URI'] = '/legal';
        $body = (new LegalController())->site(new Request())->body();
        self::assertStringContainsString('<link rel="canonical" href="https://athena-comspec.eu/legal/site">', $body);
    }

    public function testPrivateOrNoindexPagesStayNoindexWithoutStructuredData(): void
    {
        $_SERVER['REQUEST_URI'] = '/account/profil';
        $body = Response::view('layout.marketing', ['content' => 'site.contact', 'title' => 'Compte', 'marketingActive' => 'contact'])->body();
        self::assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $body);
        self::assertStringNotContainsString('<link rel="alternate" hreflang=', $body);
        self::assertStringNotContainsString('"@type":"WebSite"', $body);

        // Page joueur (noindex) : pas de ProfilePage déclaré.
        $_SERVER['REQUEST_URI'] = '/joueur/alpha';
        ob_start();
        $seo_robots = 'noindex,follow';
        $seo_json_ld = ['@type' => 'ProfilePage', 'name' => 'Alpha'];
        $seo_og_title = 'Alpha';
        require base_path('views/partials/seo_meta.php');
        $head = (string) ob_get_clean();
        self::assertStringContainsString('<meta name="robots" content="noindex,follow">', $head);
        self::assertStringNotContainsString('ProfilePage', $head);
    }

    public function testNotFoundPageIsNotIndexed(): void
    {
        $src = (string) file_get_contents(base_path('views/errors/404.php'));
        self::assertStringContainsString('<meta name="robots" content="noindex,follow">', $src);
        self::assertStringNotContainsString('cdn.tailwindcss.com', $src);
    }

    /** @param array<string, mixed>|null $stats */
    private function renderAbout(?array $stats): string
    {
        $response = Response::view('layout.marketing', [
            'content' => 'site.about',
            'title' => __('site.about_meta_title'),
            'meta_description' => __('site.about_meta_description'),
            'marketingActive' => 'about',
            'platformStats' => $stats,
            'jsonLdExtra' => [['@context' => 'https://schema.org', '@type' => 'AboutPage', 'name' => 'x']],
        ]);

        return $response->body();
    }

    private function assertPublicMeta(string $body, string $canonical): void
    {
        self::assertMatchesRegularExpression('#<title>[^<]{10,}</title>#', $body);
        self::assertMatchesRegularExpression('#<meta name="description" content="[^"]{50,}">#', $body);
        self::assertStringContainsString('<meta name="robots" content="index,follow', $body);
        self::assertStringContainsString('<link rel="canonical" href="' . $canonical . '">', $body);
        self::assertStringContainsString('<link rel="alternate" hreflang="fr" href="' . $canonical . '?lang=fr">', $body);
        self::assertStringContainsString('<link rel="alternate" hreflang="en" href="' . $canonical . '?lang=en">', $body);
        self::assertStringContainsString('<link rel="alternate" hreflang="x-default" href="' . $canonical . '">', $body);
        self::assertStringContainsString('<meta property="og:title"', $body);
        self::assertStringContainsString('<meta property="og:image" content="https://athena-comspec.eu/', $body);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $body);
        self::assertStringContainsString('"@type":"Organization"', $body);
        self::assertStringContainsString('"@type":"SearchAction"', $body);
    }
}
