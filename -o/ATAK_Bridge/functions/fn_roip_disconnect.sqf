params [
    ["_silent", false],
    ["_reason", "Link disconnected."]
];

private _state = call Iceman_fnc_roip_getState;
private _wasActive = (_state getOrDefault ["connectedRadioId", ""]) != "" || {player getVariable ["Iceman_ROIP_active", false]};

_state set ["connectedRadioId", ""];
_state set ["connectedTalkgroup", 0];
_state set ["localLink", []];
_state set ["lastPublishedSignature", ""];
_state set ["appliedSignature", "__DISCONNECT__"];

player setVariable ["Iceman_ROIP_active", false, true];
player setVariable ["Iceman_ROIP_link", [], true];
player setVariable ["Iceman_WR_bridgeActive", false, true];
player setVariable ["Iceman_Bridge_activeRecordId", "", true];
player setVariable ["Iceman_Bridge_activeRadioClass", "", true];
player setVariable ["Iceman_Bridge_activeChannelIndex", -1, true];
player setVariable ["Iceman_Bridge_activeOwner", "", true];

call Iceman_fnc_roip_applyLinks;
call Iceman_fnc_roip_updatePanel;

if (!_silent && {_wasActive}) then {
    ["ROIP", _reason, 2] call Iceman_fnc_roip_notify;
};

true
