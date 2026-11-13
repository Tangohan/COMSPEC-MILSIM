<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Zoom progressif de la carte du téléphone (mini) : un seul maître de la vue pendant l'animation.
 * Avant : la carte filait vers un autre point pendant le zoom puis se recalait sur le joueur.
 */
final class AtakMapMiniZoomFollowAssetTest extends TestCase
{
    private function sqf(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/' . $rel;
        self::assertFileExists($path);

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    public function testFollowLoopAndStatusRecentreStandDownDuringZoom(): void
    {
        $draw = $this->sqf('functions/map/fn_mapOnDraw.sqf');
        // Le bloc de suivi (seul ctrlMapAnimAdd de fn_mapOnDraw) est gardé par comspec_zoomUntil.
        self::assertSame(1, substr_count($draw, '_map ctrlMapAnimAdd ['));
        $gate = strpos($draw, 'if (_fwOn && {diag_tickTime >= (_map getVariable ["comspec_zoomUntil", -1])}) then {');
        self::assertNotFalse($gate);
        self::assertGreaterThan($gate, strpos($draw, '_map ctrlMapAnimAdd ['));

        $status = $this->sqf('functions/ui/fn_statusUpdate.sqf');
        $sGate = strpos($status, 'getVariable ["comspec_zoomUntil", -1])}) then {');
        self::assertNotFalse($sGate);
        self::assertGreaterThan($sGate, strpos($status, '[player] call comspec_atak_native_fnc_mapCenter'));
    }

    public function testZoomRecomputesCentreEachFrameAndNeverRecentresWhileFollowing(): void
    {
        $zoom = $this->sqf('functions/map/fn_mapZoom.sqf');
        $pfh = substr($zoom, (int) strpos($zoom, '[{'));
        // Centre de chaque image : ma position du moment (pas celle du départ), cible par la fonction pure.
        self::assertStringContainsString('case "ME": { private _p = [] call comspec_atak_native_fnc_gpsPos;', $pfh);
        self::assertStringContainsString('call comspec_atak_native_fnc_mapZoomAim', $pfh);
        self::assertStringContainsString('_m setVariable ["comspec_lastAim", _aim];', $pfh);
        self::assertStringContainsString('_m setVariable ["comspec_zoomUntil", diag_tickTime + 0.1];', $pfh);
        // Plus de recentrage final sur moi : seulement sans suivi, sur le centre fixe.
        self::assertStringNotContainsString('[player, _z] call comspec_atak_native_fnc_mapCenter', $zoom);
        self::assertStringContainsString('if (_mode isEqualTo "VIS") then { [_vis, _z] call comspec_atak_native_fnc_mapCenter; };', $zoom);
        // Crans rapprochés : décalage normalisé repris, pas remesuré sur un point pas encore affiché.
        self::assertStringContainsString('comspec_zoomOffN', $zoom);
    }

    public function testPureAimKeepsVisibleCentreOnTarget(): void
    {
        $aim = $this->sqf('functions/map/fn_mapZoomAim.sqf');
        self::assertStringContainsString('[_zi, [(_c select 0) + (_offN select 0) * _zi, (_c select 1) + (_offN select 1) * _zi]]', $aim);
        self::assertStringContainsString('if ((_this param [0, 0]) isEqualTo "check") exitWith {', $aim);
        self::assertStringNotContainsString('ctrlMapAnimAdd [', $aim);
        self::assertStringContainsString('class mapZoomAim {};', $this->sqf('config.cpp'));

        // Même calcul en PHP : centre visible = point visé - décalage * échelle, sur le joueur à chaque image.
        $offN = [312.0, -147.0];
        for ($i = 0; $i <= 20; $i++) {
            $t = $i / 20;
            $k = $t * $t * (3 - 2 * $t);
            $zi = 0.08 * ((0.02 / 0.08) ** $k);
            $me = [4000 + $i * 30, 6000 - $i * 12];
            $aimP = [$me[0] + $offN[0] * $zi, $me[1] + $offN[1] * $zi];
            self::assertEqualsWithDelta($me[0], $aimP[0] - $offN[0] * $zi, 1e-9);
            self::assertEqualsWithDelta($me[1], $aimP[1] - $offN[1] * $zi, 1e-9);
        }
    }
}
