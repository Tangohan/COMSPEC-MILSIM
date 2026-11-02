<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CommunityEventRepository;
use App\Repositories\PlatformUsageRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TenantUsageCounterRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserRepository;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanGift;
use PHPUnit\Framework\TestCase;

/** Plafond de membres : 0 = illimité, clé absente = catalogue du palier, jamais 50 codé en dur. */
final class FeatureGateMemberCapTest extends TestCase
{
    /**
     * @param array<string, mixed> $tenant
     * @param array<string, string|null> $plans slug => features_json (null = formule absente de la base)
     */
    private function gate(array $tenant, array $plans, int $members): FeatureGateService
    {
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn($tenant + ['id' => 10]);
        $repo = $this->createMock(SubscriptionPlanRepository::class);
        $repo->method('findBySlug')->willReturnCallback(static function (string $slug) use ($plans): ?array {
            $json = $plans[$slug] ?? null;

            return $json === null ? null : ['slug' => $slug, 'features_json' => $json];
        });
        $users = $this->createMock(UserRepository::class);
        $users->method('countActiveForTenant')->willReturn($members);

        return new FeatureGateService(
            $tenants,
            $repo,
            $users,
            (new \ReflectionClass(TenantUsageCounterRepository::class))->newInstanceWithoutConstructor(),
            $this->createMock(PlatformUsageRepository::class),
            $this->createMock(CommunityEventRepository::class),
            $this->createMock(TrainingCourseRepository::class),
        );
    }

    private const PRO_PLUS = ['plan_slug' => 'pro_plus', 'subscription_status' => 'active'];

    public function testProPlusWithZeroCapIsUnlimited(): void
    {
        $gate = $this->gate(self::PRO_PLUS, ['pro_plus' => '{"max_members":0}'], 142);
        self::assertSame(0, $gate->maxMembers(10));
        self::assertTrue($gate->canAddMember(10), '0 = illimité, comme le dit le formulaire des formules');
        $cap = $gate->memberCapacity(10);
        self::assertTrue($cap['unlimited']);
        self::assertSame(142, $cap['used']);
        self::assertNull($cap['remaining']);
        self::assertTrue($cap['can_add']);
    }

    public function testNegativeCapIsUnlimited(): void
    {
        $gate = $this->gate(self::PRO_PLUS, ['pro_plus' => '{"max_members":-1}'], 500);
        self::assertTrue($gate->canAddMember(10));
    }

    public function testMissingKeyFallsBackToCatalogNotFifty(): void
    {
        $catalog = (int) SubscriptionPlanFeaturesCatalog::defaultsForSlug('pro_plus')['max_members'];
        $gate = $this->gate(self::PRO_PLUS, ['pro_plus' => '{"events":true}'], 60);
        self::assertSame($catalog, $gate->maxMembers(10));
        self::assertSame($catalog > 60 || $catalog === 0, $gate->canAddMember(10), 'plus de 50 membres : pas bloqué par un défaut codé en dur');

        $missingRow = $this->gate(self::PRO_PLUS, [], 60);
        self::assertSame($catalog, $missingRow->maxMembers(10), 'formule absente de la base : catalogue');

        $broken = $this->gate(self::PRO_PLUS, ['pro_plus' => 'pas du json'], 60);
        self::assertSame($catalog, $broken->maxMembers(10));
    }

    public function testFreePlanStaysCapped(): void
    {
        $free = ['plan_slug' => 'free', 'subscription_status' => 'none'];
        $gate = $this->gate($free, ['free' => '{"max_members":10}'], 10);
        self::assertFalse($gate->canAddMember(10));
        $cap = $gate->memberCapacity(10);
        self::assertFalse($cap['unlimited']);
        self::assertSame(10, $cap['limit']);
        self::assertSame(0, $cap['remaining']);
        self::assertTrue($this->gate($free, ['free' => '{"max_members":10}'], 9)->canAddMember(10));

        $defaultFree = (int) SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')['max_members'];
        self::assertSame($defaultFree, $this->gate($free, ['free' => '{}'], 0)->maxMembers(10));
    }

    public function testPastDueKeepsTheBasePlanCap(): void
    {
        $pastDue = ['plan_slug' => 'pro_plus', 'subscription_status' => 'past_due'];
        $gate = $this->gate($pastDue, ['pro_plus' => '{"max_members":0}', 'free' => '{"max_members":10}'], 40);
        self::assertTrue($gate->canAddMember(10));

        $capped = $this->gate(['plan_slug' => 'standard', 'subscription_status' => 'past_due'], ['standard' => '{"max_members":25}'], 25);
        self::assertFalse($capped->canAddMember(10));
    }

    public function testGiftOrFounderTrialNeverDowngradesProPlus(): void
    {
        $now = strtotime('2026-11-02 12:00:00');
        $lowerGift = ['plan_slug' => 'pro_plus', 'subscription_status' => 'active', 'settings' => json_encode(['plan_gift' => ['plan_slug' => 'pro', 'ends_at' => null]])];
        self::assertSame('pro_plus', PlanGift::effectivePlanSlug($lowerGift, $now));

        $assigned = ['plan_slug' => 'pro_plus', 'subscription_status' => 'none', 'settings' => json_encode(['founder_trial_ends_at' => '2026-12-31'])];
        self::assertSame('pro_plus', PlanGift::effectivePlanSlug($assigned, $now));

        $giftOnFree = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['plan_gift' => ['plan_slug' => 'pro_plus', 'ends_at' => null]])];
        self::assertSame('pro_plus', PlanGift::effectivePlanSlug($giftOnFree, $now));
        $gate = $this->gate($giftOnFree, ['pro_plus' => '{"max_members":0}', 'free' => '{"max_members":10}'], 30);
        self::assertTrue($gate->canAddMember(10));
    }

    public function testTrainingCourseCapUsesCatalogWhenKeyMissing(): void
    {
        $free = ['plan_slug' => 'free', 'subscription_status' => 'none'];
        $expected = (int) SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')['max_training_courses'];
        self::assertSame($expected, $this->gate($free, ['free' => '{}'], 0)->maxTrainingCoursesForTenant(10));
        self::assertSame(0, $this->gate(self::PRO_PLUS, ['pro_plus' => '{"max_training_courses":0}'], 0)->maxTrainingCoursesForTenant(10));
    }

    public function testPlanFormNeverTurnsAnEmptyCapIntoUnlimited(): void
    {
        $out = SubscriptionPlanFeaturesCatalog::buildFeaturesFromForm(['feature_max_members' => ''], ['max_members' => 80], 'pro_plus');
        self::assertSame(80, $out['max_members']);
        $out = SubscriptionPlanFeaturesCatalog::buildFeaturesFromForm(['feature_max_members' => ''], [], 'free');
        self::assertSame((int) SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')['max_members'], $out['max_members']);
        $out = SubscriptionPlanFeaturesCatalog::buildFeaturesFromForm(['feature_max_members' => '0'], ['max_members' => 80], 'pro_plus');
        self::assertSame(0, $out['max_members'], 'saisie explicite de 0 = illimité');
    }
}
