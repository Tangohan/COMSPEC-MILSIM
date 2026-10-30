<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyBuiltInCourses;
use PHPUnit\Framework\TestCase;

final class AcademyLiveHomeTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token'=>str_repeat('a',64)];
        $GLOBALS['__app_locale']='fr';
    }

    protected function tearDown(): void { unset($GLOBALS['__app_locale']); }

    public function testGuestGetsSignInAndNoFabricatedCatalogue(): void
    {
        $body=Response::view('academy.home',['accessState'=>'guest'])->body();
        self::assertStringContainsString('data-access-state="guest"',$body);
        self::assertStringContainsString('login?redirect=academy',$body);
        self::assertStringContainsString('assets/images/academy-hero.jpg',$body);
        self::assertStringContainsString('data-academy-disclaimer',$body);
        self::assertStringNotContainsString('space-card',$body);
        self::assertStringNotContainsString('feature-course',$body);
        self::assertStringNotContainsString('Fondamentaux de l’apprentissage',$body);
    }

    public function testActiveAccountRendersItsRealCommunityCourseAndResumeLink(): void
    {
        $data=['accessState'=>'active','account'=>['id'=>7,'display_name'=>'Robin','callsign'=>'R'],'tenant'=>['id'=>42,'slug'=>'team-a','name'=>'Team A','plan_slug'=>'pro_plus'],
            'legacyCourses'=>[['slug'=>'published-course','title'=>'Existing LMS course','estimated_minutes'=>45]],
            'legacyEnrollments'=>[['course_slug'=>'published-course','course_title'=>'Existing LMS course','status'=>'in_progress','progress_percent'=>65]],'staff'=>false];
        $body=Response::view('academy.home',$data)->body();
        self::assertStringContainsString('data-access-state="active"',$body);
        self::assertStringContainsString('Team A',$body);
        self::assertStringContainsString('PRO_PLUS',$body);
        self::assertStringContainsString('Robin',$body);
        self::assertStringContainsString('formations/published-course',$body);
        self::assertStringContainsString('65 %',$body);
        self::assertStringContainsString('formations/competences',$body);
        self::assertStringNotContainsString('formation/studio',$body);
    }

    public function testUnpaidAndUnassignedAccountsGetDifferentActionsAndNoLearningData(): void
    {
        $body=Response::view('academy.home',['accessState'=>'unpaid'])->body();
        self::assertStringContainsString('platform/upgrade?from=academy',$body);
        self::assertStringNotContainsString('academy-course-list',$body);
        $body=Response::view('academy.home',['accessState'=>'no_tenant'])->body();
        self::assertStringContainsString('data-access-state="no_tenant"',$body);
        self::assertStringContainsString('/communities',$body);
    }

    public function testPublishedTitlesAreEscapedAndInstructorToolsUseExistingRoutes(): void
    {
        $body=Response::view('academy.home',['accessState'=>'active','staff'=>true,'legacyCourses'=>[['slug'=>'course','title'=>'<script>bad()</script>']]])->body();
        self::assertStringNotContainsString('<script>bad()</script>',$body);
        self::assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;',$body);
        self::assertStringContainsString('formation/studio',$body);
        self::assertStringContainsString('formation/reports',$body);
    }

    public function testRequestedModuleIsPublishedInBothLanguagesAndEnrolmentUsesAProtectedPost(): void
    {
        $course = AcademyBuiltInCourses::find('jtac-licence-ath-academy');
        foreach (['fr', 'en'] as $language) {
            $GLOBALS['__app_locale'] = $language;
            $body = Response::view('academy.home', ['accessState' => 'active', 'academyCourses' => [$course]])->body();
            self::assertStringContainsString($course['title'][$language], $body);
            self::assertStringContainsString('method="post" action="' . url('academy/action/enroll') . '"', $body);
            self::assertStringContainsString('name="_csrf_token"', $body);
            self::assertStringContainsString('name="course" value="jtac-licence-ath-academy"', $body);
            self::assertStringNotContainsString($course['chapters'][0]['theory'][$language], $body);
        }
    }

    public function testModuleRequiresPaidAccessAndDoesNotPretendStorageIsReady(): void
    {
        $course = AcademyBuiltInCourses::find('jtac-licence-ath-academy');
        $body = Response::view('academy.home', ['accessState' => 'unpaid', 'academyCourses' => [$course]])->body();
        self::assertStringNotContainsString('name="course"', $body);
        self::assertStringNotContainsString($course['chapters'][0]['theory']['fr'], $body);
        $body = Response::view('academy.home', ['accessState' => 'active', 'academyCourses' => [$course], 'academyStorageReady' => false])->body();
        self::assertStringContainsString('class="button small" disabled', $body);
    }

    public function testHomeResumesTheCurrentAccountsModuleWithoutCreatingAnotherEnrollment(): void
    {
        $course = AcademyBuiltInCourses::find('jtac-licence-ath-academy');
        $body = Response::view('academy.home', ['accessState' => 'active', 'academyCourses' => [$course],
            'academyStates' => [$course['key'] => ['chapter' => 0, 'stage' => 3, 'snapshot' => $course]],
        ])->body();
        self::assertStringContainsString('academy/classroom?course=jtac-licence-ath-academy', $body);
        self::assertStringNotContainsString('name="course" value="jtac-licence-ath-academy"', $body);
    }
}
