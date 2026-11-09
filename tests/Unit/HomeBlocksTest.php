<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\BackOfficeBlocksController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\BoSheetRepository;
use App\Repositories\TenantRepository;
use App\Services\BackOffice\Home\HomeBlocks;
use App\Services\BackOffice\Home\HomeBlocksStore;
use App\Services\BackOffice\Home\HomeLayout;
use App\Support\RichTextLite;
use App\Support\VideoEmbed;
use PHPUnit\Framework\TestCase;

/**
 * Bloc personnalisé de l'accueil du back-office : assainissement du contenu (texte riche, liens, images, vidéos),
 * limites, droits (gestionnaires seulement) et isolation de la communauté.
 */
final class HomeBlocksTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::reset();
        unset($_SESSION['tenant_id'], $_SESSION['user_id']);
    }

    public function testRichTextEscapesEverythingExceptTheWhitelist(): void
    {
        $html = RichTextLite::render("## Titre\n<script>alert(1)</script> **gras** et *italique*\n\n- un\n- deux\n\n[ok](https://example.org) [mauvais](javascript:alert(1)) [data](data:text/html,x) <img src=x onerror=alert(1)>");
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('data:text', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<h4>Titre</h4>', $html);
        self::assertStringContainsString('<strong>gras</strong>', $html);
        self::assertStringContainsString('<em>italique</em>', $html);
        self::assertStringContainsString('<ul><li>un</li><li>deux</li></ul>', $html);
        self::assertStringContainsString('<a href="https://example.org" target="_blank" rel="noopener noreferrer nofollow">ok</a>', $html);
        // Seules ces balises peuvent sortir.
        $stripped = preg_replace('#</?(p|h3|h4|ul|ol|li|blockquote|strong|em|code|br|a)(\s[^>]*)?>#', '', $html) ?? '';
        self::assertStringNotContainsString('<', $stripped);
    }

    public function testRichTextLinkAttributesCannotBreakOut(): void
    {
        $html = RichTextLite::render('[x](https://a.test/"onmouseover="alert(1))');
        self::assertStringNotContainsString('onmouseover="', $html);
        $html = RichTextLite::render("[a](https://a.test/?q=1&r=2) \x01 0 \x02");
        self::assertStringContainsString('&amp;', $html);
        self::assertStringNotContainsString("\x01", $html);
    }

    public function testVideoOnlyAcceptsStrictHosts(): void
    {
        self::assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], VideoEmbed::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        self::assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], VideoEmbed::parse('https://youtu.be/dQw4w9WgXcQ'));
        self::assertSame(['provider' => 'vimeo', 'id' => '123456789'], VideoEmbed::parse('https://vimeo.com/123456789'));
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', VideoEmbed::embedUrl('https://youtu.be/dQw4w9WgXcQ'));
        self::assertSame('https://player.vimeo.com/video/123456789?dnt=1', VideoEmbed::embedUrl('https://vimeo.com/123456789'));
        foreach ([
            'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ',
            'https://evil.test/watch?v=dQw4w9WgXcQ',
            'https://user@www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com:8443/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=court',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ"onload="x',
            'javascript:alert(1)',
            'https://vimeo.com/evil',
            '',
        ] as $bad) {
            self::assertNull(VideoEmbed::parse($bad), $bad);
            self::assertSame('', VideoEmbed::canonical($bad));
        }
    }

    public function testNormalizeSanitisesEveryTypeAndDropsUnknown(): void
    {
        $raw = ['blocks' => [
            ['type' => 'inconnu', 'props' => ['title' => 'x']],
            ['type' => 'section', 'key' => 'hero'],
            ['id' => 'b_aaa', 'type' => 'article', 'props' => ['title' => str_repeat('é', 500), 'text' => "<b>x</b>\r\n\r\n\r\n\r\ny", 'image' => 'javascript:alert(1)', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)'], 'style' => ['accent' => 'violet', 'layout' => 'left', 'width' => 'full', 'inconnu' => 'x']],
            ['id' => 'b_aaa', 'type' => 'links', 'props' => ['items' => [
                ['label' => 'Bon', 'url' => 'https://example.org/a'],
                ['label' => 'Mauvais', 'url' => 'javascript:alert(1)'],
                ['label' => 'Courrier', 'url' => 'mailto:a@b.test'],
                ['label' => '', 'url' => ''],
            ]]],
            ['type' => 'video', 'props' => ['video_url' => 'https://evil.test/watch?v=dQw4w9WgXcQ']],
            ['type' => 'video', 'props' => ['video_url' => 'https://youtu.be/dQw4w9WgXcQ']],
            ['type' => 'gallery', 'props' => ['items' => [['image' => 'https://example.org/a.jpg'], ['image' => 'ftp://x/y.jpg', 'caption' => ''], ['image' => '../../etc/passwd', 'caption' => 'c']]]],
            ['type' => 'hero', 'props' => ['title' => 'T', 'button_url' => 'data:text/html,x', 'image' => 'https://example.org/h.jpg'], 'style' => ['width' => 'full']],
            ['type' => 'sheet', 'props' => ['sheet_id' => '7abc']],
            ['type' => 'sheet', 'props' => ['sheet_id' => -3]],
        ]];
        $out = HomeBlocks::normalize($raw);
        $types = array_column($out['blocks'], 'type');
        self::assertSame(['article', 'links', 'video', 'video', 'gallery', 'hero', 'sheet', 'sheet'], $types, 'Types inconnus et sections de la vitrine retirés.');
        $ids = array_column($out['blocks'], 'id');
        self::assertCount(count($ids), array_unique($ids), 'Identifiants uniques.');

        [$article, $links, $badVideo, $goodVideo, $gallery, $hero, $sheet7, $sheetNeg] = $out['blocks'];
        self::assertSame(200, mb_strlen($article['props']['title']));
        self::assertSame('', $article['props']['image']);
        self::assertSame('', $article['props']['button_url']);
        self::assertStringNotContainsString("\n\n\n", $article['props']['text']);
        self::assertSame('default', $article['style']['accent']);
        self::assertSame('left', $article['style']['layout']);
        self::assertSame('standard', $article['style']['width'], 'Pas de pleine largeur dans l’accueil.');
        self::assertArrayNotHasKey('inconnu', $article['style']);
        self::assertSame(['https://example.org/a', '', 'mailto:a@b.test'], array_column($links['props']['items'], 'url'));
        self::assertCount(3, $links['props']['items']);
        self::assertSame('', $badVideo['props']['video_url']);
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $goodVideo['props']['video_url']);
        self::assertCount(2, $gallery['props']['items'], 'Une image refusée sans légende disparaît.');
        self::assertSame('https://example.org/a.jpg', $gallery['props']['items'][0]['image']);
        self::assertSame('', $gallery['props']['items'][1]['image']);
        self::assertSame('', $hero['props']['button_url']);
        self::assertSame('standard', $hero['style']['width']);
        self::assertSame(0, $sheet7['props']['sheet_id'], 'Identifiant de tableau non numérique : refusé.');
        self::assertSame(0, $sheetNeg['props']['sheet_id']);
    }

    public function testLimitsOnBlocksItemsAndTable(): void
    {
        $blocks = [];
        for ($i = 0; $i < HomeBlocks::MAX_BLOCKS + 10; $i++) {
            $blocks[] = ['type' => 'divider', 'props' => ['title' => 'x']];
        }
        self::assertCount(HomeBlocks::MAX_BLOCKS, HomeBlocks::normalize(['blocks' => $blocks])['blocks']);

        $items = array_fill(0, 40, ['label' => 'a', 'url' => '/x']);
        $links = HomeBlocks::normalize(['blocks' => [['type' => 'links', 'props' => ['items' => $items]]]])['blocks'][0];
        self::assertCount(HomeBlocks::MAX_ITEMS, $links['props']['items']);

        $lines = [];
        for ($r = 0; $r < 40; $r++) {
            $lines[] = implode(' | ', array_merge([$r === 0 ? str_repeat('z', 300) : 'c'], array_fill(0, 11, 'c')));
        }
        $table = HomeBlocks::normalize(['blocks' => [['type' => 'table', 'props' => ['text' => implode("\n", $lines)]]]])['blocks'][0];
        $rows = HomeBlocks::tableRows($table['props']['text']);
        self::assertCount(HomeBlocks::MAX_TABLE_LINES, $rows);
        self::assertCount(HomeBlocks::MAX_TABLE_COLS, $rows[0]);
        self::assertSame(120, mb_strlen($rows[0][0]));
    }

    public function testStarterBlocksExistForEveryTypeAndAreStable(): void
    {
        foreach (array_keys(HomeBlocks::TYPES) as $type) {
            $b = HomeBlocks::starterBlock($type);
            self::assertNotNull($b, $type);
            self::assertSame($type, $b['type']);
            self::assertSame($b, HomeBlocks::normalizeBlock($b), 'Un bloc déjà normalisé ne change plus : ' . $type);
            self::assertNotEmpty(HomeBlocks::styleKeysFor($type), $type);
        }
        self::assertNull(HomeBlocks::starterBlock('script'));
    }

    public function testCustomBlockIsInTheHomeCatalog(): void
    {
        self::assertArrayHasKey('custom', HomeLayout::catalog());
        self::assertContains('custom', array_column(HomeLayout::defaults(HomeLayout::ROLE_SUPPORT), 'id'));
    }

    public function testOnlyManagersCanEditAndTenantComesFromTheSession(): void
    {
        $store = $this->createMock(HomeBlocksStore::class);
        $controller = new BackOfficeBlocksController($store, $this->createMock(TenantRepository::class));
        $request = $this->createMock(Request::class);
        $request->expects(self::any())->method('header')->willReturnCallback(static fn (string $n): ?string => $n === 'X-CSRF-Token' ? Csrf::token() : null);

        Session::set('tenant_id', 10);
        Session::set('user_id', 5);

        // Assistance seule : refus, rien n'est écrit.
        Gate::getInstance()->setPermissions(['site.support']);
        $store->expects(self::never())->method('save');
        self::assertSame(403, $controller->save($request)->statusCode());
        self::assertSame(403, $controller->renderBlock($request)->statusCode());
        self::assertSame(403, $controller->uploadImage($request)->statusCode());
    }

    public function testManagerSavesIntoTheirOwnCommunityOnly(): void
    {
        $store = $this->createMock(HomeBlocksStore::class);
        $store->expects(self::once())->method('save')->with(10, self::anything(), 5)->willReturn(['version' => 1, 'blocks' => []]);
        $controller = new BackOfficeBlocksController($store, $this->createMock(TenantRepository::class));
        $request = $this->createMock(Request::class);
        $request->expects(self::any())->method('header')->willReturnCallback(static fn (string $n): ?string => $n === 'X-CSRF-Token' ? Csrf::token() : null);
        Session::set('tenant_id', 10);
        Session::set('user_id', 5);
        Gate::getInstance()->setPermissions(['admin.organization']);
        self::assertSame(200, $controller->save($request)->statusCode());
    }

    public function testWritesRequireTheCsrfToken(): void
    {
        $store = $this->createMock(HomeBlocksStore::class);
        $store->expects(self::never())->method('save');
        $controller = new BackOfficeBlocksController($store, $this->createMock(TenantRepository::class));
        $request = $this->createMock(Request::class);
        $request->expects(self::any())->method('header')->willReturn('jeton-faux');
        Session::set('tenant_id', 10);
        Gate::getInstance()->setPermissions(['admin.organization']);
        self::assertSame(419, $controller->save($request)->statusCode());
    }

    public function testStoreIsolationAndSheetReadRights(): void
    {
        $saved = [];
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('getSettings')->willReturnCallback(static fn (int $tid): array => $tid === 10 ? ['bo_home_blocks' => ['blocks' => [['type' => 'divider', 'props' => ['title' => 'A']]]]] : []);
        $tenants->method('mergeSettings')->willReturnCallback(static function (int $tid, array $patch) use (&$saved): void {
            $saved[$tid] = $patch;
        });
        $sheets = $this->createMock(BoSheetRepository::class);
        $sheet = static fn (int $owner, bool $shared): array => [
            'id' => 7, 'tenant_id' => 10, 'owner_user_id' => $owner, 'title' => 'T', 'shared' => $shared, 'version' => 1,
            'data' => ['cols' => [['name' => 'A']], 'rows' => [['1']]], 'updated_at' => '',
        ];
        // Le dépôt filtre déjà par communauté : une autre communauté ne trouve pas le tableau.
        $sheets->method('find')->willReturnCallback(static fn (int $tid, int $id): ?array => $tid === 10 && $id === 7 ? $sheet(99, false) : null);
        $store = new HomeBlocksStore($tenants, $sheets);

        self::assertCount(1, $store->load(10)['blocks']);
        self::assertSame([], $store->load(11)['blocks'], 'Une autre communauté ne voit pas ce contenu.');
        self::assertSame([], $store->load(0)['blocks']);

        $layout = ['blocks' => [['type' => 'sheet', 'props' => ['sheet_id' => 7]]]];
        self::assertSame([], $store->sheetsFor($layout, 10, 5), 'Tableau privé d’un autre : illisible.');
        self::assertSame([], $store->sheetsFor($layout, 11, 99), 'Autre communauté : illisible.');
        self::assertArrayHasKey(7, $store->sheetsFor($layout, 10, 99), 'Son auteur le lit.');

        $store->save(10, ['blocks' => [['type' => 'divider']]], 5);
        self::assertArrayHasKey(10, $saved);
        self::assertArrayNotHasKey(11, $saved);
        $big = array_fill(0, HomeBlocks::MAX_BLOCKS, ['type' => 'article', 'props' => ['text' => str_repeat('a', 8000)]]);
        self::assertNull($store->save(10, ['blocks' => $big], 5), 'Contenu trop volumineux : refusé.');
    }
}
