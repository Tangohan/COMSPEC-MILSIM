private _state = call Iceman_fnc_drone_getState;
private _drone = _state getOrDefault ["drone", objNull];

if (isNull _drone || {!alive _drone}) exitWith {
    ["DRONE", "Connect a supported drone first.", 4] call cTab_fnc_addNotification;
};

if (!([_drone] call Iceman_fnc_drone_canControl)) exitWith {
    ["DRONE", "You do not own this drone.", 5] call cTab_fnc_addNotification;
};

(call Iceman_fnc_drone_readUi) params ["_pos", "_altitude", "_function", "_radius"];
if (_function != "protect" && {_pos isEqualTo []}) exitWith {
    ["DRONE", "Set a valid point first.", 4] call cTab_fnc_addNotification;
};

if (_function == "protect") then {
    _pos = getPosASL player;
};

_state set ["target", _pos];
_state set ["altitude", _altitude];
_state set ["function", _function];
_state set ["radius", _radius];
_state set ["lastContacts", createHashMap];
_state set ["lastProtectTask", 0];
_state set ["lastProtectPos", []];

[_drone, _pos, _altitude, _function, _radius, player] remoteExecCall ["Iceman_fnc_drone_applyTask", 2];

private _label = switch (_function) do {
    case "loiter": {"Loiter"};
    case "scan": {"Scan"};
    case "protect": {"Protect"};
    default {"Move"};
};

["DRONE", format ["%1 task sent at %2m.", _label, round _altitude], 4] call cTab_fnc_addNotification;
call Iceman_fnc_drone_updatePanel;
