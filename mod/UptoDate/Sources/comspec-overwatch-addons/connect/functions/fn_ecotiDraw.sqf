/*
    Draw3D — alliés, marqueurs, véhicules, contours, itinéraires, bâtiment marqué.
*/
if (!([] call comspec_overwatch_connect_fnc_ecotiIsActive)) exitWith {};

private _camPos = positionCameraToWorld [0, 0, 0];
private _maxDist = missionNamespace getVariable ["comspec_overwatch_ecoti_max_dist", 1200];
private _maxIcons = missionNamespace getVariable ["comspec_overwatch_ecoti_max_icons", 40];
private _showAllies = missionNamespace getVariable ["comspec_overwatch_ecoti_show_allies", true];
private _showMarkers = missionNamespace getVariable ["comspec_overwatch_ecoti_show_markers", true];
private _showVehicles = missionNamespace getVariable ["comspec_overwatch_ecoti_show_vehicles", true];
private _showOutline = missionNamespace getVariable ["comspec_overwatch_ecoti_show_outline", true];
private _showRoute = missionNamespace getVariable ["comspec_overwatch_ecoti_show_route", true];
private _icon = "\a3\ui_f\data\map\markers\military\dot_CA.paa";
private _drawn = 0;

private _fnc_screenOk = {
    params ["_pos"];
    private _scr = worldToScreen _pos;
    if (!(_scr isEqualType []) || {(count _scr) < 2}) exitWith { false };
    private _sx = _scr select 0;
    private _sy = _scr select 1;
    (_sx > -0.05) && {_sx < 1.05} && {_sy > -0.05} && {_sy < 1.05}
};

if (_showAllies) then {
    private _units = allUnits select {
        alive _x
        && {_x != player}
        && {side group _x == side group player}
        && {(_x distance _camPos) <= (_maxDist min 800)}
    };
    {
        if (_drawn >= _maxIcons) exitWith {};
        private _pos = _x modelToWorldVisual [0, 0, 1.7];
        if (!([_pos] call _fnc_screenOk)) then { continue };
        private _dist = _camPos distance _pos;
        private _label = name _x;
        if (_label isEqualTo "") then { _label = groupId (group _x) };
        [
            _pos,
            _icon,
            [0.55, 1, 0.65, 0.95],
            _label,
            _dist,
            0.7
        ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
        _drawn = _drawn + 1;
    } forEach _units;
};

if (_showVehicles && {_drawn < _maxIcons}) then {
    private _vehs = (nearestObjects [_camPos, ["LandVehicle", "Air", "Ship"], _maxDist]) select {
        alive _x
        && {count (crew _x) > 0}
        && {side group _x == side group player || {side _x == side group player}}
    };
    {
        if (_drawn >= _maxIcons) exitWith {};
        private _pos = getPosASLVisual _x;
        _pos = ASLToAGL _pos;
        _pos set [2, (_pos select 2) + 2];
        if (!([_pos] call _fnc_screenOk)) then { continue };
        private _dist = _camPos distance _pos;
        private _dn = getText (configFile >> "CfgVehicles" >> typeOf _x >> "displayName");
        if (_dn isEqualTo "") then { _dn = typeOf _x };
        [
            _pos,
            "\a3\ui_f\data\map\markers\nato\b_armor.paa",
            [0.45, 0.8, 1, 0.95],
            _dn,
            _dist,
            0.75
        ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
        _drawn = _drawn + 1;
    } forEach _vehs;
};

if (_showMarkers && {_drawn < _maxIcons}) then {
    {
        if (_drawn >= _maxIcons) exitWith {};
        private _m = _x;
        if (_m isEqualTo "") then { continue };
        private _alpha = markerAlpha _m;
        if (_alpha <= 0.05) then { continue };
        private _pos = markerPos _m;
        if (!(_pos isEqualType []) || {(count _pos) < 2}) then { continue };
        if ((_pos select 0) == 0 && {(_pos select 1) == 0}) then { continue };
        if ((_camPos distance2D _pos) > _maxDist) then { continue };
        private _z = _pos select 2;
        if (_z < 0.5) then {
            _pos = [_pos select 0, _pos select 1, (getTerrainHeightASL [_pos select 0, _pos select 1]) + 2];
            _pos = ASLToAGL _pos;
        };
        if (!([_pos] call _fnc_screenOk)) then { continue };
        private _dist = _camPos distance _pos;
        private _txt = markerText _m;
        if (_txt isEqualTo "") then { _txt = markerType _m };
        private _col = getArray (configFile >> "CfgMarkerColors" >> markerColor _m >> "color");
        if (!(_col isEqualType []) || {(count _col) < 3}) then { _col = [1, 1, 1, 0.95] };
        if ((count _col) < 4) then { _col pushBack 0.95 } else { _col set [3, 0.95] };
        private _mIcon = getText (configFile >> "CfgMarkers" >> markerType _m >> "icon");
        if (_mIcon isEqualTo "") then { _mIcon = _icon };
        [
            _pos,
            _mIcon,
            _col,
            _txt,
            _dist,
            0.68
        ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
        _drawn = _drawn + 1;
    } forEach allMapMarkers;
};

if (_showRoute) then {
    [] call comspec_overwatch_connect_fnc_ecotiDrawRoute;
};

if (_showOutline) then {
    private _tgt = cursorObject;
    if (!isNull _tgt && {_tgt != player} && {(_tgt distance _camPos) <= 90}) then {
        private _bldgMarked = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
        if (_tgt isNotEqualTo _bldgMarked) then {
            [_tgt, [0.95, 0.88, 0.25, 0.7]] call comspec_overwatch_connect_fnc_ecotiDrawOutline;
        };
    };
};

// Anneau au sol sous l’éclairage de zone (si actif).
private _lightPos = missionNamespace getVariable ["COMSPEC_EcotiZoneLightPos", []];
if (_lightPos isEqualType [] && {(count _lightPos) >= 3}) then {
    private _r = 6;
    private _ringCol = [0.2, 1, 0.4, 0.55];
    private _prev = [];
    for "_i" from 0 to 16 do {
        private _a = (_i / 16) * 360;
        private _p = [
            (_lightPos select 0) + (_r * cos _a),
            (_lightPos select 1) + (_r * sin _a),
            (_lightPos select 2) + 0.15
        ];
        if ((count _prev) >= 3) then {
            drawLine3D [_prev, _p, _ringCol];
        };
        _prev = _p;
    };
};

private _bldg = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
if (!isNull _bldg) then {
    [_bldg] call comspec_overwatch_connect_fnc_ecotiDrawBuilding;
    private _bPos = _bldg modelToWorldVisual [0, 0, 2];
    private _bName = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuildingName", ""];
    if (_bName isEqualTo "") then {
        _bName = getText (configFile >> "CfgVehicles" >> typeOf _bldg >> "displayName");
    };
    if (_bName isEqualTo "") then { _bName = "Bâtiment"; };
    [
        _bPos,
        "\a3\ui_f\data\map\mapcontrol\Bunker_CA.paa",
        [0.4, 1, 0.55, 0.98],
        _bName,
        _camPos distance _bPos,
        0.8
    ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
};
