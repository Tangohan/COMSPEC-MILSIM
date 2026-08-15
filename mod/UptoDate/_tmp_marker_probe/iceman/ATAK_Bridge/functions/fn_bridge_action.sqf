params [["_slot", 0]];

private _state = call Iceman_fnc_bridge_getstate;
private _radioClass = _state getOrDefault ["radioClass", "ACRE_PRC152"];
private _channels = _state getOrDefault ["lastChannels", []];
private _selection = (_state getOrDefault ["selection", 0]) max 0;
private _selected = [];
if (_selection < count _channels) then {
    _selected = _channels # _selection;
};

private _legacyName = {
    params [["_class", "ACRE_PRC152"]];
    ["PRC-117F", "PRC-152"] select (_class == "ACRE_PRC152")
};

switch (_slot) do {
    case 0: {
        private _newRadio = ["ACRE_PRC117F", "ACRE_PRC152"] select (_radioClass == "ACRE_PRC117F");
        [_newRadio] call Iceman_fnc_bridge_selectradio;
    };
    case 1: {
        if (_selected isEqualTo []) exitWith {};
        if !([_selected # 0] call Iceman_fnc_bridge_haslegacyradio) exitWith {
            ["BRIDGE", format ["%1 required in your kit or current vehicle.", [_selected # 0] call _legacyName], 2] call Iceman_fnc_bridge_notify;
        };
        private _added = ["txChannels", _selected, 4] call Iceman_fnc_bridge_togglerecord;
        ["BRIDGE", format ["%1 Bridge TX: %2 CH%3 - %4", ["Removed from", "Added to"] select _added, ["PRC-117F", "PRC-152"] select ((_selected # 0) == "ACRE_PRC152"), (_selected # 1) + 1, _selected # 2], 2] call Iceman_fnc_bridge_notify;
    };
    case 2: {
        if (_selected isEqualTo []) exitWith {};
        if !([_selected # 0] call Iceman_fnc_bridge_haslegacyradio) exitWith {
            ["BRIDGE", format ["%1 required in your kit or current vehicle.", [_selected # 0] call _legacyName], 2] call Iceman_fnc_bridge_notify;
        };
        private _added = ["monitorChannels", _selected, 16] call Iceman_fnc_bridge_togglerecord;
        ["BRIDGE", format ["%1 Bridge MON: %2 CH%3 - %4", ["Removed from", "Added to"] select _added, ["PRC-117F", "PRC-152"] select ((_selected # 0) == "ACRE_PRC152"), (_selected # 1) + 1, _selected # 2], 2] call Iceman_fnc_bridge_notify;
    };
    case 3: {
        if (_selected isEqualTo []) exitWith {};
        if !([_selected # 0] call Iceman_fnc_bridge_haslegacyradio) exitWith {
            ["BRIDGE", format ["%1 required in your kit or current vehicle.", [_selected # 0] call _legacyName], 2] call Iceman_fnc_bridge_notify;
        };
        if ([_selected] call Iceman_fnc_bridge_applyactive) then {
            private _selectedId = [_selected] call Iceman_fnc_bridge_recordid;
            private _mon = +(_state getOrDefault ["monitorChannels", []]);
            if ((_mon findIf {([_x] call Iceman_fnc_bridge_recordid) == _selectedId}) < 0) then {
                _mon pushBack _selected;
                _state set ["monitorChannels", _mon];
            };
            call Iceman_fnc_bridge_applymonitors;
            ["BRIDGE", format ["Active bridge set: %1 CH%2 - %3", ["PRC-117F", "PRC-152"] select ((_selected # 0) == "ACRE_PRC152"), (_selected # 1) + 1, _selected # 2], 2] call Iceman_fnc_bridge_notify;
        } else {
            ["BRIDGE", "Could not apply bridge channel to the MPU-5.", 2] call Iceman_fnc_bridge_notify;
        };
    };
};

call Iceman_fnc_bridge_updatepanel;
true
