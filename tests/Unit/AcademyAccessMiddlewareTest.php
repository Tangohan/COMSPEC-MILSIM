<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\AcademyPaidMiddleware;
use App\Repositories\TenantRepository;
use PHPUnit\Framework\TestCase;

final class AcademyAccessMiddlewareTest extends TestCase
{
    private array $previous;
    private \ReflectionProperty $instances;

    protected function setUp(): void
    {
        $this->instances = new \ReflectionProperty(Container::class, 'instances');
        $this->previous = $this->instances->getValue();
        $_SESSION = ['tenant_id' => 10, 'user_id' => 1];
        $_GET = $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->instances->setValue(null, $this->previous);
    }

    public function testPaidPlanPassesAndLearningResponsesCannotBeCached(): void
    {
        $repo = $this->createMock(TenantRepository::class);
        $repo->expects(self::once())->method('findById')->with(10)->willReturn(['id'=>10,'plan_slug'=>'pro','subscription_status'=>'active']);
        $this->instances->setValue(null, [TenantRepository::class => $repo]);
        $response = (new AcademyPaidMiddleware())(new Request(), fn($request) => (new Response())->setBody('protected'));
        self::assertSame('protected', $response->body());
        self::assertSame('private, no-store', $response->headerValue('Cache-Control'));
    }

    public function testProPlusCanReachProtectedReadAndWriteHandlers(): void
    {
        $repo = $this->createMock(TenantRepository::class);
        $repo->expects(self::exactly(2))->method('findById')->with(10)->willReturn(['id'=>10,'plan_slug'=>'PRO_PLUS','subscription_status'=>'active']);
        $this->instances->setValue(null, [TenantRepository::class => $repo]);
        foreach (['GET','POST'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $method === 'GET' ? '/academy/dashboard' : '/academy/action/notes';
            $response = (new AcademyPaidMiddleware())(new Request(), fn ($request) => (new Response())->setBody('protected'));
            self::assertSame(200, $response->statusCode());
            self::assertSame('protected', $response->body());
            self::assertSame('private, no-store', $response->headerValue('Cache-Control'));
        }
    }

    public function testFreePlanCannotReachProtectedReadOrWriteHandlers(): void
    {
        $repo = $this->createMock(TenantRepository::class);
        $repo->method('findById')->willReturn(['id'=>10,'plan_slug'=>'free','subscription_status'=>'active']);
        $this->instances->setValue(null, [TenantRepository::class => $repo]);
        foreach (['GET','POST'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = '/academy/action/exam_submit';
            $response = (new AcademyPaidMiddleware())(new Request(), function ($request) { self::fail('Protected handler was reached'); });
            self::assertSame(302, $response->statusCode());
            self::assertStringContainsString('academy?access=paid', $response->headerValue('Location'));
        }
    }

    public function testMissingMembershipFailsClosed(): void
    {
        $repo = $this->createMock(TenantRepository::class);
        $repo->method('findById')->willReturn(null);
        $this->instances->setValue(null, [TenantRepository::class => $repo]);
        $response = (new AcademyPaidMiddleware())(new Request(), function ($request) { self::fail('Protected handler was reached'); });
        self::assertSame(302, $response->statusCode());
    }
}
