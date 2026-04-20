<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security\Conditions\DaysSinceCreationConditionEvaluator;
use App\Services\Security\Conditions\ManualApprovalConditionEvaluator;
use App\Services\Security\Conditions\StatusConditionEvaluator;
use App\Services\Security\Conditions\UnitConditionEvaluator;
use PHPUnit\Framework\TestCase;

final class AccessControlConditionsTest extends TestCase
{
    public function testDaysSinceCreationEvaluator(): void
    {
        $eval = new DaysSinceCreationConditionEvaluator();
        self::assertTrue($eval->evaluate(['created_at' => date('Y-m-d H:i:s', time() - 10 * 86400)], ['days' => 7]));
        self::assertFalse($eval->evaluate(['created_at' => date('Y-m-d H:i:s')], ['days' => 7]));
    }

    public function testUnitEvaluator(): void
    {
        $eval = new UnitConditionEvaluator();
        self::assertTrue($eval->evaluate(['unit_id' => 12], ['unit_id' => 12]));
        self::assertFalse($eval->evaluate(['unit_id' => 9], ['unit_id' => 12]));
    }

    public function testManualApprovalEvaluator(): void
    {
        $eval = new ManualApprovalConditionEvaluator();
        self::assertTrue($eval->evaluate(['access_manually_approved' => 1], ['field' => 'access_manually_approved']));
        self::assertFalse($eval->evaluate([], ['field' => 'access_manually_approved']));
    }

    public function testStatusEvaluator(): void
    {
        $eval = new StatusConditionEvaluator();
        self::assertTrue($eval->evaluate(['status' => 'active'], ['accepted' => ['active', 'recruit']])) ;
        self::assertFalse($eval->evaluate(['status' => 'suspended'], ['accepted' => ['active', 'recruit']])) ;
    }
}
