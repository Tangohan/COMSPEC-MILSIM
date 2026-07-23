/*
    Envoie un marqueur carte Arma vers Athena (création / maj / suppression).
    Gère ICON / ELLIPSE / RECTANGLE et POLYLINE (type souvent vide en Arma).
    Params: [_markerName, _deleted]
*/
params [["_markerName", ""], ["_deleted", false]];
if (_markerName isEqualTo "") exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {};
if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {};

if ((_markerName select [0, 1]) == "_") exitWith {};

if (_deleted) exitWith {
    "COMSPECExtension" callExtension ["SendMarker", [_markerName, "{}", "1", "1"]];
};

private _shape = toUpper (markerShape _markerName);
private _type = markerType _markerName;
if (!(_shape isEqualTo "POLYLINE") && {_type isEqualTo ""}) exitWith {};

private _pos = markerPos _markerName;
private _text = markerText _markerName;
private _color = markerColor _markerName;
private _dir = markerDir _markerName;
private _alpha = markerAlpha _markerName;
private _size = markerSize _markerName;
private _brush = markerBrush _markerName;
private _polyJson = "[]";

if (_shape isEqualTo "POLYLINE") then {
    private _pts = markerPolyline _markerName;
    if (!(_pts isEqualType []) || {(count _pts) < 4}) exitWith {};
    private _parts = [];
    { _parts pushBack (str _x); } forEach _pts;
    _polyJson = "[" + (_parts joinString ",") + "]";
    _pos = [_pts select 0, _pts select 1, 0];
};

private _json = format [
    "{""pos"":[%1,%2,%3],""type"":""%4"",""text"":""%5"",""color"":""%6"",""dir"":%7,""alpha"":%8,""shape"":""%9"",""size"":[%10,%11],""brush"":""%12"",""polyline"":%13}",
    _pos select 0,
    _pos select 1,
    if (count _pos > 2) then { _pos select 2 } else { 0 },
    _type,
    (_text splitString """" joinString "'"),
    _color,
    _dir,
    _alpha,
    _shape,
    _size select 0,
    _size select 1,
    _brush,
    _polyJson
];
"COMSPECExtension" callExtension ["SendMarker", [_markerName, _json, "1", "0"]];
