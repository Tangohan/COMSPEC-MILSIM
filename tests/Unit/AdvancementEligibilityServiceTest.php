<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Advancement\AdvancementEligibilityService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdvancementEligibilityServiceTest extends TestCase
{
    private array $current = [
        'rank_order' => 10,
        'obtained_at' => '2024-01-15',
    ];

    private array $target = [
        'rank_order' => 11,
        'min_time_in_previous_grade_months' => 24,
    ];

    public function testRefusesInsufficientTimeInGrade(): void
    {
        $result = AdvancementEligibilityService::evaluateFacts(
            $this->current,
            $this->target,
            true,
            new DateTimeImmutable('2025-03-15')
        );

        self::assertFalse($result['is_eligible']);
        self::assertSame('Temps de grade insuffisant : 14 mois sur 24 requis.', $result['eligibility_reason']);
    }

    public function testRefusesMissingRequiredQualification(): void
    {
        $target = $this->target;
        $target['required_qualification_id'] = 42;

        $result = AdvancementEligibilityService::evaluateFacts(
            $this->current,
            $target,
            false,
            new DateTimeImmutable('2026-01-15')
        );

        self::assertFalse($result['is_eligible']);
        self::assertStringContainsString('Qualification requise', (string) $result['eligibility_reason']);
    }

    public function testAcceptsExactEligibilityDate(): void
    {
        $result = AdvancementEligibilityService::evaluateFacts(
            $this->current,
            $this->target,
            true,
            new DateTimeImmutable('2026-01-15')
        );

        self::assertTrue($result['is_eligible']);
        self::assertNull($result['eligibility_reason']);
    }
}
