<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyAccess;
use App\Services\Academy\AcademyCurriculum;
use PHPUnit\Framework\TestCase;

final class AcademyCurriculumTest extends TestCase
{
    private function apply(array $state, string $action, array $input = [], int $now = 1000): array
    {
        return AcademyCurriculum::transition($state, AcademyCurriculum::demo(), $action, $input + ['expected_stage' => $state['stage'], 'expected_chapter' => $state['chapter']], $now);
    }

    public function testPaidAccessRejectsFreeTrialPastDueAndExpiredManualPlans(): void
    {
        $tenant = ['id' => 1, 'plan_slug' => 'pro', 'subscription_status' => 'active'];
        self::assertTrue(AcademyAccess::paid($tenant, 1000));
        foreach (['free', '', 'unknown'] as $plan) {
            self::assertFalse(AcademyAccess::paid(array_replace($tenant, ['plan_slug' => $plan]), 1000));
        }
        foreach (['trialing', 'past_due', 'canceled'] as $status) {
            self::assertFalse(AcademyAccess::paid(array_replace($tenant, ['subscription_status' => $status]), 1000));
        }
        foreach (['1970-01-01 00:01:00 UTC', 'not-a-date'] as $end) {
            self::assertFalse(AcademyAccess::paid($tenant + ['settings' => ['manual_billing' => ['ends_at' => $end]]], 1000));
        }
        self::assertFalse(AcademyAccess::paid(array_replace($tenant, ['id' => 0]), 1000));
    }

    public function testAllBilledPlansIncludingProPlusAreAccepted(): void
    {
        $slugs = array_merge(\App\Services\Billing\PlanPaymentService::PAID_PLANS, ['pro_plus', 'PRO_PLUS', ' pro_plus ']);
        foreach ($slugs as $slug) {
            self::assertTrue(AcademyAccess::paid(['id'=>1, 'plan_slug'=>$slug, 'subscription_status'=>'active'], 1000), $slug);
        }
    }

    public function testProPlusRejectsInactiveSubscriptionsAndExpiredManualPeriods(): void
    {
        $tenant = ['id'=>1, 'plan_slug'=>'pro_plus', 'subscription_status'=>'active'];
        self::assertTrue(AcademyAccess::paid($tenant + ['settings'=>['manual_billing'=>['ends_at'=>'1970-01-01 00:20:00 UTC']]], 1000));
        foreach (['trialing', 'past_due', 'canceled'] as $status) {
            self::assertFalse(AcademyAccess::paid(array_replace($tenant, ['subscription_status'=>$status]), 1000));
        }
        foreach (['1970-01-01 00:01:00 UTC', 'not-a-date'] as $end) {
            self::assertFalse(AcademyAccess::paid($tenant + ['settings'=>['manual_billing'=>['ends_at'=>$end]]], 1000));
        }
    }

    public function testFullCurriculumRequiresPracticeExamDebriefAndInstructorAtEveryChapter(): void
    {
        $course = AcademyCurriculum::demo();
        $state = ['chapter' => 0, 'stage' => 0];
        foreach ($course['chapters'] as $index => $chapter) {
            self::assertSame($index, $state['chapter']);
            $state = $this->apply($state, 'advance');
            $state = $this->apply($state, 'advance');
            $state = $this->apply($state, 'practice', ['answer' => ($chapter['answer'] + 1) % 3]);
            self::assertSame(2, $state['stage']);
            $state = $this->apply($state, 'practice', ['answer' => (string) $chapter['answer']]);
            $state = $this->apply($state, 'practice', ['answer' => (string) $chapter['answer']]);
            self::assertSame(4, $state['stage']);
            $state = $this->apply($state, 'exam_start');
            $state = $this->apply($state, 'exam_submit', ['token' => $state['exam']['token'], 'answer' => (string) $chapter['answer']]);
            self::assertSame(5, $state['stage']);
            $state = $this->apply($state, 'debrief', ['reflection' => 'Observation réussie. Je vérifierai mieux mes hypothèses.']);
            self::assertSame(6, $state['stage']);
            self::assertEmpty($state['completed'] ?? false);
            $state = AcademyCurriculum::validateChapter($state, $course, 99, 'Compétence démontrée dans cet exercice.', 1100);
        }
        self::assertTrue($state['completed']);
        self::assertCount(3, $state['validations']);
        self::assertCount(3, $state['attempts']);
    }

    public function testExpiredAssessmentFailsEvenWithCorrectAnswerAndKeepsHistory(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 4], 'exam_start');
        $state = $this->apply($state, 'exam_submit', ['token' => $state['exam']['token'], 'answer' => '0', 'score' => 100], 1300);
        self::assertSame(4, $state['stage']);
        self::assertSame(0, $state['attempts'][0]['score']);
        self::assertTrue($state['attempts'][0]['expired']);
        self::assertArrayNotHasKey('exam', $state);
    }

    public function testInvalidExamTokenCannotConsumeOrAlterAttempt(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 4], 'exam_start');
        $this->expectException(\DomainException::class);
        $this->apply($state, 'exam_submit', ['token' => 'forged', 'answer' => '0']);
    }

    public function testReplayedAdvanceCannotSkipDemonstration(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 0], 'advance');
        $this->expectException(\DomainException::class);
        $this->apply($state, 'advance', ['expected_stage' => '0']);
    }

    public function testClientCannotValidateLearningOrSkipToAssessment(): void
    {
        $this->expectException(\DomainException::class);
        $this->apply(['chapter' => 0, 'stage' => 0], 'exam_start');
    }

    public function testDebriefRequiresMeaningfulReflection(): void
    {
        $this->expectException(\DomainException::class);
        $this->apply(['chapter' => 0, 'stage' => 5], 'debrief', ['reflection' => 'ok']);
    }

    public function testInstructorCannotValidateBeforeAssessmentAndDebrief(): void
    {
        $this->expectException(\DomainException::class);
        AcademyCurriculum::validateChapter(['chapter' => 0, 'stage' => 4], AcademyCurriculum::demo(), 99, 'Validation prématurée.', 1100);
    }

    public function testCourseValidationRequiresBothLanguagesAndSafeResourceProtocols(): void
    {
        $course = AcademyCurriculum::demo();
        self::assertSame($course, AcademyCurriculum::validate($course));
        $course['chapters'][0]['resources'] = [['type' => 'document', 'title' => ['fr' => 'Fiche', 'en' => 'Sheet'], 'url' => ['fr' => 'javascript:alert(1)', 'en' => 'https://example.com/en']]];
        $this->expectException(\InvalidArgumentException::class);
        AcademyCurriculum::validate($course);
    }

    public function testCourseRejectsMissingTranslation(): void
    {
        $course = AcademyCurriculum::demo();
        unset($course['chapters'][0]['theory']['en']);
        $this->expectException(\InvalidArgumentException::class);
        AcademyCurriculum::validate($course);
    }

    public function testFrenchEnglishCataloguesHaveIdenticalKeys(): void
    {
        $fr = require dirname(__DIR__, 2) . '/lang/fr/academy.php';
        $en = require dirname(__DIR__, 2) . '/lang/en/academy.php';
        self::assertSame(array_keys($fr), array_keys($en));
    }
}
