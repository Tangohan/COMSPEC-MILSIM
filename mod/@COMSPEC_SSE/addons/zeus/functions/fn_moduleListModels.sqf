/*
    Module « Lister les modèles SSE » (intégrés + mission + locaux).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if (!hasInterface) exitWith { deleteVehicle _logic; true };

private _models = [] call comspec_sse_fnc_listModels;
private _lines = [format ["%1 modèle(s) SSE disponibles :", count _models]];
{
    _lines pushBack format [
        "• %1 [%2] — %3",
        _x getOrDefault ["name", "?"],
        _x getOrDefault ["source", "?"],
        _x getOrDefault ["id", "?"]
    ];
} forEach (_models select [0, 15]);
if (count _models > 15) then { _lines pushBack format ["… et %1 autre(s)", count _models - 15]; };

[_lines joinString "\n"] call comspec_sse_fnc_zeusNotify;
["listModels displayed"] call comspec_sse_fnc_log;

deleteVehicle _logic;
true
