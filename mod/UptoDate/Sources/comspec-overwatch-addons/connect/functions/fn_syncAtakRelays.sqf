/*
    Synchronise tous les relais connus (pose Zeus + objets encore présents).
*/
if (!hasInterface && {!isServer}) exitWith {};
private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
_list = _list select { !isNull _x };
{
    if (!(_x getVariable ["COMSPEC_AtakRelay", false])) then { continue };
    [_x, alive _x] call comspec_overwatch_connect_fnc_syncAtakRelay;
} forEach _list;
missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];
true
