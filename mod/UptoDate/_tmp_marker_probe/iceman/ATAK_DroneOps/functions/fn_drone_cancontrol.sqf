params [["_drone", objNull]];

if (!([_drone] call Iceman_fnc_drone_isSupported)) exitWith {false};

private _owner = _drone getVariable ["Iceman_DroneOps_ownerUID", ""];
(_owner isEqualTo "") || {_owner isEqualTo getPlayerUID player}
