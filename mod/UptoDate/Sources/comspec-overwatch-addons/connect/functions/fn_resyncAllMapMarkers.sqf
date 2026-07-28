/*
    Relance l’envoi Athena de tous les marqueurs carte visibles.
    Couvre les marqueurs posés avant la liaison Athena (MarkerCreated manqué)
    et ceux du Marker Dropper ATAK Enhanced / Arma.
    Diff par signature pour éviter de spammer SendMarker.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {};

private _markers = allMapMarkers;
if (!(_markers isEqualType [])) exitWith {};

private _prev = missionNamespace getVariable ["COMSPEC_Athena_MapMarkerSnap", createHashMap];
if (!(_prev isEqualType createHashMap)) then { _prev = createHashMap; };
private _next = createHashMap;

private _mirroredElsewherePrefixes = [
    "poi_local_", "qrf_contact_", "medevac_lz_", "vehicle_service_",
    "comspec_roleplay_zone_", "comspec_tabletmk_", "comspec_shape_", "ctab_u_"
];

{
    private _name = _x;
    if ((_name select [0, 1]) == "_" && {!([_name] call comspec_overwatch_connect_fnc_isSyncableMapMarker)}) then { continue };

    private _nameLower = toLower _name;
    if (({ (_nameLower find _x) == 0 } count _mirroredElsewherePrefixes) > 0) then { continue };

    private _shape = toUpper (markerShape _name);
    private _type = markerType _name;
    if (_type isEqualTo "" && {!(_shape isEqualTo "POLYLINE")}) then {
        if (_shape isEqualTo "ELLIPSE" || {_shape isEqualTo "RECTANGLE"}) then {
            _type = "mil_circle";
        } else {
            _type = "mil_dot";
        };
    };
    if (!(_shape isEqualTo "POLYLINE") && {_type isEqualTo ""}) then { continue };

    private _pos = markerPos _name;
    private _text = markerText _name;
    private _color = markerColor _name;
    private _dir = markerDir _name;
    private _alpha = markerAlpha _name;
    private _size = markerSize _name;
    private _brush = markerBrush _name;
    private _sig = format [
        "%1|%2|%3|%4|%5|%6|%7|%8|%9|%10|%11",
        _pos select 0, _pos select 1, _type, _text, _color, _dir, _alpha, _shape,
        _size select 0, _size select 1, _brush
    ];
    _next set [_name, _sig];
    if ((_prev getOrDefault [_name, ""]) isEqualTo _sig) then { continue };

    [_name, false] call comspec_overwatch_connect_fnc_syncMapMarker;
} forEach _markers;

{
    if (!(_x in _next)) then {
        "COMSPECExtension" callExtension ["SendMarker", [_x, "{}", "1", "1"]];
    };
} forEach (keys _prev);

missionNamespace setVariable ["COMSPEC_Athena_MapMarkerSnap", _next, false];
