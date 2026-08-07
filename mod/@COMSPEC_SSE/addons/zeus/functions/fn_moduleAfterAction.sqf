params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };
[_logic, 150] call comspec_sse_fnc_afterActionReport;
private _graph = [] call comspec_sse_fnc_exportMissionGraph;
hint format ["AAR généré — graphe exporté (%1 entités).", count (_graph getOrDefault ["entities", []])];
if (!isNull _logic) then { deleteVehicle _logic; };
true
