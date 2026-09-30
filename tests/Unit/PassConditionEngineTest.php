<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\Pass\PassConditionEngine;
use App\Services\Personnel\Pass\PassEvaluationContext;
use PHPUnit\Framework\TestCase;

final class PassConditionEngineTest extends TestCase
{
    public function testCatalogIncludesCoreTypes(): void
    {
        $types = array_column(PassConditionEngine::catalog(), 'type');
        self::assertContains(PassConditionEngine::TYPE_LMS, $types);
        self::assertContains(PassConditionEngine::TYPE_RAW_HOURS, $types);
        self::assertContains(PassConditionEngine::TYPE_CATEGORY_HOURS, $types);
        self::assertContains(PassConditionEngine::TYPE_MIN_GRADE, $types);
        self::assertContains(PassConditionEngine::TYPE_HIERARCHICAL_AVIS, $types);
        self::assertContains(PassConditionEngine::TYPE_BILAN_RATING, $types);
    }

    public function testAllLogicRequiresEveryCondition(): void
    {
        $engine = new PassConditionEngine();
        $ctx = new PassEvaluationContext(
            tenantId: 1,
            userId: 2,
            lmsCompleted: true,
            rawHours: 10,
            gradeMet: false,
        );
        $result = $engine->evaluate([
            ['condition_type' => PassConditionEngine::TYPE_LMS],
            ['condition_type' => PassConditionEngine::TYPE_RAW_HOURS, 'threshold_value' => 5],
            ['condition_type' => PassConditionEngine::TYPE_MIN_GRADE, 'grade_id' => 3],
        ], 'all', $ctx);

        self::assertFalse($result['eligible']);
        self::assertCount(3, $result['items']);
        self::assertTrue($result['items'][0]['passed']);
        self::assertTrue($result['items'][1]['passed']);
        self::assertFalse($result['items'][2]['passed']);
    }

    public function testAnyLogicPassesWhenOneConditionMet(): void
    {
        $engine = new PassConditionEngine();
        $ctx = new PassEvaluationContext(
            tenantId: 1,
            userId: 2,
            lmsCompleted: false,
            favorableAvisByKind: [PassConditionEngine::AVIS_CHEF_N1 => true],
        );
        $result = $engine->evaluate([
            ['condition_type' => PassConditionEngine::TYPE_LMS],
            ['condition_type' => PassConditionEngine::TYPE_HIERARCHICAL_AVIS, 'avis_kind' => PassConditionEngine::AVIS_CHEF_N1],
        ], 'any', $ctx);

        self::assertTrue($result['eligible']);
    }

    public function testBilanRatingUsesAverage(): void
    {
        $engine = new PassConditionEngine();
        $ctx = new PassEvaluationContext(
            tenantId: 1,
            userId: 2,
            bilanAverage: 3.5,
            bilanCount: 2,
            bilanLabel: 'Commandement',
        );
        $item = $engine->evaluateOne([
            'condition_type' => PassConditionEngine::TYPE_BILAN_RATING,
            'threshold_value' => 3,
            'bilan_kind' => 'commandement',
        ], $ctx);

        self::assertTrue($item['passed']);
    }
}
