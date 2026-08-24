<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ChangelogCatalog;
use PHPUnit\Framework\TestCase;

final class ChangelogCatalogTest extends TestCase
{
    public function testHydrateKeepsPublishedReleasesAndHonestPipeline(): void
    {
        $catalog = ChangelogCatalog::hydrate(['communities_total' => 0]);

        self::assertCount(6, $catalog['releases']);
        self::assertSame('2026-08-planning', $catalog['featured']['id']);
        self::assertTrue($catalog['featured']['featured']);
        self::assertSame(['c2', 'atak'], $catalog['featured']['categories']);
        self::assertContains('command', $catalog['featured']['filter_groups']);
        self::assertContains('atak', $catalog['featured']['filter_groups']);
        self::assertNotSame('', $catalog['featured']['title']);
        self::assertNotSame('', $catalog['featured']['why']);
        self::assertCount(6, $catalog['featured']['features']);
        self::assertSame('assets/images/night-team.jpg', $catalog['featured']['image']);

        $ids = array_column($catalog['releases'], 'id');
        self::assertSame([
            '2026-08-planning',
            '2026-08-intel',
            '2026-07-sse',
            '2026-07-plans',
            '2026-04-proplus',
            '2025-12-tenants',
        ], $ids);

        foreach ($catalog['releases'] as $release) {
            self::assertSame(ChangelogCatalog::STATUS_RELEASED, $release['status']);
            self::assertSame('public', $release['visibility']);
            self::assertArrayHasKey('gallery', $release);
            self::assertArrayHasKey('video', $release);
            self::assertArrayHasKey('before_after', $release);
            self::assertArrayHasKey('availability', $release);
        }

        self::assertSame([2026, 2025], $catalog['years']);
        self::assertCount(6, $catalog['modules']);
        self::assertCount(6, $catalog['pipeline']);
        foreach ($catalog['pipeline'] as $item) {
            self::assertNotSame(ChangelogCatalog::STATUS_RELEASED, $item['status']);
        }

        $statLabels = array_column($catalog['stats'], 'label');
        self::assertNotContains(__('site.cl_stat_communities'), $statLabels);
    }

    public function testCommunityCountAppearsOnlyWhenPositive(): void
    {
        $catalog = ChangelogCatalog::hydrate(['communities_total' => 4]);
        self::assertSame('4', $catalog['stats'][0]['value']);
        self::assertSame(__('site.cl_stat_communities'), $catalog['stats'][0]['label']);
    }
}
