/*
    Crée un mât vanilla détruisible et l’enregistre comme relais ATAK.
    Params: [_pos, _range, _name]
*/
params [
    ["_pos", [0, 0, 0], [[]], 3],
    ["_range", 2000, [0]],
    ["_name", "Relais ATAK", [""]]
];

if ((count _pos) < 2) exitWith { objNull };
_range = (_range max 50) min 8000;
if (_name isEqualTo "") then { _name = "Relais ATAK"; };

private _obj = createVehicle ["Land_Antenna_01_F", _pos, [], 0, "CAN_COLLIDE"];
if (isNull _obj) exitWith { objNull };
_obj setPosATL _pos;
_obj setVariable ["COMSPEC_AtakRelay", true, true];
_obj setVariable ["COMSPEC_AtakRelayRange", _range, true];
_obj setVariable ["COMSPEC_AtakRelayName", _name, true];
private _uid = format ["relay_%1_%2_%3", round (_pos select 0), round (_pos select 1), round random 9999];
_obj setVariable ["COMSPEC_AtakRelayUid", _uid, true];

private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
_list = _list select { !isNull _x };
_list pushBackUnique _obj;
missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];

_obj addEventHandler ["Killed", {
    params ["_obj"];
    [_obj, false] call comspec_overwatch_connect_fnc_syncAtakRelay;
}];
_obj addEventHandler ["Deleted", {
    params ["_obj"];
    [_obj, false] call comspec_overwatch_connect_fnc_syncAtakRelay;
}];

[_obj, true] call comspec_overwatch_connect_fnc_syncAtakRelay;
_obj
