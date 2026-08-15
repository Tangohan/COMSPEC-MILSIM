/*
    Module Zeus/Eden : Zone sans couverture.
*/

params [["_logic", objNull], ["_units", []], ["_activated", true]];

if (!_activated) exitWith {};

private _pos = getPos _logic;
private _radius = _logic getVariable ["Radius", 200];
private _intensity = _logic getVariable ["Intensity", 100];

[_pos, _radius, "no_coverage", _intensity] call comspec_overwatch_connect_fnc_createRoleplayZone;

deleteVehicle _logic;
true
