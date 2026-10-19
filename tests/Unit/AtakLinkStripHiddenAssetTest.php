<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakLinkStripHiddenAssetTest extends TestCase
{
    public function testBottomDataBarIsRemovedAndNeverPainted(): void
    {
        $root = dirname(__DIR__, 2);
        $strip = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateLinkStrip.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $preInit = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $postInit = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf'
        );
        $settings = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-16-atak-barre-donnees-bas.md'
        );

        ModVersion::assertAtLeast('1.0.165', ModVersion::fromConfigCpp($cfg));
        // L'Athena gelé a retrouvé le peintre du bandeau (99871) : il n'est plus jamais peint parce que
        // le réglage est forcé à faux et que la sortie « masqué » passe avant toute création.
        self::assertStringContainsString('99871', $strip);
        $gate = strpos($strip, 'if (!_visible) exitWith {');
        $create = strpos($strip, 'ctrlCreate');
        self::assertNotFalse($gate);
        self::assertNotFalse($create);
        self::assertLessThan($create, $gate);
        self::assertStringContainsString('_ctrl ctrlShow false', substr($strip, $gate, 200));

        self::assertStringContainsString('"comspec_overwatch_show_link_strip"', $preInit);
        self::assertMatchesRegularExpression(
            '/"comspec_overwatch_show_link_strip"[\s\S]{0,800}?false\s*\] call (?:CBA_fnc_addSetting|comspec_overwatch_connect_fnc_addSetting)/',
            $preInit
        );
        self::assertStringContainsString('[false, true] call comspec_overwatch_connect_fnc_linkStripApplySetting', $postInit);
        self::assertStringNotContainsString('COMSPEC_LinkStripVisible", "UNSET"', $postInit);

        // La mention « n'est plus affichée » vit désormais dans la description du réglage (preInit) ;
        // la page Paramètres d'Athena (gelée) garde un simple sélecteur de la barre.
        self::assertStringContainsString('plus affich', $preInit);
        self::assertStringContainsString('class ComboLinkStrip', $settings);
        self::assertStringContainsString('barre de données', strtolower($bug));
        self::assertStringContainsString('1.0.143', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
