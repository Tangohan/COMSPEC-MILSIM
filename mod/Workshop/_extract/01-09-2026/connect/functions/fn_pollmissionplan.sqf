/*
    Lit l’ordre de mission (objectifs, LD, H) depuis ATAK web. Lecture seule.
    Pose les repères sur la carte et alimente l’onglet Ordre d’Athena.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetMissionPlan", [_mapId]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
private _prev = missionNamespace getVariable ["COMSPEC_MissionPlanSig", ""];
if (_body isEqualTo _prev) exitWith { true };
missionNamespace setVariable ["COMSPEC_MissionPlanSig", _body, false];

private _nl = toString [10];
private _tab = toString [9];
private _lines = _body splitString _nl;
private _unblank = {
    params ["_s"];
    _s = trim _s;
    if (_s isEqualTo "-") then { "" } else { _s };
};

private _plan = createHashMap;
private _graphics = [];
private _timeline = [];
private _seen = [];

{
    private _cols = _x splitString _tab;
    if ((count _cols) < 1) then { continue };
    private _kind = _cols select 0;

    if (_kind isEqualTo "P" && {(count _cols) >= 4}) then {
        _plan set ["code", [_cols select 1] call _unblank];
        _plan set ["title", [_cols select 2] call _unblank];
        _plan set ["status", [_cols select 3] call _unblank];
        _plan set ["h_hour", if ((count _cols) > 4) then { [_cols select 4] call _unblank } else { "" }];
        _plan set ["sentence", if ((count _cols) > 5) then { [_cols select 5] call _unblank } else { "" }];
        _plan set ["phase", if ((count _cols) > 6) then { [_cols select 6] call _unblank } else { "" }];
        _plan set ["clock", if ((count _cols) > 7) then { [_cols select 7] call _unblank } else { "" }];
        continue;
    };

    if (_kind isEqualTo "G" && {(count _cols) >= 7}) then {
        private _id = [_cols select 1] call _unblank;
        private _code = [_cols select 2] call _unblank;
        private _label = [_cols select 3] call _unblank;
        private _gKind = [_cols select 4] call _unblank;
        private _xPos = parseNumber (_cols select 5);
        private _yPos = parseNumber (_cols select 6);
        private _state = if ((count _cols) > 7) then { [_cols select 7] call _unblank } else { "planned" };
        _graphics pushBack (createHashMapFromArray [
            ["id", _id],
            ["code", _code],
            ["label", _label],
            ["kind", _gKind],
            ["x", _xPos],
            ["y", _yPos],
            ["state", _state]
        ]);
        if ((abs _xPos) < 0.5 && {(abs _yPos) < 0.5}) then { continue };
        if (_id isEqualTo "") then { _id = _code; };
        if (_id isEqualTo "") then { continue };
        _seen pushBack _id;
        private _name = format ["comspec_mpgfx_%1", _id];
        private _mkType = "mil_dot";
        private _mkColor = "ColorGrey";
        switch (toLower _gKind) do {
            case "ld": { _mkType = "mil_start"; };
            case "pl": { _mkType = "mil_ambush"; };
            case "obj": { _mkType = "mil_objective"; };
            case "lz";
            case "hlz": { _mkType = "mil_pickup"; };
            case "orp";
            case "cp": { _mkType = "mil_join"; };
            case "axis": { _mkType = "mil_arrow"; };
        };
        switch (toLower _state) do {
            case "current": { _mkColor = "ColorBlue"; };
            case "completed": { _mkColor = "ColorGreen"; };
            case "modified": { _mkColor = "ColorYellow"; };
            default { _mkColor = "ColorGrey"; };
        };
        private _txt = if (_label isEqualTo "") then { _code } else { format ["%1 %2", _code, _label] };
        if (_name in allMapMarkers) then {
            _name setMarkerPosLocal [_xPos, _yPos];
            _name setMarkerTextLocal _txt;
            _name setMarkerTypeLocal _mkType;
            _name setMarkerColorLocal _mkColor;
        } else {
            private _mk = createMarkerLocal [_name, [_xPos, _yPos]];
            _mk setMarkerTypeLocal _mkType;
            _mk setMarkerColorLocal _mkColor;
            _mk setMarkerTextLocal _txt;
            _mk setMarkerAlphaLocal 0.9;
        };
        continue;
    };

    if (_kind isEqualTo "T" && {(count _cols) >= 3}) then {
        _timeline pushBack (createHashMapFromArray [
            ["code", [_cols select 1] call _unblank],
            ["label", [_cols select 2] call _unblank],
            ["occurred", if ((count _cols) > 3) then { [_cols select 3] call _unblank } else { "0" }],
            ["clock", if ((count _cols) > 4) then { [_cols select 4] call _unblank } else { "" }]
        ]);
    };
} forEach _lines;

private _prevIds = missionNamespace getVariable ["COMSPEC_MissionPlanMarkerIds", []];
if (!(_prevIds isEqualType [])) then { _prevIds = []; };
{
    if (!(_x in _seen)) then {
        private _n = format ["comspec_mpgfx_%1", _x];
        if (_n in allMapMarkers) then { deleteMarkerLocal _n; };
    };
} forEach _prevIds;

missionNamespace setVariable ["COMSPEC_MissionPlan", _plan, false];
missionNamespace setVariable ["COMSPEC_MissionPlanGraphics", _graphics, false];
missionNamespace setVariable ["COMSPEC_MissionPlanTimeline", _timeline, false];
missionNamespace setVariable ["COMSPEC_MissionPlanMarkerIds", _seen, false];

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};

true
