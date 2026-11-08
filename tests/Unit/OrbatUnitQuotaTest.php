<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\OrbatUnitQuota;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog as Catalog;
use PHPUnit\Framework\TestCase;

final class OrbatUnitQuotaTest extends TestCase
{
    public function testRemainingUnits(): void
    {
        self::assertNull(OrbatUnitQuota::remainingFor(0, 500));
        self::assertSame(2, OrbatUnitQuota::remainingFor(5, 3));
        self::assertSame(0, OrbatUnitQuota::remainingFor(5, 13), 'Au-dessus du plafond : rien de retiré, plus de création.');
    }

    public function testPlanDefaults(): void
    {
        $plans = Catalog::defaultsByPlanSlug();

        self::assertSame([10, 20, 60, 0], array_map(
            static fn (string $slug): int => (int) $plans[$slug]['max_orbat_units'],
            ['free', 'standard', 'pro', 'pro_plus']
        ));
        self::assertSame([false, false, true, true], array_map(
            static fn (string $slug): bool => (bool) $plans[$slug]['equipment_game_sync'],
            ['free', 'standard', 'pro', 'pro_plus']
        ));
    }
}
