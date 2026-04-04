<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\TenantUsageCounterRepository;
use PHPUnit\Framework\TestCase;

final class TenantUsageCounterPeriodTest extends TestCase
{
    public function testMonthlyPeriodStartIsFirstDayOfMonthUtc(): void
    {
        $p = TenantUsageCounterRepository::periodStartForReset('monthly');
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-01$/', $p);
    }

    public function testDailyPeriodStartIsYmd(): void
    {
        $p = TenantUsageCounterRepository::periodStartForReset('daily');
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $p);
    }
}
