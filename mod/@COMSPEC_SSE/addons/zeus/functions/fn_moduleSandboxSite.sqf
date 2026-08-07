params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };
[60] call comspec_sse_fnc_sandboxGenerateSite;
if (!isNull _logic) then { deleteVehicle _logic; };
true
