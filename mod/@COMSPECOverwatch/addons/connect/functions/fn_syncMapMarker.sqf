/*
    Envoie un marqueur carte Arma vers Athena (création / maj / suppression).
    Params: [_markerName, _deleted]
*/
params [["_markerName", ""], ["_deleted", false]];
if (_markerName isEqualTo "") exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {};

// Ignore marqueurs système / locaux temporaires
if ((_markerName select [0, 1]) == "_") exitWith {};

if (_deleted) then {
    "COMSPECExtension" callExtension ["SendMarker", [_markerName, "{}", "1", "1"]];
} else {
    if ((markerType _markerName) isEqualTo "") exitWith {};
    private _pos = markerPos _markerName;
    private _type = markerType _markerName;
    private _text = markerText _markerName;
    private _color = markerColor _markerName;
    private _dir = markerDir _markerName;
    private _alpha = markerAlpha _markerName;
    private _shape = markerShape _markerName;
    private _size = markerSize _markerName;
    private _brush = markerBrush _markerName;
    private _json = format [
        "{""pos"":[%1,%2,%3],""type"":""%4"",""text"":""%5"",""color"":""%6"",""dir"":%7,""alpha"":%8,""shape"":""%9"",""size"":[%10,%11],""brush"":""%12""}",
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
        _brush
    ];
    "COMSPECExtension" callExtension ["SendMarker", [_markerName, _json, "1", "0"]];
};
