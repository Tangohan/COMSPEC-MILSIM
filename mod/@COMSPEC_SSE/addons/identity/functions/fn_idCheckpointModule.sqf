/*
    Module Eden « Checkpoint IA » (SERVEUR, modules_identity.hpp) : gardes = unités synchronisées et leur groupe,
    sinon IA du camp choisi dans la zone. Créé deux secondes après le lancement (unités apparues, papiers décidés).
    [_logic, _units, _activated] call comspec_sse_fnc_idCheckpointModule
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {isNull _logic} || {!_activated}) exitWith { true };
private _guards = [];
{
    if (_x isKindOf "CAManBase") then { { if (!isPlayer _x) then { _guards pushBackUnique _x; }; } forEach (units group _x); };
} forEach ((synchronizedObjects _logic) + _units);
private _spec = createHashMapFromArray [
    ["radius", _logic getVariable ["Radius", -1]], ["side", _logic getVariable ["Side", "auto"]],
    ["equip", _logic getVariable ["Equipment", 1]], ["severity", _logic getVariable ["Severity", 1]],
    ["name", _logic getVariable ["CpName", ""]], ["guards", _guards]
];
[{
    params ["_pos", "_spec"];
    private _r = ["create", [_pos, _spec, objNull]] call comspec_sse_fnc_idCheckpoint;
    diag_log text format ["[COMSPEC SSE] Identité : %1", _r select 0];
}, [getPosATL _logic, _spec], 2] call CBA_fnc_waitAndExecute;
true
