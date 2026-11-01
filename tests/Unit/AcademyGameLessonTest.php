<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AcademyGameApiController;
use App\Core\Request;
use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyGameLesson;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

final class AcademyGameLessonTest extends TestCase
{
    private function lines(string $body, string $type): array
    {
        return array_values(array_map(static fn (string $l): array => explode("\t", $l), array_filter(explode("\n", $body), static fn (string $l): bool => str_starts_with($l, $type . "\t"))));
    }

    public function testLessonPagesNeverLeakAnswersOrAssessment(): void
    {
        $course = AcademyBuiltInCourses::find(AcademyBuiltInCourses::JTAC_KEY);
        self::assertIsArray($course);
        $chapter = $course['chapters'][0];
        $all = json_encode(AcademyGameLesson::pages($course, 0, 'fr'), JSON_UNESCAPED_UNICODE);
        foreach (AcademyCurriculum::examQuestions($chapter) as $q) {
            self::assertStringNotContainsString(AcademyGameLesson::text($q['feedback'], 'fr'), $all);
        }
        foreach ([2, 3] as $stage) {
            $q = AcademyCurriculum::questionForStage($chapter, $stage);
            self::assertStringNotContainsString(AcademyGameLesson::text($q['feedback'], 'fr'), $all);
        }
        foreach (AcademyGameLesson::pages($course, 0, 'en') as [, , $title, $body]) {
            self::assertLessThanOrEqual(AcademyGameLesson::PAGE_CHARS + 200, mb_strlen($body), $title);
        }
        self::assertSame([], AcademyGameLesson::pages($course, 99, 'fr'));
    }

    public function testLessonIsServedInChunksThatFitTheGameLink(): void
    {
        $course = AcademyBuiltInCourses::find(AcademyBuiltInCourses::JTAC_KEY);
        $total = count(AcademyGameLesson::pages($course, 0, 'fr'));
        $from = 0;
        $seen = [];
        do {
            $body = AcademyGameLesson::lessonBody($course, AcademyBuiltInCourses::JTAC_KEY, 0, 'fr', $from, 0);
            self::assertLessThan(8000 - 4, strlen($body));
            $head = $this->lines($body, 'L')[0];
            self::assertSame((string) $total, $head[6]);
            foreach ($this->lines($body, 'P') as $p) {
                $seen[] = (int) $p[1];
                self::assertCount(6, $p);
            }
            $from = (int) $head[9];
        } while ($from > 0 && count($seen) < 500);
        self::assertSame(range(0, $total - 1), $seen);
    }

    public function testOnlyReachedLessonsAreVisibleToLearners(): void
    {
        self::assertFalse(AcademyGameLesson::chapterAllowed(null, 0, false));
        self::assertTrue(AcademyGameLesson::chapterAllowed(null, 3, true));
        self::assertTrue(AcademyGameLesson::chapterAllowed(['chapter' => 1], 1, false));
        self::assertFalse(AcademyGameLesson::chapterAllowed(['chapter' => 1], 2, false));
        self::assertTrue(AcademyGameLesson::chapterAllowed(['chapter' => 0, 'completed' => true], 2, false));
    }

    public function testSeenAdvancesOnlyTheCurrentReadingStage(): void
    {
        $state = ['chapter' => 0, 'stage' => 0, 'snapshot' => AcademyCurriculum::demo()];
        $next = AcademyGameLesson::seen($state, 0, 0, 1000);
        self::assertSame(1, $next['stage'] ?? null);
        self::assertSame(2, AcademyGameLesson::seen($next, 0, 1, 1001)['stage'] ?? null);
        // Déjà franchie, autre leçon, exercice ou cours terminé : rien n'est écrit.
        self::assertNull(AcademyGameLesson::seen($next, 0, 0, 1002));
        self::assertNull(AcademyGameLesson::seen($state, 1, 0, 1002));
        self::assertNull(AcademyGameLesson::seen(['stage' => 2] + $state, 0, 2, 1002));
        self::assertNull(AcademyGameLesson::seen(['completed' => true] + $state, 0, 0, 1002));
    }

    public function testCourseListShowsCommunityCoursesToStaffOnly(): void
    {
        $states = ['__notes' => ['text' => 'privé'], 'fondamentaux' => ['chapter' => 1, 'stage' => 2, 'snapshot' => AcademyCurriculum::demo()]];
        $courses = [AcademyCurriculum::demo()];
        $learner = AcademyGameLesson::coursesBody($states, $courses, false, 'fr');
        self::assertCount(1, $this->lines($learner, 'C'));
        self::assertSame([], $this->lines($learner, 'S'));
        self::assertStringNotContainsString('privé', $learner);
        self::assertSame(['C', 'fondamentaux'], array_slice($this->lines($learner, 'C')[0], 0, 2));
        self::assertCount(1, $this->lines(AcademyGameLesson::coursesBody($states, $courses, true, 'en'), 'S'));
    }

    public function testFieldsCannotBreakTheLineFormat(): void
    {
        self::assertSame('a b\\nc', AcademyGameLesson::field("a\tb\r\nc"));
    }

    public function testEndpointsRequireTheAccountGameSession(): void
    {
        ComspecApiKeyAuth::resetForTests();
        $_GET = [];
        $res = (new AcademyGameApiController())->courses(new Request());
        self::assertSame(401, $res->statusCode());
        self::assertSame('session_required', $res->body());
        self::assertSame(401, (new AcademyGameApiController())->seen(new Request())->statusCode());
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach (['courses', 'lesson', 'seen'] as $route) {
            self::assertStringContainsString("/api/atak/academy/$route", $routes);
        }
    }
}
