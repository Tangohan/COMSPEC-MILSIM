/*
    Itinéraire actif ATAK Enhanced Route → polyline Athena.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["route"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

private _state = missionNamespace getVariable ["Iceman_ATAK_Route_state", createHashMap];
if (!(_state isEqualType createHashMap)) exitWith {};

private _active = _state getOrDefault ["active", false];
private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };
_cs = (_cs splitString """" joinString "");
private _armaName = format ["ctab_route_%1", (_cs splitString " " joinString "_")];

if (!_active) exitWith {
    private _was = missionNamespace getVariable ["COMSPEC_Athena_RouteShared", false];
    if (_was) then {
        "COMSPECExtension" callExtension ["SendMarker", [_armaName, "{}", "1", "1"]];
        missionNamespace setVariable ["COMSPEC_Athena_RouteShared", false, false];
        missionNamespace setVariable ["COMSPEC_Athena_RouteSig", "", false];
    };
};

private _route = _state getOrDefault ["route", []];
if (!(_route isEqualType []) || {(count _route) < 2}) exitWith {};

private _mot = _state getOrDefault ["mot", "foot"];
private _parts = [];
{
    if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
    _parts pushBack (str (_x select 0));
    _parts pushBack (str (_x select 1));
} forEach _route;
if ((count _parts) < 4) exitWith {};

private _polyJson = "[" + (_parts joinString ",") + "]";
private _sig = format ["%1|%2|%3", _mot, count _parts, _parts select 0];
private _last = missionNamespace getVariable ["COMSPEC_Athena_RouteSig", ""];
if (_sig isEqualTo _last) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_RouteSig", _sig, false];
missionNamespace setVariable ["COMSPEC_Athena_RouteShared", true, false];

private _text = format ["Itinéraire %1 (%2)", _cs, _mot];
_text = (_text splitString """" joinString "'");
private _json = format [
    "{""pos"":[%1,%2,0],""type"":"""",""text"":""%3"",""color"":""ColorBlue"",""dir"":0,""alpha"":0.9,""shape"":""POLYLINE"",""size"":[1,1],""brush"":""Solid"",""polyline"":%4,""source"":""ctab_route""}",
    _parts select 0,
    _parts select 1,
    _text,
    _polyJson
];
"COMSPECExtension" callExtension ["SendMarker", [_armaName, _json, "1", "0"]];
[format ["Itinéraire → Athena · %1", _text]] call comspec_overwatch_connect_fnc_appendModuleLog;
