params [["_record", []]];

if !([player] call Iceman_fnc_bridge_hasradio) exitWith {false};
if (isNil "acre_api_fnc_getRadioByType" || {isNil "acre_sys_data_fnc_dataEvent"}) exitWith {false};
if !(_record isEqualType [] && {(count _record) >= 4}) exitWith {false};
if !([_record # 0] call Iceman_fnc_bridge_haslegacyradio) exitWith {false};

private _radio = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
if (isNil "_radio" || {!(_radio isEqualType "")} || {_radio == ""}) exitWith {false};

private _channel = [_record] call Iceman_fnc_bridge_recordtochannel;
if (isNull _channel) exitWith {false};

private _slot = missionNamespace getVariable ["Iceman_Bridge_mpu5Slot", 15];
[_radio, "setChannelData", [_slot, _channel]] call acre_sys_data_fnc_dataEvent;
[_radio, "setCurrentChannel", _slot] call acre_sys_data_fnc_dataEvent;

if !(isNil "acre_api_fnc_setCurrentRadio") then {
    [_radio] call acre_api_fnc_setCurrentRadio;
};
if !(isNil "acre_api_fnc_setRadioChannel") then {
    [_radio, _slot + 1] call acre_api_fnc_setRadioChannel;
};

private _state = call Iceman_fnc_bridge_getstate;
_state set ["activeRecord", +_record];
_state set ["acreLastRadio", _radio];
player setVariable ["Iceman_Bridge_activeChannelData", _channel, false];
player setVariable ["Iceman_Bridge_activeRecordId", [_record] call Iceman_fnc_bridge_recordid, true];
player setVariable ["Iceman_Bridge_activeRadioClass", _record # 0, true];
player setVariable ["Iceman_Bridge_activeChannelIndex", _record # 1, true];
player setVariable ["Iceman_Bridge_activeOwner", name player, true];
player setVariable ["Iceman_WR_bridgeActive", true, true];

true
