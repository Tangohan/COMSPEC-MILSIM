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

if ((_markerName select [0, 1]) == "_") then {
    // Les marqueurs joueur Arma / Marker Dropper sont souvent `_USER_DEFINED #…`
    private _ul = toLower _markerName;
    if ((_ul find "_user_defined") < 0 && {(_ul find "user_defined") < 0}) exitWith {};
};

// Marqueurs déjà transmis via leur propre appel structuré
private _mirroredElsewherePrefixes = [
    "poi_local_", "qrf_contact_", "medevac_lz_", "vehicle_service_",
    "comspec_roleplay_zone_", "comspec_tabletmk_", "comspec_shape_", "ctab_u_"
];
private _nameLower = toLower _markerName;
if (({ (_nameLower find _x) == 0 } count _mirroredElsewherePrefixes) > 0) exitWith {};

if (_deleted) exitWith {
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) then {
        [_markerName, "{}", true] call comspec_overwatch_connect_fnc_queueMapMarker;
    } else {
        "COMSPECExtension" callExtension ["SendMarker", [_markerName, "{}", "1", "1"]];
    };
};

private _shape = toUpper (markerShape _markerName);
private _type = markerType _markerName;
// Dropper / marqueurs custom : type parfois vide → forcer un repère lisible
if (_type isEqualTo "" && {!(_shape isEqualTo "POLYLINE")}) then {
    if (_shape isEqualTo "ELLIPSE" || {_shape isEqualTo "RECTANGLE"}) then {
        _type = "mil_circle";
    } else {
        _type = "mil_dot";
    };
};
if (!(_shape isEqualTo "POLYLINE") && {_type isEqualTo ""}) exitWith {};

private _pos = markerPos _markerName;
private _text = markerText _markerName;
private _color = markerColor _markerName;
if (_color isEqualTo "" || {_color isEqualTo "Default"} || {_color isEqualTo "ColorDefault"}) then {
    _color = "ColorYellow";
};
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

private _texture = "";
if (_type isNotEqualTo "" && {!(_shape isEqualTo "POLYLINE")}) then {
    _texture = getText (configFile >> "CfgMarkers" >> _type >> "icon");
};
private _bs = toString [92];
private _texForJson = (_texture splitString _bs joinString "/");
_texForJson = (_texForJson splitString """" joinString "'");

private _json = format [
    "{""pos"":[%1,%2,%3],""type"":""%4"",""text"":""%5"",""color"":""%6"",""dir"":%7,""alpha"":%8,""shape"":""%9"",""size"":[%10,%11],""brush"":""%12"",""polyline"":%13,""source"":""arma"",""callsign"":""%14"",""grid"":""%15"",""texture"":""%16""}",
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
    _polyJson,
    (([] call comspec_overwatch_connect_fnc_getCallsign) splitString """" joinString "'"),
    mapGridPosition _pos,
    _texForJson
];

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    [_markerName, _json, false] call comspec_overwatch_connect_fnc_queueMapMarker;
};

"COMSPECExtension" callExtension ["SendMarker", [_markerName, _json, "1", "0"]];
