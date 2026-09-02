<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\PasswordResetService;
use PHPUnit\Framework\TestCase;

final class PasswordResetServiceTest extends TestCase
{
    public function testLiveHumanAccountIsResettable(): void
    {
        self::assertTrue(PasswordResetService::isResettableAccount([
            'id' => 4,
            'email' => 'pilot@example.test',
            'status' => 'active',
            'is_service_account' => 0,
        ]));
        self::assertTrue(PasswordResetService::isResettableAccount([
            'email' => 'wait@example.test',
            'status' => 'pending_verification',
        ]));
    }

    public function testMergedDeletedAndServiceAccountsAreNotResettable(): void
    {
        self::assertFalse(PasswordResetService::isResettableAccount(null));
        self::assertFalse(PasswordResetService::isResettableAccount([
            'email' => 'merged+9@merged.invalid',
            'status' => 'merged',
        ]));
        self::assertFalse(PasswordResetService::isResettableAccount([
            'email' => 'gone@deleted.invalid',
            'status' => 'active',
        ]));
        self::assertFalse(PasswordResetService::isResettableAccount([
            'email' => 'cron@example.test',
            'status' => 'active',
            'is_service_account' => 1,
        ]));
        self::assertFalse(PasswordResetService::isResettableAccount([
            'email' => 'x@example.test',
            'status' => 'deleted',
        ]));
    }
}
