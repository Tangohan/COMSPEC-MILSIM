<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AcademyController;
use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Middleware\AcademyPaidMiddleware;
use App\Repositories\CommunityEventRepository;
use App\Repositories\PlatformUsageRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TenantUsageCounterRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingRepository;
use App\Repositories\UserRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise the validated-session callback, the real native feature gate and the real Academy bridge. */
final class AcademyHomeAccessTest extends TestCase
{
    private array $instances;
    private \ReflectionProperty $container;

    protected function setUp(): void
    {
        $this->container = new \ReflectionProperty(Container::class, 'instances');
        $this->instances = $this->container->getValue();
        Gate::reset();
        $_SESSION = ['user_id'=>7, 'tenant_id'=>10, '_csrf_token'=>str_repeat('a',64)];
        $_GET = $_POST = [];
        $_SERVER['REQUEST_METHOD']='GET';
        $_SERVER['REQUEST_URI']='/academy';
        $GLOBALS['__app_locale']='fr';
    }

    protected function tearDown(): void
    {
        $this->container->setValue(null,$this->instances);
        Gate::reset();
        unset($GLOBALS['__app_locale']);
    }

    public static function proPlusEntitlements(): array
    {
        $tenant=['id'=>10,'slug'=>'team-a','name'=>'Team A','plan_slug'=>'pro_plus','subscription_status'=>'active'];
        return [
            'active subscription'=>[$tenant],
            'assigned in platform administration'=>[array_replace($tenant,['subscription_status'=>'none'])],
            'current gift on free base plan'=>[array_replace($tenant,['plan_slug'=>'free','subscription_status'=>'none','settings'=>json_encode(['plan_gift'=>['plan_slug'=>'pro_plus','ends_at'=>null]])])],
            'active provider supersedes old manual period'=>[$tenant+['paypal_subscription_id'=>'fixture-subscription','settings'=>['manual_billing'=>['ends_at'=>'2000-01-01']]]],
        ];
    }

    #[DataProvider('proPlusEntitlements')]
    public function testProPlusCanEnterAndSeeItsExistingCourses(array $tenant): void
    {
        $users=$this->createMock(UserRepository::class);
        $users->method('findById')->with(7)->willReturn(['id'=>7,'display_name'=>'Robin','tenant_id'=>10]);
        $tenants=$this->createMock(TenantRepository::class);
        $tenants->method('findById')->with(10)->willReturn($tenant);
        $plans=$this->createMock(SubscriptionPlanRepository::class);
        $plans->expects(self::once())->method('findBySlug')->with('pro_plus')->willReturn(['slug'=>'pro_plus','features_json'=>'{"training":true}']);
        $courses=$this->createMock(TrainingCourseRepository::class);
        $feature=new FeatureGateService($tenants,$plans,$users,
            (new \ReflectionClass(TenantUsageCounterRepository::class))->newInstanceWithoutConstructor(),
            $this->createMock(PlatformUsageRepository::class),
            $this->createMock(CommunityEventRepository::class),$courses);
        $training=$this->createMock(TrainingService::class);
        $training->expects(self::once())->method('getCatalogue')->with(10,7)->willReturn([
            ['id'=>99,'slug'=>'existing-course','title'=>'Formation existante','estimated_minutes'=>45],
        ]);
        $training->method('getGlobalProgress')->with(11)->willReturn(50.0);
        $enrollments=$this->createMock(TrainingEnrollmentRepository::class);
        $enrollments->method('listByUserId')->with(7,10)->willReturn([
            ['id'=>11,'status'=>'in_progress','course_slug'=>'existing-course','course_title'=>'Formation existante'],
        ]);
        $legacy=$this->createMock(TrainingRepository::class);
        $legacy->method('listPublishedForTenant')->with(10)->willReturn([]);
        $this->container->setValue(null,[UserRepository::class=>$users,TenantRepository::class=>$tenants,
            FeatureGateService::class=>$feature,TrainingService::class=>$training,
            TrainingEnrollmentRepository::class=>$enrollments,TrainingRepository::class=>$legacy]);

        $render=new \ReflectionMethod(AcademyController::class,'homeForCurrentAccount');
        $response=(new AcademyPaidMiddleware())(new Request(),fn(Request $request)=>$render->invoke(new AcademyController(),$request));
        self::assertSame(200,$response->statusCode());
        self::assertSame('private, no-store',$response->headerValue('Cache-Control'));
        $body=$response->body();
        self::assertStringContainsString('data-access-state="active"',$body);
        self::assertStringContainsString('PRO_PLUS',$body);
        self::assertStringContainsString('Formation existante',$body);
        self::assertStringContainsString('/formations/existing-course',$body);
        self::assertStringNotContainsString('data-access-state="unpaid"',$body);
    }
}
