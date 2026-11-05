<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\DiscordCompanionSettings;
use App\Support\SecretCipher;
use PHPUnit\Framework\TestCase;

final class DiscordCompanionSettingsTest extends TestCase
{
    public function testApiControllerIsRegisteredAndRejectsUnauthenticatedRequestsBeforeDatabaseAccess(): void
    {
        self::assertInstanceOf(\App\Controllers\Api\DiscordCompanionController::class, \App\Core\Container::get(\App\Controllers\Api\DiscordCompanionController::class));
        $settings = new DiscordCompanionSettings(static fn (): string => SecretCipher::encrypt(str_repeat('a', 64)));
        $before = $_SERVER;
        try {
            unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            $controller = new \App\Controllers\Api\DiscordCompanionController($settings);
            self::assertSame(401, $controller->handle(new \App\Core\Request())->statusCode());
        } finally {
            $_SERVER = $before;
        }
    }

    public function testPrivateBotApiRejectsMissingWrongAndUnencryptedCredentials(): void
    {
        $secret = str_repeat('a', 64);
        $settings = new DiscordCompanionSettings(static fn (string $key): string => SecretCipher::encrypt($secret));
        self::assertTrue($settings->accepts('Bearer ' . $secret));
        self::assertFalse($settings->accepts('Bearer ' . str_repeat('b', 64)));
        self::assertFalse($settings->accepts(''));
        self::assertFalse($settings->accepts('Basic ' . $secret));
        self::assertFalse((new DiscordCompanionSettings(static fn (): string => ''))->accepts('Bearer '));
        self::assertFalse((new DiscordCompanionSettings(static fn (): string => $secret))->accepts('Bearer ' . $secret));
    }
}
