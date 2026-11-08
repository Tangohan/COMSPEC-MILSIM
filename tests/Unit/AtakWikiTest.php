<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakWikiController;
use App\Core\Request;
use App\Core\Response;
use App\Support\HtmlTranslator;
use App\Support\Wiki\AtakWiki;
use App\Support\Wiki\BbcodeRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Wiki ATAK public (/wiki/atak, docs/wiki-atak.md) : une page par sujet de docs/steam/discussions en français et en
 * anglais, conversion BBCode sûre, liens internes valides, nouveautés tirées du changelog, accès depuis le site.
 */
final class AtakWikiTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_URI'] = '/wiki/atak';
        $_GET = [];
        $GLOBALS['__app_locale'] = 'fr';
        unset($GLOBALS['__app_translator']);
        AtakWiki::useRoot($this->root);
    }

    protected function tearDown(): void
    {
        AtakWiki::useRoot(null);
        HtmlTranslator::reset();
        unset($GLOBALS['__app_locale'], $GLOBALS['__app_translator']);
        $_GET = [];
    }

    public function testEveryDiscussionFileProducesAPageInBothLanguages(): void
    {
        $files = glob($this->root . '/' . AtakWiki::DISCUSSIONS_DIR . '/*.txt') ?: [];
        self::assertGreaterThan(60, count($files));
        $catalog = AtakWiki::catalog();
        $controller = new AtakWikiController();
        foreach ($files as $file) {
            $base = basename($file);
            self::assertMatchesRegularExpression('/^\d+_[a-z0-9\-]+\.txt$/i', $base);
            if (str_starts_with($base, '00_')) {
                // L'index devient le sommaire /wiki/atak/apps.
                $body = $this->body($controller->apps(new Request(), []));
                self::assertStringContainsString('Wiki ATAK', $body);
                continue;
            }
            $slug = (string) preg_replace('/^\d+_|\.txt$/', '', $base);
            self::assertArrayHasKey($slug, $catalog['pages'], $base);
            $page = $catalog['pages'][$slug];
            self::assertNotSame('autres', $page['category'], $base . ' doit figurer dans 00_INDEX.txt');
            $bodies = [];
            foreach (['fr', 'en'] as $lang) {
                $GLOBALS['__app_locale'] = $lang;
                $response = $controller->show(new Request(), ['slug' => $slug]);
                self::assertSame(200, $response->statusCode(), $base);
                $body = $response->body();
                self::assertStringContainsString('<h1 class="atw-title atw-title--page" translate="no">' . htmlspecialchars((string) $page[$lang]['title'], ENT_QUOTES, 'UTF-8') . '</h1>', $body, $base);
                $doc = AtakWiki::render($slug, $lang);
                self::assertNotSame('', $doc['html'], $base);
                self::assertDoesNotMatchRegularExpression('/\[\/?(?:h[1-3]|b|i|list|olist|url|quote|code|spoiler)\]/i', strip_tags($doc['html']), $base . ' : BBCode resté en texte');
                self::assertStringNotContainsString('===== RÉPONSE', $doc['html'], $base);
                $bodies[$lang] = $doc['html'];
            }
            self::assertNotSame($bodies['fr'], $bodies['en'], $base . ' : partie anglaise distincte');
            self::assertStringNotContainsString('<h2 id="francais">', $bodies['fr'], $base);
            self::assertStringNotContainsString('<h2 id="english">', $bodies['en'], $base);
        }
        self::assertSame(count($files) - 1, count($catalog['pages']));
    }

    public function testIndexGivesCategoriesEnglishNamesAndClassNames(): void
    {
        $pages = AtakWiki::catalog()['pages'];
        self::assertSame('Carte', $pages['carte']['fr']['title']);
        self::assertSame('Map', $pages['carte']['en']['title']);
        self::assertSame('Map', $pages['carte']['class']);
        self::assertSame('navigation', $pages['carte']['category']);
        self::assertSame('Itinéraire', $pages['itineraire']['fr']['title']);
        self::assertSame('Route', $pages['itineraire']['en']['title']);
        self::assertSame('Target Deck', $pages['recherches']['en']['title']);
        self::assertSame('help', $pages[AtakWiki::HELP_INSTALL]['kind']);
        self::assertSame('help', $pages[AtakWiki::HELP_CBA]['kind']);
        $soon = [];
        foreach (AtakWiki::catalog()['categories'] as $cat) {
            foreach ($cat['items'] as $it) {
                if ($it['soon']) {
                    $soon[] = $it['fr']['name'];
                }
            }
        }
        self::assertSame(['Brèche', 'OSINT'], $soon);
    }

    public function testBbcodeConversionIsSafe(): void
    {
        $r = new BbcodeRenderer();
        $html = $r->render(implode("\n", [
            '<script>alert(1)</script>',
            '[b]<img src=x onerror=alert(1)>[/b]',
            '[url=javascript:alert(1)]clic[/url]',
            '[url=" onmouseover="alert(1)]a[/url]',
            '[url=data:text/html;base64,AAAA]b[/url]',
            '[url]javascript:alert(2)[/url]',
            '[url=//evil.example/x]c[/url]',
            '[quote=<b onclick=1>]x[/quote]',
            '[code]<b>[/b][/code]',
            '[spoiler][i]caché',
            '[b][i]croisé[/b] reste[/i] [URGENT] [/list] [*]',
        ]))['html'];
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('javascript:', strtolower(html_entity_decode(implode(' ', $this->hrefs($html)))));
        self::assertDoesNotMatchRegularExpression('/<[a-z][^>]*\son[a-z]+=/i', $html, 'aucun attribut d’événement');
        self::assertDoesNotMatchRegularExpression('/href="(?!https?:\/\/|\/[^\/]|#)/', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<code>&lt;b&gt;[/b]</code>', $html);
        self::assertStringContainsString('[URGENT]', $html);
        $this->assertWellFormed($html);
    }

    public function testBbcodeConversionCoversSteamTags(): void
    {
        $r = new BbcodeRenderer(['h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3']);
        $out = $r->render("[h1]Titre[/h1]\n[h3]Sous-titre[/h3]\nUn [b]gras[/b], [i]italique[/i], [u]souligné[/u].\n\nAutre paragraphe\n[list]\n[*]un\n[*]deux\n[/list]\n[olist]\n[*]premier\n[/olist]\n[hr][/hr]\n[quote=Auteur]Cité[/quote]\n[table][tr][th]A[/th][th]B[/th][/tr][tr][td]1[/td][td]2[/td][/tr][/table]\n[url=https://steamcommunity.com/x]Steam[/url] [url=/wiki/atak/gps]GPS[/url]\n[spoiler]Secret[/spoiler]\n[noparse][b]brut[/b][/noparse]");
        $html = $out['html'];
        self::assertStringContainsString('<h2 id="titre">Titre</h2>', $html);
        self::assertStringContainsString('<h3 id="sous-titre">Sous-titre</h3>', $html);
        self::assertStringContainsString('<p>Un <strong>gras</strong>, <em>italique</em>, <u>souligné</u>.</p><p>Autre paragraphe</p>', $html);
        self::assertStringContainsString('<ul><li>un</li><li>deux</li></ul>', $html);
        self::assertStringContainsString('<ol><li>premier</li></ol>', $html);
        self::assertStringContainsString('<hr>', $html);
        self::assertStringContainsString('<blockquote><p class="atw-quote__by">Auteur</p><p>Cité</p></blockquote>', $html);
        self::assertStringContainsString('<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>', $html);
        self::assertStringContainsString('<a href="https://steamcommunity.com/x" target="_blank" rel="noopener noreferrer nofollow">Steam</a>', $html);
        self::assertStringContainsString('<a href="/wiki/atak/gps">GPS</a>', $html);
        self::assertStringContainsString('<details class="atw-spoiler"><summary>Afficher</summary><div><p>Secret</p></div></details>', $html);
        self::assertStringContainsString('[b]brut[/b]', $html);
        self::assertSame([['id' => 'titre', 'text' => 'Titre']], $out['toc']);
        $this->assertWellFormed($html);
    }

    public function testInternalLinksPointToExistingPagesAndAnchors(): void
    {
        $catalog = AtakWiki::catalog();
        $prefix = AtakWiki::url();
        $linked = 0;
        foreach (array_keys($catalog['pages']) as $slug) {
            foreach (['fr', 'en'] as $lang) {
                $GLOBALS['__app_locale'] = $lang;
                $body = (new AtakWikiController())->show(new Request(), ['slug' => (string) $slug])->body();
                foreach ($this->hrefs($body) as $href) {
                    if (str_starts_with($href, '#')) {
                        self::assertStringContainsString('id="' . substr($href, 1) . '"', $body, "$slug ($lang) : ancre $href");
                        continue;
                    }
                    if (!str_starts_with($href, $prefix . '/')) {
                        continue;
                    }
                    $target = rawurldecode((string) strtok(substr($href, strlen($prefix) + 1), '#?'));
                    self::assertTrue(isset($catalog['pages'][$target]) || in_array($target, AtakWiki::RESERVED, true), "$slug ($lang) : lien vers « $target »");
                }
                $linked += substr_count(AtakWiki::render((string) $slug, $lang)['html'], 'class="atw-applink"');
            }
        }
        self::assertGreaterThan(100, $linked, 'les mentions d’autres apps deviennent des liens');
        $carte = AtakWiki::render('carte', 'fr')['html'];
        self::assertStringContainsString('<a class="atw-applink" href="' . AtakWiki::url('gps') . '">GPS</a>, <a class="atw-applink" href="' . AtakWiki::url('itineraire') . '">Itinéraire</a>', $carte);
        self::assertStringNotContainsString('href="' . AtakWiki::url('carte') . '"', $carte, 'pas de lien vers soi-même');
        self::assertStringContainsString('href="' . AtakWiki::url('itineraire') . '">Route</a>', AtakWiki::render('carte', 'en')['html']);
    }

    public function testHomeNewsSearchAndUnknownPage(): void
    {
        $controller = new AtakWikiController();
        $home = $this->body($controller->home(new Request(), []));
        self::assertStringContainsString(AtakWiki::WORKSHOP_URL, $home);
        self::assertStringContainsString('3684656708', $home);
        self::assertStringContainsString('Prérequis et compatibilités', $home);
        self::assertStringContainsString('Installation et serveur dédié', $home);
        self::assertStringNotContainsString('Les apps (lanceur, App Store)', $home);
        self::assertStringContainsString('<meta name="description"', $home);

        $md = str_replace("\r\n", "\n", (string) file_get_contents($this->root . '/' . AtakWiki::CHANGELOG));
        preg_match('/^## [^\n]*ATAK natif (\d+\.\d+\.\d+)/m', $md, $m);
        $releases = AtakWiki::catalog()['releases'];
        // Une version par « Résumé joueur », sauf l'exemple entre ``` de la règle d'écriture.
        $outsideFences = (string) preg_replace('/^```.*?^```[ \t]*$/ms', '', $md);
        self::assertCount(preg_match_all('/^### Résumé joueur/m', $outsideFences), $releases);
        self::assertGreaterThan(preg_match_all('/^### Résumé joueur/m', $outsideFences), preg_match_all('/^### Résumé joueur/m', $md));
        // Une entrée « version à attribuer » peut précéder la dernière version numérotée (rouge sur main depuis #1450).
        $numbered = array_values(array_filter($releases, static fn (array $r): bool => $r['version'] !== ''));
        self::assertSame($m[1], $numbered[0]['version']);
        self::assertNotSame('', $releases[0]['en']['title']);
        self::assertNotSame($releases[0]['fr']['title'], $releases[0]['en']['title']);
        $news = $this->body($controller->news(new Request(), []));
        self::assertStringContainsString('id="v-' . str_replace('.', '-', $m[1]) . '"', $news);

        $_GET = ['q' => 'medevac'];
        $hits = AtakWiki::search('medevac', 'fr');
        self::assertSame('medical', $hits[0]['slug'] ?? null);
        self::assertSame('meteo', AtakWiki::search('METEO previsions', 'fr')[0]['slug'] ?? null, 'sans accents ni casse');
        self::assertSame([], AtakWiki::search('zzqqxx', 'fr'));
        $search = $this->body($controller->search(new Request(), []));
        self::assertStringContainsString('<mark>', $search);
        self::assertStringContainsString('noindex', $search);

        $missing = $controller->show(new Request(), ['slug' => 'nexiste-pas']);
        self::assertSame(404, $missing->statusCode());
        self::assertSame(404, $controller->show(new Request(), ['slug' => '../../.env'])->statusCode());
    }

    public function testEnglishInterfaceIsTranslated(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        $body = HtmlTranslator::translate($this->body((new AtakWikiController())->show(new Request(), ['slug' => 'carte'])));
        self::assertStringContainsString('ATAK app Map', $body);
        self::assertStringContainsString('All apps', $body);
        self::assertStringContainsString('On this page', $body);
        self::assertStringContainsString('What it is for', $body);
        self::assertStringNotContainsString('Toutes les apps', $body);
    }

    public function testRoutesAreRegisteredAndPublic(): void
    {
        $routes = (string) file_get_contents($this->root . '/routes/web.php');
        foreach (["'/wiki'", "'/wiki/atak'", "'/wiki/atak/apps'", "'/wiki/atak/nouveautes'", "'/wiki/atak/recherche'", "'/wiki/atak/{slug}'"] as $path) {
            self::assertMatchesRegularExpression('#\$router->get\(' . preg_quote($path, '#') . ', \[\\\\App\\\\Controllers\\\\Web\\\\AtakWikiController::class, \'[a-z]+\'\]\);#', $routes, $path);
        }
        // Les adresses fixes passent avant /wiki/atak/{slug}.
        self::assertLessThan(strpos($routes, "'/wiki/atak/{slug}'"), strpos($routes, "'/wiki/atak/recherche'"));
        self::assertStringContainsString('/wiki/atak', (string) file_get_contents($this->root . '/app/Controllers/Web/SeoController.php'));
    }

    public function testPublicMenusLinkToTheWiki(): void
    {
        $needle = "url('wiki/atak')";
        self::assertSame(2, substr_count((string) file_get_contents($this->root . '/views/partials/home_nav_drawer.php'), $needle), 'menu de l’accueil, connecté ou non');
        self::assertStringContainsString($needle, (string) file_get_contents($this->root . '/views/partials/home_topbar.php'));
        self::assertStringContainsString($needle, (string) file_get_contents($this->root . '/views/partials/public_site_footer.php'));
    }

    public function testAtakPagesLinkToTheWiki(): void
    {
        $needle = "url('wiki/atak')";
        self::assertStringContainsString($needle, (string) file_get_contents($this->root . '/views/site/atak_native.php'));
        self::assertStringContainsString($needle, (string) file_get_contents($this->root . '/views/atak/mod.php'));
    }

    public function testPortalHelpLinksToTheWiki(): void
    {
        $needle = "url('wiki/atak')";
        self::assertStringContainsString($needle, (string) file_get_contents($this->root . '/views/documentation/site/index.php'));
        self::assertStringContainsString($needle, (string) file_get_contents($this->root . '/views/partials/portal_footer.php'));
    }

    private function body(Response $response): string
    {
        self::assertSame(200, $response->statusCode());

        return $response->body();
    }

    /** @return list<string> */
    private function hrefs(string $html): array
    {
        preg_match_all('/href="([^"]*)"/', $html, $m);

        return array_map(static fn (string $h): string => html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m[1]);
    }

    private function assertWellFormed(string $html): void
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // htmlspecialchars n'écrit que des entités XML (&amp; &lt; &gt; &quot; &apos;) : seuls hr et br sont à fermer.
        $ok = $doc->loadXML('<root>' . str_replace(['<hr>', '<br>'], ['<hr/>', '<br/>'], $html) . '</root>');
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        self::assertTrue($ok, 'HTML bien formé : ' . $html);
    }
}
