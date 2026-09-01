/*
    Navigation GPS Athena : poll des waypoints (sequence 8e colonne optionnelle),
    marqueurs locaux numérotés + polyline d’itinéraire, reached à proximité,
    alimente COMSPEC_GpsNav* pour POST /api/atak/position (module gps_navigation).
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _fnc_clearGpsVisuals = {
    private _prev = missionNamespace getVariable ["COMSPEC_GpsWpIds", []];
    if (!(_prev isEqualType [])) then { _prev = []; };
    {
        private _n = format ["COMSPEC_GPS_WP_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
    } forEach _prev;
    private _rtPrev = missionNamespace getVariable ["COMSPEC_GpsRouteIds", []];
    if (!(_rtPrev isEqualType [])) then { _rtPrev = []; };
    {
        private _n = format ["COMSPEC_GPS_RT_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
    } forEach _rtPrev;
    missionNamespace setVariable ["COMSPEC_GpsWpIds", [], false];
    missionNamespace setVariable ["COMSPEC_GpsRouteIds", [], false];
};

private _fnc_clearGpsNav = {
    missionNamespace setVariable ["COMSPEC_GpsNavActive", false, false];
    missionNamespace setVariable ["COMSPEC_GpsNavRouteId", "", false];
    missionNamespace setVariable ["COMSPEC_GpsNavWaypointId", "", false];
    missionNamespace setVariable ["COMSPEC_GpsNavDistanceM", -1, false];
    missionNamespace setVariable ["COMSPEC_GpsNavEtaSeconds", -1, false];
    missionNamespace setVariable ["COMSPEC_GpsNavLabel", "", false];
};

if (!(["gps_navigation"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {
    [] call _fnc_clearGpsVisuals;
    [] call _fnc_clearGpsNav;
    false
};

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetWaypoints", [_mapId, "40", "all"]]] call comspec_overwatch_connect_fnc_extResult;
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

private _unblank = {
    params ["_s"];
    _s = trim (str _s);
    if (_s isEqualTo "-") then { "" } else { _s };
};

private _byRoute = createHashMap;
private _recvIdx = 0;
{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 7) then { continue };

    private _id = [_cols select 0] call _unblank;
    if (_id isEqualTo "") then { continue };
    private _routeId = [_cols select 1] call _unblank;
    if (_routeId isEqualTo "") then { _routeId = "0"; };
    private _label = [_cols select 2] call _unblank;
    private _px = parseNumber (_cols select 3);
    private _py = parseNumber (_cols select 4);
    private _radius = parseNumber (_cols select 5);
    if (_radius < 8) then { _radius = 25; };
    private _reachedRaw = [_cols select 6] call _unblank;
    private _isReached = _reachedRaw in ["1", "true", "TRUE"];
    private _seq = _recvIdx;
    if ((count _cols) >= 8) then {
        private _seqRaw = [_cols select 7] call _unblank;
        if !(_seqRaw isEqualTo "") then {
            private _seqNum = parseNumber _seqRaw;
            if (_seqNum > 0) then { _seq = _seqNum; };
        };
    };
    _recvIdx = _recvIdx + 1;

    private _list = _byRoute getOrDefault [_routeId, []];
    _list pushBack [_id, _routeId, _label, _px, _py, _radius, _isReached, _seq];
    _byRoute set [_routeId, _list];
} forEach _lines;

if ((count _byRoute) isEqualTo 0) exitWith {
    [] call _fnc_clearGpsVisuals;
    [] call _fnc_clearGpsNav;
    false
};

{
    private _rid = _x;
    private _list = _byRoute get _rid;
    _list = [_list, [], { _x select 7 }, "ASCEND"] call BIS_fnc_sortBy;
    _byRoute set [_rid, _list];
} forEach (keys _byRoute);

private _keepRoute = str (missionNamespace getVariable ["COMSPEC_GpsNavRouteId", ""]);
_keepRoute = (_keepRoute splitString """" joinString "");
if (_keepRoute in ["-", "0"]) then { _keepRoute = ""; };

private _fnc_nextUnreached = {
    params ["_list"];
    private _found = [];
    {
        if (!(_x select 6)) exitWith { _found = _x; };
    } forEach _list;
    _found
};

private _activeRoute = "";
if (_keepRoute != "" && {_keepRoute in _byRoute}) then {
    private _keptNext = [_byRoute get _keepRoute] call _fnc_nextUnreached;
    if !(_keptNext isEqualTo []) then { _activeRoute = _keepRoute; };
};
if (_activeRoute isEqualTo "") then {
    private _bestD = 1e12;
    {
        private _rid = _x;
        private _next = [_byRoute get _rid] call _fnc_nextUnreached;
        if (_next isEqualTo []) then { continue };
        private _d = [_pos select 0, _pos select 1, 0] distance2D [_next select 3, _next select 4, 0];
        if (_d < _bestD) then {
            _bestD = _d;
            _activeRoute = _rid;
        };
    } forEach (keys _byRoute);
};

private _activeList = if (_activeRoute != "" && {_activeRoute in _byRoute}) then {
    _byRoute get _activeRoute
} else {
    []
};
private _next = [_activeList] call _fnc_nextUnreached;

private _seenWp = [];
private _seenRt = [];
if (_activeRoute != "" && {(count _activeList) > 0}) then {
    private _disp = 0;
    {
        _x params ["_id", "_routeId", "_label", "_px", "_py", "_radius", "_isReached", "_seq"];
        _disp = _disp + 1;
        private _num = if (_seq > 0) then { round _seq } else { _disp };
        private _name = format ["COMSPEC_GPS_WP_%1", _id];
        _seenWp pushBack _id;
        private _txt = if (_label isEqualTo "") then {
            str _num
        } else {
            format ["%1 — %2", _num, _label]
        };
        private _color = if (_isReached) then { "ColorGrey" } else { "ColorYellow" };
        private _alpha = if (_isReached) then { 0.45 } else { 0.95 };
        if (_name in allMapMarkers) then {
            _name setMarkerPosLocal [_px, _py];
            _name setMarkerTextLocal _txt;
            _name setMarkerTypeLocal "mil_pickup";
            _name setMarkerColorLocal _color;
            _name setMarkerAlphaLocal _alpha;
        } else {
            private _mk = createMarkerLocal [_name, [_px, _py]];
            _mk setMarkerTypeLocal "mil_pickup";
            _mk setMarkerColorLocal _color;
            _mk setMarkerTextLocal _txt;
            _mk setMarkerAlphaLocal _alpha;
        };
    } forEach _activeList;

    private _flat = [];
    {
        if (!(_x select 6)) then {
            _flat pushBack (_x select 3);
            _flat pushBack (_x select 4);
        };
    } forEach _activeList;
    private _rtName = format ["COMSPEC_GPS_RT_%1", _activeRoute];
    _seenRt pushBack _activeRoute;
    if ((count _flat) >= 4) then {
        if (_rtName in allMapMarkers) then {
            _rtName setMarkerPolylineLocal _flat;
            _rtName setMarkerPosLocal [_flat select 0, _flat select 1, 0];
            _rtName setMarkerColorLocal "ColorYellow";
            _rtName setMarkerAlphaLocal 0.75;
        } else {
            private _route = createMarkerLocal [_rtName, [_flat select 0, _flat select 1, 0]];
            _route setMarkerShapeLocal "POLYLINE";
            _route setMarkerColorLocal "ColorYellow";
            _route setMarkerBrushLocal "Solid";
            _route setMarkerPolylineLocal _flat;
            _route setMarkerAlphaLocal 0.75;
        };
    } else {
        if (_rtName in allMapMarkers) then { deleteMarkerLocal _rtName; };
        _seenRt = _seenRt - [_activeRoute];
    };
};

private _prevWp = missionNamespace getVariable ["COMSPEC_GpsWpIds", []];
if (!(_prevWp isEqualType [])) then { _prevWp = []; };
{
    if (!(_x in _seenWp)) then {
        private _n = format ["COMSPEC_GPS_WP_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
    };
} forEach _prevWp;
private _prevRt = missionNamespace getVariable ["COMSPEC_GpsRouteIds", []];
if (!(_prevRt isEqualType [])) then { _prevRt = []; };
{
    if (!(_x in _seenRt)) then {
        private _n = format ["COMSPEC_GPS_RT_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
    };
} forEach _prevRt;
missionNamespace setVariable ["COMSPEC_GpsWpIds", _seenWp, false];
missionNamespace setVariable ["COMSPEC_GpsRouteIds", _seenRt, false];

if (_next isEqualTo []) exitWith {
    [] call _fnc_clearGpsNav;
    false
};

_next params ["_wpId", "_routeId", "_label", "_px", "_py", "_radius", "_isReached", "_seq"];
private _dist = [_pos select 0, _pos select 1, 0] distance2D [_px, _py, 0];
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
        private _hitName = format ["COMSPEC_GPS_WP_%1", _wpId];
        if (_hitName in allMapMarkers) then {
            _hitName setMarkerColorLocal "ColorGrey";
            _hitName setMarkerAlphaLocal 0.45;
        };
    };
};

true
