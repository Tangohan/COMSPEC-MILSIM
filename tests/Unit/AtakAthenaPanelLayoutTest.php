<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAthenaPanelLayoutTest extends TestCase
{
    public function testAthenaTileShowsConnectionAndProfileOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $hpp = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        $upd = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf'
        );
        $lay = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $auth = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_authAction.sqf'
        );

        self::assertStringContainsString('text = "Connexion"', $hpp);
        self::assertStringContainsString('idc = 9734', $hpp);
        self::assertStringContainsString('athena_homeAction', $hpp);
        self::assertStringContainsString('idc = 9701', $hpp);
        self::assertStringContainsString('idc = 9805', $hpp);
        self::assertStringContainsString('idc = 9806', $hpp);
        self::assertStringContainsString('Déconnecter', $hpp);
        self::assertStringContainsString('Rouvrir canal', $hpp);
        self::assertStringContainsString('idc = 9770', $hpp);
        self::assertStringContainsString('idc = 9773', $hpp);

        self::assertStringContainsString('Liaison OK', $lay);
        self::assertStringContainsString('Connexion Athena', $lay);
        self::assertStringContainsString('9805', $lay);
        self::assertStringContainsString('9806', $lay);
        self::assertStringContainsString('9770', $lay);
        self::assertStringContainsString('ctrlPosition _group', $lay);

        self::assertStringContainsString('Steam non associé', $upd);
        self::assertStringContainsString('Compte non connecté', $upd);
        self::assertStringContainsString('AFFECTATION', $upd);
        self::assertStringContainsString('Canal poste', $upd);
        self::assertStringContainsString('Position remontée', $upd);
        self::assertStringContainsString('Temps de mission', $upd);
        self::assertStringContainsString('comspec_profile_name', $upd);
        self::assertStringContainsString('COMSPEC_SteamLinked', $upd);
        self::assertStringNotContainsString('packet_loss', $upd);

        self::assertStringContainsString('logout', $auth);
        self::assertStringContainsString('1.0.82', $cfg);
        self::assertStringContainsString('athena_homeAction', $cfg);
        self::assertStringContainsString('athena_selectHome', $cfg);
    }
}
