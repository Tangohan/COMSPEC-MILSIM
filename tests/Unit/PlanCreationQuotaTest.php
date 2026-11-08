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
use App\Services\Billing\PlanCreationQuota;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog as Catalog;
use App\Services\Platform\FeatureGateService;
use App\Support\HtmlTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Plafonds de création de la formule Gratuit (offres de recrutement, mini-articles, modèles de message) : laissés
 * au propriétaire de la plateforme, illimités tant qu'une formule enregistrée ne les définit pas.
 */
final class PlanCreationQuotaTest extends TestCase
{
    private const KEYS = [PlanCreationQuota::OFFERS, PlanCreationQuota::ARTICLES, PlanCreationQuota::TEMPLATES];

    private const FREE = ['plan_slug' => 'free', 'subscription_status' => 'none'];

    /**
     * @param array<string, mixed> $tenant
     * @param array<string, string|null> $plans slug => features_json
     */
    private function gate(array $tenant, array $plans): FeatureGateService
    {
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn($tenant + ['id' => 10]);
        $repo = $this->createMock(SubscriptionPlanRepository::class);
        $repo->method('findBySlug')->willReturnCallback(static function (string $slug) use ($plans): ?array {
            $json = $plans[$slug] ?? null;

            return $json === null ? null : ['slug' => $slug, 'features_json' => $json];
        });

        return new FeatureGateService(
            $tenants,
            $repo,
            $this->createMock(UserRepository::class),
            (new \ReflectionClass(TenantUsageCounterRepository::class))->newInstanceWithoutConstructor(),
            $this->createMock(PlatformUsageRepository::class),
            $this->createMock(CommunityEventRepository::class),
            $this->createMock(TrainingCourseRepository::class),
        );
    }

    public function testEachRestrictionHasItsOwnKeyInTheCatalog(): void
    {
        foreach (self::KEYS as $key) {
            self::assertContains($key, Catalog::INT_FEATURES, $key);
            self::assertContains($key, Catalog::OWNER_DECIDES, $key);
            self::assertTrue(Catalog::definitions()[$key]['read'], $key);
        }
        self::assertSame(self::KEYS, array_values(array_unique(self::KEYS)));
    }

    public function testCatalogDefaultsAreCappedForFreeAndOpenForProPlus(): void
    {
        $plans = Catalog::defaultsByPlanSlug();
        foreach (self::KEYS as $key) {
            self::assertGreaterThan(0, $plans['free'][$key], $key . ' : gratuit plafonné');
            self::assertGreaterThan($plans['free'][$key], $plans['standard'][$key], $key . ' : Standard plus large');
            self::assertSame(0, $plans['pro'][$key], $key);
            self::assertSame(0, $plans['pro_plus'][$key], $key);
        }
    }

    public function testExistingPlanWithoutTheKeyStaysUnlimited(): void
    {
        foreach (self::KEYS as $key) {
            self::assertSame(0, $this->gate(self::FREE, ['free' => '{"max_members":10}'])->intWhenDefined(10, $key), $key . ' : clé absente');
            self::assertSame(0, $this->gate(self::FREE, [])->intWhenDefined(10, $key), $key . ' : formule absente de la base');
            self::assertSame(0, $this->gate(self::FREE, ['free' => 'pas du json'])->intWhenDefined(10, $key), $key . ' : JSON illisible');
        }
    }

    public function testValueSavedByTheOwnerIsApplied(): void
    {
        foreach (self::KEYS as $key) {
            self::assertSame(4, $this->gate(self::FREE, ['free' => '{"' . $key . '":4}'])->intWhenDefined(10, $key));
            self::assertSame(0, $this->gate(self::FREE, ['free' => '{"' . $key . '":0}'])->intWhenDefined(10, $key), '0 = illimité');
            self::assertSame(0, $this->gate(self::FREE, ['free' => '{"' . $key . '":-3}'])->intWhenDefined(10, $key), 'négatif = illimité');
        }
    }

    public function testPastDueFallsBackToTheFreePlan(): void
    {
        $pastDue = ['plan_slug' => 'pro', 'subscription_status' => 'past_due'];
        $gate = $this->gate($pastDue, ['pro' => '{"max_mini_articles":0}', 'free' => '{"max_mini_articles":5}']);
        self::assertSame(5, $gate->intWhenDefined(10, PlanCreationQuota::ARTICLES));
    }

    public function testRemainingNeverRemovesWhatExists(): void
    {
        self::assertNull(PlanCreationQuota::remainingFor(0, 500));
        self::assertSame(2, PlanCreationQuota::remainingFor(5, 3));
        self::assertSame(0, PlanCreationQuota::remainingFor(3, 9), 'au-dessus du plafond : plus de création, rien de retiré');
    }

    public function testPlanValuesShowUnlimitedUntilTheOwnerSavesAndSavingKeepsIt(): void
    {
        $pv = Catalog::planValues(['slug' => 'free', 'features_json' => '{"max_members":10}']);
        foreach (self::KEYS as $key) {
            self::assertSame(0, $pv['values'][$key], $key . ' : affiché illimité');
            self::assertContains($key, $pv['defaulted'], $key);
        }
        // Champ vidé dans le formulaire : jamais le défaut du catalogue imposé par accident.
        $saved = Catalog::buildFeaturesFromForm([], ['max_members' => 10], 'free');
        foreach (self::KEYS as $key) {
            self::assertSame(0, $saved[$key], $key);
        }
        $set = Catalog::buildFeaturesFromForm(['feature_' . PlanCreationQuota::OFFERS => '3'], [], 'free');
        self::assertSame(3, $set[PlanCreationQuota::OFFERS]);
    }

    public function testStartupMigrationNeverImposesTheNewCaps(): void
    {
        foreach (Catalog::defaultsToMergeIntoExisting() as $slug => $defaults) {
            foreach (self::KEYS as $key) {
                self::assertArrayNotHasKey($key, $defaults, $slug . ' : ' . $key);
            }
            self::assertArrayHasKey('max_members', $defaults);
        }
        $boot = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/community_platform_migration.php');
        self::assertStringContainsString('defaultsToMergeIntoExisting()', $boot);
    }

    public function testMessagesInviteToUpgradeAndNameTheLimit(): void
    {
        foreach (self::KEYS as $key) {
            $msg = PlanCreationQuota::messageFor($key, 7);
            self::assertStringContainsString('7', $msg);
            self::assertStringContainsString('formule supérieure', $msg);
        }
    }

    public function testMessagesAreTranslated(): void
    {
        foreach (self::KEYS as $key) {
            $en = HtmlTranslator::translateText(PlanCreationQuota::messageFor($key, 7));
            self::assertStringStartsWith('Your plan is limited to 7', $en, $key);
        }
    }

    public function testControllersCheckTheQuotaBeforeCreating(): void
    {
        $root = dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/';
        $expect = [
            'RecruitmentOffersController.php' => ['OFFERS', 'openings->create('],
            'TenantMiniArticlesController.php' => ['ARTICLES', 'articles->insert('],
            'TenantCommunicationsController.php' => ['TEMPLATES', 'templateRepository->create('],
        ];
        foreach ($expect as $file => [$const, $write]) {
            $src = (string) file_get_contents($root . $file);
            $check = strpos($src, 'PlanCreationQuota::blockMessage($tenantId, PlanCreationQuota::' . $const);
            $insert = strpos($src, $write);
            self::assertNotFalse($check, $file);
            self::assertNotFalse($insert, $file);
            self::assertLessThan($insert, $check, $file . ' : contrôle avant écriture');
        }
    }
}
