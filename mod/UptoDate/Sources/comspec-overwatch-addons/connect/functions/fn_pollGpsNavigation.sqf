/*
    Navigation GPS Athena : poll des waypoints non atteints, marque reached à proximité,
    et alimente COMSPEC_GpsNav* pour enrichir POST /api/atak/position (module gps_navigation).
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(["gps_navigation"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {
    missionNamespace setVariable ["COMSPEC_GpsNavActive", false, false];
    false
};

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetWaypoints", [_mapId, "40", "0"]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
private _lines = _body splitString (toString [10]);
private _tab = toString [9];
private _unit = player;
if (isNull _unit || {!alive _unit}) exitWith { false };
private _pos = getPosASL _unit;
private _speed = vectorMagnitude (velocity _unit);
if (_speed < 0.05) then { _speed = 0.05; };

private _best = [];
private _bestDist = 1e12;

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 7) then { continue };

    private _unblank = {
        params ["_s"];
        _s = trim _s;
        if (_s isEqualTo "-") then { "" } else { _s };
    };

    private _id = [_cols select 0] call _unblank;
    if (_id isEqualTo "") then { continue };
    private _routeId = [_cols select 1] call _unblank;
    private _label = [_cols select 2] call _unblank;
    private _px = parseNumber (_cols select 3);
    private _py = parseNumber (_cols select 4);
    private _radius = parseNumber (_cols select 5);
    if (_radius < 8) then { _radius = 25; };
    private _reached = [_cols select 6] call _unblank;
    if (_reached isEqualTo "1") then { continue };

    private _d = [_pos select 0, _pos select 1, 0] distance2D [_px, _py, 0];
    if (_d < _bestDist) then {
        _bestDist = _d;
        _best = [_id, _routeId, _label, _px, _py, _radius, _d];
    };
} forEach _lines;

if (_best isEqualTo []) exitWith {
    missionNamespace setVariable ["COMSPEC_GpsNavActive", false, false];
    missionNamespace setVariable ["COMSPEC_GpsNavRouteId", "", false];
    missionNamespace setVariable ["COMSPEC_GpsNavWaypointId", "", false];
    missionNamespace setVariable ["COMSPEC_GpsNavDistanceM", -1, false];
    missionNamespace setVariable ["COMSPEC_GpsNavEtaSeconds", -1, false];
    false
};

_best params ["_wpId", "_routeId", "_label", "_px", "_py", "_radius", "_dist"];
private _eta = round (_dist / _speed);
missionNamespace setVariable ["COMSPEC_GpsNavActive", true, false];
missionNamespace setVariable ["COMSPEC_GpsNavRouteId", _routeId, false];
missionNamespace setVariable ["COMSPEC_GpsNavWaypointId", _wpId, false];
missionNamespace setVariable ["COMSPEC_GpsNavDistanceM", round _dist, false];
missionNamespace setVariable ["COMSPEC_GpsNavEtaSeconds", _eta, false];
missionNamespace setVariable ["COMSPEC_GpsNavLabel", _label, false];

if (_dist <= _radius) then {
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    private _mark = ["COMSPECExtension" callExtension ["MarkWaypointReached", [_wpId, _cs, "1"]]] call comspec_overwatch_connect_fnc_extResult;
    private _parsed = [_mark] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
    if ((_parsed isEqualType []) && {(count _parsed) > 0} && {_parsed select 0}) then {
        ["Point GPS atteint" + (if (_label isEqualTo "") then { "" } else { " — " + _label }), "gps", "info"] call comspec_overwatch_connect_fnc_announce;
    };
};

true
