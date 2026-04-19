<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Controllers\Api\IntegrationsPublicEventsController;
use App\Controllers\Api\MePreferencesApiController;
use App\Core\Request;
use App\Repositories\CommunityEventRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserUiPreferencesRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;
use App\Services\Profile\UserUiPreferencesValidationService;
use PHPUnit\Framework\TestCase;

final class CriticalApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test',
        ];
    }

    public function testIntegrationsPublicEventsReturnsStandardizedErrorEnvelopeWhenContextIsMissing(): void
    {
        $events = $this->createMock(CommunityEventRepository::class);
        $featureGate = $this->createMock(FeatureGateService::class);

        $controller = new IntegrationsPublicEventsController($events, $featureGate);
        $response = $controller->upcoming(new Request());
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(400, $response->statusCode());
        $this->assertSame(false, $payload['success']);
        $this->assertSame('invalid_context', $payload['error']['code']);
        $this->assertArrayHasKey('message', $payload['error']);
    }

    public function testIntegrationsPublicEventsContractForEnabledModule(): void
    {
        $events = $this->createMock(CommunityEventRepository::class);
        $featureGate = $this->createMock(FeatureGateService::class);

        $featureGate
            ->expects($this->once())
            ->method('allowsLimitedFeatureModule')
            ->with(42, 'events')
            ->willReturn(true);

        $events
            ->expects($this->once())
            ->method('upcomingForTenant')
            ->with(42, 100)
            ->willReturn([
                [
                    'title' => 'Op Night',
                    'starts_at' => '2026-05-01 20:00:00',
                    'ends_at' => null,
                    'location' => 'Altis',
                    'event_type' => 'training',
                ],
            ]);

        $controller = new IntegrationsPublicEventsController($events, $featureGate);
        $request = new Request();
        $request->setAttribute('integration_tenant_id', 42);

        $response = $controller->upcoming($request);
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->statusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('Op Night', $payload['events'][0]['title']);
        $this->assertArrayHasKey('starts_at', $payload['events'][0]);
        $this->assertArrayHasKey('type', $payload['events'][0]);
    }

    public function testMePreferencesUnauthorizedContractIsStandardized(): void
    {
        $auth = $this->createMock(AuthService::class);
        $uiRepo = $this->createMock(UserUiPreferencesRepository::class);
        $notifRepo = $this->createMock(UserNotificationPreferencesRepository::class);
        $validation = $this->createMock(UserUiPreferencesValidationService::class);

        $auth->method('user')->willReturn(null);

        $controller = new MePreferencesApiController($auth, $uiRepo, $notifRepo, $validation);
        $response = $controller->handle(new Request());
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(401, $response->statusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('unauthorized', $payload['error']['code']);
        $this->assertSame('Non autorisé.', $payload['error']['message']);
    }
}
