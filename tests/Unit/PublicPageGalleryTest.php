<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\PublicPageBuilder;
use PHPUnit\Framework\TestCase;

/** Vitrine : galerie de la présentation gérée dans l’éditeur et corrections du mode jour. */
final class PublicPageGalleryTest extends TestCase
{
    /** @return array<string, mixed> */
    private function aboutProps(array $gallery): array
    {
        $layout = PublicPageBuilder::normalize(['blocks' => [['id' => 's_about', 'type' => 'section', 'key' => 'about', 'props' => ['gallery' => $gallery]]]]);
        foreach ($layout['blocks'] as $b) {
            if (($b['key'] ?? '') === 'about') {
                return $b['props'];
            }
        }

        return [];
    }

    public function testGalleryKeepsValidImagesInOrderAndCapsAtThree(): void
    {
        $props = $this->aboutProps([
            ['image' => 'assets/img/communities/spear/page/a.jpg', 'alt' => 'Patrouille', 'caption' => "Opé\nKestrel"],
            ['image' => 'javascript:alert(1)', 'alt' => 'x'],
            ['image' => 'https://cdn.example.org/b.webp', 'alt' => ''],
            ['image' => '/assets/img/communities/spear/page/c.png'],
            ['image' => 'assets/img/communities/spear/page/d.png'],
        ]);
        self::assertCount(PublicPageBuilder::MAX_GALLERY, $props['gallery']);
        self::assertSame('assets/img/communities/spear/page/a.jpg', $props['gallery'][0]['image']);
        self::assertSame('Opé Kestrel', $props['gallery'][0]['caption'], 'légende sur une ligne');
        self::assertSame('https://cdn.example.org/b.webp', $props['gallery'][1]['image'], 'lien javascript: rejeté');
        self::assertSame('', $props['gallery'][1]['alt']);
    }

    public function testEmptyOrInvalidGalleryIsDropped(): void
    {
        self::assertArrayNotHasKey('gallery', $this->aboutProps([]));
        self::assertArrayNotHasKey('gallery', $this->aboutProps([['image' => '../../etc/passwd'], 'nope']));
    }

    public function testShowcaseNeverRendersEmptySlotsOutsideEditMode(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/community/show_showcase.php');
        $css = (string) file_get_contents($root . '/public/assets/css/community-vitrine-day.css');
        $js = (string) file_get_contents($root . '/public/assets/js/community-page-editor.js');

        self::assertStringContainsString('$galSlots = $pbEditing ? 3 : count($gal);', $view);
        self::assertStringContainsString('cl-about--solo', $view);
        self::assertStringNotContainsString("? 'Galerie' : '—'", $view);
        self::assertStringContainsString("'community-vitrine-day'", $view);
        self::assertStringContainsString('class="cl-doctrine"', $view);
        self::assertStringContainsString('data-cl-img="image"', $view);
        // Cartes « Features & accès » lisibles en clair, valeurs d’origine conservées en sombre.
        self::assertStringContainsString('.cl-feature-card__title { color: var(--cl-ink); }', $css);
        self::assertStringContainsString('.cl-vitrine.pb-dark) .cl-feature-card__title { color: #f4f7f8; }', $css);
        self::assertStringContainsString('function galleryPanel(b, el, focusImage)', $js);
    }
}
