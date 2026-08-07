params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated || {!hasInterface}) exitWith { true };

private _models = [] call comspec_sse_fnc_listModels;
private _lines = [format ["=== MODÈLES SSE (%1) ===", count _models]];
{
    _lines pushBack format [
        "%1 | %2 | %3 | %4/%5",
        _x getOrDefault ["id", "?"],
        _x getOrDefault ["name", "?"],
        _x getOrDefault ["source", "?"],
        _x getOrDefault ["profile", "?"],
        _x getOrDefault ["theme", "?"]
    ];
} forEach _models;

hint (_lines joinString endl);
["listModels displayed"] call comspec_sse_fnc_log;

deleteVehicle _logic;
true
