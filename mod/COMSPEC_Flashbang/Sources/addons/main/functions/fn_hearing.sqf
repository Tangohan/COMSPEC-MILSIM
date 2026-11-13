/*
    comspec_fb_fnc_hearing : ouïe étouffée du joueur local. _vol = 1 normal, 0,25 très étouffé (jamais le silence total).
    Avec ACE : passe par ace_common_fnc_setHearingCapability (ACE prend le plus bas de toutes les sources et règle
    lui-même sons, radio vanilla, ACRE2 et TFAR). Sans ACE : fadeSound / fadeSpeech, et la radio (fadeRadio, ACRE2,
    TFAR) si le réglage « Radio étouffée » est actif. _restore = true : rend les valeurs d'avant l'effet.
    [_vol, _restore] call comspec_fb_fnc_hearing
*/
params [["_vol", 1, [0]], ["_restore", false, [false]]];
private _st = comspec_fb_state;
private _orig = _st getOrDefault ["orig", createHashMap];
if (!_restore && {abs ((_st getOrDefault ["lastVol", 1]) - _vol) < 0.02}) exitWith {};
_st set ["lastVol", _vol];

if (!isNil "ace_common_fnc_setHearingCapability") exitWith {
    if (_restore) then {
        ["comspec_fb", 1, false, 0.5] call ace_common_fnc_setHearingCapability;
    } else {
        ["comspec_fb", _vol, true, 0.3] call ace_common_fnc_setHearingCapability;
    };
    if (_restore) then { 0.5 fadeSpeech 1 } else { 0.3 fadeSpeech _vol };
};

private _s0 = _orig getOrDefault ["sound", 1];
if (_restore) then {
    0.5 fadeSound _s0;
    0.5 fadeSpeech 1;
} else {
    0.3 fadeSound (_s0 * _vol);
    0.3 fadeSpeech _vol;
};
if (missionNamespace getVariable ["comspec_fb_radioMuffle", true]) then {
    private _r0 = _orig getOrDefault ["radio", 1];
    private _rv = [_vol, 1] select _restore;
    0.3 fadeRadio (_r0 * _rv);
    if (!isNil "acre_api_fnc_setGlobalVolume") then {
        [(_orig getOrDefault ["acre", 1]) * (_rv ^ 0.33)] call acre_api_fnc_setGlobalVolume;
    };
    private _unit = _st getOrDefault ["unit", objNull];
    if (!isNull _unit && {!isNil {_orig get "tfar"}}) then {
        _unit setVariable ["tf_globalVolume", (_orig get "tfar") * _rv];
    };
};
