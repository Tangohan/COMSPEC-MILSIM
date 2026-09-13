/*
    Relais d’une IA alliée vue seulement côté serveur (hors bulle du client).
*/
params [
    ["_nid", "", [""]],
    ["_px", 0, [0]],
    ["_py", 0, [0]],
    ["_dir", 0, [0]],
    ["_asl", 0, [0]]
];
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if ((abs _px < 1) && { abs _py < 1 }) exitWith { false };

private _nidTrim = trim _nid;
if (_nidTrim isEqualTo "") exitWith { false };

private _allyId = format ["ALLY-%1", (_nidTrim splitString ":") joinString "-"];
private _lastKey = format ["COMSPEC_AllySnapLast_%1", _allyId];
private _last = missionNamespace getVariable [_lastKey, -1e9];
if ((diag_tickTime - _last) < 3) exitWith { false };
missionNamespace setVariable [_lastKey, diag_tickTime, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _extra = format [
    "{""ally_ai"":true,""is_ai"":true,""source"":""ally"",""ally_id"":""%1"",""display_name"":""%1"",""affiliation"":""friend"",""in_vehicle"":false,""military_id"":""""}",
    (_allyId splitString """" joinString "")
];

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_px, 2] call _fnc_num,
    [_py, 2] call _fnc_num,
    [_dir, 2] call _fnc_num,
    _allyId,
    "Unité alliée",
    "stable",
    "",
    "",
    "",
    _extra,
    "",
    "",
    [_asl, 3] call _fnc_num
]];
true
