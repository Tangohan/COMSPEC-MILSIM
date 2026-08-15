/*
    Module Zeus/Eden : Brouilleur actif.
*/

params [["_logic", objNull], ["_units", []], ["_activated", true]];

if (!_activated) exitWith {};

private _pos = getPos _logic;
private _radius = _logic getVariable ["Radius", 400];
private _intensity = _logic getVariable ["Intensity", 80];

[_pos, _radius, "jammer", _intensity] call comspec_overwatch_connect_fnc_createRoleplayZone;

deleteVehicle _logic;
true
