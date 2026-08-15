private _state = call Iceman_fnc_bridge_getstate;
private _records = +(_state getOrDefault ["monitorChannels", []]);
private _active = _state getOrDefault ["activeRecord", []];
if (_active isNotEqualTo []) then {
    private _activeId = [_active] call Iceman_fnc_bridge_recordid;
    if ((_records findIf {([_x] call Iceman_fnc_bridge_recordid) == _activeId}) < 0) then {
        _records pushBack _active;
    };
};

private _channels = [];
private _availableRecords = [];
{
    if (_x isEqualType [] && {(count _x) >= 4} && {[_x # 0] call Iceman_fnc_bridge_haslegacyradio}) then {
        private _channel = [_x] call Iceman_fnc_bridge_recordtochannel;
        if (!isNull _channel) then {
            _availableRecords pushBack _x;
            _channels pushBack _channel;
        };
    };
} forEach _records;

player setVariable ["Iceman_Bridge_monitorChannelData", _channels, false];
player setVariable ["Iceman_Bridge_monitorRecords", _availableRecords, false];
true
