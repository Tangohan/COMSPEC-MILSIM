params [
    ["_entity", objNull, [objNull]]
];
private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = [_data, "uid", "SSE-XXXX"] call comspec_sse_fnc_getPair;
private _type = [_data, "type", "OBJ"] call comspec_sse_fnc_getPair;
if (_uid isEqualType []) then { _uid = "SSE-XXXX"; };
if (_type isEqualType []) then { _type = "OBJ"; };
private _mission = missionNamespace getVariable ["comspec_sse_missionId", "OP"];
private _room = "SITE";
if (!isNil "_data" && {_data isEqualType []}) then {
    private _cluster = [_data, "cluster", createHashMap] call comspec_sse_fnc_getPair;
    if (_cluster isEqualType createHashMap) then {
        private _r = _cluster getOrDefault ["room", ""];
        if (_r isEqualTo "") then { _r = _cluster getOrDefault ["area", ""]; };
        if (_r isNotEqualTo "") then { _room = _r; };
    };
    private _traces = [_data, "traces", []] call comspec_sse_fnc_getPair;
    if (_traces isEqualType [] && {count _traces > 0}) then {
        private _t0 = _traces select 0;
        if (_t0 isEqualType createHashMap) then {
            private _area = _t0 getOrDefault ["area", ""];
            if (_area isNotEqualTo "") then { _room = _area; };
        };
    };
};
// Nettoyage pour label custody (pas d'espaces / accents critiques)
_room = toUpper ([_room, " ", "-"] call BIS_fnc_replaceString);
format ["%1-%2-%3-%4", _mission, _room, _type, _uid]
