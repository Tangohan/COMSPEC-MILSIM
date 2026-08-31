<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Support\LoginWelcomeGate;
use PHPUnit\Framework\TestCase;

final class LoginWelcomeGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        LoginWelcomeGate::clear();
    }

    protected function tearDown(): void
    {
        LoginWelcomeGate::clear();
        parent::tearDown();
    }

    public function testArmSetsPendingAndContinueUrl(): void
    {
        LoginWelcomeGate::arm('/public/jnet');
        $this->assertTrue(LoginWelcomeGate::isPending());
        $this->assertSame('/public/jnet', LoginWelcomeGate::continueUrl());
    }

    public function testConsumeClearsPendingAndReturnsUrl(): void
    {
        LoginWelcomeGate::arm('/public/dashboard');
        $url = LoginWelcomeGate::consume();
        $this->assertSame('/public/dashboard', $url);
        $this->assertFalse(LoginWelcomeGate::isPending());
    }

    public function testArmEmptyFallsBackToDashboardUrlHelper(): void
    {
        LoginWelcomeGate::arm('   ');
        $this->assertTrue(LoginWelcomeGate::isPending());
        $this->assertNotSame('', LoginWelcomeGate::continueUrl());
    }
}
