/*
    Poll des zones tactiques Athena (GET /api/atak/zones via GetTacticalZones).
    Stocke COMSPEC_DangerZones, pose des marqueurs locaux COMSPEC_TZ_<id>,
    alerte à l’entrée d’une zone dangereuse (alert_on_entry). Distinct des zones roleplay.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetTacticalZones", [_mapId, "80"]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
private _lines = _body splitString (toString [10]);
private _tab = toString [9];

private _unblank = {
    params ["_s"];
    _s = trim (str _s);
    if (_s isEqualTo "-") then { "" } else { _s };
};

private _fnc_zoneColor = {
    params ["_type"];
    switch (_type) do {
        case "LZ";
        case "DZ";
        case "EXTRACT_POINT": { "ColorBlue" };
        case "OBJECTIVE": { "ColorYellow" };
        case "DANGER_ZONE";
        case "NO_GO_AREA";
        case "RESTRICTED_AREA": { "ColorRed" };
        case "FREE_FIRE_ZONE": { "ColorOrange" };
        case "SAFE_ZONE";
        case "RALLY_POINT": { "ColorGreen" };
        default { "ColorGrey" };
    }
};

private _fnc_parsePoly = {
    params ["_rawPoly"];
    private _pts = [];
    if (_rawPoly isEqualTo "") exitWith { _pts };
    {
        private _pair = _x splitString ",";
        if ((count _pair) >= 2) then {
            _pts pushBack [parseNumber (_pair select 0), parseNumber (_pair select 1)];
        };
    } forEach (_rawPoly splitString ";");
    _pts
};

private _seen = [];
{
    if (_x isEqualTo "") then { continue };
    private _cols = _x splitString _tab;
    if ((count _cols) < 9) then { continue };

    private _id = [_cols select 0] call _unblank;
    if (_id isEqualTo "") then { continue };
    private _zoneType = toUpper ([_cols select 1] call _unblank);
    if (_zoneType isEqualTo "") then { _zoneType = "OTHER"; };
    private _geomType = toUpper ([_cols select 2] call _unblank);
    if (_geomType isEqualTo "") then { _geomType = "CIRCLE"; };
    private _cx = parseNumber (_cols select 3);
    private _cy = parseNumber (_cols select 4);
    private _radius = parseNumber (_cols select 5);
    if (_radius < 1) then { _radius = 25; };
    private _threat = [_cols select 6] call _unblank;
    if (_threat isEqualTo "") then { _threat = "MEDIUM"; };
    private _label = [_cols select 7] call _unblank;
    private _alert = parseNumber ([_cols select 8] call _unblank);
    private _polyRaw = if ((count _cols) > 9) then { [_cols select 9] call _unblank } else { "" };
    private _poly = [_polyRaw] call _fnc_parsePoly;

    private _centerOrPoints = [_cx, _cy];
    if ((count _poly) >= 3) then {
        _centerOrPoints = _poly;
        if !(_geomType in ["POLYGON", "POLYLINE", "PATH", "RECTANGLE"]) then {
            _geomType = "POLYGON";
        };
    };

    [_id, _geomType, _centerOrPoints, _radius, _zoneType, _threat, _label, _alert] call comspec_overwatch_connect_fnc_receiveDangerZone;
    _seen pushBack _id;

    private _name = format ["COMSPEC_TZ_%1", _id];
    private _color = [_zoneType] call _fnc_zoneColor;
    private _txt = if (_label isNotEqualTo "") then {
        _label
    } else {
        switch (_zoneType) do {
            case "LZ": { "Zone de poser" };
            case "DZ": { "Zone de largage" };
            case "EXTRACT_POINT": { "Extraction" };
            case "OBJECTIVE": { "Objectif" };
            case "DANGER_ZONE": { "Zone dangereuse" };
            case "NO_GO_AREA": { "Zone interdite" };
            case "RESTRICTED_AREA": { "Zone réglementée" };
            case "RALLY_POINT": { "Point de ralliement" };
            case "SAFE_ZONE": { "Zone sûre" };
            default { "Zone" };
        }
    };

    if (_name in allMapMarkers) then { deleteMarkerLocal _name; };

    if ((count _poly) >= 3 && {_geomType in ["POLYGON", "POLYLINE", "PATH", "RECTANGLE"]}) then {
        private _flat = [];
        { _flat pushBack (_x select 0); _flat pushBack (_x select 1); } forEach _poly;
        if ((count _flat) >= 4) then {
            private _mk = createMarkerLocal [_name, [(_poly select 0) select 0, (_poly select 0) select 1, 0]];
            _mk setMarkerShapeLocal "POLYLINE";
            _mk setMarkerColorLocal _color;
            _mk setMarkerBrushLocal "Solid";
            _mk setMarkerPolylineLocal _flat;
            _mk setMarkerAlphaLocal 0.7;
            _mk setMarkerTextLocal _txt;
        };
    } else {
        private _mk = createMarkerLocal [_name, [_cx, _cy]];
        _mk setMarkerShapeLocal "ELLIPSE";
        _mk setMarkerSizeLocal [_radius, _radius];
        _mk setMarkerColorLocal _color;
        _mk setMarkerBrushLocal "Border";
        _mk setMarkerAlphaLocal 0.7;
        _mk setMarkerTextLocal _txt;
    };
} forEach _lines;

private _zones = missionNamespace getVariable ["COMSPEC_DangerZones", []];
_zones = _zones select { (_x select 0) in _seen };
missionNamespace setVariable ["COMSPEC_DangerZones", _zones, true];

private _prev = missionNamespace getVariable ["COMSPEC_TacticalZoneIds", []];
if (!(_prev isEqualType [])) then { _prev = []; };
{
    if (!(_x in _seen)) then {
        private _n = format ["COMSPEC_TZ_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
        [_x] call comspec_overwatch_connect_fnc_deleteDangerZone;
    };
} forEach _prev;
missionNamespace setVariable ["COMSPEC_TacticalZoneIds", _seen, false];

private _hit = [player] call comspec_overwatch_connect_fnc_checkPlayerInDangerZone;
if ((count _hit) >= 2) then {
    private _zid = _hit select 0;
    private _ztype = _hit select 1;
    private _threat = _hit param [2, "MEDIUM"];
    private _zlabel = _hit param [3, ""];
    private _alertFlag = _hit param [4, 0];
    private _shouldWarn = (_alertFlag isEqualTo 1)
        || {_alertFlag isEqualTo true}
        || {_ztype in ["DANGER_ZONE", "NO_GO_AREA", "RESTRICTED_AREA"]};
    if (_shouldWarn) then {
        [_zid, _ztype, _threat, _zlabel] call comspec_overwatch_connect_fnc_warnDangerZoneEntry;
    };
};

true
