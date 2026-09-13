/*
    Effets visuels client (brouillage / interférence).
    Params:
      [_intensity 0-1, _durationSec, _stop]
      ou [true] / [false, ...] avec premier bool = stop (compat appels historiques).
*/
if (!hasInterface) exitWith {};

// Les ppEffects Arma s’appliquent à tout le viewport (3D + HUD), y compris
// autour du téléphone ATAK. Le réglage « effets visuels » vise uniquement
// l’écran du terminal (overlay / HTML). On nettoie d’éventuels effets restants.

// Compat : [true] call … devait stopper — l’ancien params type [0] ignorait le bool
if (_this isEqualType true) exitWith {
    if (_this) then { [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects; };
};
if (_this isEqualType [] && {(count _this) > 0} && {(_this select 0) isEqualType true}) exitWith {
    if (_this select 0) then {
        [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    } else {
        private _rest = _this select [1, (count _this) - 1];
        if (_rest isEqualTo []) then { _rest = [0.5, 8, false]; };
        _rest call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    };
};

params [
    ["_intensity", 0.5, [0]],
    ["_durationSec", 8, [0]],
    ["_stop", false, [true, false]]
];

private _pfhId = missionNamespace getVariable ["COMSPEC_RoleplayPpFnc", -1];
private _aberId = missionNamespace getVariable ["COMSPEC_RoleplayPpAber", -1];
private _grainId = missionNamespace getVariable ["COMSPEC_RoleplayPpGrain", -1];
private _colorId = missionNamespace getVariable ["COMSPEC_RoleplayPpColor", -1];

private _cleanup = {
    if (_pfhId >= 0) then {
        [_pfhId] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_RoleplayPpFnc", -1, false];
    };
    if (_aberId >= 0 && {_aberId isEqualType 0}) then {
        _aberId ppEffectEnable false;
        ppEffectDestroy _aberId;
        missionNamespace setVariable ["COMSPEC_RoleplayPpAber", -1, false];
    };
    if (_grainId >= 0 && {_grainId isEqualType 0}) then {
        _grainId ppEffectEnable false;
        ppEffectDestroy _grainId;
        missionNamespace setVariable ["COMSPEC_RoleplayPpGrain", -1, false];
    };
    if (_colorId >= 0 && {_colorId isEqualType 0}) then {
        _colorId ppEffectEnable false;
        ppEffectDestroy _colorId;
        missionNamespace setVariable ["COMSPEC_RoleplayPpColor", -1, false];
    };
};

if (_stop) exitWith { call _cleanup; };

// Ne jamais appliquer de ppEffect monde : ça recouvre le 3D et le cadre du téléphone.
call _cleanup;

