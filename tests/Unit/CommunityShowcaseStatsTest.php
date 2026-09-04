<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\TenantCommunityProfileService;
use PHPUnit\Framework\TestCase;

final class CommunityShowcaseStatsTest extends TestCase
{
    public function testStaleManualZeroDoesNotHideRenderedPublicUnits(): void
    {
        $viewModel = TenantCommunityProfileService::getShowcaseViewModel(
            [
                'public_stats_mode' => 'manual',
                'public_stats_manual' => ['unites' => '0'],
            ],
            ['unites_public' => '12'],
            [],
        );

        self::assertSame('12', $viewModel['stats']['unites']);
    }

    public function testNonZeroManualUnitFigureRemainsAnIntentionalOverride(): void
    {
        $viewModel = TenantCommunityProfileService::getShowcaseViewModel(
            [
                'public_stats_mode' => 'manual',
                'public_stats_manual' => ['unites' => '7'],
            ],
            ['unites_public' => '12'],
            [],
        );

        self::assertSame('7', $viewModel['stats']['unites']);
    }
}
