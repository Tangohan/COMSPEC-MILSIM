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

    private function service(): SenioritySummaryService
    {
        return new SenioritySummaryService(
            $this->createStub(SeniorityRepository::class),
            new SeniorityEngine()
        );
    }
}
