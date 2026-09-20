/*
    Grain, aberration et correction couleur du tube (complète ACE, ne remplace pas l’aveuglement ACE).
*/
if (!hasInterface) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_EcotiPpOn", false]) exitWith { true };

private _aceNvg = isClass (configFile >> "CfgPatches" >> "ace_nightvision");
private _grain = if (_aceNvg) then { 0.022 } else { 0.042 };

private _g = ppEffectCreate ["FilmGrain", 2010];
if (!(_g isEqualType 0) || { _g < 0 }) exitWith { false };

private _c = ppEffectCreate ["ChromAberration", 250];
if (!(_c isEqualType 0) || { _c < 0 }) exitWith {
    ppEffectDestroy _g;
    false
};

private _col = ppEffectCreate ["ColorCorrections", 1510];
if (!(_col isEqualType 0) || { _col < 0 }) exitWith {
    ppEffectDestroy _g;
    ppEffectDestroy _c;
    false
};

_g ppEffectEnable true;
_g ppEffectAdjust [_grain, 1.35, 1.8];
_g ppEffectCommit 0.45;

_c ppEffectEnable true;
_c ppEffectAdjust [0.007, 0.007, true];
_c ppEffectCommit 0.45;

_col ppEffectEnable true;
_col ppEffectAdjust [
    1.0, 1.12, 0.02,
    [0, 0, 0, 0],
    [0.08, 0.28, 0.12, 0.82],
    [0.12, 0.52, 0.18, 0.45]
];
_col ppEffectCommit 0.45;

missionNamespace setVariable ["COMSPEC_EcotiPpGrain", _g, false];
missionNamespace setVariable ["COMSPEC_EcotiPpChrom", _c, false];
missionNamespace setVariable ["COMSPEC_EcotiPpColor", _col, false];
missionNamespace setVariable ["COMSPEC_EcotiPpOn", true, false];
missionNamespace setVariable ["COMSPEC_EcotiGateLevel", 0, false];

true
