<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyCurriculum;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcademyRichCurriculumTest extends TestCase
{
    private function text(string $value): array
    {
        return ['fr' => 'FR ' . $value, 'en' => 'EN ' . $value];
    }

    private function question(string $label, int $answer): array
    {
        return [
            'question' => $this->text($label),
            'options' => [$this->text('A'), $this->text('B'), $this->text('C')],
            'answer' => $answer,
            'feedback' => $this->text('Explanation for ' . $label),
        ];
    }

    private function course(): array
    {
        $question = $this->question('Chapter check', 0);
        return [
            'key' => 'jtac-licence-ath-academy', 'built_in' => true, 'version' => '1.0',
            'title' => $this->text('Simulation learning'), 'summary' => $this->text('Module summary'),
            'license' => $this->text('Internal simulation licence'), 'duration_minutes' => 90,
            'chapters' => [[
                'title' => $this->text('Complete module'),
                'theory' => $this->text('Theory lesson'), 'demonstration' => $this->text('Worked example'),
                ...$question,
                'guided' => ['brief' => $this->text('Guided case'), 'hint' => $this->text('Use the lesson'), ...$this->question('Guided question', 1)],
                'simulation' => ['brief' => $this->text('New independent case'), ...$this->question('Independent question', 2)],
                'assessment' => [
                    'instructions' => $this->text('Answer ten questions'), 'seconds' => 900, 'pass_percent' => 80,
                    'questions' => array_map(fn (int $i): array => $this->question('Exam question ' . $i, $i % 3), range(0, 9)),
                ],
                'debrief' => ['prompt' => $this->text('Reflect using evidence'), 'criteria' => [$this->text('State an improvement')]],
                'validation' => ['brief' => $this->text('Instructor review'), 'criteria' => [$this->text('Competence demonstrated')]],
                'resources' => [['type' => 'document', 'title' => $this->text('Reference sheet'), 'body' => $this->text('Self-contained reference text')]],
            ]],
        ];
    }

    private function apply(array $state, string $action, array $input = [], int $now = 1000): array
    {
        return AcademyCurriculum::transition($state, $this->course(), $action, $input + [
            'expected_stage' => $state['stage'], 'expected_chapter' => $state['chapter'],
        ], $now);
    }

    private function examAnswers(int $correct = 10): array
    {
        return array_map(static fn (int $i): string => (string) ($i < $correct ? $i % 3 : ($i + 1) % 3), range(0, 9));
    }

    public function testGuidedAndIndependentCasesUseTheirOwnAnswersAndFeedback(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 2], 'practice', ['answer' => '0']);
        self::assertSame(2, $state['stage']);
        $state = $this->apply($state, 'practice', ['answer' => '1']);
        self::assertSame(3, $state['stage']);
        self::assertSame($this->text('Explanation for Guided question'), $state['last_practice_feedback']);
        $state = $this->apply($state, 'practice', ['answer' => '1']);
        self::assertSame(3, $state['stage']);
        $state = $this->apply($state, 'practice', ['answer' => '2']);
        self::assertSame(4, $state['stage']);
        self::assertCount(4, $state['practice']);
        self::assertSame($this->text('Explanation for Independent question'), $state['last_practice_feedback']);
    }

    public function testThresholdUsesAllQuestionsAndIgnoresClientScore(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 4], 'exam_start', ['deadline' => 999999]);
        self::assertSame(1900, $state['exam']['deadline']);
        $state = $this->apply($state, 'exam_submit', ['token' => $state['exam']['token'], 'answers' => $this->examAnswers(7), 'score' => 100]);
        self::assertSame(4, $state['stage']);
        self::assertSame(70, $state['attempts'][0]['score']);
        self::assertFalse($state['attempts'][0]['passed']);
        $state = $this->apply($state, 'exam_start', [], 1100);
        $state = $this->apply($state, 'exam_submit', ['token' => $state['exam']['token'], 'answers' => $this->examAnswers(8)], 1150);
        self::assertSame(5, $state['stage']);
        self::assertSame(80, $state['attempts'][1]['score']);
        self::assertSame(10, $state['attempts'][1]['question_count']);
        self::assertSame(8, $state['attempts'][1]['correct_count']);
        self::assertTrue($state['attempts'][1]['results'][0]['correct']);
        self::assertFalse($state['attempts'][1]['results'][9]['correct']);
        self::assertArrayNotHasKey('answer', $state['attempts'][1]['results'][0]);
    }

    public function testMissingOrMalformedAnswersCannotPassUsingTheLegacySingleAnswer(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 4], 'exam_start');
        $state = $this->apply($state, 'exam_submit', [
            'token' => $state['exam']['token'], 'answers' => [0 => ['0'], 1 => true, 2 => '02', 3 => 0.0, 99 => '0'], 'answer' => 0,
        ]);
        self::assertSame(0, $state['attempts'][0]['score']);
        self::assertSame(array_fill(0, 10, null), $state['attempts'][0]['answers']);
        self::assertSame(4, $state['stage']);
    }

    public function testDeadlineIsEnforcedAtTheBoundaryEvenWhenEveryAnswerIsCorrect(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 4], 'exam_start');
        $state = $this->apply($state, 'exam_submit', ['token' => $state['exam']['token'], 'answers' => $this->examAnswers(), 'deadline' => 999999], 1900);
        self::assertSame(4, $state['stage']);
        self::assertTrue($state['attempts'][0]['expired']);
        self::assertSame(0, $state['attempts'][0]['score']);
        self::assertArrayNotHasKey('exam', $state);
    }

    public function testSubmittedExamTokenCannotBeReplayed(): void
    {
        $state = $this->apply(['chapter' => 0, 'stage' => 4], 'exam_start');
        $token = $state['exam']['token'];
        $state = $this->apply($state, 'exam_submit', ['token' => $token, 'answers' => $this->examAnswers()]);
        $this->expectException(DomainException::class);
        $this->apply($state, 'exam_submit', ['token' => $token, 'answers' => $this->examAnswers(), 'expected_stage' => 4]);
    }

    public function testSevenStagesIssueInternalLicenseOnlyAfterAnotherInstructorValidates(): void
    {
        $state = ['chapter' => 0, 'stage' => 0, 'learner_id' => 4, 'tenant_id' => 14];
        $state = $this->apply($state, 'advance');
        $state = $this->apply($state, 'advance');
        $state = $this->apply($state, 'practice', ['answer' => '1']);
        $state = $this->apply($state, 'practice', ['answer' => '2']);
        $state = $this->apply($state, 'exam_start');
        $state = $this->apply($state, 'exam_submit', ['token' => $state['exam']['token'], 'answers' => $this->examAnswers(8)]);
        $state = $this->apply($state, 'debrief', ['reflection' => 'I distinguished facts from assumptions and will make uncertainty explicit.']);
        self::assertSame(6, $state['stage']);
        self::assertArrayNotHasKey('license', $state);
        self::assertFalse($state['completed'] ?? false);
        $state = AcademyCurriculum::validateChapter($state, $this->course(), 99, 'Competence demonstrated with reviewable evidence.', 1300);
        self::assertTrue($state['completed']);
        self::assertMatchesRegularExpression('/\AATH-ACADEMY-JTAC-[0-9A-F]{12}\z/', $state['license']['number']);
        self::assertSame(1300, $state['license']['issued_at']);
        self::assertSame(99, $state['license']['validated_by']);
        self::assertSame('1.0', $state['license']['course_version']);
        self::assertSame('simulation', $state['license']['scope']);
    }

    public function testLearnerCannotSelfValidateAtTheDomainLayer(): void
    {
        $this->expectException(DomainException::class);
        AcademyCurriculum::validateChapter(['chapter' => 0, 'stage' => 6, 'learner_id' => 4], $this->course(), 4, 'I approve my own result.', 1300);
    }

    public function testMissingExamAndDebriefEvidenceCannotProduceAnInternalLicense(): void
    {
        $this->expectException(DomainException::class);
        AcademyCurriculum::validateChapter(['chapter' => 0, 'stage' => 6, 'learner_id' => 4], $this->course(), 99, 'Incomplete state should remain pending.', 1300);
    }

    public function testCourseRevisionKeepsItsOwnTimingAndAnswers(): void
    {
        $snapshot = $this->course();
        $revised = $this->course();
        $revised['chapters'][0]['assessment']['seconds'] = 60;
        $revised['chapters'][0]['guided']['answer'] = 2;
        self::assertSame(60, AcademyCurriculum::examSeconds($revised['chapters'][0]));
        self::assertSame(900, AcademyCurriculum::examSeconds($snapshot['chapters'][0]));
        self::assertSame(1, AcademyCurriculum::questionForStage($snapshot['chapters'][0], 2)['answer']);
        self::assertCount(10, AcademyCurriculum::examQuestions($snapshot['chapters'][0]));
    }

    public function testRichBilingualContentAndInlineResourcesValidateWithoutExternalFiles(): void
    {
        $course = $this->course();
        self::assertSame($course, AcademyCurriculum::validate($course));
        $course['chapters'][0]['resources'][0]['body']['en'] = '';
        $this->expectException(InvalidArgumentException::class);
        AcademyCurriculum::validate($course);
    }

    #[DataProvider('invalidRichField')]
    public function testInvalidRichAssessmentDataIsRejected(array $path, mixed $value): void
    {
        $course = $this->course();
        $target =& $course;
        foreach ($path as $field) {
            $target =& $target[$field];
        }
        $target = $value;
        $this->expectException(InvalidArgumentException::class);
        AcademyCurriculum::validate($course);
    }

    public static function invalidRichField(): array
    {
        return [
            'missing simulation translation' => [['chapters', 0, 'simulation', 'brief', 'en'], ''],
            'no exam questions' => [['chapters', 0, 'assessment', 'questions'], []],
            'unbounded duration' => [['chapters', 0, 'assessment', 'seconds'], 999999],
            'noninteger duration' => [['chapters', 0, 'assessment', 'seconds'], '900'],
            'zero pass mark' => [['chapters', 0, 'assessment', 'pass_percent'], 0],
            'invalid answer index' => [['chapters', 0, 'assessment', 'questions', 0, 'answer'], 3],
            'missing feedback translation' => [['chapters', 0, 'assessment', 'questions', 0, 'feedback', 'en'], ''],
            'empty validation rubric' => [['chapters', 0, 'validation', 'criteria'], []],
            'invalid resource URL type' => [['chapters', 0, 'resources', 0, 'url'], 'https://example.com'],
            'unsafe media URL despite body' => [['chapters', 0, 'resources', 0, 'url'], ['fr' => 'javascript:alert(1)', 'en' => 'https://example.com']],
        ];
    }
}
