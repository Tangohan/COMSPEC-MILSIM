<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SeniorityRepository;
use App\Services\Personnel\SeniorityEngine;
use App\Services\Personnel\SenioritySummaryService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SenioritySummaryServiceTest extends TestCase
{
    public function testLabelFromStartDateIsEmptyWhenMissing(): void
    {
        $svc = $this->service();
        self::assertSame(['label' => '', 'days' => 0], $svc->labelFromStartDate(''));
        self::assertSame(['label' => '', 'days' => 0], $svc->labelFromStartDate('0000-00-00'));
    }

    public function testLabelFromStartDateCountsElapsedTime(): void
    {
        $svc = $this->service();
        $start = (new DateTimeImmutable('today'))->modify('-400 days')->format('Y-m-d');
        $pack = $svc->labelFromStartDate($start);
        self::assertGreaterThanOrEqual(399, $pack['days']);
        self::assertLessThanOrEqual(401, $pack['days']);
        self::assertStringContainsString('an', $pack['label']);
    }

    public function testDashboardLabelsPreferEarlierPrePlatformDate(): void
    {
        $repo = $this->createMock(SeniorityRepository::class);
        $repo->method('schemaReady')->willReturn(true);
        $repo->method('findDefinitionIdByTenantAndCode')->willReturnCallback(
            static function (int $tenantId, string $code): ?int {
                return match ($code) {
                    'tenure_community' => 1,
                    'tenure_pre_platform' => 2,
                    default => null,
                };
            }
        );
        $repo->method('earliestStartByUsersForDefinition')->willReturnCallback(
            static function (int $definitionId, array $userIds): array {
                if ($definitionId === 1) {
                    return [7 => '2024-01-01'];
                }

                return [7 => '2018-06-15'];
            }
        );
        $svc = new SenioritySummaryService($repo, new SeniorityEngine());
        $out = $svc->dashboardLabelsByUsers(1, [7], []);
        self::assertArrayHasKey(7, $out);
        self::assertGreaterThan((int) $out[7]['community_days'], (int) $out[7]['days']);
        self::assertSame($out[7]['days'], $out[7]['pre_platform_days']);
    }

    public function testPersonnelSummaryPrefersCommunityOverLongerPrePlatform(): void
    {
        $repo = $this->createMock(SeniorityRepository::class);
        $repo->method('schemaReady')->willReturn(true);
        $repo->method('listVisibleDefinitionsForTenant')->willReturn([
            ['id' => 1, 'code' => 'tenure_org_pre_platform', 'label' => 'Création de l’entité (avant la plateforme)', 'calc_mode' => 'from_start'],
            ['id' => 2, 'code' => 'tenure_community', 'label' => 'Ancienneté dans la communauté', 'calc_mode' => 'from_start'],
            ['id' => 3, 'code' => 'tenure_pre_platform', 'label' => 'Ancienneté antérieure à la plateforme', 'calc_mode' => 'from_start'],
            ['id' => 4, 'code' => 'tenure_service', 'label' => 'Ancienneté de service cumulée', 'calc_mode' => 'from_start'],
        ]);
        $repo->method('listPeriodsForUserAndDefinition')->willReturnCallback(
            static function (int $userId, int $definitionId): array {
                return match ($definitionId) {
                    1 => [],
                    2 => [['start_date' => (new DateTimeImmutable('today'))->modify('-159 days')->format('Y-m-d')]],
                    3 => [['start_date' => (new DateTimeImmutable('today'))->modify('-225 days')->format('Y-m-d')]],
                    4 => [['start_date' => (new DateTimeImmutable('today'))->modify('-159 days')->format('Y-m-d')]],
                    default => [],
                };
            }
        );
        $svc = new SenioritySummaryService($repo, new SeniorityEngine());
        $out = $svc->personnelSenioritySummary(1, 7);
        self::assertNotNull($out['global']);
        self::assertSame('tenure_community', $out['global']['basis_code']);
        self::assertSame('Ancienneté dans la communauté', $out['global']['basis_label']);
        $labels = array_map(static fn (array $r): string => (string) $r['label'], $out['detail']);
        self::assertContains('Ancienneté antérieure à la plateforme', $labels);
        self::assertContains('Ancienneté de service cumulée', $labels);
        self::assertNotContains('Création de l’entité (avant la plateforme)', $labels);
    }

    private function service(): SenioritySummaryService
    {
        return new SenioritySummaryService(
            $this->createStub(SeniorityRepository::class),
            new SeniorityEngine()
        );
    }
}
