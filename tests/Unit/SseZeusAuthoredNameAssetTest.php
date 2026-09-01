<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Zeus « Profil d'identité SSE » : Nom + Prénom doivent entrer dans l'identité
 * générée, pas seulement dans les champs du terminal.
 */
final class SseZeusAuthoredNameAssetTest extends TestCase
{
    public function testApplyProfilePushesNamesIntoSseIdentity(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseApplyProfile.sqf'
        );
        self::assertStringContainsString('comspec_sse_fnc_setIdentity', $sqf);
        self::assertStringContainsString('["name", _full]', $sqf);
        self::assertStringContainsString('["first_name", _first]', $sqf);
        self::assertStringContainsString('["last_name", _last]', $sqf);
        self::assertStringContainsString('1.4.95', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        ));
    }

    public function testAuthoredBridgeWritesBackIntoIdentitySection(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/core/functions/fn_syncIdentityBridgeVars.sqf'
        );
        self::assertStringContainsString('_identity set ["name", _full]', $sqf);
        self::assertStringContainsString('comspec_sse_fnc_setSection', $sqf);
        self::assertStringContainsString('0.7.18', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/main/script_mod.hpp'
        ));
    }

    public function testAthenaPayloadPrefersAuthoredNames(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/network/functions/fn_buildAthenaPersonPayload.sqf'
        );
        self::assertStringContainsString('COMSPEC_SSE_NameAuthored', $sqf);
        self::assertStringContainsString('COMSPEC_SSE_FirstName', $sqf);
        self::assertStringContainsString('COMSPEC_SSE_LastName', $sqf);
    }
}
