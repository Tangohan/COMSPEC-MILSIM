<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelPhaseRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PhaseRules\PhaseFactsLoader;
use App\Services\Personnel\PhaseRules\PhaseRuleEngine;
use App\Services\Personnel\PhaseRules\PhaseTransitionService;
use PHPUnit\Framework\TestCase;

final class PhaseTransitionServiceTest extends TestCase
{
    /** @return array{0: PhaseTransitionService, 1: UserRepository, 2: PersonnelPhaseRepository} */
    private function service(): array
    {
        $phases = $this->createMock(PersonnelPhaseRepository::class);
        $profiles = $this->createStub(PersonnelProfileRepository::class);
        $users = $this->createMock(UserRepository::class);
        $engine = new PhaseRuleEngine();
        $facts = $this->createStub(PhaseFactsLoader::class);
        $svc = $this->getMockBuilder(PhaseTransitionService::class)
            ->setConstructorArgs([$phases, $profiles, $users, $engine, $facts])
            ->onlyMethods(['checklistForMember', 'apply'])
            ->getMock();

        return [$svc, $users, $phases];
    }

    public function testManualBlockedWhenNotEligible(): void
    {
        [$svc] = $this->service();
        $svc->method('checklistForMember')->willReturn($this->check(false, 'manual_gate'));
        $svc->expects($this->never())->method('apply');

        $out = $svc->applyManual(1, 10, 2, false, '');
        self::assertFalse($out['ok']);
        self::assertSame('Les conditions ne sont pas encore remplies.', $out['error'] ?? null);
    }

    public function testManualAllowedWhenEligible(): void
    {
        [$svc] = $this->service();
        $svc->method('checklistForMember')->willReturn($this->check(true, 'manual_gate'));
        $svc->expects($this->once())->method('apply')->willReturn(['ok' => true, 'applied' => true]);

        $out = $svc->applyManual(1, 10, 2, false, '');
        self::assertTrue($out['ok']);
        self::assertTrue($out['applied'] ?? false);
    }

    public function testOverrideRequiresReason(): void
    {
        [$svc] = $this->service();
        $svc->method('checklistForMember')->willReturn($this->check(false, 'manual_gate'));
        $svc->expects($this->never())->method('apply');

        $out = $svc->applyManual(1, 10, 2, true, '  ');
        self::assertFalse($out['ok']);
        self::assertSame('Indiquez le motif du passage forcé.', $out['error'] ?? null);
    }

    public function testOverrideWithReasonAppliesOnce(): void
    {
        [$svc] = $this->service();
        $svc->method('checklistForMember')->willReturn($this->check(false, 'manual_gate'));
        $svc->expects($this->once())->method('apply')->willReturn(['ok' => true, 'applied' => true]);

        $out = $svc->applyManual(1, 10, 2, true, 'Décision du commandement');
        self::assertTrue($out['ok']);
    }

    public function testAutomaticSkipsEmptyOrIneligibleAndDoesNotApply(): void
    {
        [$svc, $users, $phases] = $this->service();
        $phases->method('schemaReady')->willReturn(true);
        $users->method('allForTenant')->willReturn([
            ['id' => 10, 'status' => 'active'],
            ['id' => 11, 'status' => 'inactive'],
        ]);
        $svc->method('checklistForMember')->willReturn($this->check(false, 'automatic'));
        $svc->expects($this->never())->method('apply');

        $stats = $svc->runAutomaticForTenant(1);
        self::assertSame(0, $stats['applied']);
        self::assertGreaterThan(0, $stats['skipped']);
    }

    public function testAutomaticAppliesWhenEligibleThenSecondRunIsIdempotent(): void
    {
        [$svc, $users, $phases] = $this->service();
        $phases->method('schemaReady')->willReturn(true);
        $users->method('allForTenant')->willReturn([['id' => 10, 'status' => 'active']]);
        $svc->method('checklistForMember')->willReturn($this->check(true, 'automatic'));
        $svc->expects($this->exactly(2))->method('apply')
            ->willReturnOnConsecutiveCalls(
                ['ok' => true, 'applied' => true],
                ['ok' => true, 'applied' => false]
            );

        $first = $svc->runAutomaticForTenant(1);
        $second = $svc->runAutomaticForTenant(1);
        self::assertSame(1, $first['applied']);
        self::assertSame(0, $second['applied']);
    }

    /**
     * @return array{phase: array<string, mixed>, next: array<string, mixed>, rule_set: array<string, mixed>, conditions: list<array<string, mixed>>, evaluation: array<string, mixed>, effect: string}
     */
    private function check(bool $eligible, string $effect): array
    {
        return [
            'phase' => ['id' => 1, 'label' => 'Formation', 'position' => 2],
            'next' => ['id' => 2, 'label' => 'Intégré', 'position' => 3, 'target_member_status' => 'Disponible'],
            'rule_set' => ['id' => 9, 'effect' => $effect, 'is_active' => 1],
            'conditions' => $eligible ? [['condition_type' => PhaseRuleEngine::TYPE_TUTOR]] : [],
            'evaluation' => ['eligible' => $eligible, 'logic' => 'all', 'items' => []],
            'effect' => $effect,
        ];
    }
}
