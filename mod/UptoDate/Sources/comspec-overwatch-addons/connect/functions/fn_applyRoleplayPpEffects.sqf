/*
    Effets visuels client (brouillage / interférence).
    Params:
      [_intensity 0-1, _durationSec, _stop]
      ou [true] / [false, ...] avec premier bool = stop (compat appels historiques).
*/
if (!hasInterface) exitWith {};

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

private _cleanup = {
    if (_pfhId >= 0) then {
        [_pfhId] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_RoleplayPpFnc", -1, false];
    };
    if (_aberId >= 0 && {_aberId isEqualType 0}) then {
        _aberId ppEffectEnable false;
        _aberId ppEffectDestroy;
        missionNamespace setVariable ["COMSPEC_RoleplayPpAber", -1, false];
    };
    if (_grainId >= 0 && {_grainId isEqualType 0}) then {
        _grainId ppEffectEnable false;
        _grainId ppEffectDestroy;
        missionNamespace setVariable ["COMSPEC_RoleplayPpGrain", -1, false];
    };
};

if (_stop) exitWith { call _cleanup; };

if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", true])) exitWith {};
if (_intensity <= 0.05) exitWith { call _cleanup; };

call _cleanup;

private _aberration = ppEffectCreate ["ChromAberration", 210];
_aberration ppEffectEnable true;
private _grain = ppEffectCreate ["FilmGrain", 2010];
_grain ppEffectEnable true;

missionNamespace setVariable ["COMSPEC_RoleplayPpAber", _aberration, false];
missionNamespace setVariable ["COMSPEC_RoleplayPpGrain", _grain, false];

private _endTime = CBA_missionTime + (_durationSec max 1);

private _handle = [{
    params ["_args", "_h"];
    _args params ["_aberration", "_grain", "_endTime", "_baseIntensity"];

    if (CBA_missionTime >= _endTime) exitWith {
        _aberration ppEffectEnable false;
        _grain ppEffectEnable false;
        _aberration ppEffectDestroy;
        _grain ppEffectDestroy;
        missionNamespace setVariable ["COMSPEC_RoleplayPpAber", -1, false];
        missionNamespace setVariable ["COMSPEC_RoleplayPpGrain", -1, false];
        missionNamespace setVariable ["COMSPEC_RoleplayPpFnc", -1, false];
        [_h] call CBA_fnc_removePerFrameHandler;
    };

    private _strength = linearConversion [_endTime - 8, _endTime, CBA_missionTime, _baseIntensity, 0.08, true];
    private _jolt = _strength * (0.008 + random 0.04);
    _aberration ppEffectAdjust [_jolt, _jolt, true];
    _aberration ppEffectCommit 0.12;
    _grain ppEffectAdjust [_strength * (0.08 + random 0.22), 1, 1, 0, 1, false];
    _grain ppEffectCommit 0.12;
}, 0.15, [_aberration, _grain, _endTime, _intensity min 1 max 0]] call CBA_fnc_addPerFrameHandler;

missionNamespace setVariable ["COMSPEC_RoleplayPpFnc", _handle, false];
