<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif, caméras IP : toute classe de caméra posable (Eden / Zeus) est couverte par le registre, visible de son
 * camp dans l'app Vidéo live et dans les sources du TOC, et son modèle porte les points que le flux utilise.
 * Cause du défaut corrigé : une caméra posée dans l'éditeur n'a pas de poseur, donc personne ne pouvait la partager ;
 * fn_camShare « can » la refusait à tout le monde quand le partage est obligatoire (réglage par défaut).
 */
final class AtakCameraRegistryAssetTest extends TestCase
{
    private const ADDONS = 'mod/COMSPEC_ATAK_Native/Sources/addons/';

    private function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::ADDONS . $rel));
    }

    /** @return array<string, string> classe => parent, pour toutes les classes de cfg_cameras.hpp */
    private function classes(): array
    {
        preg_match_all('/^class (COMSPEC_ATAK_Cam\w*): (\w+)/m', $this->read('towers/cfg_cameras.hpp'), $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $row) {
            $out[$row[1]] = $row[2];
        }

        return $out;
    }

    public function testEveryPlaceableCameraClassIsCoveredByTheRegistry(): void
    {
        $classes = $this->classes();
        $cfg = $this->read('towers/cfg_cameras.hpp');
        $config = $this->read('towers/config.cpp');
        preg_match('/#define COMSPEC_CAM_UNITS (.+)/', $config, $u);
        preg_match_all('/"(COMSPEC_ATAK_Cam_\w+)"/', $u[1] ?? '', $units);
        self::assertCount(16, $units[1], 'seize classes de caméras posables');
        foreach ($units[1] as $cls) {
            self::assertArrayHasKey($cls, $classes, $cls);
            // Remonte l'héritage jusqu'à la base : c'est sur elle que l'init et le balayage du postInit enregistrent.
            $c = $cls;
            $hops = 0;
            while ($c !== 'COMSPEC_ATAK_Cam_Base' && isset($classes[$c]) && $hops++ < 8) {
                $c = $classes[$c];
            }
            self::assertSame('COMSPEC_ATAK_Cam_Base', $c, $cls . ' hérite de COMSPEC_ATAK_Cam_Base');
        }
        // La base hérite de la caméra d'origine : le registre (fn_camAction « srvReg ») filtre par isKindOf.
        self::assertSame('COMSPEC_ATAK_CameraObj', $classes['COMSPEC_ATAK_Cam_Base']);
        self::assertStringContainsString('#define CAM_CLASS "COMSPEC_ATAK_CameraObj"', $this->read('main/functions/camera/fn_camAction.sqf'));
        self::assertStringContainsString('{_a isKindOf CAM_CLASS}', $this->read('main/functions/camera/fn_camAction.sqf'));
        // Enregistrement : à l'init de chaque caméra, et en secours sur toutes les caméras de la mission au postInit.
        self::assertStringContainsString('class COMSPEC_ATAK_Cam_Base { class comspec_atak_native_cam { serverInit="[_this select 0] call comspec_atak_native_fnc_camProp"; }; };', $config);
        self::assertStringContainsString("forEach (allMissionObjects 'COMSPEC_ATAK_Cam_Base')", $config);
        // L'œil du flux est lu dans la config (hérité de la base) : chaque modèle porte aussi les points eye / dir.
        self::assertStringContainsString('comspec_camEye[]=', $cfg);
        preg_match_all('/model="[^"]*\\\\(cam_\w+\.p3d)"/', $cfg, $models);
        self::assertGreaterThanOrEqual(13, count(array_unique($models[1])));
        foreach (array_unique($models[1]) as $file) {
            $bin = (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::ADDONS . 'items/data/cam/' . $file);
            self::assertNotSame('', $bin, $file);
            foreach (["\x01eye\0", "\x01dir\0"] as $tag) {
                self::assertStringContainsString($tag, $bin, $file . ' : point mémoire ' . trim($tag, "\x01\0"));
            }
        }
    }

    public function testEditorCamerasAreVisibleToTheirWholeSide(): void
    {
        $prop = $this->read('towers/functions/cam/fn_camProp.sqf');
        $share = $this->read('main/functions/camera/fn_camShare.sqf');
        self::assertStringContainsString('_o setVariable ["COMSPEC_ATAK_CamPublic", true, true];', $prop);
        // La marque est posée avant l'inscription au registre : aucun client ne voit la caméra sans elle.
        self::assertLessThan(strpos($prop, '["srvReg", _o, true] call'), strpos($prop, 'COMSPEC_ATAK_CamPublic'));
        self::assertStringContainsString('if ([_a] call _isCam && {_a getVariable ["COMSPEC_ATAK_CamPublic", false]}) exitWith { true };', $share);
        // Une seule inscription par caméra, journal d'une ligne par caméra.
        self::assertStringContainsString('if ((_o getVariable ["COMSPEC_ATAK_CamSide", ""]) isNotEqualTo "") exitWith {};', $prop);
        self::assertStringContainsString('[COMSPEC CAM] caméra | classe=%1 nom=%2 camp=%3 lives=%4 toc=%4 raison=%5', $prop);
        // Vidéo live et TOC lisent la même liste.
        self::assertStringContainsString('["list"] call comspec_atak_native_fnc_camAction', $this->read('main/functions/pages/fn_pageLivecam.sqf'));
        self::assertStringContainsString('(["list"] call comspec_atak_native_fnc_camAction)', $this->read('main/functions/toc/fn_tocCam.sqf'));
    }

    /**
     * Règle de visibilité de fn_camList « visible » (fonction pure), rejouée ici ligne à ligne : le test échoue si
     * l'ordre ou une condition de la fonction SQF change sans que ce miroir suive.
     */
    public function testVisibilityRuleMirrorsThePureSqfFunction(): void
    {
        $sqf = $this->read('main/functions/camera/fn_camList.sqf');
        self::assertStringContainsString('class camAction {}; class camList {}; class camView {};', $this->read('main/config.cpp'));
        $order = [
            'if (_camSide isEqualTo "") exitWith { [false,',
            'if (_camSide isNotEqualTo _viewSide) exitWith { [false,',
            'if (!_required) exitWith { [true,',
            'if (_public) exitWith { [true,',
            'if ([_ownerName isEqualTo _viewName, _ownerUid isEqualTo _viewUid] select (_ownerUid isNotEqualTo "")) exitWith { [true,',
            'if (("ALL" in _access) || {_viewUid in _access}) exitWith { [true,',
        ];
        $at = 0;
        foreach ($order as $line) {
            $pos = strpos($sqf, $line, $at);
            self::assertNotFalse($pos, $line);
            $at = $pos;
        }
        $visible = static function (string $camSide, bool $public, string $ownerUid, string $ownerName, array $access, string $viewSide, string $viewUid, string $viewName, bool $required): bool {
            if ($camSide === '' || $camSide !== $viewSide) {
                return false;
            }
            if (!$required || $public) {
                return true;
            }
            if ($ownerUid !== '' ? $ownerUid === $viewUid : $ownerName === $viewName) {
                return true;
            }

            return in_array('ALL', $access, true) || in_array($viewUid, $access, true);
        };
        // Le cas du défaut : caméra d'éditeur (ni poseur ni accès), partage obligatoire.
        self::assertFalse($visible('WEST', false, '', '', [], 'WEST', '765', 'Tetard', true), 'avant correction : invisible pour tous');
        self::assertTrue($visible('WEST', true, '', '', [], 'WEST', '765', 'Tetard', true), 'caméra d\'infrastructure : tout le camp');
        self::assertFalse($visible('WEST', true, '', '', [], 'EAST', '765', 'Tetard', true), 'jamais pour un autre camp');
        self::assertFalse($visible('', true, '', '', [], 'WEST', '765', 'Tetard', true), 'pas encore enregistrée');
        // Les caméras posées par un joueur gardent leur règle.
        self::assertTrue($visible('WEST', false, '765', 'Tetard', [], 'WEST', '765', 'Tetard', true));
        self::assertFalse($visible('WEST', false, '765', 'Tetard', [], 'WEST', '999', 'Autre', true));
        self::assertTrue($visible('WEST', false, '765', 'Tetard', ['999'], 'WEST', '999', 'Autre', true));
        self::assertTrue($visible('WEST', false, '765', 'Tetard', ['ALL'], 'WEST', '999', 'Autre', true));
        self::assertTrue($visible('WEST', false, '765', 'Tetard', [], 'WEST', '999', 'Autre', false));
        // Une ligne de la liste : [id, nom, type, état, position, ptz].
        self::assertStringContainsString('[netId _a, _a getVariable ["COMSPEC_ATAK_CamName", "CAM"], ["type", _a] call comspec_atak_native_fnc_camList,', $sqf);
        self::assertStringContainsString('(getNumber (configOf _a >> "comspec_camPtz")) isEqualTo 1]', $sqf);
    }
}
