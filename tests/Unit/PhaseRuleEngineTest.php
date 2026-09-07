<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PhaseRules\PhaseEvaluationContext;
use App\Services\Personnel\PhaseRules\PhaseRuleEngine;
use PHPUnit\Framework\TestCase;

final class PhaseRuleEngineTest extends TestCase
{
    private PhaseRuleEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new PhaseRuleEngine();
    }

    public function testEmptyRuleSetIsNeverEligible(): void
    {
        $ctx = new PhaseEvaluationContext(1, 10);
        $out = $this->engine->evaluate([], 'all', $ctx);

        self::assertFalse($out['eligible']);
        self::assertSame([], $out['items']);
    }

    public function testAllRequiresEveryCondition(): void
    {
        $ctx = new PhaseEvaluationContext(1, 10, rawHours: 20.0, tutorAssigned: false);
        $out = $this->engine->evaluate([
            ['condition_type' => PhaseRuleEngine::TYPE_RAW_HOURS, 'threshold_value' => 10],
            ['condition_type' => PhaseRuleEngine::TYPE_TUTOR],
        ], 'all', $ctx);

        self::assertFalse($out['eligible']);
    }

    public function testAnyPassesWhenOneConditionPasses(): void
    {
        $ctx = new PhaseEvaluationContext(1, 10, rawHours: 20.0, tutorAssigned: false);
        $out = $this->engine->evaluate([
            ['condition_type' => PhaseRuleEngine::TYPE_RAW_HOURS, 'threshold_value' => 10],
            ['condition_type' => PhaseRuleEngine::TYPE_TUTOR],
        ], 'any', $ctx);

        self::assertTrue($out['eligible']);
    }

    public function testPlaytimeZeroFailsThreshold(): void
    {
        $ctx = new PhaseEvaluationContext(1, 10, rawHours: 0.0);
        $item = $this->engine->evaluateOne(
            ['condition_type' => PhaseRuleEngine::TYPE_RAW_HOURS, 'threshold_value' => 20],
            $ctx
        );

        self::assertFalse($item['passed']);
        self::assertStringContainsString('0 h / 20 h', $item['reason']);
    }

    public function testExpiredQualificationFailsWhenValidityRequired(): void
    {
        $ctx = new PhaseEvaluationContext(
            1,
            10,
            qualificationHeldId: 5,
            qualificationValid: false,
            qualificationLabel: 'Chef de groupe'
        );
        $item = $this->engine->evaluateOne([
            'condition_type' => PhaseRuleEngine::TYPE_QUALIFICATION,
            'qualification_id' => 5,
            'require_validity' => true,
        ], $ctx);

        self::assertFalse($item['passed']);
        self::assertSame('non remplie', $item['current']);
    }

    public function testRsvpWithoutCheckInIsNotAttendance(): void
    {
        $ctx = new PhaseEvaluationContext(1, 10, checkedInCount: 0);
        $item = $this->engine->evaluateOne(
            ['condition_type' => PhaseRuleEngine::TYPE_CHECKED_IN, 'threshold_value' => 1, 'window_days' => 90],
            $ctx
        );

        self::assertFalse($item['passed']);
        self::assertStringContainsString('présence(s) pointée(s)', $item['reason']);
    }

    public function testNoRetrogradationHelper(): void
    {
        self::assertTrue(\App\Services\Personnel\PhaseRules\PhaseTransitionService::isForwardTransition(1, 2));
        self::assertFalse(\App\Services\Personnel\PhaseRules\PhaseTransitionService::isForwardTransition(3, 2));
        self::assertFalse(\App\Services\Personnel\PhaseRules\PhaseTransitionService::isForwardTransition(2, 2));
    }

    public function testRosterBadgeGateAndProgress(): void
    {
        $gate = \App\Services\Personnel\PhaseRules\PhaseTransitionService::rosterBadge([
            'phase' => ['label' => 'Formation'],
            'next' => ['label' => 'Intégré'],
            'effect' => 'manual_gate',
            'evaluation' => [
                'eligible' => true,
                'items' => [['passed' => true], ['passed' => true]],
            ],
        ]);
        self::assertSame('VALIDATION REQUISE', $gate['label'] ?? null);

        $progress = \App\Services\Personnel\PhaseRules\PhaseTransitionService::rosterBadge([
            'phase' => ['label' => 'Formation'],
            'next' => ['label' => 'Intégré'],
            'effect' => 'manual_gate',
            'evaluation' => [
                'eligible' => false,
                'items' => [['passed' => true], ['passed' => false], ['passed' => true], ['passed' => false], ['passed' => false]],
            ],
        ]);
        self::assertSame('Formation · 2/5', $progress['label'] ?? null);
        self::assertSame('progress', $progress['kind'] ?? null);
    }
}
