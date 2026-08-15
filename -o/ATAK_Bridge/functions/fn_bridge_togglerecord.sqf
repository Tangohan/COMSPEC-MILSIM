params [
    ["_key", "txChannels"],
    ["_record", []],
    ["_maxCount", 999]
];

if !(_record isEqualType [] && {(count _record) >= 4}) exitWith {false};

private _state = call Iceman_fnc_bridge_getstate;
private _records = +(_state getOrDefault [_key, []]);
private _id = [_record] call Iceman_fnc_bridge_recordid;
private _index = _records findIf {([_x] call Iceman_fnc_bridge_recordid) == _id};
private _added = false;

if (_index >= 0) then {
    _records deleteAt _index;
} else {
    if ((count _records) >= _maxCount) then {
        _records deleteAt 0;
    };
    _records pushBack _record;
    _added = true;
};

_state set [_key, _records];
if (_key == "monitorChannels") then {
    call Iceman_fnc_bridge_applymonitors;
};

_added
