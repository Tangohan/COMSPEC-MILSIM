<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyLmsBridge;
use App\Repositories\TrainingEnrollmentRepository;
use App\Services\Training\TrainingService;
use PHPUnit\Framework\TestCase;

final class AcademyLmsBridgeTest extends TestCase
{
    public function testReusesNativeCatalogueAndProgressForTheCurrentAccountAndTenant(): void
    {
        $training = $this->createMock(TrainingService::class);
        $enrollments = $this->createMock(TrainingEnrollmentRepository::class);
        $native = [['id'=>101,'slug'=>'existing-course','title'=>'Published course']];
        $training->expects(self::once())->method('getCatalogue')->with(42,7)->willReturn($native);
        $enrollments->expects(self::once())->method('listByUserId')->with(7,42)->willReturn([
            ['id'=>11,'status'=>'in_progress','course_slug'=>'existing-course'],
            ['id'=>12,'status'=>'pending_approval'],
            ['id'=>13,'status'=>'revoked'],
            ['id'=>14,'status'=>'withdrawn'],
            ['id'=>15,'status'=>'expired'],
        ]);
        $training->expects(self::once())->method('getGlobalProgress')->with(11)->willReturn(64.5);
        $data = (new AcademyLmsBridge($training,$enrollments))->load(42,7);
        self::assertSame($native,$data['legacyCourses']);
        self::assertCount(2,$data['legacyEnrollments']);
        self::assertSame(65,$data['legacyEnrollments'][0]['progress_percent']);
        self::assertSame(0,$data['legacyEnrollments'][1]['progress_percent']);
    }

    public function testNoAccountOrTenantCannotReadAnotherCommunityCatalogue(): void
    {
        $training = $this->createMock(TrainingService::class);
        $enrollments = $this->createMock(TrainingEnrollmentRepository::class);
        $training->expects(self::never())->method('getCatalogue');
        $enrollments->expects(self::never())->method('listByUserId');
        $bridge = new AcademyLmsBridge($training,$enrollments);
        self::assertSame(['legacyCourses'=>[],'legacyEnrollments'=>[]],$bridge->load(0,7));
        self::assertSame(['legacyCourses'=>[],'legacyEnrollments'=>[]],$bridge->load(42,0));
    }
    public function testLegacyModulesAreRecoveredWithoutReplacingNewerCoursesWithTheSameSlug(): void
    {
        $training = $this->createMock(TrainingService::class);
        $enrollments = $this->createMock(TrainingEnrollmentRepository::class);
        $legacy = $this->createMock(\App\Repositories\TrainingRepository::class);
        $training->method('getCatalogue')->with(42,7)->willReturn([['slug'=>'shared','title'=>'Modern version']]);
        $enrollments->method('listByUserId')->with(7,42)->willReturn([]);
        $legacy->expects(self::once())->method('listPublishedForTenant')->with(42)->willReturn([
            ['slug'=>'shared','title'=>'Old version'], ['slug'=>'legacy-only','title'=>'Existing module','summary'=>'Published resource'],
        ]);
        $data=(new AcademyLmsBridge($training,$enrollments,$legacy))->load(42,7);
        self::assertCount(2,$data['legacyCourses']);
        self::assertSame('Modern version',$data['legacyCourses'][0]['title']);
        self::assertSame('legacy-only',$data['legacyCourses'][1]['slug']);
        self::assertSame('Published resource',$data['legacyCourses'][1]['short_description']);
    }
}
