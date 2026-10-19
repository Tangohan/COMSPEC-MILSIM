<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CommunityCardDecorator;
use PHPUnit\Framework\TestCase;

final class CommunityCardDecoratorTest extends TestCase
{
    public function testBrandingAndStatsAreMergedPerCommunity(): void
    {
        $rows = [
            ['id' => 1, 'slug' => 'orion', 'logo_url' => 'https://cdn.example/old.png'],
            ['id' => 2, 'slug' => 'loup', 'logo_url' => ''],
        ];
        $branding = [1 => ['banner_url' => 'https://cdn.example/banner.jpg', 'logo_url' => '/uploads/logo.png', 'primary_color' => '#112233', 'accent_color' => '#C9A06B']];
        $stats = ['orion' => ['members' => 42, 'activity' => 80.6, 'units' => 18, 'ops_per_month' => 6.33, 'upcoming_ops' => 2, 'rating' => 4.66, 'rating_count' => 12, 'age_days' => 230]];
        $out = CommunityCardDecorator::decorate($rows, $branding, $stats, static fn (string $slug): ?string => $slug === 'loup' ? '/public/assets/img/communities/loup-cover.jpg' : null);

        self::assertSame('https://cdn.example/banner.jpg', $out[0]['card_banner']);
        self::assertSame('#c9a06b', $out[0]['card_accent']);
        self::assertSame('/uploads/logo.png', $out[0]['card_logo']);
        self::assertSame(42, $out[0]['card_members']);
        self::assertSame(81, $out[0]['card_activity']);
        self::assertSame(18, $out[0]['card_units']);
        self::assertSame(6.3, $out[0]['card_ops']);
        self::assertSame(4.7, $out[0]['card_rating']);
        self::assertSame(230, $out[0]['card_age_days']);

        // Sans marque ni chiffres : couverture déposée, pas de couleur, zéros.
        self::assertSame('/public/assets/img/communities/loup-cover.jpg', $out[1]['card_banner']);
        self::assertNull($out[1]['card_accent']);
        self::assertSame('', $out[1]['card_logo']);
        self::assertSame(0, $out[1]['card_members']);
        self::assertNull($out[1]['card_activity']);
        self::assertNull($out[1]['card_rating']);
    }

    public function testUnsafeValuesAreDropped(): void
    {
        $out = CommunityCardDecorator::decorate(
            [['id' => 3, 'slug' => 'x', 'logo_url' => 'javascript:alert(1)']],
            [3 => ['banner_url' => '//evil.example/a.jpg', 'logo_url' => 'data:image/png;base64,AAA', 'primary_color' => 'red;background:url(x)', 'accent_color' => '']],
            []
        );
        self::assertSame('', $out[0]['card_banner']);
        self::assertSame('', $out[0]['card_logo']);
        self::assertNull($out[0]['card_accent']);
    }

    public function testHelpers(): void
    {
        self::assertSame('#abc', CommunityCardDecorator::color('#ABC'));
        self::assertNull(CommunityCardDecorator::color('blue'));
        self::assertSame('', CommunityCardDecorator::age(null));
        self::assertSame('12 jours', CommunityCardDecorator::age(12));
        self::assertSame('7 mois', CommunityCardDecorator::age(230));
        self::assertSame('2 ans', CommunityCardDecorator::age(800));
        self::assertSame('6,3', CommunityCardDecorator::compact(6.3));
        self::assertSame('4', CommunityCardDecorator::compact(4.0));
        self::assertSame('1,2 k', CommunityCardDecorator::compact(1234));
    }

    public function testRegistryUsesTheDecoratedCards(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/CommunityController.php');
        self::assertStringContainsString('CommunityCardDecorator::decorate', $ctrl);
        $view = (string) file_get_contents($root . '/views/community/registry.php');
        foreach (['cr-stats', 'card_members', 'card_banner', 'cr-card--spot', 'data-registry-copy', 'data-cr-count-to'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        // Les filtres de l’annuaire restent branchés sur les mêmes attributs.
        foreach (['data-cr-open', 'data-cr-pinned', 'data-cr-search', 'data-cr-lang'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
    }
}
