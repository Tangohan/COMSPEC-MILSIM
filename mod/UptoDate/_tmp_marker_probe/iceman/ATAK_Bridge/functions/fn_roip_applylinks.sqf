if (!hasInterface) exitWith {false};

private _state = call Iceman_fnc_roip_getState;
private _radio = "";
if !(isNil "acre_api_fnc_getRadioByType") then {
    private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
    if (!(isNil "_candidate") && {_candidate isEqualType ""}) then {_radio = _candidate};
};

if (_radio == "" || {isNil "acre_sys_data_fnc_dataEvent"}) exitWith {
    _state set ["activeLinks", []];
    _state set ["appliedSignature", "__NO_RADIO__"];
    missionNamespace setVariable ["Iceman_ROIP_activeLinks", []];
    player setVariable ["Iceman_Bridge_monitorChannelData", [], false];
    player setVariable ["Iceman_Bridge_monitorRecords", [], false];
    false
};

private _links = call Iceman_fnc_roip_getActiveLinks;
_state set ["activeLinks", _links];
missionNamespace setVariable ["Iceman_ROIP_activeLinks", _links];

private _wrState = if !(isNil "Iceman_fnc_wr_getState") then {call Iceman_fnc_wr_getState} else {createHashMap};
private _monitors = +(_wrState getOrDefault ["monitorTalkgroups", player getVariable ["Iceman_WR_monitorTalkgroups", []]]);
private _bank = _wrState getOrDefault ["frequency", player getVariable ["Iceman_WR_frequency", "32.0"]];
private _linkSignature = _links apply {
    private _link = _x # 0;
    [_link # 1, _link # 2, _link # 3, _link # 4, _link # 5, _link # 6, _link # 7, _link # 8, _link # 12]
};
private _signature = str [_radio, _bank, _monitors, _linkSignature];
if ((_state getOrDefault ["appliedSignature", ""]) == _signature) exitWith {true};

if !(isNil "Iceman_fnc_wr_syncAcreChannels") then {
    _wrState set ["acreChannelSignature", ""];
    call Iceman_fnc_wr_syncAcreChannels;
};

private _channels = [_radio, "getState", "channels"] call acre_sys_data_fnc_dataEvent;
if (isNil "_channels" || {!(_channels isEqualType [])} || {_channels isEqualTo []}) exitWith {false};

private _monitorChannels = [];
private _monitorRecords = [];
{
    private _link = _x # 0;
    private _tg = (_link # 3) max 1 min 16;
    private _slot = _tg - 1;
    if (_slot < count _channels) then {
        private _channel = [_link] call Iceman_fnc_roip_linkToChannel;
        if (!isNull _channel) then {
            _channels set [_slot, _channel];
            if (_tg in _monitors || {_tg == 16}) then {
                _monitorChannels pushBack _channel;
                _monitorRecords pushBack _link;
            };
        };
    };
} forEach _links;

[_radio, "setState", ["channels", _channels]] call acre_sys_data_fnc_dataEvent;
player setVariable ["Iceman_Bridge_monitorChannelData", _monitorChannels, false];
player setVariable ["Iceman_Bridge_monitorRecords", _monitorRecords, false];
player setVariable ["Iceman_ROIP_routedLinks", _linkSignature, false];
_state set ["appliedSignature", _signature];

true
