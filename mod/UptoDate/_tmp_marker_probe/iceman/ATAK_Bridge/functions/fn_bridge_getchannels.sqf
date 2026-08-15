params [["_radioClass", "ACRE_PRC152"]];

private _channels = [];
for "_i" from 0 to 98 do {
    private _record = [_radioClass, _i] call Iceman_fnc_bridge_channelrecord;
    if (_record isNotEqualTo []) then {
        _channels pushBack _record;
    };
};

_channels
