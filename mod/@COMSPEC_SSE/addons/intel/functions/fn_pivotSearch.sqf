/*
    Pivot : trouve d'autres entités SSE partageant numéro / alias / grid / id.
    [_entity] call comspec_sse_fnc_pivotSearch
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { [] };

private _keys = [];
private _id = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (!isNil "_id" && {_id isEqualType createHashMap}) then {
    { if (_y != "") then { _keys pushBackUnique (toLower str _y); }; } forEach [
        _id getOrDefault ["alias", ""],
        _id getOrDefault ["phone", ""],
        _id getOrDefault ["plate", ""],
        _id getOrDefault ["name", ""]
    ];
};
private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
if (!isNil "_devices" && {_devices isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _n = _x getOrDefault ["phoneNumber", ""];
            if (_n != "") then { _keys pushBackUnique (toLower _n); };
        };
    } forEach _devices;
};
private _locs = [_entity, "locations"] call comspec_sse_fnc_getSection;
if (!isNil "_locs" && {_locs isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _g = _x getOrDefault ["grid", ""];
            if (_g != "") then { _keys pushBackUnique (toLower _g); };
        };
    } forEach _locs;
};

if (_keys isEqualTo []) exitWith { [] };

private _matches = [];
{
    private _o = _x;
    if (!isNull _o && {_o != _entity} && {!isNil {[_o] call comspec_sse_fnc_getData}}) then {
        private _blob = toLower str ([_o] call comspec_sse_fnc_getData);
        private _hit = false;
        { if ((_blob find _x) >= 0) exitWith { _hit = true; }; } forEach _keys;
        if (_hit) then {
            _matches pushBack _o;
            ["SSE_NetworkLinked", [netId _entity, netId _o, _keys]] call comspec_sse_fnc_emitEvent;
        };
    };
} forEach (allUnits + vehicles + (allMissionObjects "All"));

_matches
