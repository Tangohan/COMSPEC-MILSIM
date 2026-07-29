<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use PHPUnit\Framework\TestCase;

/** Aligné sur {@see SubscriptionPlanFeaturesCatalog} et le seed community_platform. */
final class PlanFeaturesSchemaTest extends TestCase
{
    public function testProPlusFeaturesAllowIntegrationsAndUnlimitedCourses(): void
    {
        $f = SubscriptionPlanFeaturesCatalog::defaultsForSlug('pro_plus');
        self::assertTrue($f['advanced_integrations']);
        self::assertSame(0, (int) $f['max_training_courses']);
        self::assertTrue($f['cooperation']);
        self::assertTrue($f['courrier']);
        self::assertTrue($f['recruitment']);
    }

    public function testStandardFeaturesCapCoursesAndDisallowIntegrations(): void
    {
        $f = SubscriptionPlanFeaturesCatalog::defaultsForSlug('standard');
        self::assertSame(25, (int) $f['max_training_courses']);
        self::assertFalse($f['advanced_integrations']);
        self::assertTrue($f['events']);
        self::assertTrue($f['atak']);
        self::assertTrue($f['courrier']);
        self::assertFalse($f['cooperation']);
        self::assertFalse($f['analytics']);
    }

    public function testFreePlanKeepsCoreModulesWithoutPremium(): void
    {
        $f = SubscriptionPlanFeaturesCatalog::defaultsForSlug('free');
        self::assertTrue($f['forum']);
        self::assertTrue($f['messages']);
        self::assertTrue($f['recruitment']);
        self::assertFalse($f['atak']);
        self::assertFalse($f['courrier']);
        self::assertFalse($f['cooperation']);
        self::assertSame(10, (int) $f['max_members']);
    }

    public function testCatalogDefinitionsCoverAllBoolAndIntKeys(): void
    {
        $defs = SubscriptionPlanFeaturesCatalog::definitions();
        foreach (SubscriptionPlanFeaturesCatalog::BOOL_FEATURES as $key) {
            self::assertArrayHasKey($key, $defs);
            self::assertSame('bool', $defs[$key]['type']);
        }
        foreach (SubscriptionPlanFeaturesCatalog::INT_FEATURES as $key) {
            self::assertArrayHasKey($key, $defs);
            self::assertSame('int', $defs[$key]['type']);
        }
    }
}
