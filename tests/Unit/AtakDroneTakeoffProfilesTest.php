<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

/**
 * Bouton « Démarrer et décoller » de l'app Drone : profil de décollage par famille de drone (quadricoptère, drone à
 * rotor, avion sur piste, lancement à la main du RQ-11 Raven, drone terrestre), contrôles avant décollage, retour
 * sur la ligne d'état du téléphone seulement.
 */
final class AtakDroneTakeoffProfilesTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const FILES = [
        'functions/drone/fn_droneProfile.sqf',
        'functions/drone/fn_droneCmd.sqf',
        'functions/drone/fn_droneAction.sqf',
        'functions/drone/fn_pageDrone.sqf',
    ];

    private function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel));
    }

    private function profile(): string
    {
        return $this->read(self::MAIN . 'functions/drone/fn_droneProfile.sqf');
    }

    /** SQF sans commentaires ni chaînes : pour compter les délimiteurs. */
    private function strip(string $sqf): string
    {
        $sqf = (string) preg_replace('/"(?:[^"]|"")*"/s', '""', $sqf);
        $sqf = (string) preg_replace('#/\*.*?\*/#s', '', $sqf);

        return (string) preg_replace('#//[^\n]*#', '', $sqf);
    }

    public function testSqfDelimitersAreBalanced(): void
    {
        foreach (self::FILES as $f) {
            $code = $this->strip($this->read(self::MAIN . $f));
            self::assertSame(substr_count($code, '{'), substr_count($code, '}'), "accolades $f");
            self::assertSame(substr_count($code, '['), substr_count($code, ']'), "crochets $f");
            self::assertSame(substr_count($code, '('), substr_count($code, ')'), "parenthèses $f");
        }
    }

    public function testNoScreenNotificationInTouchedFiles(): void
    {
        foreach (self::FILES as $f) {
            // cutText ["", "PLAIN"] (ancien, hors décollage) vide un calque de la manette : ce n'est pas une notification.
            $code = str_replace('cutText ["", ""]', '', $this->strip($this->read(self::MAIN . $f)));
            self::assertSame(0, preg_match('/(?<![_A-Za-z])hint\s*[\[(\'"]/', $code), "hint dans $f");
            foreach (['systemChat', 'hintSilent', 'cutText', 'titleText', 'cutRsc', 'titleRsc', 'BIS_fnc_showNotification'] as $bad) {
                self::assertStringNotContainsString($bad, $code, "$bad dans $f");
            }
        }
        // Le retour d'échec passe par la ligne d'état de l'app (journal du drone), pas par le toast du téléphone.
        $action = $this->read(self::MAIN . 'functions/drone/fn_droneAction.sqf');
        self::assertStringContainsString('"TAKEOFF"', $action);
        self::assertMatchesRegularExpression('/_kind isEqualTo "TAKEOFF"\) exitWith \{\n\s+\[\[_what\] call _takeoffWhy, "#e5483a"\] call _log;/', $action);
    }

    public function testProfileTableCoversTheFamilies(): void
    {
        $p = $this->profile();
        foreach (['QUAD', 'HELI', 'PLANE', 'LAUNCH', 'UGV'] as $family) {
            self::assertStringContainsString('["' . $family . '", [[[', $p, $family);
        }
        // Valeurs sûres par défaut : hauteur de sécurité, vitesse mini d'un avion au-dessus du décrochage.
        self::assertStringContainsString('["safeAgl", 6]', $p);
        self::assertStringContainsString('["minKmh", 120]', $p);
        self::assertStringContainsString('["minKmh", 55]', $p);
        self::assertStringContainsString('stallSpeed', $p);
        self::assertStringContainsString('* 1.3', $p);
        // Extensible : surcharges mission et attribut de config d'un mod.
        self::assertStringContainsString('COMSPEC_ATAK_DroneProfiles', $p);
        self::assertStringContainsString('COMSPEC_ATAK_DroneClassKinds', $p);
        self::assertStringContainsString('comspec_droneProfile', $p);
    }

    /** @return array<string, list<string>> */
    private function classTable(): array
    {
        $p = $this->profile();
        self::assertSame(1, preg_match('/private _classes = \[(.*?)\n\];/s', $p, $m));
        $out = [];
        preg_match_all('/\["([A-Z]+)", \[(.*?)\]\]/s', $m[1], $rows, PREG_SET_ORDER);
        foreach ($rows as $r) {
            preg_match_all('/"([A-Za-z0-9_]+)"/', $r[2], $c);
            $out[$r[1]] = $c[1];
        }

        return $out;
    }

    public function testVanillaArma3DronesAreInTheClassTable(): void
    {
        $t = $this->classTable();
        // AR-2 Darter / Tayran (quadricoptères)
        foreach (['UAV_01_base_F', 'B_UAV_01_F', 'O_UAV_01_F', 'I_UAV_01_F'] as $c) {
            self::assertContains($c, $t['QUAD'], $c);
        }
        // Falcon, Pelican (rotors)
        foreach (['UAV_03_base_F', 'B_T_UAV_03_F', 'UAV_06_base_F', 'B_UAV_06_F', 'B_UAV_06_medical_F'] as $c) {
            self::assertContains($c, $t['HELI'], $c);
        }
        // Greyhawk, Ababil, Aegis, Fenghuang, Sentinel (avions)
        foreach (['UAV_02_base_F', 'B_UAV_02_F', 'B_UAV_02_CAS_F', 'O_UAV_02_F', 'O_UAV_02_CAS_F', 'I_UAV_02_F', 'I_UAV_02_CAS_F', 'UAV_04_base_F', 'O_T_UAV_04_CAS_F', 'UAV_05_base_F', 'B_UAV_05_F'] as $c) {
            self::assertContains($c, $t['PLANE'], $c);
        }
        // Drones terrestres : pas de décollage
        foreach (['UGV_01_base_F', 'B_UGV_01_F', 'O_UGV_01_F', 'I_UGV_01_F', 'B_UGV_01_rcws_F'] as $c) {
            self::assertContains($c, $t['UGV'], $c);
        }
        self::assertContains('Mavic_drone_base_F', $t['QUAD']);
    }

    public function testRavenIsDetectedByNameAndAnyLightPlaneIsHandLaunched(): void
    {
        $p = $this->profile();
        self::assertSame(1, preg_match('/private _launchRx = "(.*)";/', $p, $m));
        $rx = '/^' . str_replace('/', '\/', $m[1]) . '$/';
        foreach (['RQ11_Raven', 'rq_11_raven', 'RHS_RQ11', 'CUP_B_RQ11_Raven', 'USP_RQ-11B', 'B_UAV_Raven_F', 'RQ_11B', 'GX_RQ11B', 'gx_rq_11b_raven', 'RQ-11B', 'rq 11b'] as $cls) {
            self::assertSame(1, preg_match($rx, strtolower($cls)), $cls);
        }
        foreach (['B_UAV_01_F', 'B_UAV_02_F', 'B_UAV_05_F'] as $cls) {
            self::assertSame(0, preg_match($rx, strtolower($cls)), $cls);
        }
        // Repli par la config : voilure fixe lente = lancement à la main ; vtol = montée verticale.
        self::assertMatchesRegularExpression('/maxSpeed.*<= 140.*"LAUNCH"/s', $p);
        self::assertMatchesRegularExpression('/vtol.*"HELI"/s', $p);
        // La config gagne sur la table en cas de conflit (avion déclaré rotor, ou l'inverse).
        self::assertStringContainsString('_hit in ["PLANE", "LAUNCH"] && {_plane}', $p);
        self::assertStringContainsString('_hit in ["QUAD", "HELI"] && {_heli}', $p);
    }

    public function testChecksBeforeTakeoff(): void
    {
        $p = $this->profile();
        foreach (['"DEAD"', '"UGV"', '"UNKNOWN"', '"FLYING"', '"FUEL"', '"CONNECTED"', '"OVERHEAD"', '"NOROOM"'] as $code) {
            self::assertStringContainsString($code, $p, $code);
        }
        foreach (['isUAVConnected', 'UAVControl', 'terrainIntersectASL', 'lineIntersectsSurfaces', 'getTerrainHeightASL', 'isEngineOn', 'fuel _d', 'surfaceIsWater'] as $needle) {
            self::assertStringContainsString($needle, $p, $needle);
        }
        self::assertStringContainsString('"ROLL"', $p);
        self::assertStringContainsString('"ASSIST"', $p);
        self::assertStringContainsString('"CLIMB"', $p);
    }

    public function testCommandUsesTheProfileInsteadOfTheBlindVerticalShove(): void
    {
        $cmd = $this->read(self::MAIN . 'functions/drone/fn_droneCmd.sqf');
        self::assertStringNotContainsString('setVelocityModelSpace [0, 0, 3]', $cmd);
        self::assertStringContainsString('["check", _d, true] call comspec_atak_native_fnc_droneProfile', $cmd);
        self::assertStringContainsString('if (_startFail || {_startPending}) exitWith {};', $cmd);
        // L'ordre ne part pas avant la hauteur de sécurité : il se rejoue depuis la montée.
        self::assertStringContainsString('[_d, _cmd, _args] call comspec_atak_native_fnc_droneCmd;', $cmd);
        // Vitesse et altitude relevées pour une voilure fixe, stationnaire d'avion = orbite.
        self::assertStringContainsString('_minK', $cmd);
        self::assertStringContainsString('"LOITER"', $cmd);
        self::assertStringContainsString('setWaypointLoiterRadius 300', $cmd);
        // Déjà en vol : on ne relance pas.
        self::assertStringContainsString('if (_code isEqualTo "FLYING") exitWith { false };', $cmd);
        // Échec : événement lu par le téléphone.
        self::assertStringContainsString('"TAKEOFF"', $cmd);
    }

    public function testPhoneShowsFailuresAndHidesTheButtonForGroundDrones(): void
    {
        $act = $this->read(self::MAIN . 'functions/drone/fn_droneAction.sqf');
        self::assertStringContainsString('(["check", _d] call comspec_atak_native_fnc_droneProfile) params ["_code", "_pf"];', $act);
        foreach (['DEAD', 'UGV', 'UNKNOWN', 'FLYING', 'FUEL', 'CONNECTED', 'OVERHEAD', 'NOROOM', 'STUCK', 'CRASH'] as $code) {
            self::assertStringContainsString('["' . $code . '", "', $act, $code);
        }
        $page = $this->read(self::MAIN . 'functions/drone/fn_pageDrone.sqf');
        self::assertStringContainsString('if (_fam isEqualTo "UGV")', $page);
        self::assertStringContainsString('"DÉMARRER ET DÉCOLLER"', $page);
    }

    public function testFunctionIsRegistered(): void
    {
        self::assertStringContainsString('class droneProfile {};', $this->read(self::MAIN . 'config.cpp'));
        self::assertTrue(is_file(dirname(__DIR__, 2) . '/' . self::MAIN . 'functions/drone/fn_droneProfile.sqf'));
    }

    public function testEnglishFileStaysStrictAndCarriesTheNewTexts(): void
    {
        $en = $this->read(self::MAIN . 'i18n/en.txt');
        self::assertStringNotContainsString('\\"', $en);
        self::assertTrue(str_ends_with($en, "]\n]\n"));
        self::assertStringNotContainsString(",\n]", $en);
        // Les fins de ligne dépendent du checkout (LF sur le CI, CRLF sous Windows) : seul le contenu compte.
        foreach ([
            '["Batterie trop faible pour décoller", "Battery too low to take off"]',
            '["Drone déjà en vol : décollage ignoré", "Drone already flying: take-off ignored"]',
            '["Décollage : %1", "Take-off: %1"]',
        ] as $line) {
            self::assertStringContainsString($line, $en);
        }
        // Toute entrée a sa virgule, sauf la dernière.
        self::assertSame(0, preg_match('/\]\n\[/', $en), 'virgule manquante entre deux entrées');
        $xx = $this->read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        self::assertStringContainsString('["Batterie trop faible pour décoller", "", "Battery too low to take off"]', $xx);
        self::assertTrue(str_ends_with($xx, "]\n]\n"));
    }

    public function testVersionsAndChangelogs(): void
    {
        $hpp = $this->read(self::MAIN . 'script_component.hpp');
        $v = ModVersion::match('/#define VERSION_STR "([0-9.]+)"/', $hpp);
        ModVersion::assertAtLeast('4.24.2', $v);
        self::assertStringContainsString('#define VERSION ' . $v, $hpp);
        self::assertStringStartsWith($v . '.', ModVersion::match('/#define VERSION_NAME "([^"]+)"/', $hpp));
        $log = $this->read('CHANGELOG-ATAK.md');
        self::assertMatchesRegularExpression('/\n## Drones : « Démarrer et décoller » adapté à chaque drone[^\n]+\(ATAK natif 4.24.2.AG - Washington, COMSPEC Link 2\.0\.126\)\n\n### Résumé joueur\nType : correctif\nFR : [^\n]+\n(- [^\n]+\n)+EN : [^\n]+\n(- [^\n]+\n)+/', $log);
        $steam = $this->read('mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt');
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.24.2 Washington', $steam);
        self::assertStringContainsString('Raven', $steam);
        // Aucun binaire ni DLL touché : Link reste aligné.
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.127"', $hpp);
    }
}
