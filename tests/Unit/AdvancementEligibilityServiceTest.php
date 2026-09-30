<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeDefinitionRepository;
use App\Repositories\PersonnelGradeHistoryRepository;
use App\Repositories\QualificationAwardRepository;
use App\Services\Personnel\AdvancementEligibilityService;
use App\Services\Personnel\QualificationTemporalStatusService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdvancementEligibilityServiceTest extends TestCase
{
    public function testTempsDeGradeInsuffisant(): void
    {
        $svc = $this->service(
            current: $this->historyRow('2025-07-30', 10),
            target: $this->gradeRow(20, 24, null),
            predecessorId: 11,
        );
        $r = $svc->evaluate(1, 5, 20, new DateTimeImmutable('2026-09-30'));
        self::assertFalse($r['is_eligible']);
        self::assertStringContainsString('Temps de grade insuffisant', (string) $r['eligibility_reason']);
        self::assertStringContainsString('14 mois sur 24', (string) $r['eligibility_reason']);
    }

    public function testQualificationManquante(): void
    {
        $svc = $this->service(
            current: $this->historyRow('2024-09-30', 10),
            target: $this->gradeRow(20, 12, 99, 'CEFEO'),
            predecessorId: 11,
            awards: [],
        );
        $r = $svc->evaluate(1, 5, 20, new DateTimeImmutable('2026-09-30'));
        self::assertFalse($r['is_eligible']);
        self::assertSame('Qualification CEFEO manquante', $r['eligibility_reason']);
    }

    public function testJourExactDEcheanceEstEligible(): void
    {
        $svc = $this->service(
            current: $this->historyRow('2024-09-30', 10),
            target: $this->gradeRow(20, 12, null),
            predecessorId: 11,
        );
        $r = $svc->evaluate(1, 5, 20, new DateTimeImmutable('2025-09-30'));
        self::assertTrue($r['is_eligible']);
        self::assertNull($r['eligibility_reason']);
    }

    public function testVeilleDuJourExactResteNonEligible(): void
    {
        $svc = $this->service(
            current: $this->historyRow('2024-09-30', 10),
            target: $this->gradeRow(20, 12, null),
            predecessorId: 11,
        );
        $r = $svc->evaluate(1, 5, 20, new DateTimeImmutable('2025-09-29'));
        self::assertFalse($r['is_eligible']);
        self::assertStringContainsString('Temps de grade insuffisant', (string) $r['eligibility_reason']);
    }

    public function testQualificationValideLeJourExact(): void
    {
        $awards = [[
            'definition_id' => 99,
            'admin_status' => 'obtained',
            'expires_at' => '2025-09-30',
            'grace_period_days' => 0,
            'alert_before_expiry_days' => 30,
        ]];
        $svc = $this->service(
            current: $this->historyRow('2024-09-30', 10),
            target: $this->gradeRow(20, 12, 99, 'CEFEO'),
            predecessorId: 11,
            awards: $awards,
        );
        $r = $svc->evaluate(1, 5, 20, new DateTimeImmutable('2025-09-30'));
        self::assertTrue($r['is_eligible']);
        self::assertTrue($r['qualification_ok']);
    }

    public function testGradePasImmediatementInferieur(): void
    {
        $svc = $this->service(
            current: $this->historyRow('2020-01-01', 5, 99),
            target: $this->gradeRow(20, 0, null),
            predecessorId: 11,
        );
        $r = $svc->evaluate(1, 5, 20, new DateTimeImmutable('2026-09-30'));
        self::assertFalse($r['is_eligible']);
        self::assertStringContainsString('immédiatement inférieur', (string) $r['eligibility_reason']);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $target
     * @param list<array<string, mixed>> $awards
     */
    private function service(array $current, array $target, int $predecessorId, array $awards = []): AdvancementEligibilityService
    {
        $history = $this->createMock(PersonnelGradeHistoryRepository::class);
        $history->method('currentForPersonnel')->willReturn($current);

        $grades = $this->createMock(GradeDefinitionRepository::class);
        $grades->method('find')->willReturn($target);
        $grades->method('findImmediatePredecessor')->willReturn(['id' => $predecessorId, 'rank_order' => 10]);

        $awardRepo = $this->createMock(QualificationAwardRepository::class);
        $awardRepo->method('listForUser')->willReturn($awards);

        return new AdvancementEligibilityService(
            $history,
            $grades,
            $awardRepo,
            new QualificationTemporalStatusService()
        );
    }

    /** @return array<string, mixed> */
    private function historyRow(string $obtainedAt, int $rankOrder, int $gradeId = 11): array
    {
        return [
            'grade_id' => $gradeId,
            'grade_label' => 'Soldat',
            'grade_code' => 'SDT',
            'rank_order' => $rankOrder,
            'obtained_at' => $obtainedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function gradeRow(int $id, int $months, ?int $qualId, string $qualName = ''): array
    {
        return [
            'id' => $id,
            'label' => 'Sergent',
            'code' => 'SGT',
            'rank_order' => 20,
            'min_time_in_previous_grade_months' => $months > 0 ? $months : null,
            'required_qualification_id' => $qualId,
            'required_qualification_name' => $qualName,
            'archived_at' => null,
        ];
    }
}
