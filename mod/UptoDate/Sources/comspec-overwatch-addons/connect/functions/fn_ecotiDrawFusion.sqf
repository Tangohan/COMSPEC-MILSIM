/*
    Halo thermique discret sur une source déjà affichée (pas de révélation hors alliés).
    Params: [_pos, _dist, _kind] — kind = unit | vehicle | building
*/
params [
    ["_pos", [0, 0, 0], [[]]],
    ["_dist", 0, [0]],
    ["_kind", "unit", [""]]
];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_ecoti_fusion", true])) exitWith {};
if ([] call comspec_overwatch_connect_fnc_ecotiA3tiPresent) exitWith {};
if (!(_pos isEqualType []) || {(count _pos) < 3}) exitWith {};

private _glow = "\z\comspec_overwatch\addons\connect\img\ecoti\ecoti_glow_ca.png";
private _theme = [] call comspec_overwatch_connect_fnc_ecotiThemeColors;
private _col = _theme getOrDefault ["thermal", [1, 0.96, 0.88, 0.55]];
_col = [_col] call comspec_overwatch_connect_fnc_ecotiNormalizeColor;

private _maxD = if (_kind isEqualTo "building") then { 900 } else { 380 };
if (_dist > _maxD) exitWith {};

private _a = linearConversion [18, _maxD, _dist, 0.58, 0.08, true];
if (_kind isEqualTo "vehicle") then { _a = (_a * 1.15) min 0.7; };
if (_kind isEqualTo "building") then { _a = (_a * 0.85) min 0.5; };
_col set [3, ((_col select 3) * _a) min 1];

private _sz = switch (_kind) do {
    case "vehicle": { linearConversion [12, 380, _dist, 2.1, 0.45, true] };
    case "building": { linearConversion [20, 900, _dist, 2.8, 0.7, true] };
    default { linearConversion [8, 380, _dist, 1.65, 0.32, true] };
};

drawIcon3D [_glow, _col, _pos, _sz, _sz, 0, "", 0, 0.001, "PuristaMedium"];
