/*
    Envoie un marqueur tactique local (POI / évacuation / renfort / service) vers Athena carte.
    Les préfixes poi_local_ / medevac_lz_ / … sont exclus du miroir générique MarkerCreated.
    Params: [_markerName, _pos, _type, _color, _text, _source]
*/
params [
    ["_markerName", "", [""]],
    ["_pos", [], [[]]],
    ["_type", "mil_dot", [""]],
    ["_color", "ColorYellow", [""]],
    ["_text", "", [""]],
    ["_source", "ace_tactical", [""]]
];

if (_markerName isEqualTo "") exitWith { false };
if (!(_pos isEqualType []) || {(count _pos) < 2}) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if (_type isEqualTo "") then { _type = "mil_dot"; };
if (_color isEqualTo "" || {_color isEqualTo "Default"} || {_color isEqualTo "ColorDefault"}) then {
    _color = "ColorYellow";
};

private _safeText = (_text splitString """" joinString "'");
private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isEqualTo "") then { _cs = name player; };
_cs = (_cs splitString """" joinString "'");

private _json = format [
    "{""pos"":[%1,%2,%3],""type"":""%4"",""text"":""%5"",""color"":""%6"",""dir"":0,""alpha"":1,""shape"":""ICON"",""size"":[1,1],""brush"":""Solid"",""polyline"":[],""source"":""%7"",""callsign"":""%8"",""grid"":""%9""}",
    _pos select 0,
    _pos select 1,
    if ((count _pos) > 2) then { _pos select 2 } else { 0 },
    _type,
    _safeText,
    _color,
    _source,
    _cs,
    mapGridPosition _pos
];

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    [_markerName, _json, false] call comspec_overwatch_connect_fnc_queueMapMarker;
    true
};

"COMSPECExtension" callExtension ["SendMarker", [_markerName, _json, "1", "0"]];
true
