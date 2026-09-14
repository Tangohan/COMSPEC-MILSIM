/*
    Clic dans la liste Effectifs de la tablette.
    Params : [_index]
*/
params [["_idx", -1]];
if (_idx < 0) exitWith {};
private _rows = missionNamespace getVariable ["COMSPEC_DeviceRosterRows", []];
if (!(_rows isEqualType []) || {_idx >= count _rows}) exitWith {};
[(_rows select _idx), true] call comspec_overwatch_connect_fnc_reachOverlaySelect;
