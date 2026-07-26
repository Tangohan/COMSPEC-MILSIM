/*
    Module Zeus/Eden : Zone de couverture dégradée.
*/

params [["_logic", objNull], ["_units", []], ["_activated", true]];

if (!_activated) exitWith {};

private _pos = getPos _logic;
private _radius = _logic getVariable ["Radius", 500];
private _intensity = _logic getVariable ["Intensity", 30];

[_pos, _radius, "degraded", _intensity] call comspec_overwatch_connect_fnc_createRoleplayZone;

deleteVehicle _logic;
true
