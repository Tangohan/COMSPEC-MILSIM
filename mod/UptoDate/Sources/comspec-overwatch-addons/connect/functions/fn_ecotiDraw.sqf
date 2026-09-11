/*
    Draw3D — alliés, marqueurs, véhicules + wireframe bâtiment marqué.
*/
if (!([] call comspec_overwatch_connect_fnc_ecotiIsActive)) exitWith {};

private _camPos = positionCameraToWorld [0, 0, 0];
private _maxDist = missionNamespace getVariable ["comspec_overwatch_ecoti_max_dist", 1200];
private _maxIcons = missionNamespace getVariable ["comspec_overwatch_ecoti_max_icons", 40];
private _showAllies = missionNamespace getVariable ["comspec_overwatch_ecoti_show_allies", true];
private _showMarkers = missionNamespace getVariable ["comspec_overwatch_ecoti_show_markers", true];
private _showVehicles = missionNamespace getVariable ["comspec_overwatch_ecoti_show_vehicles", true];
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
        private _txt = format ["%1  %2", _label, [_dist] call comspec_overwatch_connect_fnc_ecotiFormatDistance];
        drawIcon3D [_icon, [0.45, 0.95, 0.55, 0.9], _pos, 0.55, 0.55, 0, _txt, 1, 0.028, "PuristaMedium"];
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
        private _txt = format ["%1  %2", _dn, [_dist] call comspec_overwatch_connect_fnc_ecotiFormatDistance];
        drawIcon3D [
            "\a3\ui_f\data\map\markers\nato\b_armor.paa",
            [0.4, 0.75, 1, 0.85],
            _pos, 0.6, 0.6, 0, _txt, 1, 0.026, "PuristaMedium"
        ];
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
        if (!(_col isEqualType []) || {(count _col) < 3}) then { _col = [1, 1, 1, 0.85] };
        if ((count _col) < 4) then { _col pushBack 0.85 } else { _col set [3, 0.85] };
        private _mIcon = getText (configFile >> "CfgMarkers" >> markerType _m >> "icon");
        if (_mIcon isEqualTo "") then { _mIcon = _icon };
        drawIcon3D [
            _mIcon,
            _col,
            _pos, 0.55, 0.55, 0,
            format ["%1  %2", _txt, [_dist] call comspec_overwatch_connect_fnc_ecotiFormatDistance],
            1, 0.025, "PuristaMedium"
        ];
        _drawn = _drawn + 1;
    } forEach allMapMarkers;
};

private _bldg = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
if (!isNull _bldg) then {
    [_bldg] call comspec_overwatch_connect_fnc_ecotiDrawBuilding;
};
