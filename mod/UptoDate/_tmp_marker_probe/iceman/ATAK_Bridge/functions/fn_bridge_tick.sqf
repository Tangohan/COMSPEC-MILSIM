call Iceman_fnc_bridge_applymonitors;

private _state = call Iceman_fnc_bridge_getstate;
private _active = _state getOrDefault ["activeRecord", []];
private _bridgeActive = _active isEqualType [] && {(count _active) >= 4} && {[_active # 0] call Iceman_fnc_bridge_haslegacyradio};
player setVariable ["Iceman_WR_bridgeActive", _bridgeActive, true];
if (_bridgeActive) then {
    player setVariable ["Iceman_Bridge_activeRecordId", [_active] call Iceman_fnc_bridge_recordid, true];
    player setVariable ["Iceman_Bridge_activeRadioClass", _active # 0, true];
    player setVariable ["Iceman_Bridge_activeChannelIndex", _active # 1, true];
    player setVariable ["Iceman_Bridge_activeOwner", name player, true];
} else {
    player setVariable ["Iceman_Bridge_activeRecordId", "", true];
    player setVariable ["Iceman_Bridge_activeOwner", "", true];
};

private _pageGroup = uiNamespace getVariable ["Iceman_ATAK_Bridge_group", controlNull];
if (!isNull _pageGroup) then {
    call Iceman_fnc_bridge_updatepanel;
};

true
