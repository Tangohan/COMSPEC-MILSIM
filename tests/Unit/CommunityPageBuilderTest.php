<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\PublicPageBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Vitrine personnalisable : ce qui arrive du navigateur est filtré, le brouillon reste privé jusqu’à la
 * publication, et l’édition en place reste réservée aux gestionnaires de la communauté.
 */
final class CommunityPageBuilderTest extends TestCase
{
    public function testDefaultsKeepEveryNativeSectionInOrder(): void
    {
        $layout = PublicPageBuilder::resolve([]);
        $keys = array_column($layout['blocks'], 'key');
        self::assertSame(array_keys(PublicPageBuilder::SECTIONS), $keys);
        self::assertSame('solid', $layout['header']['style']);
        self::assertTrue($layout['header']['show_back']);
        self::assertSame('light', $layout['theme']);
    }

    public function testNormalizeDropsUnknownTypesAndRestoresMissingSections(): void
    {
        $layout = PublicPageBuilder::normalize([
            'theme' => 'neon',
            'blocks' => [
                ['type' => 'section', 'key' => 'faq'],
                ['type' => 'script', 'props' => ['text' => 'x']],
                ['type' => 'section', 'key' => 'faq'],
                ['type' => 'section', 'key' => 'hero', 'hidden' => true],
                ['type' => 'tiles', 'id' => 'b_ok1', 'props' => ['title' => 'Nos métiers', 'items' => [['title' => 'A', 'url' => 'javascript:alert(1)', 'image' => 'https://example.org/a.jpg']]]],
            ],
        ]);
        self::assertSame('light', $layout['theme']);
        $types = array_map(static fn (array $b): string => $b['type'] === 'section' ? $b['key'] : $b['type'], $layout['blocks']);
        // L’ordre choisi est gardé ; les sections absentes reprennent leur place par défaut autour.
        $pos = array_flip($types);
        self::assertTrue($pos['faq'] < $pos['hero'] && $pos['hero'] < $pos['tiles']);
        self::assertNotContains('script', $types);
        self::assertSame(1, count(array_keys($types, 'faq', true)));
        foreach (array_keys(PublicPageBuilder::SECTIONS) as $k) {
            self::assertContains($k, $types);
        }
        $tiles = $layout['blocks'][$pos['tiles']];
        self::assertSame('', $tiles['props']['items'][0]['url']);
        self::assertSame('https://example.org/a.jpg', $tiles['props']['items'][0]['image']);
        self::assertSame('3', $tiles['style']['columns']);
        self::assertTrue($layout['blocks'][$pos['hero']]['hidden']);
    }

    public function testLinksAndImagesAreFiltered(): void
    {
        self::assertSame('#contact', PublicPageBuilder::link('#contact'));
        self::assertSame('https://discord.gg/abc', PublicPageBuilder::link('https://discord.gg/abc'));
        self::assertSame('/c/demo/forum', PublicPageBuilder::link('/c/demo/forum'));
        self::assertSame('', PublicPageBuilder::link('javascript:alert(1)'));
        self::assertSame('', PublicPageBuilder::link('//evil.example'));
        self::assertSame('', PublicPageBuilder::link('https://x" onmouseover="y'));
        self::assertSame('assets/img/communities/demo/page/a.jpg', PublicPageBuilder::image('assets/img/communities/demo/page/a.jpg'));
        self::assertSame('', PublicPageBuilder::image('assets/../../.env'));
        self::assertSame('', PublicPageBuilder::image('data:image/svg+xml;base64,AAA'));
    }

    public function testStyleValuesOutsideTheListFallBackToDefault(): void
    {
        $layout = PublicPageBuilder::normalize(['blocks' => [
            ['type' => 'infobox', 'style' => ['tone' => 'danger', 'icon' => '<svg>', 'size' => 'huge']],
        ]]);
        $box = $layout['blocks'][0];
        self::assertSame('danger', $box['style']['tone']);
        self::assertSame('info', $box['style']['icon']);
        self::assertSame('standard', $box['style']['size']);
        self::assertArrayNotHasKey('columns', $box['style']);
    }

    public function testDraftFieldsOnlyAcceptKnownKeysAndPublishMovesThemIntoSettings(): void
    {
        $draft = PublicPageBuilder::normalizeDraft([
            'blocks' => [['type' => 'cta', 'props' => ['title' => 'Rejoignez-nous']]],
            'fields' => [
                'public_hero_headline' => "  Le 2e bataillon  ",
                'registration_mode' => 'open',
                'public_faq' => [['q' => 'Âge ?', 'a' => '16 ans'], ['q' => '', 'a' => ''], 'x'],
            ],
        ], 7);
        self::assertSame('Le 2e bataillon', $draft['fields']['public_hero_headline']);
        self::assertArrayNotHasKey('registration_mode', $draft['fields']);
        self::assertSame([['q' => 'Âge ?', 'a' => '16 ans']], $draft['fields']['public_faq']);
        self::assertSame(7, $draft['updated_by']);

        $community = ['public_hero_headline' => 'Ancien', 'public_page_builder_draft' => $draft];
        self::assertTrue(PublicPageBuilder::hasDraft($community));
        self::assertNotContains('cta', array_column(PublicPageBuilder::resolve($community)['blocks'], 'type'));
        self::assertContains('cta', array_column(PublicPageBuilder::resolve($community, true)['blocks'], 'type'));
        self::assertSame('Le 2e bataillon', PublicPageBuilder::applyDraftFields($community)['public_hero_headline']);

        $published = PublicPageBuilder::publish($community);
        self::assertArrayNotHasKey('public_page_builder_draft', $published);
        self::assertSame('Le 2e bataillon', $published['public_hero_headline']);
        $types = array_column($published['public_page_builder']['blocks'], 'type');
        self::assertContains('cta', $types);
        self::assertArrayNotHasKey('fields', $published['public_page_builder']);
    }

    public function testStarterBlocksExistForEveryLibraryType(): void
    {
        foreach (array_keys(PublicPageBuilder::BLOCK_TYPES) as $type) {
            $b = PublicPageBuilder::starterBlock($type);
            self::assertIsArray($b, $type);
            self::assertSame($type, $b['type']);
            self::assertNotSame('', $b['props']['title'], $type);
        }
        self::assertNull(PublicPageBuilder::starterBlock('section'));
        self::assertSame('full', PublicPageBuilder::starterBlock('hero')['style']['width']);
    }

    public function testEditorIsWiredBehindManagerRightsAndCsrf(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['draft', 'publish', 'discard', 'block', 'image'] as $ep) {
            self::assertStringContainsString("'/c/{slug}/page-editor/" . $ep . "'", $routes);
        }
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/CommunityPageEditorController.php');
        self::assertStringContainsString('Csrf::validate', $ctrl);
        self::assertStringContainsString('->canEdit(', $ctrl);
        $access = (string) file_get_contents($root . '/app/Services/Community/CommunityPageEditorAccess.php');
        self::assertStringContainsString("allows('admin.organization')", $access);
        self::assertStringContainsString("(int) (\$user['tenant_id'] ?? 0) !== \$tenantId", $access);
        $show = (string) file_get_contents($root . '/app/Controllers/Web/CommunityController.php');
        self::assertStringContainsString('$pageEditorCanEdit && (string) $request->query(\'edition\'', $show);
        $view = (string) file_get_contents($root . '/views/community/show_showcase.php');
        self::assertStringContainsString("if (\$pbCanEdit) { require base_path('views/community/partials/page_editor.php'); }", $view);
        self::assertStringContainsString('data-cl-nav', $view);
    }
}
