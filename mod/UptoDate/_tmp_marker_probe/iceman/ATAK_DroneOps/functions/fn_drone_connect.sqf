params [["_drone", objNull]];

if (isNull _drone) then {
    private _candidates = vehicles select {
        (_x distance player) < 35 &&
        {[_x] call Iceman_fnc_drone_isSupported} &&
        {[_x] call Iceman_fnc_drone_canControl}
    };
    _candidates = [_candidates, [], {_x distance player}, "ASCEND"] call BIS_fnc_sortBy;
    _drone = _candidates param [0, objNull];
};

if (!([_drone] call Iceman_fnc_drone_isSupported)) exitWith {
    ["DRONE", "No supported Black Hornet / AR-2 drone found nearby.", 5] call cTab_fnc_addNotification;
};

if (!([_drone] call Iceman_fnc_drone_canControl)) exitWith {
    private _ownerName = _drone getVariable ["Iceman_DroneOps_ownerName", "another operator"];
    ["DRONE", format ["Drone is already controlled by %1.", _ownerName], 5] call cTab_fnc_addNotification;
};

private _state = call Iceman_fnc_drone_getState;
_state set ["drone", _drone];
_state set ["lastContacts", createHashMap];
_state set ["contacts", []];
_state set ["markerCounter", 0];

_drone setVariable ["Iceman_DroneOps_ownerUID", getPlayerUID player, true];
_drone setVariable ["Iceman_DroneOps_ownerName", name player, true];
_drone setVariable ["Iceman_DroneOps_ownerUnit", player, true];
_drone setVariable ["Iceman_DroneOps_active", true, true];
if ((_drone getVariable ["Iceman_DroneOps_ownerDroneIndex", 0]) <= 0) then {
    private _uid = getPlayerUID player;
    private _used = vehicles select {
        (_x getVariable ["Iceman_DroneOps_ownerUID", ""]) isEqualTo _uid &&
        {(_x getVariable ["Iceman_DroneOps_ownerDroneIndex", 0]) > 0}
    };
    private _maxIndex = 0;
    {
        _maxIndex = _maxIndex max (_x getVariable ["Iceman_DroneOps_ownerDroneIndex", 0]);
    } forEach _used;
    _drone setVariable ["Iceman_DroneOps_ownerDroneIndex", _maxIndex + 1, true];
};
_drone setVariable ["Iceman_DroneOps_markerPrefix", "BH", true];

player enableUAVConnectability [_drone, true];
[_drone] call Iceman_fnc_drone_startup;
[_drone] call Iceman_fnc_drone_registerFeedLocal;
[_drone] remoteExecCall ["Iceman_fnc_drone_registerFeedLocal", 0, _drone];

player reveal _drone;
["DRONE", format ["Connected %1 to ATAK.", getText (configOf _drone >> "displayName")], 4] call cTab_fnc_addNotification;
call Iceman_fnc_drone_updatePanel;

_drone
