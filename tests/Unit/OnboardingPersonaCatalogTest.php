<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Portal\OnboardingPersonaCatalog;
use PHPUnit\Framework\TestCase;

final class OnboardingPersonaCatalogTest extends TestCase
{
    public function testEveryPersonaProvidesAThreeStepJourney(): void
    {
        $catalog = OnboardingPersonaCatalog::all();

        self::assertSame(['member', 'command', 'operations', 'training'], array_keys($catalog));
        foreach ($catalog as $persona) {
            self::assertNotSame('', trim($persona['label']));
            self::assertNotSame('', trim($persona['description']));
            self::assertCount(3, $persona['steps']);
            foreach ($persona['steps'] as $step) {
                self::assertNotSame('', trim($step['label']));
                self::assertStringStartsWith('/', $step['href']);
            }
        }
    }

    public function testNormalizeRejectsUnknownPersona(): void
    {
        self::assertSame('operations', OnboardingPersonaCatalog::normalize(' OPERATIONS '));
        self::assertNull(OnboardingPersonaCatalog::normalize('unknown'));
        self::assertNull(OnboardingPersonaCatalog::normalize(null));
    }

    public function testCompletedStepsAreUniqueSortedAndBounded(): void
    {
        self::assertSame([0, 1, 2], OnboardingPersonaCatalog::normalizeCompletedSteps([2, '1', 2, 0, 8, -1, 'invalid', ['invalid']]));
        self::assertSame([0], OnboardingPersonaCatalog::normalizeCompletedSteps([0, 1], 1));
        self::assertSame([], OnboardingPersonaCatalog::normalizeCompletedSteps('invalid'));
        self::assertSame([], OnboardingPersonaCatalog::normalizeCompletedSteps([0], 0));
    }
}
