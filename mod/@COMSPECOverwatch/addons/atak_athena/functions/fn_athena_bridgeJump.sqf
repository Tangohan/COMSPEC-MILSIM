/*
    Plan HAHO/HALO (Iceman) → polyline + repères Athena.
    Lit Iceman_ATAK_Jump_state sans modifier le mod Iceman.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["jump"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _state = missionNamespace getVariable ["Iceman_ATAK_Jump_state", createHashMap];
if (!(_state isEqualType createHashMap)) exitWith {};

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };
_cs = (_cs splitString """" joinString "");
private _safeCs = (_cs splitString " " joinString "_");
private _armaLine = format ["ctab_jump_%1", _safeCs];
private _armaJp = format ["ctab_jump_jp_%1", _safeCs];
private _armaDz = format ["ctab_jump_dz_%1", _safeCs];

private _planned = _state getOrDefault ["planned", false];
if (!_planned) exitWith {
    private _was = missionNamespace getVariable ["COMSPEC_Athena_JumpShared", false];
    if (_was) then {
        "COMSPECExtension" callExtension ["SendMarker", [_armaLine, "{}", "1", "1"]];
        "COMSPECExtension" callExtension ["SendMarker", [_armaJp, "{}", "1", "1"]];
        "COMSPECExtension" callExtension ["SendMarker", [_armaDz, "{}", "1", "1"]];
        missionNamespace setVariable ["COMSPEC_Athena_JumpShared", false, false];
        missionNamespace setVariable ["COMSPEC_Athena_JumpSig", "", false];
    };
};

private _path = _state getOrDefault ["path", []];
private _jp = _state getOrDefault ["jumpPoint", []];
private _dz = _state getOrDefault ["dropZone", []];
private _mode = toUpper (str (_state getOrDefault ["mode", "HAHO"]));
if (!(_path isEqualType []) || {(count _path) < 2}) exitWith {};
if (!(_jp isEqualType []) || {(count _jp) < 2}) exitWith {};
if (!(_dz isEqualType []) || {(count _dz) < 2}) exitWith {};

private _parts = [];
{
    if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
    _parts pushBack (str (_x select 0));
    _parts pushBack (str (_x select 1));
} forEach _path;
if ((count _parts) < 4) exitWith {};

private _dist = round (_state getOrDefault ["distance", 0]);
private _sig = format ["%1|%2|%3|%4|%5", _mode, _dist, _jp select 0, _jp select 1, count _parts];
private _last = missionNamespace getVariable ["COMSPEC_Athena_JumpSig", ""];
if (_sig isEqualTo _last) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_JumpSig", _sig, false];
missionNamespace setVariable ["COMSPEC_Athena_JumpShared", true, false];

private _polyJson = "[" + (_parts joinString ",") + "]";
private _lineText = format ["Saut %1 %2 (%3 m)", _mode, _cs, _dist];
_lineText = (_lineText splitString """" joinString "'");
private _lineJson = format [
    "{""pos"":[%1,%2,0],""type"":"""",""text"":""%3"",""color"":""ColorYellow"",""dir"":0,""alpha"":0.9,""shape"":""POLYLINE"",""size"":[1,1],""brush"":""Solid"",""polyline"":%4,""source"":""ctab_jump""}",
    _parts select 0,
    _parts select 1,
    _lineText,
    _polyJson
];
"COMSPECExtension" callExtension ["SendMarker", [_armaLine, _lineJson, "1", "0"]];

private _jpText = format ["JP %1", _mode];
_jpText = (_jpText splitString """" joinString "'");
private _jpJson = format [
    "{""pos"":[%1,%2,0],""type"":""mil_start"",""text"":""%3"",""color"":""ColorGreen"",""dir"":0,""alpha"":1,""shape"":""ICON"",""size"":[1,1],""brush"":""Solid"",""polyline"":[],""source"":""ctab_jump""}",
    _jp select 0,
    _jp select 1,
    _jpText
];
"COMSPECExtension" callExtension ["SendMarker", [_armaJp, _jpJson, "1", "0"]];

private _dzText = format ["DZ %1", _cs];
_dzText = (_dzText splitString """" joinString "'");
private _dzJson = format [
    "{""pos"":[%1,%2,0],""type"":""mil_end"",""text"":""%3"",""color"":""ColorRed"",""dir"":0,""alpha"":1,""shape"":""ICON"",""size"":[1,1],""brush"":""Solid"",""polyline"":[],""source"":""ctab_jump""}",
    _dz select 0,
    _dz select 1,
    _dzText
];
"COMSPECExtension" callExtension ["SendMarker", [_armaDz, _dzJson, "1", "0"]];
[format ["Plan de saut %1 vers Athena (%2 m)", _mode, _dist]] call comspec_overwatch_connect_fnc_appendModuleLog;
