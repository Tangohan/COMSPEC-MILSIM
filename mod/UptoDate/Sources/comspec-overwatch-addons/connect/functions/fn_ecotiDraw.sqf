/*
    Draw3D — alliés, marqueurs, véhicules, contours, itinéraires, bâtiment marqué.
    Pastilles : mode world3d (drawIcon3D) ou screen2d (HUD écran anti-chevauchement).
*/
if (!([] call comspec_overwatch_connect_fnc_ecotiIsActive)) exitWith {
    [] call comspec_overwatch_connect_fnc_ecotiHudHide;
};

missionNamespace setVariable ["COMSPEC_EcotiBadgeQueue", [], false];

private _camPos = positionCameraToWorld [0, 0, 0];
private _maxDist = missionNamespace getVariable ["comspec_overwatch_ecoti_max_dist", 1200];
private _maxIcons = missionNamespace getVariable ["comspec_overwatch_ecoti_max_icons", 40];
private _showAllies = missionNamespace getVariable ["comspec_overwatch_ecoti_show_allies", true];
private _showMarkers = missionNamespace getVariable ["comspec_overwatch_ecoti_show_markers", true];
private _showVehicles = missionNamespace getVariable ["comspec_overwatch_ecoti_show_vehicles", true];
private _showOutline = missionNamespace getVariable ["comspec_overwatch_ecoti_show_outline", true];
private _showRoute = missionNamespace getVariable ["comspec_overwatch_ecoti_show_route", true];
private _showUnitOutline = missionNamespace getVariable ["comspec_overwatch_ecoti_show_unit_outline", true];
private _icon = [] call comspec_overwatch_connect_fnc_ecotiIconPath;
private _drawn = 0;
private _theme = [] call comspec_overwatch_connect_fnc_ecotiThemeColors;
private _fusionOn = missionNamespace getVariable ["comspec_overwatch_ecoti_fusion", true];
if (!(_fusionOn isEqualType true)) then { _fusionOn = true; };
private _colAllies = _theme getOrDefault ["allies", [0.75, 1, 0.95, 1]];
private _colVeh = _theme getOrDefault ["vehicles", [0.7, 0.95, 1, 1]];
private _colOutline = _theme getOrDefault ["outline", [1, 1, 0.7, 0.95]];
private _colUnit = _theme getOrDefault ["unit", [0.8, 1, 0.95, 0.98]];
private _colBldg = _theme getOrDefault ["building", [0.65, 1, 0.9, 0.98]];
if (_fusionOn && {!([] call comspec_overwatch_connect_fnc_ecotiA3tiPresent)}) then {
    private _hot = _theme getOrDefault ["thermal", [1, 0.96, 0.86, 0.95]];
    _colOutline = [_hot select 0, _hot select 1, _hot select 2, 0.96];
    _colBldg = [_hot select 0, _hot select 1, _hot select 2, 0.98];
};

private _bldg = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
private _bldgMk = missionNamespace getVariable ["COMSPEC_EcotiBuildingMarker", ""];
private _bldgPos = if (!isNull _bldg) then { getPosATL _bldg } else { [] };
private _cutaway = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
if (!(_cutaway isEqualType true)) then { _cutaway = false; };

private _labelSlots = [];

private _fnc_screenOk = {
    params ["_pos"];
    private _scr = worldToScreen _pos;
    if (!(_scr isEqualType []) || {(count _scr) < 2}) exitWith { false };
    private _sx = _scr select 0;
    private _sy = _scr select 1;
    (_sx > -0.05) && {_sx < 1.05} && {_sy > -0.05} && {_sy < 1.05}
};

private _fnc_needsCompact = {
    params ["_pos"];
    private _scr = worldToScreen _pos;
    if (!(_scr isEqualType []) || {(count _scr) < 2}) exitWith { true };
    private _hit = false;
    {
        if ((_scr distance2D _x) < 0.07) exitWith { _hit = true };
    } forEach _labelSlots;
    if (!_hit) then {
        _labelSlots pushBack _scr;
    };
    _hit
};

private _fnc_labelScore = {
    params ["_label"];
    private _s = trim _label;
    private _score = 100 - ((count _s) min 80);
    if ((_s find "[") >= 0) then { _score = _score - 25; };
    if ((_s find "(") >= 0) then { _score = _score - 5; };
    _score
};

// Fusionne les entrées trop proches (même lieu = un seul badge).
private _fnc_dedupSpatial = {
    params ["_entries", ["_radius", 14]];
    private _out = [];
    {
        private _cand = _x;
        _cand params ["_pos", "_ic", "_col", "_label", "_dist", "_isz"];
        private _idx = -1;
        {
            if (((_out select _forEachIndex) select 0) distance2D _pos <= _radius) exitWith {
                _idx = _forEachIndex;
            };
        } forEach _out;
        if (_idx < 0) then {
            _out pushBack _cand;
        } else {
            private _prev = _out select _idx;
            private _scoreNew = [_label] call _fnc_labelScore;
            private _scoreOld = [_prev select 3] call _fnc_labelScore;
            if (_scoreNew > _scoreOld || {_scoreNew == _scoreOld && {_dist < (_prev select 4)}}) then {
                _out set [_idx, _cand];
            };
        };
    } forEach _entries;
    _out
};

private _fnc_drawSorted = {
    params ["_entries"];
    _entries = [_entries, [], { _x select 4 }, "ASCEND"] call BIS_fnc_sortBy;
    {
        if (_drawn >= _maxIcons) exitWith {};
        _x params ["_pos", "_ic", "_col", "_label", "_dist", "_isz"];
        private _compact = [_pos] call _fnc_needsCompact;
        [_pos, _ic, _col, _label, _dist, _isz, _compact] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
        _drawn = _drawn + 1;
    } forEach _entries;
};

if (_showAllies) then {
    private _entries = [];
    private _units = allUnits select {
        alive _x
        && {_x != player}
        && {side group _x == side group player}
        && {(_x distance _camPos) <= (_maxDist min 800)}
    };
    {
        private _pos = _x modelToWorldVisual [0, 0, 1.7];
        if (!([_pos] call _fnc_screenOk)) then { continue };
        private _dist = _camPos distance _pos;
        private _label = name _x;
        if (_label isEqualTo "") then { _label = groupId (group _x) };
        if (_showUnitOutline && {_dist <= 120}) then {
            [_x, _colUnit] call comspec_overwatch_connect_fnc_ecotiDrawUnitOutline;
        };
        if (_fusionOn) then {
            [_pos, _dist, "unit"] call comspec_overwatch_connect_fnc_ecotiDrawFusion;
        };
        _entries pushBack [_pos, _icon, _colAllies, _label, _dist, 0.5];
    } forEach _units;
    [_entries] call _fnc_drawSorted;
};

if (_showVehicles && {_drawn < _maxIcons}) then {
    private _entries = [];
    private _vehs = (nearestObjects [_camPos, ["LandVehicle", "Air", "Ship"], _maxDist]) select {
        alive _x
        && {count (crew _x) > 0}
        && {side group _x == side group player || {side _x == side group player}}
    };
    {
        private _pos = getPosASLVisual _x;
        _pos = ASLToAGL _pos;
        _pos set [2, (_pos select 2) + 2];
        if (!([_pos] call _fnc_screenOk)) then { continue };
        private _dist = _camPos distance _pos;
        private _dn = getText (configFile >> "CfgVehicles" >> typeOf _x >> "displayName");
        if (_dn isEqualTo "") then { _dn = typeOf _x };
        if (_fusionOn && {isEngineOn _x}) then {
            [_pos, _dist, "vehicle"] call comspec_overwatch_connect_fnc_ecotiDrawFusion;
        };
        _entries pushBack [_pos, "\a3\ui_f\data\map\markers\nato\b_armor.paa", _colVeh, _dn, _dist, 0.52];
    } forEach _vehs;
    [[_entries] call _fnc_dedupSpatial] call _fnc_drawSorted;
};

if (_showMarkers && {_drawn < _maxIcons}) then {
    private _entries = [];
    {
        private _m = _x;
        if (_m isEqualTo "") then { continue };
        // Marqueur ECOTI du bâtiment déjà géré à part (silhouette + un libellé).
        if (_bldgMk isNotEqualTo "" && {_m isEqualTo _bldgMk}) then { continue };
        if (((toLower _m) find "comspec_ecoti_bldg_") == 0) then { continue };

        private _alpha = markerAlpha _m;
        if (_alpha <= 0.05) then { continue };
        private _pos = markerPos _m;
        if (!(_pos isEqualType []) || {(count _pos) < 2}) then { continue };
        if ((_pos select 0) == 0 && {(_pos select 1) == 0}) then { continue };
        if ((_camPos distance2D _pos) > _maxDist) then { continue };

        // Déjà couvert par le bâtiment désigné.
        if ((count _bldgPos) >= 2 && {(_pos distance2D _bldgPos) <= 18}) then { continue };

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
        _col = [_col] call comspec_overwatch_connect_fnc_ecotiNormalizeColor;
        private _lum = ((_col select 0) + (_col select 1) + (_col select 2)) / 3;
        if (_lum < 0.45) then {
            _col = [
                ((_col select 0) + 0.35) min 1,
                ((_col select 1) + 0.35) min 1,
                ((_col select 2) + 0.35) min 1,
                1
            ];
        } else {
            _col set [3, 1];
        };
        private _mIcon = getText (configFile >> "CfgMarkers" >> markerType _m >> "icon");
        if (_mIcon isEqualTo "") then { _mIcon = _icon };
        _entries pushBack [_pos, _mIcon, _col, _txt, _dist, 0.48];
    } forEach allMapMarkers;

    _entries = [_entries, 16] call _fnc_dedupSpatial;
    [_entries] call _fnc_drawSorted;
};

if (_showRoute) then {
    [] call comspec_overwatch_connect_fnc_ecotiDrawRoute;
};

if (_showOutline) then {
    private _tgt = cursorObject;
    if (!isNull _tgt && {_tgt != player} && {(_tgt distance _camPos) <= 90}) then {
        if (_tgt isNotEqualTo _bldg) then {
            [_tgt, _colOutline] call comspec_overwatch_connect_fnc_ecotiDrawOutline;
        };
    };
};

private _lightPos = missionNamespace getVariable ["COMSPEC_EcotiZoneLightPos", []];
if (_lightPos isEqualType [] && {(count _lightPos) >= 3}) then {
    private _r = 6;
    private _ringCol = _colAllies;
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

if (!isNull _bldg) then {
    // Silhouette / étages seulement — le libellé nom est unique ci-dessous.
    [_bldg, false] call comspec_overwatch_connect_fnc_ecotiDrawBuilding;

    private _bPos = _bldg modelToWorldVisual [0, 0, 2];
    private _bName = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuildingName", ""];
    if (_bName isEqualTo "") then {
        _bName = getText (configFile >> "CfgVehicles" >> typeOf _bldg >> "displayName");
    };
    if (_bName isEqualTo "") then { _bName = "Bâtiment"; };

    private _floors = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloorCount", 1];
    private _sel = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
    if (!(_sel isEqualType 0)) then { _sel = 0; };
    if (!(_floors isEqualType 0)) then { _floors = 1; };
    private _label = if (_cutaway) then {
        format ["%1 · ét. %2/%3", _bName, (_sel max 0) + 1, _floors max 1]
    } else {
        _bName
    };

    private _bDist = _camPos distance _bPos;
    if (_fusionOn) then {
        [_bPos, _bDist, "building"] call comspec_overwatch_connect_fnc_ecotiDrawFusion;
    };
    private _bCompact = if ([_bPos] call _fnc_screenOk) then { [_bPos] call _fnc_needsCompact } else { false };
    [
        _bPos,
        "\a3\ui_f\data\map\mapcontrol\Bunker_CA.paa",
        _colBldg,
        _label,
        _bDist,
        0.55,
        _bCompact
    ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
};

// Flush pastilles : 2D écran ou rien (déjà dessinées en 3D).
private _mode = missionNamespace getVariable ["comspec_overwatch_ecoti_render_mode", "world3d"];
if (!(_mode isEqualType "")) then { _mode = "world3d"; };
_mode = toLower _mode;
if (_mode in ["screen2d", "screen", "hud2d", "2d"]) then {
    private _q = missionNamespace getVariable ["COMSPEC_EcotiBadgeQueue", []];
    if (!(_q isEqualType [])) then { _q = []; };
    [_q] call comspec_overwatch_connect_fnc_ecotiHudRender;
} else {
    [] call comspec_overwatch_connect_fnc_ecotiHudHide;
};
