<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakPlainTextAssetTest extends TestCase
{
    public function testAtakPagesUseIcemanStructuredRender(): void
    {
        $root = dirname(__DIR__, 2);
        $fnDir = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions';
        $helper = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setPlainText.sqf'
        );
        $connectCfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $athenaCfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $theme = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/atak_theme.hpp'
        );
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        $hub = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/message_hub_page.hpp'
        );

        self::assertStringContainsString('ctrlSetStructuredText parseText', $helper);
        self::assertStringContainsString('case 38:', $helper);
        self::assertStringContainsString('case 60:', $helper);
        self::assertStringContainsString('case 62:', $helper);
        self::assertStringContainsString('_w < 0.02', $helper);
        self::assertStringContainsString('class setPlainText {}', $connectCfg);
        ModVersion::assertAtLeast('1.0.165', ModVersion::fromConfigCpp($athenaCfg));
        // La classe de thème COMSPEC_ATAK_StructuredText a été retirée : les pages
        // déclarent désormais directement des cadres RscStructuredText (rendu IceMan).
        self::assertStringNotContainsString('class COMSPEC_ATAK_StructuredText', $theme);
        self::assertStringContainsString('class Detail: RscStructuredText', $page);
        self::assertStringContainsString('class Feedback: RscStructuredText', $page);
        self::assertStringContainsString('class Hint: RscStructuredText', $hub);

        // Le helper n'est plus la seule voie : les pages Athena écrivent désormais
        // directement leur HTML IceMan dans des cadres RscStructuredText déclarés
        // (hpp). Le helper reste obligatoire pour les cadres créés à chaud, dont
        // la taille n'est pas garantie (alerte plein écran).
        $alert = (string) file_get_contents($fnDir . '/fn_athena_paintFullscreenAlert.sqf');
        self::assertStringContainsString('ctrlCreate ["RscStructuredText"', $alert);
        self::assertStringContainsString('call comspec_overwatch_connect_fnc_setPlainText', $alert);
        self::assertStringNotContainsString('ctrlSetStructuredText parseText', $alert);
    }
}
