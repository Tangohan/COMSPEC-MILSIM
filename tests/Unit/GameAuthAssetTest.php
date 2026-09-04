<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GameAuthAssetTest extends TestCase
{
    public function testGameAuthApiIsWiredAndDoesNotTrustClientTenant(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $svc = (string) file_get_contents($root . '/app/Services/Game/GameAuthService.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/Game/GameAuthApiController.php');
        $auth = (string) file_get_contents($root . '/app/Support/ComspecApiKeyAuth.php');
        $sqfInit = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_initAuth.sqf');
        $sqfConnect = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_connect.sqf');
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        $pos = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf');
        $wait = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_waitAthenaReady.sqf');
        $bo = (string) file_get_contents($root . '/views/admin/atak-config/_game_experience.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $sqfPoll = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf');
        $sqfRestore = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_restoreSession.sqf');
        $exp = (string) file_get_contents($root . '/app/Services/Game/GameOverwatchExperienceService.php');

        self::assertStringContainsString('/api/game/v1/auth/password', $routes);
        self::assertStringContainsString('/api/game/v1/auth/otp/request', $routes);
        self::assertStringContainsString('/api/game/v1/auth/steam/exchange', $routes);
        self::assertStringContainsString('/api/game/v1/session/restore', $routes);
        self::assertStringContainsString('/api/game/v1/bootstrap', $routes);
        self::assertStringContainsString('pickMembership', $svc);
        self::assertStringContainsString('STEAM_NOT_LINKED', $svc);
        self::assertStringContainsString('pairing_token', $svc);
        self::assertStringContainsString('acceptGameSessionToken', $auth);
        self::assertStringContainsString('/api/game/v1/auth/', $auth);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_restoreSession', $sqfInit);
        self::assertStringContainsString('loginSteam', $sqfInit);
        $sqfSteam = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_loginSteam.sqf');
        self::assertStringContainsString('Connexion Steam — %1', $sqfSteam);
        self::assertStringContainsString('plus une saisie joueur', $sqfConnect);
        self::assertStringNotContainsString('LinkBySteam', $sqfConnect);
        self::assertStringContainsString('CryptProtectData', $dll);
        self::assertStringContainsString('AuthPassword', $dll);
        self::assertStringContainsString('CallsignCell', $dll);
        self::assertStringContainsString('OperatorTacticalIdentity', $svc);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_isReady', $pos);
        self::assertStringContainsString('Pas de session', $wait);
        self::assertStringContainsString('Expérience en jeu', $bo);
        self::assertStringContainsString('OVERWATCH_GAME_AUTH_V1', $catalog);
        self::assertStringContainsString('detected_mod_version', $svc);
        self::assertStringContainsString('Pack actuel', $sqfPoll);
        self::assertStringContainsString('version exigée', $sqfPoll);
        self::assertStringContainsString('authStateCells', $sqfPoll);
        self::assertStringContainsString('Communauté :', $sqfPoll);
        self::assertStringNotContainsString('_s = str _s', $sqfPoll);
        self::assertStringContainsString('_minModRequired', $dll);
        self::assertStringContainsString('CaptureVersionHints', $dll);
        self::assertStringContainsString('fnc_packVersion', $sqfRestore);
        self::assertStringNotContainsString('openLogin', $sqfRestore);
        self::assertStringContainsString('Pack Overwatch minimal', $bo);
        self::assertStringContainsString("'min_mod_version' => '1.5.0'", $exp);
        self::assertFileExists($root . '/bootstrap/athena_game_auth_migration.php');
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_athena_auth.hpp');
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_packVersion.sqf');
        self::assertStringNotContainsString('php://input', $ctrl);
    }

    public function testSteamIdMustBeLinkedOnAnAthenaAccount(): void
    {
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Game/GameAuthService.php');
        $dll = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        self::assertStringContainsString('resolveAccountByLinkedSteam', $svc);
        self::assertStringContainsString('findBySteamId', $svc);
        self::assertStringContainsString('pairing_token', $svc);
        self::assertStringContainsString('STEAM_NOT_LINKED', $svc);
        self::assertStringNotContainsString('if (pairing.Length < 32)', $dll);
        self::assertStringContainsString('AuthSteam', $dll);
        self::assertStringContainsString('replaceSteamId', $svc);
        self::assertStringContainsString('$user = $this->users->findBySteamId($steamId);', $svc);
    }

    public function testSteamSessionBecomesReadyEvenIfC2PingFails(): void
    {
        $dll = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        self::assertStringContainsString('FinishGameAuthReady', $dll);
        self::assertStringContainsString('C2_DEGRADED', $dll);
        self::assertStringContainsString('return FinishGameAuthReady(verify);', $dll);
        self::assertStringNotContainsString('return "ERR|C2_UNAVAILABLE";', $dll);
    }

    public function testMissingSteamOnPositionIsLoggedOncePerWindow(): void
    {
        $guard = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/AtakArmaWriteGuard.php');
        self::assertStringContainsString('logThrottled', $guard);
        self::assertStringContainsString("'steam_required'", $guard);
        self::assertStringContainsString('300,', $guard);
    }

    public function testPasswordAuthDoesNotRequireASteamIdToIssueTokens(): void
    {
        $root = dirname(__DIR__, 2);
        $svc = (string) file_get_contents($root . '/app/Services/Game/GameAuthService.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AthenaAccountRepository.php');
        self::assertStringContainsString('function resolveSteamId(array $body, array $account): string', $svc);
        self::assertStringContainsString('$steamId = $this->resolveSteamId($body, $account);', $svc);
        self::assertStringContainsString('if ($this->hasSteamId($steamId)) {', $svc);
        self::assertStringNotContainsString('if ($steamId !== \'\') {', $svc);
        self::assertStringContainsString('upsertPairing((int) $account[\'id\'], $deviceId, $steamId, $pairingHash)', $svc);
        self::assertStringContainsString('carrySteamIdFromSession', $svc);
        self::assertStringContainsString('function upsertPairing(int $accountId, string $deviceId, ?string $steamId, string $tokenHash)', $repo);
        self::assertStringContainsString('if ($accountId <= 0 || $deviceId === \'\' || $steamId === \'\' || $tokenHash === \'\')', $repo);
        self::assertStringContainsString('function assignSteamIdIfEmpty(int $accountId, string $steamId): bool', $repo);
        self::assertStringContainsString('attachSteamFromEmailLogin', $svc);
        self::assertStringContainsString('GAME_STEAM_LINKED_MEMBER', $svc);
        self::assertStringContainsString('GAME_STEAM_LINKED_STAFF', $svc);
        self::assertStringContainsString('issueForAccount($account, $body, null, true)', $svc);
        self::assertStringContainsString("'steam_message'", $svc);
    }

    public function testResolveSteamIdReturnsEmptyStringWhenMissingOrInvalid(): void
    {
        $ref = new \ReflectionClass(\App\Services\Game\GameAuthService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('resolveSteamId');
        $method->setAccessible(true);

        self::assertSame('', $method->invoke($svc, [], []));
        self::assertSame('', $method->invoke($svc, ['steam_id' => ''], []));
        self::assertSame('', $method->invoke($svc, ['steam_id' => '_SP_player'], []));
        self::assertSame('', $method->invoke($svc, ['steam_id' => 'LOCAL'], ['steam_id' => null]));
        self::assertSame('76561198000000000', $method->invoke($svc, ['steam_id' => '76561198000000000'], []));
        self::assertSame(
            '76561198000000000',
            $method->invoke($svc, ['steam_id' => ''], ['steam_id' => '76561198000000000'])
        );
        self::assertSame(
            '76561198000000000',
            $method->invoke($svc, ['steam_id' => '_SP_player'], ['steam_id' => '76561198000000000'])
        );
    }

    public function testNullSteamIdIsNotTreatedAsPresent(): void
    {
        $ref = new \ReflectionClass(\App\Services\Game\GameAuthService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $has = $ref->getMethod('hasSteamId');
        $has->setAccessible(true);

        self::assertFalse($has->invoke($svc, null));
        self::assertFalse($has->invoke($svc, ''));
        self::assertFalse($has->invoke($svc, 76561198000000000));
        self::assertTrue($has->invoke($svc, '76561198000000000'));
        self::assertTrue(null !== '');
    }

    public function testRestoreReusesSessionSteamIdWhenTheClientOmitsIt(): void
    {
        $ref = new \ReflectionClass(\App\Services\Game\GameAuthService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('carrySteamIdFromSession');
        $method->setAccessible(true);

        $body = [];
        $method->invokeArgs($svc, [&$body, ['steam_id' => '76561198000000000']]);
        self::assertSame('76561198000000000', $body['steam_id']);

        $body = ['steam_id' => ''];
        $method->invokeArgs($svc, [&$body, ['steam_id' => null]]);
        self::assertSame('', $body['steam_id']);
    }

    public function testRestoreSteamMustMatchTheAccountOrSelectedEffectifsRecord(): void
    {
        $ref = new \ReflectionClass(\App\Services\Game\GameAuthService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('verifyRestoredSteam');
        $method->setAccessible(true);

        $account = ['id' => 10, 'steam_id' => '76561198000000000'];
        $membership = ['user_id' => 20, 'tenant_id' => 30, 'user_steam_id' => '76561198000000000'];
        self::assertSame('76561198000000000', $method->invokeArgs($svc, [&$account, $membership, '76561198000000000']));

        $account = ['id' => 10, 'steam_id' => '76561198000000001'];
        $membership['user_steam_id'] = '76561198000000001';
        self::assertSame('', $method->invokeArgs($svc, [&$account, $membership, '76561198000000000']));

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Game/GameAuthService.php');
        self::assertStringContainsString("return \$this->fail('STEAM_NOT_LINKED', 403);", $source);
        self::assertStringContainsString("\$body['_verify_restored_steam'] = true;", $source);
    }
}
