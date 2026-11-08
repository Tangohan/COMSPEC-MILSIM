<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Écran TOC et PC du poste : diffusion des diapositives, rendu, contenu choisi depuis le téléphone, outils de la carte.
 * Lecture du code SQF seulement : le rendu se vérifie en jeu (liste dans la PR).
 */
final class AtakTocPostTest extends TestCase
{
    private const DIR = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testDiffusionAttendLaDesignationEtLeRefusSeVoit(): void
    {
        $cast = self::read(self::DIR . 'pages/fn_screenCast.sqf');
        // Hors serveur, le « cast » immédiat était refusé : la désignation revient par le réseau.
        self::assertStringContainsString('["castWait", [_a, 0]] call _fn', $cast);
        self::assertStringContainsString('case "castWait":', $cast);
        self::assertStringContainsString("Cet objet n'est pas encore un écran", $cast);
        self::assertStringContainsString("L'écran n'a pas répondu", $cast);
        // Écran noir muet remplacé par la raison, écrite sur l'écran.
        self::assertStringContainsString('COMSPEC_ATAK_ScreenMsg', $cast);
        self::assertStringContainsString('COMSPEC_ATAK_ScreenWhy', $cast);
        // Repli data: de fn_slideWebShow : image recopiée quand même.
        self::assertStringContainsString('{_webAge > 6}', $cast);
    }

    public function testEcranEnColonnesPlaquesSansChevauchement(): void
    {
        $scr = self::read(self::DIR . 'toc/fn_tocScreen.sqf');
        self::assertStringContainsString('private _place = {', $scr);
        self::assertStringContainsString('private _clusters =', $scr);
        self::assertStringContainsString('private _setTxt = {', $scr);
        self::assertStringContainsString('(floor (diag_tickTime / 8)) mod _nPg', $scr);
        self::assertStringContainsString('"ORBAT", "TOC", "CAM"', $scr);
        // Titre et heure ne se recouvrent plus.
        self::assertStringContainsString('_title ctrlSetPosition [0.012, 0.012, 0.5, 0.06]', $scr);
        self::assertStringContainsString('_clock ctrlSetPosition [0.515, 0.016, 0.475, 0.056]', $scr);
        self::assertStringNotContainsString('} forEach _nodes;
            // PIP : objectif', $scr);
    }

    public function testContenuChoisiDepuisLeTelephoneEtLePc(): void
    {
        self::assertStringContainsString('case "show":', self::read(self::DIR . 'toc/fn_tocScreen.sqf'));
        self::assertStringContainsString('case "screen":', self::read(self::DIR . 'toc/fn_tocAction.sqf'));
        self::assertStringContainsString('["DIAPOSITIVE", "SLIDES"]', self::read(self::DIR . 'toc/fn_pageToc.sqf'));
        self::assertStringContainsString('["show", [_x, _k]]', self::read(self::DIR . 'toc/fn_tocLaptop.sqf'));
    }

    public function testOutilsDuPosteReutilisentLeTelephone(): void
    {
        $lap = self::read(self::DIR . 'toc/fn_tocLaptop.sqf');
        self::assertStringContainsString('call comspec_atak_native_fnc_markerDrop', $lap);
        self::assertStringContainsString('call comspec_atak_native_fnc_gridParse', $lap);
        self::assertStringContainsString('["load"] call comspec_atak_native_fnc_bftOrbat', $lap);
        foreach (['case "tool":', 'case "search":', 'case "left":', 'case "layer":', 'case "RÉSEAU":', 'case "ORBAT":'] as $needle) {
            self::assertStringContainsString($needle, $lap);
        }
        // Plaques : texte blanc cerné de noir, TOC et OBJECTIF passent par le placement sans chevauchement.
        self::assertStringContainsString('"OBJECTIF"] call _lbl', $lap);
        self::assertStringContainsString('[_tocPos, "TOC"] call _lbl', $lap);
        self::assertStringNotContainsString('Pas de position connue pour cette sélection', $lap);
    }
}
