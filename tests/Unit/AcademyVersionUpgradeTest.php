<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyVersionUpgrade;
use PHPUnit\Framework\TestCase;

final class AcademyVersionUpgradeTest extends TestCase
{
    private static function oldJtac(): array
    {
        $course = AcademyBuiltInCourses::find(AcademyBuiltInCourses::JTAC_KEY);
        $course['version'] = '1.0';
        $course['chapters'] = [$course['chapters'][0]];
        return $course;
    }

    public function testCompletedOldVersionShowsBadgeAndKeepsLicenceWhenUpgraded(): void
    {
        $licence = ['number' => 'ATH-ACADEMY-JTAC-ABC', 'issued_at' => 10, 'validated_by' => 7, 'course_version' => '1.0'];
        $state = ['chapter' => 0, 'stage' => 6, 'learner_id' => 3, 'snapshot' => self::oldJtac(), 'completed' => true, 'license' => $licence,
            'validations' => [0 => ['by' => 7, 'comment' => 'Validé.', 'at' => 10]], 'debriefs' => [0 => 'Débriefing du chapitre un.']];
        $current = AcademyBuiltInCourses::find(AcademyBuiltInCourses::JTAC_KEY);
        // Pas d'auto-passage : un parcours terminé reste sur sa copie.
        self::assertSame($state, AcademyBuiltInCourses::refreshSnapshot($state, AcademyBuiltInCourses::JTAC_KEY));
        $info = AcademyVersionUpgrade::outdated($state, AcademyBuiltInCourses::JTAC_KEY, $current);
        self::assertNotNull($info);
        self::assertSame(['1.0', '2.1.0', true], [$info['from'], $info['to'], $info['completed']]);

        $next = AcademyVersionUpgrade::upgrade($state, $current, 3, 99);
        self::assertSame($licence, $next['license']);
        self::assertSame([$licence], $next['license_history']);
        self::assertFalse($next['completed']);
        self::assertSame('2.1.0', $next['snapshot']['version']);
        self::assertArrayHasKey(0, $next['validations']);
        self::assertSame([1, 0], [$next['chapter'], $next['stage']]);
        self::assertNull(AcademyVersionUpgrade::outdated($next, AcademyBuiltInCourses::JTAC_KEY, $current));
    }

    public function testInProgressStudioUpgradeKeepsProgressAndFollowsChapters(): void
    {
        $old = AcademyCurriculum::demo();
        $old['key'] = 'course-old';
        $old['version'] = '1.0';
        $new = $old;
        $new['key'] = 'course-new';
        $new['version'] = '2.0';
        $new['published'] = true;
        array_unshift($new['chapters'], $old['chapters'][1]);
        unset($new['chapters'][2]);
        $new['chapters'] = array_values($new['chapters']);
        $map = AcademyVersionUpgrade::currentMap([$new], ['course-new' => ['course-old']]);
        $state = ['chapter' => 1, 'stage' => 3, 'snapshot' => $old, 'validations' => [0 => ['by' => 7]], 'debriefs' => [0 => 'x', 1 => 'y']];
        self::assertSame('course-new', AcademyVersionUpgrade::outdated($state, 'course-old', $map['course-old'])['key']);
        $next = AcademyVersionUpgrade::upgrade($state, $map['course-old'], 3, 5);
        self::assertSame([0, 3], [$next['chapter'], $next['stage']]);
        self::assertSame([1], array_keys($next['validations']));
        self::assertSame(['y', 'x'], [$next['debriefs'][0], $next['debriefs'][1]]);
    }

    public function testDebriefNeedsTextUnlessNothingToImproveIsTicked(): void
    {
        $course = AcademyCurriculum::demo();
        $state = ['chapter' => 0, 'stage' => 5];
        $input = ['expected_stage' => 5, 'expected_chapter' => 0, 'reflection' => ''];
        try {
            AcademyCurriculum::transition($state, $course, 'debrief', $input, 1);
            self::fail('empty debrief accepted');
        } catch (\DomainException $e) {
            self::assertSame('reflection_required', $e->getMessage());
        }
        $next = AcademyCurriculum::transition($state, $course, 'debrief', $input + ['nothing_to_improve' => '1'], 1);
        self::assertSame(AcademyCurriculum::NOTHING_TO_IMPROVE, $next['debriefs'][0]);
        self::assertSame(6, $next['stage']);
        $this->expectException(\DomainException::class);
        AcademyCurriculum::transition($state, $course, 'debrief', ['reflection' => str_repeat('a', 5001), 'nothing_to_improve' => '1'] + $input, 1);
    }
}
