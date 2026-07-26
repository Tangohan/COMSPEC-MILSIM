/*
    Module Zeus/Eden : Zone d'interférence.
*/

params [["_logic", objNull], ["_units", []], ["_activated", true]];

if (!_activated) exitWith {};

private _pos = getPos _logic;
private _radius = _logic getVariable ["Radius", 300];
private _intensity = _logic getVariable ["Intensity", 50];

[_pos, _radius, "interference", _intensity] call comspec_overwatch_connect_fnc_createRoleplayZone;

deleteVehicle _logic;
true
