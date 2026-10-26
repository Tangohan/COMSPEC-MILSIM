<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Téléphone natif : première sortie en main sans compte Athena, l'app Athena s'ouvre avec un code (page /lier du site). */
final class AtakFirstOpenLinkOfferTest extends TestCase
{
    public function testFirstOpenWithoutAccountLandsOnAthenaApp(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/ui/fn_displayLoad.sqf');
        self::assertStringContainsString('COMSPEC_ATAK_LinkOffered', $src);
        self::assertStringContainsString('comspec_atak_native_fnc_athenaStatus', $src);
        self::assertStringContainsString('["start"] call comspec_atak_native_fnc_athenaLink', $src);
        self::assertStringContainsString('_page = "ATHENA"', $src);
        // Mode local et BattlEye : jamais proposé (clés « off », « unset », « battleye » exclues).
        self::assertStringContainsString('in ["disconnected", "unlinked", "code", "error"]', $src);
        self::assertStringContainsString('[_page] call comspec_atak_native_fnc_navigate;', $src);
    }
}
