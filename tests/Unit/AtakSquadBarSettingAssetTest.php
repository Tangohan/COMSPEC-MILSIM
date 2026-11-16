<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : réglette d'effectif de la carte (fn_squadBar) masquable par un réglage du joueur, et dessin sobre
 * (cases teintées, pastille du chef, filet d'accent pour moi, compteur collé aux cases).
 */
final class AtakSquadBarSettingAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const KEY = 'comspec_atak_native_map_squadbar';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testTheSettingIsAClientCheckboxOnByDefault(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        // Portée 0 : réglage du joueur (pas imposé par le serveur), coché par défaut.
        self::assertMatchesRegularExpression('/\["' . self::KEY . '", "CHECKBOX",\s*\["Afficher la réglette d\'effectif sur la carte", "[^"]+"\],\s*\["COMSPEC", "ATAK · Carte"\], true, 0, \{/', $pre);
        self::assertStringContainsString('["MAP"] call comspec_atak_native_fnc_pageRender;', $pre);
    }

    public function testMapAndBarHonourTheSettingAndDefaultToShown(): void
    {
        $map = self::read(self::MAIN . 'functions/pages/fn_pageMap.sqf');
        $bar = self::read(self::MAIN . 'functions/teams/fn_squadBar.sqf');
        // Absent = comportement d'avant (affichée) : seule la valeur false masque.
        self::assertStringContainsString('(missionNamespace getVariable ["' . self::KEY . '", true]) isNotEqualTo false', $map);
        self::assertStringContainsString('(missionNamespace getVariable ["' . self::KEY . '", true]) isEqualTo false) then { [] }', $bar);
        self::assertStringContainsString('"' . self::KEY . '"', self::read(self::MAIN . 'functions/pages/fn_pageSettings.sqf'));
        // Rien par image.
        self::assertStringNotContainsString(self::KEY, self::read(self::MAIN . 'functions/map/fn_mapOnDraw.sqf'));
    }

    public function testTheStripIsSoberAndAsWideAsItsCells(): void
    {
        $bar = self::read(self::MAIN . 'functions/teams/fn_squadBar.sqf');
        self::assertStringContainsString('case "geom"', $bar);
        // Plus de « A* » ni de longue barre : pastille du chef, compteur collé, fond arrêté au compteur.
        self::assertStringNotContainsString('"*"] select _lead', $bar);
        self::assertStringContainsString('if (_lead) then {', $bar);
        self::assertStringContainsString('private _cntX = _x0 + _inRow * (_cw + _gap);', $bar);
        self::assertStringContainsString('((_p select 2) + pixelW * 6) min _w', $bar);
        self::assertStringContainsString('["geom", [(_sqRect select 3) - pixelH * 6', self::read(self::MAIN . 'functions/pages/fn_pageMap.sqf'));
    }

    public function testEnglishEntriesExist(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        self::assertStringContainsString('["Afficher la réglette d\'effectif sur la carte", "Show the squad strip on the map"],', $en);
        self::assertStringContainsString('"Your group\'s boxes under the map compass: one per teammate, the letter is their fire team, a dot marks the leader. Untick to hide it. The Group tab and the ORBAT are not affected."', $en);
        self::assertStringContainsString('["Réglette d\'effectif sous la boussole", "Squad strip under the compass"],', $en);
        self::assertMatchesRegularExpression('/\["[^\n]*"\]\n\]\s*$/', $en, 'aucune virgule après la dernière entrée');
    }

    public function testNoOnScreenNotificationIsAdded(): void
    {
        foreach (['functions/teams/fn_squadBar.sqf', 'functions/pages/fn_pageMap.sqf'] as $file) {
            self::assertDoesNotMatchRegularExpression('/\b(systemChat|hint|hintSilent|cutText|titleText)\b/', self::read(self::MAIN . $file), $file);
        }
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        $start = strpos($pre, '["' . self::KEY . '"');
        self::assertNotFalse($start);
        $block = substr($pre, $start, (int) strpos($pre, 'fnc_addSetting;', $start) - $start);
        self::assertDoesNotMatchRegularExpression('/\b(systemChat|hint|hintSilent|cutText|titleText)\b/', $block);
    }
}
