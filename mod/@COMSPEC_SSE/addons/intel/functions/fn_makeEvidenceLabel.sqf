params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { "OP-SITE-OBJ-SSE-XXXX" };

private _slug = {
    params ["_s"];
    if (isNil "_s") then { _s = ""; };
    if !(_s isEqualType "") then { _s = str _s; };
    private _out = toUpper ((_s splitString " /:_") joinString "-");
    if (_out isEqualTo "") then { _out = "X"; };
    _out
};

private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = "SSE-XXXX";
private _type = "OBJ";
private _room = "SITE";
if (!isNil "_data" && {_data isEqualType []}) then {
    _uid = [_data, "uid", "SSE-XXXX"] call comspec_sse_fnc_getPair;
    _type = [_data, "type", "OBJ"] call comspec_sse_fnc_getPair;
    if (isNil "_uid") then { _uid = "SSE-XXXX"; };
    if (isNil "_type") then { _type = "OBJ"; };
    if (_uid isEqualType []) then { _uid = "SSE-XXXX"; };
    if (_type isEqualType []) then { _type = "OBJ"; };

    private _cluster = [_data, "cluster", createHashMap] call comspec_sse_fnc_getPair;
    if (!isNil "_cluster" && {_cluster isEqualType createHashMap}) then {
        private _r = _cluster getOrDefault ["room", ""];
        if (isNil "_r" || {_r isEqualTo ""}) then { _r = _cluster getOrDefault ["area", ""]; };
        if (!isNil "_r" && {_r isEqualType ""} && {_r isNotEqualTo ""}) then { _room = _r; };
    };
    private _traces = [_data, "traces", []] call comspec_sse_fnc_getPair;
    if (!isNil "_traces" && {_traces isEqualType []} && {count _traces > 0}) then {
        private _t0 = _traces select 0;
        if (_t0 isEqualType createHashMap) then {
            private _area = _t0 getOrDefault ["area", ""];
            if (!isNil "_area" && {_area isEqualType ""} && {_area isNotEqualTo ""}) then { _room = _area; };
        };
    };
};

private _mission = missionNamespace getVariable ["comspec_sse_missionId", "OP"];
if (isNil "_mission" || {!(_mission isEqualType "")} || {_mission isEqualTo ""}) then { _mission = "OP"; };

format ["%1-%2-%3-%4", [_mission] call _slug, [_room] call _slug, [_type] call _slug, [_uid] call _slug]
