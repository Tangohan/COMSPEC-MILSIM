<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Game\GameOverwatchExperienceService;
use App\Support\GameFeatureGate;
use PHPUnit\Framework\TestCase;

/** Réglages « Connexion en jeu » réellement appliqués : fonctions coupées, méthodes de connexion. */
final class GameFeatureGateTest extends TestCase
{
    public function testFeatureForRoutes(): void
    {
        self::assertSame('chat_enabled', GameFeatureGate::featureFor('POST', '/api/chat'));
        self::assertSame('jtac_enabled', GameFeatureGate::featureFor('post', 'api/nine-line'));
        self::assertSame('photos_enabled', GameFeatureGate::featureFor('POST', '/api/recon/images'));
        self::assertSame('markers_enabled', GameFeatureGate::featureFor('POST', '/api/atak/marker'));
        self::assertSame(null, GameFeatureGate::featureFor('GET', '/api/chat'));
        self::assertSame(null, GameFeatureGate::featureFor('POST', '/api/chat/12/delete'));
        self::assertSame(null, GameFeatureGate::featureFor('POST', '/api/atak/position'));
    }

    public function testDenial(): void
    {
        self::assertSame(null, GameFeatureGate::denial(['chat_enabled' => true], 'chat_enabled'));
        self::assertSame(null, GameFeatureGate::denial([], 'chat_enabled'));
        $d = GameFeatureGate::denial(['chat_enabled' => false], 'chat_enabled');
        self::assertSame('feature_disabled', $d['error']);
        self::assertSame(null, GameFeatureGate::denial(['chat_enabled' => false], null));
    }

    public function testAuthMethods(): void
    {
        self::assertSame(['password' => true, 'otp' => true, 'steam' => true], GameOverwatchExperienceService::authMethods([]));
        self::assertSame(['password' => true, 'otp' => false, 'steam' => true], GameOverwatchExperienceService::authMethods(['auth_otp' => false]));
    }
}
