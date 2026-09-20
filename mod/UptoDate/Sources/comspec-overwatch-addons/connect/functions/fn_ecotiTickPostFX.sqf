/*
    Cadence ~8 Hz : active / coupe le tube, autogating, HUD boussole / grille.
*/
if (!hasInterface) exitWith {};

private _want = [] call comspec_overwatch_connect_fnc_ecotiIsActive;
private _tube = missionNamespace getVariable ["comspec_overwatch_ecoti_tube_fx", true];
if (!(_tube isEqualType true)) then { _tube = true; };
private _gate = missionNamespace getVariable ["comspec_overwatch_ecoti_autogate", true];
if (!(_gate isEqualType true)) then { _gate = true; };
private _gps = missionNamespace getVariable ["comspec_overwatch_ecoti_gps_hud", true];
if (!(_gps isEqualType true)) then { _gps = true; };
private _compassOnly = missionNamespace getVariable ["comspec_overwatch_ecoti_compass_only", false];
if (!(_compassOnly isEqualType true)) then { _compassOnly = false; };

private _on = missionNamespace getVariable ["COMSPEC_EcotiPpOn", false];
if (!(_on isEqualType true)) then { _on = false; };

if (!_want) exitWith {
    if (_on) then { [] call comspec_overwatch_connect_fnc_ecotiStopPostFX; };
    [] call comspec_overwatch_connect_fnc_ecotiChromeHide;
};

if (_gps || {_compassOnly}) then {
    [] call comspec_overwatch_connect_fnc_ecotiChromeEnsure;
    [] call comspec_overwatch_connect_fnc_ecotiChromeRender;
} else {
    [] call comspec_overwatch_connect_fnc_ecotiChromeHide;
};

if (!_tube) exitWith {
    if (_on) then { [] call comspec_overwatch_connect_fnc_ecotiStopPostFX; };
};

if (!_on) then {
    [] call comspec_overwatch_connect_fnc_ecotiInitPostFX;
};

private _colH = missionNamespace getVariable ["COMSPEC_EcotiPpColor", -1];
if (!(_colH isEqualType 0) || { _colH < 0 }) exitWith {};

private _flare = 0;
if (_gate) then {
    private _camPos = positionCameraToWorld [0, 0, 0];
    private _look = _camPos vectorFromTo (positionCameraToWorld [0, 0, 1]);
    {
        if (!alive _x) then { continue };
        if !(isLightOn _x) then { continue };
        private _d = _camPos distance _x;
        if (_d > 110) then { continue };
        private _dir = _camPos vectorFromTo (getPosATL _x);
        if ((vectorMagnitude _dir) < 0.2) then { continue };
        private _dot = _look vectorDotProduct (vectorNormalized _dir);
        if (_dot > 0.52) then {
            _flare = _flare max ((_dot * (1 - (_d / 110))) min 1);
        };
    } forEach (nearestObjects [_camPos, ["LandVehicle", "Air"], 110]);
};

missionNamespace setVariable ["COMSPEC_EcotiGateLevel", _flare, false];

private _fusion = missionNamespace getVariable ["comspec_overwatch_ecoti_fusion", true];
if (!(_fusion isEqualType true)) then { _fusion = true; };
private _a3ti = [] call comspec_overwatch_connect_fnc_ecotiA3tiPresent;

private _bright = 1.0 - (_flare * 0.48);
private _contrast = 1.12 + (_flare * 0.28);
private _mid = if (_fusion && {!_a3ti}) then {
    [0.14, 0.24, 0.16, 0.72]
} else {
    [0.08, 0.28, 0.12, 0.82]
};
private _desat = if (_fusion && {!_a3ti}) then {
    [0.18, 0.42, 0.22, 0.38]
} else {
    [0.12, 0.52, 0.18, 0.45]
};

_colH ppEffectAdjust [
    _bright, _contrast, 0.02 + (_flare * 0.04),
    [0, 0, 0, 0],
    _mid,
    _desat
];
_colH ppEffectCommit 0.1;
