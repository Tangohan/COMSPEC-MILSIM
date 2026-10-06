/*
    Module « Panneau Zeus SSE » : ouvre le contrôle Zeus (vérité / connu)
    sur l'entité ciblée.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if (!hasInterface) exitWith { deleteVehicle _logic; true };

private _target = _ctx get "target";
if (!isNull _target && {!isNil "comspec_sse_fnc_uiSetRecord"}) then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

deleteVehicle _logic;

if (isNil "comspec_sse_fnc_uiOpenScreen") exitWith {
    ["Panneau Zeus SSE indisponible (addon UI absent).", "error"] call comspec_sse_fnc_zeusNotify;
    false
};
["zeus"] call comspec_sse_fnc_uiOpenScreen;
true
