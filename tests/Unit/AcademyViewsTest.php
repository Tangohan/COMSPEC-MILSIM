<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyCurriculum;
use PHPUnit\Framework\TestCase;

final class AcademyViewsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 1];
        $_SERVER['REQUEST_URI'] = '/academy/classroom?course=fondamentaux';
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    public function testAllLearningSpacesRenderInBothLanguages(): void
    {
        $course = AcademyCurriculum::demo();
        $state = ['chapter' => 0, 'stage' => 0, 'snapshot' => $course];
        foreach (['fr','en'] as $lang) {
            $GLOBALS['__app_locale'] = $lang;
            $home = Response::view('academy.home', ['paidRequired' => true]);
            self::assertStringContainsString('<html lang="' . $lang . '">', $home->body());
            foreach (['dashboard','catalogue','courses','classroom','laboratory','simulators','exams','library','notebook','record','instructor'] as $space) {
                $response = Response::view('academy.space', ['space' => $space, 'staff' => true, 'states' => ['fondamentaux' => $state], 'courses' => [$course], 'selected' => $course, 'state' => $state, 'learners' => [], 'notice' => '']);
                self::assertSame(200, $response->statusCode());
                self::assertStringContainsString('<html lang="' . $lang . '">', $response->body());
                self::assertStringContainsString('id="main"', $response->body());
            }
        }
    }

    public function testEveryLessonStageAndCompletedStateRenderWithoutExposingCorrectAnswer(): void
    {
        $course = AcademyCurriculum::demo();
        foreach (range(0,6) as $stage) {
            $state = ['chapter' => 0, 'stage' => $stage, 'snapshot' => $course];
            if ($stage === 4) $state['exam'] = ['deadline' => time() + 300, 'token' => 'exam-token'];
            $body = Response::view('academy.space', ['space' => 'classroom', 'staff' => false, 'states' => ['fondamentaux' => $state], 'courses' => [$course], 'selected' => $course, 'state' => $state, 'learners' => [], 'notice' => ''])->body();
            self::assertStringNotContainsString('data-answer', $body);
            self::assertStringNotContainsString('"answer":', $body);
            self::assertStringContainsString('aria-current="step"', $body);
        }
    }

    public function testNotebookContentIsEscapedAndStaffNeverSeePrivateNotes(): void
    {
        $course = AcademyCurriculum::demo();
        $notes = '<script>alert("private")</script>';
        $data = ['staff' => true, 'states' => ['__notes' => ['text' => $notes]], 'courses' => [$course], 'selected' => $course, 'state' => null, 'learners' => [], 'notice' => ''];
        $body = Response::view('academy.space', $data + ['space' => 'notebook'])->body();
        self::assertStringNotContainsString($notes, $body);
        self::assertStringContainsString('&lt;script&gt;', $body);
        self::assertStringNotContainsString('private', strip_tags(Response::view('academy.space', $data + ['space' => 'instructor'])->body()));
    }
}
