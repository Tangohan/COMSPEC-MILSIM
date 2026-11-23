<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bouton « LiveConf » de l'écran Zeus (4.32.1) : logo du mod (PAA du PBO) + nom, masqué avec l'ATH Zeus (Retour arrière).
 */
final class AtakZeusLiveConfButtonAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testButtonShowsLogoAndLiveConfOnly(): void
    {
        $c = $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        self::assertStringContainsString('_b ctrlSetText "LiveConf";', $c);
        self::assertStringContainsString('_b ctrlSetTooltip "LiveConf";', $c);
        self::assertStringContainsString('_lg ctrlSetText "\z\comspec_atak_native\addons\main\data\logo_athena.paa";', $c);
        self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . 'data/logo_athena.paa');
        // Plus de libellé « CENTRE ZEUS » sur le bouton ni sur le titre du Centre (seule reste la section du relevé SERVEUR).
        self::assertSame(1, substr_count($c, '"CENTRE ZEUS"'));
        self::assertStringContainsString('"CENTRE ZEUS" call _sec;', $c);
        self::assertStringNotContainsString('("CENTRE ZEUS") call _tr', $c);
        self::assertStringContainsString('["toggle"] call comspec_atak_native_fnc_zeusCentre; }];', $c);
    }

    public function testButtonFollowsTheZeusInterface(): void
    {
        $c = $this->read(self::MAIN . 'functions/zeus/fn_zeusCentre.sqf');
        self::assertStringContainsString('case "ath": {', $c);
        self::assertStringContainsString('_x ctrlShow _vis;', $c);
        self::assertStringContainsString('displayAddEventHandler ["KeyDown"', $c);
        self::assertStringContainsString('isEqualTo 14', $c);
        self::assertStringContainsString('CBA_fnc_addPerFrameHandler', $c);
        self::assertStringContainsString('CBA_fnc_removePerFrameHandler', $c);
    }

    public function testRenamedLabelsAreInBothDictionaries(): void
    {
        foreach (['Sources/addons/main/i18n/en.txt', 'localisation/template/xx.txt'] as $rel) {
            $d = $this->read('mod/COMSPEC_ATAK_Native/' . $rel);
            self::assertMatchesRegularExpression('/^\["LiveConf", ("", )?"LiveConf"\]/m', $d, $rel);
            self::assertStringContainsString('["Ouvrir LiveConf"', $d);
            self::assertStringNotContainsString('["Centre Zeus"', $d);
        }
    }
}
